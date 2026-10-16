<?php
declare(strict_types=1);
// Ponte compatibile WordPress per Frase (next.frase.io) -> blog di Valutazione Diamanti. Versione 2.1.30.
// Frase pubblica solo su CMS noti: il sito risponde come un WordPress minimale su /wp-json/ (REST API v2),
// con autenticazione Basic "utente + password applicazione" generata in Admin > Blog > Collegamento Frase.
// Gestisce: indice, utente, tipi, categorie, tag, articoli, media (copertina) e meta SEO Rank Math/Yoast.
// Ogni richiesta viene annotata (metodo, percorso, esito, nomi dei campi; mai testi o password) in data/wp-bridge.log.
// Richiesta riscritta da /wp-json/...: gli URL del sito si calcolano come per gli altri script in /blog/.
if (preg_match('~^(.*?)/(?:wp-json|wp-admin|wp-login\.php|xmlrpc\.php)(?:/|$)~', (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $wpbPrefix)) $_SERVER['SCRIPT_NAME'] = $wpbPrefix[1] . '/blog/wp-bridge.php';
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/blog.php';
security_headers(true);

const WPB_MAX_BODY = 15 * 1024 * 1024;
const WPB_MAX_IMAGE = 8 * 1024 * 1024;
const WPB_MAX_PIXELS = 25000000;
const WPB_LOG = PRIVATE_DIR . '/wp-bridge.log';
const WPB_MEDIA = PRIVATE_DIR . '/wp-media.json';
const WPB_TERMS = PRIVATE_DIR . '/wp-terms.json';

$wpbMethod = 'GET'; $wpbRoute = '/'; $wpbKeys = []; $wpbCover = null; $wpbUser = '';

function wpb_log(int $status, string $note = ''): void {
    global $wpbMethod, $wpbRoute, $wpbKeys;
    $line = gmdate('Y-m-d H:i:s') . ' ' . $wpbMethod . ' ' . mb_substr($wpbRoute, 0, 160) . ' ' . $status
        . ($wpbKeys ? ' campi=' . mb_substr(implode(',', $wpbKeys), 0, 300) : '') . ($note !== '' ? ' ' . $note : '');
    $line = str_replace(["\r", "\n"], ' ', $line) . "\n";
    $old = is_file(WPB_LOG) ? (string)@file_get_contents(WPB_LOG) : '';
    $lines = array_slice(explode("\n", trim($old)), -199);
    @file_put_contents(WPB_LOG, ltrim(implode("\n", $lines) . "\n" . $line, "\n"), LOCK_EX);
    @chmod(WPB_LOG, 0600);
}
function wpb_send(mixed $value, int $status = 200, array $headers = []): never {
    wpb_log($status);
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    foreach ($headers as $name => $v) header($name . ': ' . $v);
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function wpb_error(string $code, string $message, int $status): never {
    wpb_send(['code' => $code, 'message' => $message, 'data' => ['status' => $status]], $status);
}
function wpb_json_load(string $file, array $default): array {
    if (!is_file($file)) return $default;
    try { $data = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR); } catch (JsonException) { return $default; }
    return is_array($data) ? array_replace($default, $data) : $default;
}
function wpb_json_save(string $file, array $data): void {
    $tmp = $file . '-' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('save');
    @chmod($tmp, 0600);
    if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('save'); }
}
function wpb_text(mixed $value, int $max): string {
    if (is_array($value)) $value = $value['raw'] ?? $value['rendered'] ?? '';
    if (!is_scalar($value)) return '';
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, $max);
}
function wpb_raw(mixed $value): string {
    if (is_array($value)) $value = $value['raw'] ?? $value['rendered'] ?? '';
    return is_scalar($value) ? (string)$value : '';
}
/** Le variabili di Rank Math/Yoast (%sep%, %sitename%, %title%...) non hanno senso qui. */
function wpb_seo_text(mixed $value, int $max, string $title): string {
    $text = wpb_text($value, $max * 2);
    $text = str_ireplace(['%sep%', '%sitename%', '%title%', '%%sep%%', '%%sitename%%', '%%title%%'], ['-', 'Valutazione Diamanti', $title, '-', 'Valutazione Diamanti', $title], $text);
    return mb_substr(trim((string)preg_replace(['/%%?[a-z_]+%%?/i', '/\s+/u'], ['', ' '], $text)), 0, $max);
}
function wpb_iso(string $value): string {
    if ($value === '') $value = gmdate('Y-m-d');
    return strlen($value) === 10 ? $value . 'T00:00:00' : substr(str_replace(' ', 'T', $value), 0, 19);
}

