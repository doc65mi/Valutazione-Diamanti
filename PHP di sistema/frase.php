<?php
declare(strict_types=1);
// Collegamento diretto con Frase (next.frase.io) tramite la sua API REST. Versione 2.1.30.
// API: https://next.frase.io/api/v1, chiave nell'intestazione X-API-KEY (Frase > Settings > API).
// Percorsi usati (dal pacchetto ufficiale @frase/mcp-server): GET /content, GET /content/{id},
// GET /content/{id}/export?format=html, POST /webhooks, DELETE /webhooks/{id}.
// Il webhook (blog/frase.php) riceve content.approved / content.published firmati HMAC-SHA256 e importa l'articolo.
require_once __DIR__.'/blog.php';

const FRASE_API = 'https://next.frase.io/api/v1';
const FRASE_EVENTS = ['content.approved', 'content.published'];
const FRASE_LOG = PRIVATE_DIR . '/frase.log';
const FRASE_MAX_IMAGE = 8 * 1024 * 1024;

/** Registro diagnostico: evento, id, esito. Mai chiavi, firme o testi degli articoli. */
function frase_log(string $line): void {
    $line = gmdate('Y-m-d H:i:s') . ' ' . str_replace(["\r", "\n"], ' ', mb_substr($line, 0, 400)) . "\n";
    $old = is_file(FRASE_LOG) ? (string)@file_get_contents(FRASE_LOG) : '';
    $lines = array_slice(array_filter(explode("\n", $old)), -199);
    @file_put_contents(FRASE_LOG, implode("\n", $lines) . ($lines ? "\n" : '') . $line, LOCK_EX);
    @chmod(FRASE_LOG, 0600);
}
function frase_log_lines(int $max = 40): array {
    return is_file(FRASE_LOG) ? array_slice(array_reverse(array_filter(explode("\n", (string)file_get_contents(FRASE_LOG)))), 0, $max) : [];
}

/** Chiamata all'API Frase. Restituisce [status, corpo decodificato o testo]. */
function frase_request(string $key, string $method, string $path, array $query = [], ?array $body = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Estensione PHP cURL non disponibile sul server.');
    $url = FRASE_API . $path . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    $headers = ['X-API-KEY: ' . $key, 'Accept: application/json', 'User-Agent: ValutazioneDiamanti-Blog/' . BLOG_VERSION];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Frase non raggiungibile: ' . $error);
    $data = str_contains($type, 'json') ? json_decode((string)$raw, true) : (string)$raw;
    return [$status, $data ?? (string)$raw];
}
/** Come frase_request, ma solleva un errore leggibile se Frase risponde con un codice di errore. */
function frase_call(string $key, string $method, string $path, array $query = [], ?array $body = null): mixed {
    [$status, $data] = frase_request($key, $method, $path, $query, $body);
    if ($status >= 200 && $status < 300) return $data;
    $msg = is_array($data) ? (string)($data['error']['message'] ?? $data['message'] ?? (is_string($data['error'] ?? null) ? $data['error'] : '')) : '';
    if ($status === 401 || $status === 403) throw new RuntimeException('Chiave API Frase non valida o senza permessi.');
    throw new RuntimeException('Frase ha risposto ' . $status . ($msg !== '' ? ': ' . mb_substr(strip_tags($msg), 0, 200) : '.'));
}
function frase_key(array $c): string {
    $key = (string)($c['frase_api_key'] ?? '');
    if ($key === '') throw new RuntimeException('Collegamento Frase non attivo: inserire la chiave API in Admin > Blog.');
    return $key;
}

/** Elenco articoli di Frase (prima pagina, i più recenti). */
function frase_list(array $c, int $page = 1): array {
    $res = frase_call(frase_key($c), 'GET', '/content', ['page' => max(1, $page), 'page_size' => 25]);
    if (!is_array($res)) throw new RuntimeException('Risposta di Frase non riconosciuta.');
    $items = is_array($res['data'] ?? null) ? $res['data'] : (array_is_list($res) ? $res : []);
    $out = [];
    foreach ($items as $i) if (is_array($i) && isset($i['id'])) $out[] = ['id' => (string)$i['id'], 'title' => mb_substr(strip_tags((string)($i['title'] ?? '')), 0, 240),
        'status' => (string)($i['status'] ?? ''), 'updated_at' => (string)($i['updated_at'] ?? $i['created_at'] ?? ''), 'word_count' => (int)($i['word_count'] ?? 0)];
    return ['items' => $out, 'pagination' => is_array($res['pagination'] ?? null) ? $res['pagination'] : null];
}

