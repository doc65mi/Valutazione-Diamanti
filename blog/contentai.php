<?php
declare(strict_types=1);
// Ricevitore ContentAI Pro (Content Studio) -> blog di Valutazione Diamanti. Versione 2.1.21, registro eventi 2.1.31.
// POST JSON con "Authorization: Bearer <token>" (lo stesso token del collegamento webhook in Admin > Blog).
// I nuovi articoli arrivano in BOZZA, oppure online se in Admin > Blog e attivo "Pubblica subito" (auto_publish).
// Ogni invio lascia una riga in data/contentai.log, visibile in Admin > Blog > Ultimi eventi.
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/blog.php';
security_headers(true);

const CONTENTAI_MAX_BODY = 15 * 1024 * 1024;
const CONTENTAI_MAX_IMAGE = 8 * 1024 * 1024;
const CONTENTAI_MAX_PIXELS = 9000000;

function contentai_text(mixed $value, int $max): string {
    if (!is_string($value)) return '';
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', strip_tags($value))), 0, $max);
}
function contentai_slug(mixed $value): string {
    if (!is_string($value) || strlen($value) > 180 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value)) throw new InvalidArgumentException('slug');
    return $value;
}
/** Copertina: PNG verificato, ricodificato (scarta metadati) e salvato come WebP/JPEG con nome generato dal server. */
function contentai_store_cover(mixed $image, string $slug): ?string {
    if (!is_array($image) || !is_string($image['base64'] ?? null) || $image['base64'] === '') return null;
    if (strlen($image['base64']) > (int)(CONTENTAI_MAX_IMAGE * 4 / 3) + 16) throw new InvalidArgumentException('image');
    $bytes = base64_decode($image['base64'], true);
    if ($bytes === false || strlen($bytes) < 100 || strlen($bytes) > CONTENTAI_MAX_IMAGE) throw new InvalidArgumentException('image');
    if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) throw new InvalidArgumentException('image');
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] < 200 || $info[1] < 100 || $info[0] * $info[1] > CONTENTAI_MAX_PIXELS) throw new InvalidArgumentException('image');
    if ((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/png') throw new InvalidArgumentException('image');
    $img = @imagecreatefromstring($bytes);
    if (!$img) throw new InvalidArgumentException('image');
    if (imagesx($img) > 1200) {
        $scaled = imagescale($img, 1200, -1, IMG_BICUBIC);
        imagedestroy($img);
        if (!$scaled) throw new RuntimeException('resize');
        $img = $scaled;
    }
    $dir = ROOT . '/uploads/site';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('uploads');
    $webp = function_exists('imagewebp');
    $name = 'blog-' . $slug . '-' . bin2hex(random_bytes(4)) . ($webp ? '.webp' : '.jpg');
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
/** Nomi dei campi ricevuti (non i valori), per riconoscere il formato reale di ContentAI Pro dal registro. */
function contentai_fields(array $input): string {
    $art = is_array($input['article'] ?? null) ? $input['article'] : [];
    $clean = fn(array $keys) => implode(',', array_map(fn($k) => preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$k), array_slice($keys, 0, 30)));
    return 'campi: ' . $clean(array_keys($input)) . ($art ? ' | article: ' . $clean(array_keys($art)) : '');
}
/** Elimina solo file generati da questo endpoint (nome validato da regex, cartella fissa). */
function contentai_remove_cover(?string $url): void {
    if (!$url) return;
    $name = basename((string)parse_url($url, PHP_URL_PATH));
    if (!preg_match('/^blog-[a-z0-9-]+-[a-f0-9]{8}\.(?:webp|jpg)$/D', $name)) return;
    $path = ROOT . '/uploads/site/' . $name;
    if (is_file($path)) @unlink($path);
}

$newCover = null;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Allow: POST'); fail('Usare POST.', 405); }
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') fail('HTTPS richiesto.', 400);

    // Autenticazione prima di qualunque lavoro: fail closed se il token non e mai stato generato.
    $c = blog_config();
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$c['webhook_hash'] || !preg_match('/^Bearer (\S{16,200})$/D', $auth, $m) || !hash_equals($c['webhook_hash'], hash('sha256', $m[1]))) {
        contentai_log('rifiutato: ' . (!$c['webhook_hash'] ? 'token non ancora generato in Admin > Blog' : ($auth === '' ? 'header Authorization assente (il server potrebbe non inoltrarlo)' : 'token non valido')));
        rate('blog-contentai-fail', 10, 900);
        fail('Autenticazione non valida.', 401);
    }
    rate('blog-contentai', 600, 3600);
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) fail('Usare application/json.', 415);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > CONTENTAI_MAX_BODY) fail('Contenuto troppo grande.', 413);
    $raw = file_get_contents('php://input', false, null, 0, CONTENTAI_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > CONTENTAI_MAX_BODY) fail('Contenuto troppo grande.', 413);
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) fail('Oggetto JSON richiesto.');
    $event = $input['event'] ?? null;
    if ($event === 'ping') { contentai_log('ping ricevuto: collegamento funzionante'); respond(['ok' => true]); }
    if ($event !== 'article.publish') { contentai_log('evento non supportato: ' . (is_string($event) ? preg_replace('/[^A-Za-z0-9_.-]/', '', mb_substr($event, 0, 40)) : '(assente)') . ' | ' . contentai_fields($input)); fail('Evento non supportato.'); }

    set_time_limit(60);
    $art = $input['article'] ?? null;
    $site = is_array($input['site'] ?? null) ? $input['site'] : [];
    if (!is_array($art)) throw new InvalidArgumentException('article');
    $externalId = $art['id'] ?? null;
    if (!is_string($externalId) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $externalId)) throw new InvalidArgumentException('id');
    $slug = contentai_slug($art['slug'] ?? null);
    $role = strtoupper(contentai_text($art['role'] ?? '', 12));
    if (!in_array($role, ['PILLAR', 'CLUSTER'], true)) $role = '';
    $pillarSlug = '';
    if (is_array($art['pillar'] ?? null) && isset($art['pillar']['slug'])) $pillarSlug = contentai_slug($art['pillar']['slug']);
    $lang = ($site['language'] ?? 'it') === 'en' ? 'en' : 'it';
    $flat = [
        'id' => 'cai-' . $externalId, 'title' => $art['title'] ?? '', 'slug' => $slug, 'language' => $lang, 'published_at' => '',
        'content' => (string)($art['htmlWithoutFaq'] ?? $art['html'] ?? ''), 'image_url' => '',
        'category' => $lang === 'en' ? ($role === 'PILLAR' ? 'Guides' : 'Insights') : ($role === 'PILLAR' ? 'Guide' : 'Approfondimenti'),
        'image_alt' => contentai_text($art['image']['alt'] ?? '', 240),
    ];
    $excerpt = contentai_text($art['excerpt'] ?? '', 400);
    if ($excerpt !== '') $flat['excerpt'] = $excerpt;
    foreach (['title', 'content'] as $key) if (!is_string($flat[$key])) throw new InvalidArgumentException($key);

    $lock = blog_lock(true);
    $db = blog_db();
    $a = blog_article_data($flat, true, $c); // sanifica HTML, valida titolo/slug, stato bozza salvo auto_publish
    $old = blog_one($db, 'SELECT * FROM blog_articles WHERE source_id=?', [$a['source_id']]);
    if ($old) foreach (['slug', 'status', 'featured', 'published_at', 'language'] as $field) $a[$field] = $old[$field];

    $keywords = [];
    foreach (array_slice(is_array($art['keywords'] ?? null) ? $art['keywords'] : [], 0, 12) as $keyword) { $text = contentai_text($keyword, 60); if ($text !== '') $keywords[] = $text; }
    $faq = [];
    foreach (array_slice(is_array($art['faq'] ?? null) ? $art['faq'] : [], 0, 8) as $item) {
        if (!is_array($item)) continue;
        $question = contentai_text($item['question'] ?? '', 240); $answer = contentai_text($item['answer'] ?? '', 900);
        if ($question !== '' && $answer !== '') $faq[] = ['question' => $question, 'answer' => $answer];
    }
    $a['meta_title'] = contentai_text($art['metaTitle'] ?? '', 160);
    $a['meta_description'] = contentai_text($art['metaDescription'] ?? '', 400);
    $a['keywords'] = mb_substr(implode(', ', $keywords), 0, 400);
    $a['faq_json'] = $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
    $a['role'] = $role;
    $a['pillar_slug'] = $pillarSlug;

    $newCover = contentai_store_cover($art['image'] ?? null, $a['slug']);
    if ($newCover) $a['image_url'] = rtrim($c['site_url'], '/') . '/uploads/site/' . $newCover;
    elseif ($old) { $a['image_url'] = $old['image_url']; $a['image_alt'] = $old['image_alt']; }
    $a['updated_at'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');

    $id = blog_persist_article($db, $a, $old ? (int)$old['id'] : null);
    if ($newCover && $old && $old['image_url'] !== $a['image_url']) contentai_remove_cover($old['image_url']);
    audit('blog-contentai', (string)$id);
    contentai_log('articolo ' . $externalId . ' -> ' . $id . ' (' . ($old ? 'aggiornato' : 'nuovo') . ', ' . ($a['status'] === 'published' ? 'pubblicato' : 'bozza') . ($newCover ? ', con copertina' : '') . ') | ' . contentai_fields($input));
    seo_indexes();
    respond(['id' => $id, 'status' => $a['status'], 'url' => $a['status'] === 'published' ? blog_article_url($c, $a) : null], $old ? 200 : 201);
} catch (JsonException | InvalidArgumentException $e) {
    contentai_log('dati non validi (' . ($e instanceof JsonException ? 'JSON' : mb_substr($e->getMessage(), 0, 120)) . ')' . (isset($input) && is_array($input) ? ' | ' . contentai_fields($input) : ''));
    if ($newCover) contentai_remove_cover('/uploads/site/' . $newCover);
    fail('Dati articolo non validi.', 422);
} catch (PDOException $e) {
    if ($newCover) contentai_remove_cover('/uploads/site/' . $newCover);
    error_log('Blog contentai database: ' . $e->getCode());
    contentai_log('conflitto: slug o identificatore già usato da un altro articolo');
    fail('Slug o identificatore in conflitto.', 409);
} catch (Throwable $e) {
    if ($newCover) contentai_remove_cover('/uploads/site/' . $newCover);
    error_log('Blog contentai: ' . get_class($e));
    contentai_log('errore interno: ' . get_class($e));
    fail('Ricezione temporaneamente non disponibile.', 503);
}