// ---------- tassonomie (categoria = colonna category, tag = colonna keywords) ----------
function wpb_terms(): array {
    return wpb_json_load(WPB_TERMS, ['next' => 10, 'category' => [
        ['id' => 1, 'name' => 'Approfondimenti', 'slug' => 'approfondimenti'],
        ['id' => 2, 'name' => 'Guide', 'slug' => 'guide'],
    ], 'post_tag' => []]);
}
function wpb_term_out(array $t, string $tax, PDO $db): array {
    $col = $tax === 'category' ? 'category' : 'keywords';
    $count = $tax === 'category' ? (int)blog_one($db, 'SELECT COUNT(*) AS n FROM blog_articles WHERE category=?', [$t['name']])['n'] : 0;
    return ['id' => $t['id'], 'count' => $count, 'description' => '', 'link' => blog_url(blog_config(), '/'), 'name' => $t['name'], 'slug' => $t['slug'],
        'taxonomy' => $tax, 'parent' => 0, 'meta' => []];
}
/** Restituisce l'id del termine con quel nome, creandolo se manca. */
function wpb_term_id(array &$terms, string $tax, string $name): int {
    foreach ($terms[$tax] as $t) if (mb_strtolower($t['name']) === mb_strtolower($name)) return (int)$t['id'];
    $id = (int)$terms['next']; $terms['next'] = $id + 1;
    $terms[$tax][] = ['id' => $id, 'name' => $name, 'slug' => blog_slugify($name) ?: 'termine-' . $id];
    return $id;
}
function wpb_term_name(array $terms, string $tax, mixed $id): ?string {
    foreach ($terms[$tax] as $t) if ((int)$t['id'] === (int)$id) return $t['name'];
    return null;
}

// ---------- media (solo immagini, ricodificate dal server) ----------
function wpb_media(): array { return wpb_json_load(WPB_MEDIA, ['next' => 1000, 'items' => []]); }
function wpb_store_image(string $bytes): string {
    if (strlen($bytes) < 100 || strlen($bytes) > WPB_MAX_IMAGE) throw new InvalidArgumentException('image');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) throw new InvalidArgumentException('image');
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[0] < 50 || $info[1] < 50 || $info[0] * $info[1] > WPB_MAX_PIXELS) throw new InvalidArgumentException('image');
    $img = @imagecreatefromstring($bytes);
    if (!$img) throw new InvalidArgumentException('image');
    if (imagesx($img) > 1200) {
        $scaled = imagescale($img, 1200, -1, IMG_BICUBIC); imagedestroy($img);
        if (!$scaled) throw new RuntimeException('resize');
        $img = $scaled;
    }
    $dir = ROOT . '/uploads/site';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('uploads');
    $webp = function_exists('imagewebp');
    $name = 'blog-frase-' . bin2hex(random_bytes(4)) . ($webp ? '.webp' : '.jpg');
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(8));
    if ($webp) { imagepalettetotruecolor($img); $ok = imagewebp($img, $tmp, 82); }
    else {
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        $ok = imagejpeg($flat, $tmp, 85); imagedestroy($flat);
    }
    imagedestroy($img);
    if (!$ok || !rename($tmp, $dir . '/' . $name)) { @unlink($tmp); throw new RuntimeException('save'); }
    @chmod($dir . '/' . $name, 0644);
    return $name;
}
function wpb_media_out(array $m): array {
    return ['id' => $m['id'], 'date' => wpb_iso($m['date']), 'date_gmt' => wpb_iso($m['date']), 'slug' => pathinfo($m['file'], PATHINFO_FILENAME), 'status' => 'inherit',
        'type' => 'attachment', 'link' => $m['url'], 'title' => ['raw' => $m['title'], 'rendered' => blog_h($m['title'])], 'author' => 1,
        'alt_text' => $m['alt'], 'caption' => ['raw' => '', 'rendered' => ''], 'description' => ['raw' => '', 'rendered' => ''],
        'media_type' => 'image', 'mime_type' => str_ends_with($m['file'], '.webp') ? 'image/webp' : 'image/jpeg', 'post' => $m['post'] ?? null,
        'source_url' => $m['url'], 'media_details' => ['width' => $m['width'], 'height' => $m['height'], 'file' => $m['file'], 'sizes' => [
            'full' => ['file' => $m['file'], 'width' => $m['width'], 'height' => $m['height'], 'source_url' => $m['url']]]]];
}
function wpb_media_by_url(array $media, string $url): int {
    foreach ($media['items'] as $m) if ($m['url'] === $url) return (int)$m['id'];
    return 0;
}