function frase_plain(mixed $v, int $max): string {
    return is_scalar($v) ? mb_substr(trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$v), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, $max) : '';
}
/** Testo dell'export HTML: solo il contenuto di <body>, senza il primo <h1> (il titolo è già mostrato dal blog). */
function frase_body_html(string $html): string {
    if (preg_match('~<body[^>]*>(.*)</body>~is', $html, $m)) $html = $m[1];
    $html = (string)preg_replace('~<(script|style|head)\b[^>]*>.*?</\1>~is', '', $html);
    return (string)preg_replace('~^\s*<h1\b[^>]*>.*?</h1>~is', '', $html, 1);
}
/** Scarica e ricodifica la copertina (solo HTTPS verso indirizzi pubblici). Restituisce il nome file o null. */
function frase_store_cover(string $url, string $slug): ?string {
    $host = (string)parse_url($url, PHP_URL_HOST);
    if (!blog_valid_url($url, false) || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https' || $host === '') return null;
    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    $ch = curl_init($url);
    $bytes = '';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host . ':443:' . $ip],
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$bytes) { $bytes .= $chunk; return strlen($bytes) > FRASE_MAX_IMAGE ? 0 : strlen($chunk); }]);
    $ok = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if (!$ok || $status !== 200 || strlen($bytes) < 100) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) return null;
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[0] < 50 || $info[1] < 50 || $info[0] * $info[1] > 25000000) return null;
    $img = @imagecreatefromstring($bytes);
    if (!$img) return null;
    if (imagesx($img) > 1200) { $scaled = imagescale($img, 1200, -1, IMG_BICUBIC); imagedestroy($img); if (!$scaled) return null; $img = $scaled; }
    $dir = ROOT . '/uploads/site';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $webp = function_exists('imagewebp');
    $name = 'blog-' . substr($slug, 0, 80) . '-' . bin2hex(random_bytes(4)) . ($webp ? '.webp' : '.jpg');
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(8));
    if ($webp) { imagepalettetotruecolor($img); $saved = imagewebp($img, $tmp, 82); }
    else {
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255)); imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        $saved = imagejpeg($flat, $tmp, 85); imagedestroy($flat);
    }
    imagedestroy($img);
    if (!$saved || !rename($tmp, $dir . '/' . $name)) { @unlink($tmp); return null; }
    @chmod($dir . '/' . $name, 0644);
    return $name;
}
function frase_remove_cover(string $url): void {
    $name = basename((string)parse_url($url, PHP_URL_PATH));
    if (preg_match('/^blog-[a-z0-9-]+-[a-f0-9]{8}\.(?:webp|jpg)$/D', $name) && is_file(ROOT . '/uploads/site/' . $name)) @unlink(ROOT . '/uploads/site/' . $name);
}

/**
 * Importa (o aggiorna) un articolo di Frase nel blog. Un articolo già importato mantiene slug, stato,
 * data e "in primo piano" decisi sul sito; nuovi articoli: bozza, oppure pubblicati se "Pubblica subito" è attivo.
 */
function frase_import(string $contentId, string $origin): array {
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $contentId)) throw new InvalidArgumentException('Identificatore Frase non valido.');
    $c = blog_config(); $key = frase_key($c);
    $res = frase_call($key, 'GET', '/content/' . rawurlencode($contentId));
    $item = is_array($res['data'] ?? null) ? $res['data'] : $res;
    if (!is_array($item)) throw new RuntimeException('Risposta di Frase non riconosciuta.');
    $body = is_string($item['body'] ?? null) ? $item['body'] : '';
    if ($body === '' || !preg_match('~<(p|h[1-6]|ul|ol|div|article|section)\b~i', $body)) { // markdown o vuoto: si chiede l'export HTML
        $export = frase_call($key, 'GET', '/content/' . rawurlencode($contentId) . '/export', ['format' => 'html']);
        $body = is_string($export) ? $export : (string)(is_array($export) ? ($export['data'] ?? '') : '');
    }
    $html = frase_body_html($body);
    $title = frase_plain($item['title'] ?? '', 240);
    $lang = strtolower(substr((string)($item['language'] ?? $item['lang'] ?? 'it'), 0, 2)) === 'en' ? 'en' : 'it';
    $keywords = [];
    foreach (array_slice(is_array($item['target_keywords'] ?? null) ? $item['target_keywords'] : [], 0, 12) as $k) if (($t = frase_plain($k, 60)) !== '') $keywords[] = $t;

    $lock = blog_lock(true);
    $db = blog_db();
    $source = 'frase-' . $contentId;
    $old = blog_one($db, 'SELECT * FROM blog_articles WHERE source_id=?', [$source]);
    // Lo stesso articolo può essere già arrivato dal connettore WordPress di Frase (2.1.39): si aggiorna quello, senza doppioni.
    if (!$old && is_string($item['slug'] ?? null) && $item['slug'] !== '') {
        $old = blog_one($db, "SELECT * FROM blog_articles WHERE slug=? AND source_id LIKE 'frase-%'", [blog_slugify($item['slug'])]);
        if ($old) $source = (string)$old['source_id'];
    }
    $flat = ['id' => $source, 'title' => $title, 'slug' => (string)($item['slug'] ?? ''), 'language' => $lang, 'content' => $html, 'published_at' => '',
        'category' => $lang === 'en' ? 'Insights' : 'Approfondimenti', 'image_url' => '', 'image_alt' => $title];
    $excerpt = frase_plain($item['excerpt'] ?? $item['meta_description'] ?? '', 400);
    if ($excerpt !== '') $flat['excerpt'] = $excerpt;
    $a = blog_article_data($flat, true, $c); // sanifica l'HTML; stato: bozza salvo "Pubblica subito"
    if ($old) foreach (['slug', 'status', 'featured', 'published_at', 'language', 'category', 'faq_json', 'role', 'pillar_slug'] as $f) $a[$f] = $old[$f];
    else {
        $base = $a['slug']; $n = 2;
        while (blog_one($db, 'SELECT id FROM blog_articles WHERE slug=?', [$a['slug']])) $a['slug'] = substr($base, 0, 170) . '-' . $n++;
    }
    $a['meta_title'] = frase_plain($item['meta_title'] ?? '', 160);
    $a['meta_description'] = frase_plain($item['meta_description'] ?? '', 400);
    $a['keywords'] = mb_substr(implode(', ', $keywords), 0, 400);

    $cover = is_string($item['featured_image'] ?? null) ? trim($item['featured_image']) : '';
    $newCover = null;
    if ($cover !== '') {
        $newCover = frase_store_cover($cover, $a['slug']);
        if ($newCover) $a['image_url'] = rtrim($c['site_url'], '/') . '/uploads/site/' . $newCover;
        elseif (blog_valid_url($cover, false) && str_starts_with($cover, 'https://')) $a['image_url'] = $cover; // copertina lasciata su Frase
    } elseif ($old) { $a['image_url'] = $old['image_url']; $a['image_alt'] = $old['image_alt']; }
    $a['updated_at'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    try { $id = blog_persist_article($db, $a, $old ? (int)$old['id'] : null); }
    catch (Throwable $e) { if ($newCover) frase_remove_cover('/uploads/site/' . $newCover); throw $e; }
    if ($newCover && $old && $old['image_url'] !== $a['image_url']) frase_remove_cover($old['image_url']);
    audit('blog-frase', (string)$id);
    seo_indexes();
    frase_log($origin . ' ' . $contentId . ' -> articolo ' . $id . ' (' . ($old ? 'aggiornato' : 'nuovo') . ', ' . ($a['status'] === 'published' ? 'pubblicato' : 'bozza') . ')');
    return ['id' => $id, 'status' => $a['status'], 'updated' => (bool)$old, 'title' => $a['title']];
}

/** Registra sul conto Frase il webhook verso questo sito (sostituendo quello creato in precedenza). */
function frase_connect(array $c, string $key): array {
    if (!is_array(frase_call($key, 'GET', '/content', ['page' => 1, 'page_size' => 1]))) throw new RuntimeException('Risposta di Frase non riconosciuta: verificare la chiave API.');
    if (!empty($c['frase_webhook_id']) && !empty($c['frase_api_key'])) {
        try { frase_request((string)$c['frase_api_key'], 'DELETE', '/webhooks/' . rawurlencode((string)$c['frase_webhook_id'])); } catch (Throwable) {}
    }
    $url = rtrim($c['site_url'], '/') . '/blog/frase.php';
    $webhook = null; $warning = '';
    if (str_starts_with($url, 'https://')) {
        try {
            $res = frase_call($key, 'POST', '/webhooks', [], ['url' => $url, 'events' => FRASE_EVENTS, 'name' => 'Blog Valutazione Diamanti']);
            $webhook = is_array($res['data'] ?? null) ? $res['data'] : $res;
        } catch (RuntimeException $e) { $warning = $e->getMessage(); }
    } else $warning = 'Il sito non è in HTTPS: niente invio automatico, usa «Importa da Frase».';
    $c['frase_api_key'] = $key;
    $c['frase_webhook_id'] = is_array($webhook) ? (string)($webhook['id'] ?? '') : '';
    $c['frase_webhook_secret'] = is_array($webhook) ? (string)($webhook['secret'] ?? '') : '';
    blog_save_config($c);
    frase_log('collegamento ' . ($c['frase_webhook_id'] !== '' ? 'attivo, webhook ' . $c['frase_webhook_id'] : 'senza webhook: ' . $warning));
    return ['webhook' => $c['frase_webhook_id'] !== '', 'warning' => $warning, 'url' => $url];
}
function frase_disconnect(array $c): void {
    if (!empty($c['frase_webhook_id']) && !empty($c['frase_api_key'])) {
        try { frase_request((string)$c['frase_api_key'], 'DELETE', '/webhooks/' . rawurlencode((string)$c['frase_webhook_id'])); } catch (Throwable) {}
    }
    unset($c['frase_api_key'], $c['frase_webhook_id'], $c['frase_webhook_secret']);
    blog_save_config($c);
    frase_log('collegamento rimosso');
}