// ---------- articoli ----------
function wpb_post_out(array $a, PDO $db): array {
    $c = blog_config(); $terms = wpb_terms();
    $cat = 0; foreach ($terms['category'] as $t) if (mb_strtolower($t['name']) === mb_strtolower((string)$a['category'])) $cat = (int)$t['id'];
    $tags = [];
    foreach (array_filter(array_map('trim', explode(',', (string)$a['keywords']))) as $k) foreach ($terms['post_tag'] as $t) if (mb_strtolower($t['name']) === mb_strtolower($k)) $tags[] = (int)$t['id'];
    $link = blog_article_url($c, $a);
    $date = wpb_iso((string)$a['published_at']);
    $modified = substr(str_replace('Z', '', (string)$a['updated_at']), 0, 19);
    return ['id' => (int)$a['id'], 'date' => $date, 'date_gmt' => $date, 'guid' => ['rendered' => $link, 'raw' => $link],
        'modified' => $modified, 'modified_gmt' => $modified, 'slug' => $a['slug'], 'status' => $a['status'] === 'published' ? 'publish' : 'draft',
        'type' => 'post', 'link' => $link, 'title' => ['raw' => $a['title'], 'rendered' => blog_h($a['title'])],
        'content' => ['raw' => $a['content'], 'rendered' => $a['content'], 'protected' => false],
        'excerpt' => ['raw' => $a['excerpt'], 'rendered' => '<p>' . blog_h($a['excerpt']) . '</p>', 'protected' => false],
        'author' => 1, 'featured_media' => $a['image_url'] !== '' ? wpb_media_by_url(wpb_media(), $a['image_url']) : 0,
        'comment_status' => 'closed', 'ping_status' => 'closed', 'sticky' => (bool)$a['featured'], 'template' => '', 'format' => 'standard',
        'meta' => ['rank_math_title' => $a['meta_title'] ?? '', 'rank_math_description' => $a['meta_description'] ?? '', 'rank_math_focus_keyword' => $a['keywords'] ?? ''],
        'categories' => $cat ? [$cat] : [], 'tags' => $tags, 'lang' => $a['language']];
}
/** Applica i meta SEO (Rank Math, Yoast o campi diretti) all'articolo. */
function wpb_apply_meta(array &$a, mixed $meta): void {
    if (!is_array($meta)) return;
    foreach (['rank_math_title', '_yoast_wpseo_title', 'yoast_wpseo_title', 'meta_title', 'seo_title'] as $k)
        if (isset($meta[$k]) && ($v = wpb_seo_text($meta[$k], 160, $a['title'])) !== '') { $a['meta_title'] = $v; break; }
    foreach (['rank_math_description', '_yoast_wpseo_metadesc', 'yoast_wpseo_metadesc', 'meta_description', 'seo_description'] as $k)
        if (isset($meta[$k]) && ($v = wpb_seo_text($meta[$k], 400, $a['title'])) !== '') { $a['meta_description'] = $v; break; }
    foreach (['rank_math_focus_keyword', '_yoast_wpseo_focuskw', 'yoast_wpseo_focuskw', 'focus_keyword'] as $k)
        if (isset($meta[$k]) && ($v = wpb_text($meta[$k], 200)) !== '') { // la parola chiave principale va in testa ai tag
            $list = array_filter(array_map('trim', explode(',', $v . ',' . (string)($a['keywords'] ?? ''))));
            $a['keywords'] = mb_substr(implode(', ', array_values(array_unique($list))), 0, 400); break;
        }
}
function wpb_save_post(PDO $db, array $in, ?array $old): array {
    $c = blog_config(); $terms = wpb_terms(); $media = wpb_media();
    $title = wpb_text($in['title'] ?? ($old['title'] ?? ''), 240);
    $html = array_key_exists('content', $in) ? wpb_raw($in['content']) : (string)($old['content'] ?? '');
    $status = (string)($in['status'] ?? ($old ? ($old['status'] === 'published' ? 'publish' : 'draft') : 'draft'));
    $slug = $old && $old['status'] === 'published' ? $old['slug'] : (wpb_text($in['slug'] ?? '', 180) ?: ($old['slug'] ?? ''));
    $date = substr((string)($in['date'] ?? $in['date_gmt'] ?? ''), 0, 10);
    if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || $date > gmdate('Y-m-d'))) $date = '';
    if ($date === '' && $old) $date = (string)$old['published_at'];
    $lang = (string)($in['lang'] ?? $in['language'] ?? ($old['language'] ?? 'it')) === 'en' ? 'en' : 'it';

    $category = $old['category'] ?? ($lang === 'en' ? 'Insights' : 'Approfondimenti');
    if (isset($in['categories'])) {
        $ids = is_array($in['categories']) ? $in['categories'] : explode(',', (string)$in['categories']);
        foreach ($ids as $id) if (($name = wpb_term_name($terms, 'category', $id)) !== null) { $category = $name; break; }
    }
    $keywords = $old['keywords'] ?? '';
    if (isset($in['tags'])) {
        $ids = is_array($in['tags']) ? $in['tags'] : explode(',', (string)$in['tags']);
        $names = []; foreach ($ids as $id) if (($name = wpb_term_name($terms, 'post_tag', $id)) !== null) $names[] = $name;
        $keywords = mb_substr(implode(', ', array_slice($names, 0, 12)), 0, 400);
    }
    $image = $old['image_url'] ?? ''; $alt = $old['image_alt'] ?? '';
    if (isset($in['featured_media'])) {
        $image = ''; $alt = '';
        foreach ($media['items'] as $m) if ((int)$m['id'] === (int)$in['featured_media']) { $image = $m['url']; $alt = $m['alt']; }
    }
    // Senza copertina caricata, la prima immagine HTTPS del testo diventa la copertina.
    if ($image === '' && preg_match('/<img[^>]+src=["\'](https:\/\/[^"\']+)["\']/i', $html, $m) && blog_valid_url(html_entity_decode($m[1]), false)) $image = html_entity_decode($m[1]);

    $flat = ['id' => $old['source_id'] ?? ('frase-' . bin2hex(random_bytes(8))), 'title' => $title, 'slug' => $slug, 'language' => $lang,
        'content' => $html, 'category' => $category, 'image_url' => $image, 'image_alt' => $alt !== '' ? $alt : $title, 'published_at' => $date];
    $excerpt = wpb_text($in['excerpt'] ?? '', 400);
    if ($excerpt !== '') $flat['excerpt'] = $excerpt; elseif ($old && !array_key_exists('content', $in)) $flat['excerpt'] = $old['excerpt'];
    $a = blog_article_data($flat, true, $c);
    if (!$old) { // come WordPress: slug già usato -> slug-2, slug-3...
        $base = $a['slug']; $n = 2;
        while (blog_one($db, 'SELECT id FROM blog_articles WHERE slug=?', [$a['slug']])) $a['slug'] = substr($base, 0, 170) . '-' . $n++;
    }
    // Lo stato lo sceglie chi pubblica da Frase: "Pubblica" = online, bozza o programmato = bozza.
    $a['status'] = $status === 'publish' ? 'published' : 'draft';
    $a['featured'] = (int)($old['featured'] ?? 0);
    $a['keywords'] = $keywords;
    $a['meta_title'] = $old['meta_title'] ?? ''; $a['meta_description'] = $old['meta_description'] ?? '';
    $a['faq_json'] = $old['faq_json'] ?? null; $a['role'] = $old['role'] ?? ''; $a['pillar_slug'] = $old['pillar_slug'] ?? '';
    wpb_apply_meta($a, $in['meta'] ?? null);
    foreach (['yoast_meta', 'rank_math_meta', 'seo'] as $k) wpb_apply_meta($a, $in[$k] ?? null);
    $a['updated_at'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    $id = blog_persist_article($db, $a, $old ? (int)$old['id'] : null);
    foreach ($media['items'] as &$m) if ($m['url'] === $a['image_url']) $m['post'] = $id;
    unset($m); wpb_json_save(WPB_MEDIA, $media);
    audit('blog-frase', (string)$id); seo_indexes();
    return blog_one($db, 'SELECT * FROM blog_articles WHERE id=?', [$id]);
}

// ---------- richiesta ----------
function wpb_input(): array {
    global $wpbMethod;
    if (in_array($wpbMethod, ['GET', 'HEAD', 'DELETE'], true)) return $_GET;
    $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_starts_with($type, 'multipart/form-data') || str_starts_with($type, 'application/x-www-form-urlencoded')) return $_POST + $_GET;
    if (str_starts_with($type, 'application/json')) {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > WPB_MAX_BODY) wpb_error('rest_too_large', 'Contenuto troppo grande.', 413);
        $raw = (string)file_get_contents('php://input', false, null, 0, WPB_MAX_BODY + 1);
        if (strlen($raw) > WPB_MAX_BODY) wpb_error('rest_too_large', 'Contenuto troppo grande.', 413);
        if (trim($raw) === '') return $_GET;
        try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); } catch (JsonException) { wpb_error('rest_invalid_json', 'JSON non valido.', 400); }
        return is_array($data) ? $data + $_GET : $_GET;
    }
    return $_GET;
}
function wpb_authenticate(array $c): void {
    $user = $_SERVER['PHP_AUTH_USER'] ?? null; $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($pass === null && preg_match('/^Basic\s+([A-Za-z0-9+\/=]{4,400})$/D', $auth, $m)) {
        $decoded = base64_decode($m[1], true);
        if ($decoded !== false && str_contains($decoded, ':')) [$user, $pass] = explode(':', $decoded, 2);
    }
    global $wpbUser; $wpbUser = mb_substr(trim((string)$user), 0, 120);
    $hash = (string)($c['wp_password_hash'] ?? '');
    $pass = $pass === null ? '' : str_replace(' ', '', (string)$pass);
    if ($hash === '' || $pass === '' || !hash_equals($hash, hash('sha256', $pass))) {
        rate('blog-frase-fail', 10, 900);
        wpb_error('rest_not_logged_in', $hash === '' ? 'Collegamento Frase non attivo: generare la password in Admin > Blog.' : 'Utente o password applicazione non validi.', 401);
    }
}

try {
    $wpbMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $override = strtoupper((string)($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_GET['_method'] ?? ''));
    if ($wpbMethod === 'POST' && in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) $wpbMethod = $override;
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    // Frase chiede l'URL che termina in /wp-admin: chi lo apre nel browser arriva all'amministrazione del sito.
    if (!isset($_GET['rest_route']) && preg_match('~/(wp-admin(?:/.*)?|wp-login\.php|xmlrpc\.php)$~D', $path, $m)) {
        $wpbRoute = '/' . $m[1];
        if ($m[1] === 'xmlrpc.php') wpb_error('xmlrpc_disabled', 'XML-RPC non disponibile: usare la REST API.', 405);
        wpb_log(302); header('Location: ' . rtrim(blog_config()['site_url'], '/') . '/admin/', true, 302); exit;
    }
    $route = isset($_GET['rest_route']) ? (string)$_GET['rest_route'] : '';
    if ($route === '' && preg_match('~/wp-json(/[^?]*)?~', $path, $m)) $route = $m[1] ?? '/';
    $wpbRoute = '/' . trim(rawurldecode($route ?: '/'), '/');
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') wpb_error('rest_https_required', 'HTTPS richiesto.', 400);

    $c = blog_config();
    $site = rtrim($c['site_url'], '/');
    $api = $site . '/wp-json/';

    // Indice pubblico: serve a Frase per riconoscere un sito WordPress.
    if ($wpbRoute === '/' && in_array($wpbMethod, ['GET', 'HEAD'], true)) {
        wpb_send(['name' => 'Valutazione Diamanti', 'description' => $c['subtitle'], 'url' => $site, 'home' => $site, 'gmt_offset' => 1,
            'timezone_string' => 'Europe/Rome', 'namespaces' => ['wp/v2', 'rankmath/v1'],
            'authentication' => ['application-passwords' => ['endpoints' => ['authorization' => $site . '/admin/']]],
            'routes' => ['/wp/v2/posts' => ['namespace' => 'wp/v2', 'methods' => ['GET', 'POST']], '/wp/v2/media' => ['namespace' => 'wp/v2', 'methods' => ['GET', 'POST']],
                '/wp/v2/categories' => ['namespace' => 'wp/v2', 'methods' => ['GET', 'POST']], '/wp/v2/tags' => ['namespace' => 'wp/v2', 'methods' => ['GET', 'POST']],
                '/wp/v2/users/me' => ['namespace' => 'wp/v2', 'methods' => ['GET']], '/rankmath/v1/updateMeta' => ['namespace' => 'rankmath/v1', 'methods' => ['POST']]],
            '_links' => ['help' => [['href' => $site]]]], 200, ['Link' => '<' . $api . '>; rel="https://api.w.org/"']);
    }
    wpb_authenticate($c);
    rate('blog-frase', 1200, 3600);
    $in = wpb_input();
    $wpbKeys = array_slice(array_map('strval', array_keys($in)), 0, 30);

    if ($wpbRoute === '/wp/v2/users/me' && $wpbMethod === 'GET') {
        wpb_send(['id' => 1, 'username' => 'frase', 'name' => 'Valutazione Diamanti', 'first_name' => '', 'last_name' => '', 'email' => filter_var($wpbUser, FILTER_VALIDATE_EMAIL) ? $wpbUser : '',
            'url' => $site, 'description' => '', 'link' => blog_url($c, '/'), 'locale' => 'it_IT', 'nickname' => 'frase', 'slug' => 'frase',
            'roles' => ['administrator'], 'registered_date' => '2026-01-01T00:00:00+00:00',
            'capabilities' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true, 'edit_published_posts' => true, 'delete_posts' => true,
                'upload_files' => true, 'manage_categories' => true, 'edit_others_posts' => true, 'administrator' => true],
            'extra_capabilities' => ['administrator' => true], 'avatar_urls' => (object)[], 'meta' => []]);
    }
    if ($wpbRoute === '/wp/v2/settings' && $wpbMethod === 'GET') {
        wpb_send(['title' => 'Valutazione Diamanti', 'description' => $c['subtitle'], 'url' => $site, 'timezone' => 'Europe/Rome', 'date_format' => 'd/m/Y',
            'time_format' => 'H:i', 'start_of_week' => 1, 'language' => 'it_IT', 'posts_per_page' => 10, 'default_category' => 1, 'default_post_format' => '0']);
    }
    if ($wpbRoute === '/wp/v2/plugins' && $wpbMethod === 'GET') {
        wpb_send([['plugin' => 'seo-by-rank-math/rank-math', 'status' => 'active', 'name' => 'Rank Math SEO', 'version' => '1.0.0', 'textdomain' => 'rank-math']]);
    }
    if (preg_match('~^/wp/v2/types(?:/(post))?$~D', $wpbRoute, $m) && $wpbMethod === 'GET') {
        $post = ['name' => 'Articoli', 'slug' => 'post', 'description' => '', 'hierarchical' => false, 'has_archive' => false, 'rest_base' => 'posts',
            'rest_namespace' => 'wp/v2', 'taxonomies' => ['category', 'post_tag'], 'viewable' => true, 'supports' => ['title' => true, 'editor' => true, 'excerpt' => true, 'thumbnail' => true, 'custom-fields' => true],
            'labels' => ['name' => 'Articoli', 'singular_name' => 'Articolo']];
        wpb_send(isset($m[1]) ? $post : ['post' => $post]);
    }
    if (preg_match('~^/wp/v2/taxonomies(?:/(category|post_tag))?$~D', $wpbRoute, $m) && $wpbMethod === 'GET') {
        $tx = ['category' => ['name' => 'Categorie', 'slug' => 'category', 'rest_base' => 'categories', 'hierarchical' => true, 'types' => ['post'], 'rest_namespace' => 'wp/v2'],
            'post_tag' => ['name' => 'Tag', 'slug' => 'post_tag', 'rest_base' => 'tags', 'hierarchical' => false, 'types' => ['post'], 'rest_namespace' => 'wp/v2']];
        wpb_send(isset($m[1]) ? $tx[$m[1]] : $tx);
    }
    if (preg_match('~^/wp/v2/(pages|comments|users|blocks)$~D', $wpbRoute) && $wpbMethod === 'GET') {
        wpb_send($wpbRoute === '/wp/v2/users' ? [['id' => 1, 'name' => 'Valutazione Diamanti', 'slug' => 'frase']] : [], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']);
    }

    $lock = blog_lock($wpbMethod !== 'GET');
    $db = blog_db();

    if (preg_match('~^/wp/v2/(categories|tags)(?:/(\d+))?$~D', $wpbRoute, $m)) {
        $tax = $m[1] === 'categories' ? 'category' : 'post_tag'; $terms = wpb_terms();
        if ($wpbMethod === 'GET' && !isset($m[2])) {
            $list = $terms[$tax];
            $search = mb_strtolower(wpb_text($in['search'] ?? '', 80)); $slug = (string)($in['slug'] ?? '');
            if ($search !== '') $list = array_values(array_filter($list, fn($t) => str_contains(mb_strtolower($t['name']), $search)));
            if ($slug !== '') { $slugs = explode(',', $slug); $list = array_values(array_filter($list, fn($t) => in_array($t['slug'], $slugs, true))); }
            wpb_send(array_map(fn($t) => wpb_term_out($t, $tax, $db), $list), 200, ['X-WP-Total' => (string)count($list), 'X-WP-TotalPages' => '1']);
        }
        if ($wpbMethod === 'GET') {
            foreach ($terms[$tax] as $t) if ((int)$t['id'] === (int)$m[2]) wpb_send(wpb_term_out($t, $tax, $db));
            wpb_error('rest_term_invalid', 'Termine non trovato.', 404);
        }
        if ($wpbMethod === 'POST' && !isset($m[2])) {
            $name = wpb_text($in['name'] ?? '', $tax === 'category' ? 80 : 60);
            if ($name === '') wpb_error('rest_missing_callback_param', 'Nome richiesto.', 400);
            foreach ($terms[$tax] as $t) if (mb_strtolower($t['name']) === mb_strtolower($name))
                wpb_send(['code' => 'term_exists', 'message' => 'Termine già presente.', 'data' => ['status' => 400, 'term_id' => $t['id']]], 400);
            $id = wpb_term_id($terms, $tax, $name); wpb_json_save(WPB_TERMS, $terms);
            foreach ($terms[$tax] as $t) if ((int)$t['id'] === $id) wpb_send(wpb_term_out($t, $tax, $db), 201);
        }
        wpb_error('rest_no_route', 'Operazione non disponibile.', 404);
    }

    if (preg_match('~^/wp/v2/media(?:/(\d+))?$~D', $wpbRoute, $m)) {
        $media = wpb_media();
        if ($wpbMethod === 'GET' && !isset($m[1])) {
            $items = array_reverse($media['items']);
            wpb_send(array_map('wpb_media_out', array_slice($items, 0, 100)), 200, ['X-WP-Total' => (string)count($items), 'X-WP-TotalPages' => '1']);
        }
        if (isset($m[1])) {
            foreach ($media['items'] as $i => $item) {
                if ((int)$item['id'] !== (int)$m[1]) continue;
                if ($wpbMethod === 'GET') wpb_send(wpb_media_out($item));
                if (in_array($wpbMethod, ['POST', 'PUT', 'PATCH'], true)) {
                    if (isset($in['alt_text'])) $media['items'][$i]['alt'] = wpb_text($in['alt_text'], 240);
                    if (isset($in['title'])) $media['items'][$i]['title'] = wpb_text($in['title'], 240);
                    wpb_json_save(WPB_MEDIA, $media);
                    $db->prepare('UPDATE blog_articles SET image_alt=? WHERE image_url=?')->execute([$media['items'][$i]['alt'], $item['url']]);
                    wpb_send(wpb_media_out($media['items'][$i]));
                }
            }
            wpb_error('rest_post_invalid_id', 'Media non trovato.', 404);
        }
        if ($wpbMethod === 'POST') {
            set_time_limit(60);
            if (!empty($_FILES)) {
                $file = $_FILES['file'] ?? reset($_FILES);
                if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) wpb_error('rest_upload_no_data', 'Immagine non ricevuta (forse oltre il limite di upload del server).', 400);
                $bytes = (string)file_get_contents($file['tmp_name']); $filename = (string)$file['name'];
            } else {
                if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > WPB_MAX_IMAGE) wpb_error('rest_upload_file_too_big', 'Immagine troppo grande (massimo 8 MB).', 413);
                $bytes = (string)file_get_contents('php://input', false, null, 0, WPB_MAX_IMAGE + 1);
                $filename = preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', (string)($_SERVER['HTTP_CONTENT_DISPOSITION'] ?? ''), $f) ? rawurldecode($f[1]) : 'immagine';
            }
            if ($bytes === '') wpb_error('rest_upload_no_data', 'Immagine non ricevuta (forse oltre il limite di upload del server).', 400);
            $name = wpb_store_image($bytes); $wpbCover = $name;
            $size = getimagesize(ROOT . '/uploads/site/' . $name) ?: [0, 0];
            $id = (int)$media['next']; $media['next'] = $id + 1;
            $title = wpb_text($in['title'] ?? pathinfo($filename, PATHINFO_FILENAME), 240);
            $item = ['id' => $id, 'file' => $name, 'url' => rtrim($c['site_url'], '/') . '/uploads/site/' . $name, 'alt' => wpb_text($in['alt_text'] ?? '', 240),
                'title' => $title, 'width' => $size[0], 'height' => $size[1], 'date' => gmdate('Y-m-d\TH:i:s'), 'post' => isset($in['post']) ? (int)$in['post'] : null];
            $media['items'][] = $item; $media['items'] = array_slice($media['items'], -500);
            wpb_json_save(WPB_MEDIA, $media); $wpbCover = null;
            if ($item['post']) {
                $post = blog_one($db, 'SELECT * FROM blog_articles WHERE id=?', [$item['post']]);
                if ($post && $post['image_url'] === '') $db->prepare('UPDATE blog_articles SET image_url=?, image_alt=? WHERE id=?')->execute([$item['url'], $item['alt'] ?: $post['title'], $post['id']]);
            }
            wpb_send(wpb_media_out($item), 201);
        }
        wpb_error('rest_no_route', 'Operazione non disponibile.', 404);
    }

    if (preg_match('~^/wp/v2/posts(?:/(\d+))?$~D', $wpbRoute, $m)) {
        if ($wpbMethod === 'GET' && !isset($m[1])) {
            $where = []; $params = [];
            $status = (string)($in['status'] ?? 'publish');
            if ($status === 'publish') $where[] = "status='published'"; elseif ($status === 'draft') $where[] = "status='draft'";
            if (!empty($in['slug'])) { $slugs = array_slice(explode(',', (string)$in['slug']), 0, 20); $where[] = 'slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')'; array_push($params, ...$slugs); }
            if (($search = wpb_text($in['search'] ?? '', 120)) !== '') { $where[] = 'title LIKE ?'; $params[] = '%' . $search . '%'; }
            if (!empty($in['include'])) { $ids = array_map('intval', array_slice(is_array($in['include']) ? $in['include'] : explode(',', (string)$in['include']), 0, 100)); $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; array_push($params, ...$ids); }
            $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
            $total = (int)blog_one($db, 'SELECT COUNT(*) AS n FROM blog_articles' . $sqlWhere, $params)['n'];
            $per = max(1, min(100, (int)($in['per_page'] ?? 10))); $page = max(1, (int)($in['page'] ?? 1));
            $rows = blog_rows($db, 'SELECT * FROM blog_articles' . $sqlWhere . ' ORDER BY published_at DESC, id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
            wpb_send(array_map(fn($a) => wpb_post_out($a, $db), $rows), 200, ['X-WP-Total' => (string)$total, 'X-WP-TotalPages' => (string)max(1, (int)ceil($total / $per))]);
        }
        if ($wpbMethod === 'POST' && !isset($m[1])) {
            set_time_limit(60);
            $terms = wpb_terms(); // le categorie/tag passati per nome (alcuni client) diventano id
            foreach (['categories' => 'category', 'tags' => 'post_tag'] as $key => $tax) if (isset($in[$key]) && is_array($in[$key]))
                foreach ($in[$key] as $i => $v) if (is_string($v) && !ctype_digit($v)) $in[$key][$i] = wpb_term_id($terms, $tax, wpb_text($v, 80));
            wpb_json_save(WPB_TERMS, $terms);
            $a = wpb_save_post($db, $in, null);
            wpb_send(wpb_post_out($a, $db), 201);
        }
        if (isset($m[1])) {
            $old = blog_one($db, 'SELECT * FROM blog_articles WHERE id=?', [(int)$m[1]]);
            if (!$old) wpb_error('rest_post_invalid_id', 'Articolo non trovato.', 404);
            if ($wpbMethod === 'GET') wpb_send(wpb_post_out($old, $db));
            if (in_array($wpbMethod, ['POST', 'PUT', 'PATCH'], true)) {
                $terms = wpb_terms();
                foreach (['categories' => 'category', 'tags' => 'post_tag'] as $key => $tax) if (isset($in[$key]) && is_array($in[$key]))
                    foreach ($in[$key] as $i => $v) if (is_string($v) && !ctype_digit($v)) $in[$key][$i] = wpb_term_id($terms, $tax, wpb_text($v, 80));
                wpb_json_save(WPB_TERMS, $terms);
                wpb_send(wpb_post_out(wpb_save_post($db, $in, $old), $db));
            }
            if ($wpbMethod === 'DELETE') {
                // Frase può togliere solo gli articoli che ha creato; gli altri tornano solo in bozza.
                $out = wpb_post_out($old, $db);
                if (str_starts_with((string)$old['source_id'], 'frase-') && filter_var($in['force'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $db->prepare('DELETE FROM blog_articles WHERE id=?')->execute([(int)$old['id']]);
                    audit('blog-frase-delete', (string)$old['id']); seo_indexes();
                    wpb_send(['deleted' => true, 'previous' => $out]);
                }
                $db->prepare("UPDATE blog_articles SET status='draft' WHERE id=?")->execute([(int)$old['id']]);
                audit('blog-frase-draft', (string)$old['id']); seo_indexes();
                $out['status'] = 'trash'; wpb_send($out);
            }
        }
        wpb_error('rest_no_route', 'Operazione non disponibile.', 404);
    }

    // Rank Math: POST /rankmath/v1/updateMeta {objectType:"post", objectID, meta:{rank_math_title,...}}
    if ($wpbRoute === '/rankmath/v1/updateMeta' && $wpbMethod === 'POST') {
        $id = (int)($in['objectID'] ?? $in['object_id'] ?? $in['post_id'] ?? 0);
        $a = $id ? blog_one($db, 'SELECT * FROM blog_articles WHERE id=?', [$id]) : null;
        if (!$a) wpb_error('rest_post_invalid_id', 'Articolo non trovato.', 404);
        wpb_apply_meta($a, $in['meta'] ?? $in);
        $db->prepare('UPDATE blog_articles SET meta_title=?, meta_description=?, keywords=? WHERE id=?')->execute([$a['meta_title'], $a['meta_description'], $a['keywords'], $id]);
        audit('blog-frase-seo', (string)$id);
        wpb_send(['slug' => true, 'schemas' => [], 'success' => true]);
    }
    if (str_starts_with($wpbRoute, '/rankmath/v1/') && $wpbMethod === 'GET') wpb_send(['success' => true]);

    wpb_error('rest_no_route', 'Nessun percorso corrisponde a URL e metodo richiesti.', 404);
} catch (InvalidArgumentException $e) {
    if ($wpbCover) @unlink(ROOT . '/uploads/site/' . $wpbCover);
    wpb_error('rest_invalid_param', $e->getMessage() === 'image' ? 'Immagine non valida: usare JPEG, PNG, WebP o GIF.' : $e->getMessage(), 400);
} catch (PDOException $e) {
    if ($wpbCover) @unlink(ROOT . '/uploads/site/' . $wpbCover);
    error_log('Blog frase database: ' . $e->getCode());
    wpb_error('rest_conflict', 'Slug già usato da un altro articolo.', 409);
} catch (Throwable $e) {
    if ($wpbCover) @unlink(ROOT . '/uploads/site/' . $wpbCover);
    error_log('Blog frase: ' . get_class($e) . ' ' . $e->getMessage());
    wpb_error('rest_unavailable', 'Ricezione temporaneamente non disponibile.', 503);
}
