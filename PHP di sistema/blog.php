<?php
declare(strict_types=1);

const BLOG_VERSION = '2.1.68';
const PRIVATE_DIR = ROOT . '/data';
function blog_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function blog_abort_request(int $code, string $message): void { http_response_code($code); echo blog_h($message); exit; }
function blog_prefix(): string {
    $script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '/blog/index.php');
    return rtrim(str_replace('\\','/', dirname(dirname($script))), '/.\\');
}
function blog_site_url(): string {
    $site = rtrim(settings()['site_url'], '/');
    if ($site !== '') return $site;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-zA-Z0-9.\[\]:-]+$/D', $host)) $host = 'localhost';
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $host . blog_prefix();
}
function blog_config(): array {
    $file = PRIVATE_DIR . '/blog-config.json';
    $stored = is_file($file) ? json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR) : [];
    return array_replace(['version'=>BLOG_VERSION,'db'=>['driver'=>'sqlite'],'webhook_hash'=>'','auto_publish'=>false],$stored,
        ['base_url'=>blog_site_url().'/blog','site_url'=>blog_site_url(),'brand'=>'Valutazione Diamanti','subtitle'=>'Guide e approfondimenti su diamanti, gioielli e valutazione.']);
}
function blog_save_config(array $c): void {
    unset($c['base_url'],$c['site_url'],$c['brand'],$c['subtitle']);
    $tmp = PRIVATE_DIR . '/blog-config-' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($tmp, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Configurazione non scrivibile.');
    chmod($tmp, 0600);
    unset($c['base_url'],$c['site_url'],$c['brand'],$c['subtitle']);
    if (!rename($tmp, PRIVATE_DIR . '/blog-config.json')) { unlink($tmp); throw new RuntimeException('Impossibile salvare la configurazione.'); }
}
function blog_connection(array $c): PDO {
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . PRIVATE_DIR . '/blog.sqlite');
        $pdo->exec('PRAGMA busy_timeout=5000');
    } else {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $c['host']) || !preg_match('/^[a-zA-Z0-9_]+$/D', $c['database'])) throw new RuntimeException('Host o nome database non valido.');
        $pdo = new PDO('mysql:host=' . $c['host'] . ';port=' . (int)$c['port'] . ';dbname=' . $c['database'] . ';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_TIMEOUT => 10]);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}
// Campi aggiunti dal connettore ContentAI (2.1.21). I nomi e le definizioni sono costanti: mai input esterno.
const BLOG_EXTRA_COLUMNS = [
    'meta_title' => "VARCHAR(160) NOT NULL DEFAULT ''",
    'meta_description' => "VARCHAR(400) NOT NULL DEFAULT ''",
    'keywords' => "VARCHAR(400) NOT NULL DEFAULT ''",
    'faq_json' => 'TEXT',
    'role' => "VARCHAR(12) NOT NULL DEFAULT ''",
    'pillar_slug' => "VARCHAR(180) NOT NULL DEFAULT ''",
    // Slug precedenti separati da spazio (2.1.63): cambiando lo slug il vecchio indirizzo risponde con un 301.
    'old_slugs' => 'TEXT',
];
function blog_columns(PDO $pdo): array {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $rows = $pdo->query($mysql ? 'SHOW COLUMNS FROM blog_articles' : 'PRAGMA table_info(blog_articles)')->fetchAll(PDO::FETCH_ASSOC);
    return array_column($rows, $mysql ? 'Field' : 'name');
}
/** Migrazione additiva e idempotente: aggiunge solo le colonne mancanti, non modifica ne elimina dati. */
function blog_migrate(PDO $pdo): void {
    $existing = blog_columns($pdo);
    foreach (BLOG_EXTRA_COLUMNS as $name => $definition) {
        if (in_array($name, $existing, true)) continue;
        try { $pdo->exec('ALTER TABLE blog_articles ADD COLUMN ' . $name . ' ' . $definition); }
        catch (PDOException $e) { if (!in_array($name, blog_columns($pdo), true)) throw $e; } // richiesta concorrente gia migrata
    }
}
function blog_schema(PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $pk = $mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_articles (
        id $pk, source_id VARCHAR(160) UNIQUE, slug VARCHAR(180) NOT NULL UNIQUE,
        title VARCHAR(240) NOT NULL, excerpt TEXT NOT NULL, content TEXT NOT NULL,
        language VARCHAR(5) NOT NULL, category VARCHAR(80) NOT NULL, image_url TEXT NOT NULL, image_alt VARCHAR(240) NOT NULL,
        status VARCHAR(16) NOT NULL, featured INTEGER NOT NULL DEFAULT 0,
        published_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL
    )$tail");
    blog_migrate($pdo);
}
function blog_rows(PDO $db, string $sql, array $params = []): array { $s = $db->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function blog_one(PDO $db, string $sql, array $params = []): ?array { return blog_rows($db, $sql, $params)[0] ?? null; }
function blog_base(array $c): string { return rtrim($c['base_url'], '/'); }
function blog_url(array $c, string $path = ''): string { return blog_base($c) . $path; }
// Blog inglese con lo stesso schema del sito (2.1.63, audit): /en/blog/ e /en/blog/<slug>. I vecchi indirizzi
// /blog/?lang=en e /blog/articolo/<slug>?lang=en rispondono con un 301 (blog/index.php).
function blog_home_url(array $c, string $lang): string { return $lang === 'en' ? preg_replace('~/blog$~', '', blog_base($c)) . '/en/blog/' : blog_url($c, '/'); }
function blog_article_url(array $c, array $a): string { return ($a['language'] ?? 'it') === 'en' ? blog_home_url($c, 'en') . rawurlencode($a['slug']) : blog_url($c, '/articolo/' . rawurlencode($a['slug'])); }
/** Estratto senza parole tagliate (2.1.63): taglia all'ultima parola intera entro $max caratteri e aggiunge «…».
 *  Anche gli estratti già salvati tronchi a metà parola (es. «da solo a stabi») perdono il pezzo di parola. */
function blog_excerpt(string $text, int $max = 220, string $content = ''): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '' && $content !== '') $text = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $cut = false;
    if (mb_strlen($text) > $max) { $text = mb_substr($text, 0, $max + 1); $cut = true; }
    elseif ($content !== '' && !preg_match('/[.!?…»"”)]$/u', $text)) {
        // Estratto salvato tronco: il testo dell'articolo prosegue subito dopo, senza spazio, nella stessa parola.
        $plain = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $at = $text !== '' ? mb_strpos($plain, $text) : false;
        if ($at !== false && mb_strlen($plain) > $at + mb_strlen($text)) { $text .= mb_substr($plain, $at + mb_strlen($text), 1); $cut = true; }
    }
    if (!$cut) return $text;
    if (preg_match('/^(.*\S)\s+\S*$/su', $text, $m) && !preg_match('/\s/u', mb_substr($text, -1))) $text = $m[1];
    // 2.1.67: niente articoli o preposizioni rimasti in fondo prima dei puntini («… e la…», «… per…»).
    $text = rtrim($text, " ,;:–—-");
    while (preg_match('/^(.*\\S)\\s+(?:a|e|i|o|il|lo|la|le|gli|l’|un|una|uno|di|da|in|con|su|per|tra|fra|che|del|della|dei|delle|al|alla|ai|nel|nella|the|an|and|or|of|to|in|for|with|on|by|at)$/iu', $text, $m)) $text = rtrim($m[1], " ,;:–—-");
    return $text . '…';
}
function blog_valid_url(string $v, bool $empty = true): bool { return ($empty && $v === '') || (filter_var($v, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string)parse_url($v, PHP_URL_SCHEME)), ['https','http'], true) && !parse_url($v, PHP_URL_USER) && !parse_url($v, PHP_URL_PASS)); }
function blog_slugify(string $v): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    return substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $v : $ascii)), '-'), 0, 180);
}
function blog_sanitize_html(string $html): string {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $old = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $allowed = ['p','br','h2','h3','h4','ul','ol','li','strong','em','b','i','a','blockquote','img','figure','figcaption','hr','table','thead','tbody','tr','th','td'];
    $clean = function (DOMNode $node) use (&$clean, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) { $node->removeChild($child); continue; }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowed, true)) {
                if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form','input','button','template'], true)) $node->removeChild($child);
                else { $clean($child); while ($child->firstChild) $node->insertBefore($child->firstChild, $child); $node->removeChild($child); }
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name); $value = $attr->value;
                $ok = in_array($name, ['alt','title'], true) || ($tag === 'p' && $name === 'class' && trim($value) === 'answer') || ($tag === 'a' && $name === 'href' && blog_valid_url($value, false)) || ($tag === 'img' && $name === 'src' && blog_valid_url($value, false));
                if (!$ok) $child->removeAttribute($attr->name);
            }
            if ($tag === 'a') $child->setAttribute('rel', 'noopener noreferrer');
            if ($tag === 'img') { $child->setAttribute('loading', 'lazy'); $child->setAttribute('referrerpolicy', 'no-referrer'); }
            $clean($child);
        }
    };
    $body = $doc->getElementsByTagName('body')->item(0); $clean($body);
    $out = ''; foreach ($body->childNodes as $child) $out .= $doc->saveHTML($child);
    return $out;
}
function blog_article_data(array $in, bool $webhook, array $c): array {
    foreach (['id','title','slug','excerpt','content','category','image_url','image_alt','published_at','status','featured','language'] as $key) if (isset($in[$key]) && !is_scalar($in[$key])) throw new InvalidArgumentException('Campo non valido: ' . $key);
    $title = trim((string)($in['title'] ?? '')); $slug = blog_slugify(trim((string)($in['slug'] ?? '')) ?: $title);
    $html = (string)($in['content'] ?? '');
    if ($title === '' || mb_strlen($title) > 240 || $slug === '' || strlen($html) > 500000) throw new InvalidArgumentException('Titolo, slug o contenuto non valido.');
    $content = blog_sanitize_html($html);
    if (trim(strip_tags($content)) === '') throw new InvalidArgumentException('Inserire il testo dell’articolo.');
    $image = trim((string)($in['image_url'] ?? ''));
    if (!blog_valid_url($image)) throw new InvalidArgumentException('URL immagine non valido.');
    $source = $webhook ? trim((string)($in['id'] ?? '')) : null;
    if ($webhook && ($source === '' || mb_strlen($source) > 160)) throw new InvalidArgumentException('Identificatore id richiesto, massimo 160 caratteri.');
    $published = (string)($in['published_at'] ?? '');
    if ($published !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $published) || !($d = DateTimeImmutable::createFromFormat('!Y-m-d', $published)) || $d->format('Y-m-d') !== $published || $published > gmdate('Y-m-d'))) throw new InvalidArgumentException('Data non valida o futura. Per programmare una pubblicazione mantenere la bozza.');
    return [
        'language' => (($in['language'] ?? 'it') === 'en' ? 'en' : 'it'), 'source_id' => $source, 'slug' => $slug, 'title' => $title,
        'excerpt' => trim(strip_tags((string)($in['excerpt'] ?? ''))) !== '' ? blog_excerpt(strip_tags((string)$in['excerpt']), 400) : blog_excerpt('', 180, $content),
        'content' => $content, 'category' => mb_substr(trim(strip_tags((string)($in['category'] ?? 'Approfondimenti'))), 0, 80),
        'image_url' => $image, 'image_alt' => mb_substr(trim(strip_tags((string)($in['image_alt'] ?? $title))), 0, 240),
        'status' => $webhook ? (!empty($c['auto_publish']) ? 'published' : 'draft') : (($in['status'] ?? '') === 'published' ? 'published' : 'draft'),
        'featured' => $webhook ? 0 : (!empty($in['featured']) ? 1 : 0),
        'published_at' => $published === '' ? gmdate('Y-m-d') : $published, 'updated_at' => gmdate('c')
    ];
}
function blog_persist_article(PDO $db, array $a, ?int $id = null): int {
    if ($id !== null) {
        $was = blog_one($db, 'SELECT slug, old_slugs FROM blog_articles WHERE id = ?', [$id]);
        if ($was && isset($a['slug']) && $was['slug'] !== $a['slug']) {
            $list = array_values(array_diff(array_filter(preg_split('/\s+/', trim((string)$was['old_slugs'] . ' ' . $was['slug']))), [$a['slug']]));
            $a['old_slugs'] = implode(' ', array_slice(array_unique($list), -20));
        }
        $sets = implode(',', array_map(fn($k) => "$k = ?", array_keys($a)));
        $s = $db->prepare("UPDATE blog_articles SET $sets WHERE id = ?"); $s->execute([...array_values($a), $id]); return $id;
    }
    $s = $db->prepare('INSERT INTO blog_articles (' . implode(',', array_keys($a)) . ') VALUES (' . implode(',', array_fill(0, count($a), '?')) . ')');
    $s->execute(array_values($a)); return (int)$db->lastInsertId();
}
function blog_db_move(PDO $old, array $destination, array $c): void {
    $target = blog_connection($destination); blog_schema($target);
    if ((int)$target->query('SELECT COUNT(*) FROM blog_articles')->fetchColumn() !== 0) throw new RuntimeException('La destinazione contiene articoli. Usare un database vuoto; nessun dato è stato sovrascritto.');
    $articles = blog_rows($old, 'SELECT * FROM blog_articles ORDER BY id');
    $target->beginTransaction();
    try {
        foreach ($articles as $a) {
            $s = $target->prepare('INSERT INTO blog_articles (' . implode(',', array_keys($a)) . ') VALUES (' . implode(',', array_fill(0, count($a), '?')) . ')'); $s->execute(array_values($a));
        }
        $copied = blog_rows($target, 'SELECT * FROM blog_articles ORDER BY id');
        if (count($articles) !== count($copied)) throw new RuntimeException('Verifica trasferimento fallita.');
        foreach ($articles as $i => $a) foreach ($a as $key => $value) if ((string)$value !== (string)$copied[$i][$key]) throw new RuntimeException('Verifica contenuto fallita.');
        $target->commit();
    } catch (Throwable $e) { if ($target->inTransaction()) $target->rollBack(); throw $e; }
    $c['db'] = $destination; blog_save_config($c);
}

/** FAQ salvate dal connettore: array di coppie question/answer gia ripulite, mai HTML. */
function blog_faq_items(?string $json): array {
    if ($json === null || $json === '') return [];
    try { $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR); } catch (JsonException) { return []; }
    if (!is_array($data)) return [];
    $items = [];
    foreach (array_slice($data, 0, 8) as $item) {
        if (!is_array($item) || !is_string($item['question'] ?? null) || !is_string($item['answer'] ?? null)) continue;
        $q = trim($item['question']); $a = trim($item['answer']);
        if ($q !== '' && $a !== '') $items[] = ['question' => mb_substr($q, 0, 240), 'answer' => mb_substr($a, 0, 900)];
    }
    return $items;
}

function blog_db(): PDO { $db=blog_connection(blog_config()['db']); blog_schema($db); return $db; }
function blog_lock(bool $exclusive = false) { $handle=fopen(ROOT.'/data/blog.lock','c'); if(!$handle||!flock($handle,$exclusive?LOCK_EX:LOCK_SH))throw new RuntimeException('Blog occupato.');return $handle; }
function blog_seo_entries(): array { return blog_rows(blog_db(), "SELECT slug,language,title,updated_at,image_url FROM blog_articles WHERE status='published' ORDER BY published_at DESC,id DESC"); }

// Registro diagnostico del collegamento ContentAI Pro (2.1.31): evento, id, esito, nomi dei campi ricevuti. Mai token o testi.
const CONTENTAI_LOG = PRIVATE_DIR . '/contentai.log';
function contentai_log(string $line): void {
    $line = gmdate('Y-m-d H:i:s') . ' ' . str_replace(["\r", "\n"], ' ', mb_substr($line, 0, 400)) . "\n";
    $old = is_file(CONTENTAI_LOG) ? (string)@file_get_contents(CONTENTAI_LOG) : '';
    $lines = array_slice(array_filter(explode("\n", $old)), -199);
    @file_put_contents(CONTENTAI_LOG, implode("\n", $lines) . ($lines ? "\n" : '') . $line, LOCK_EX);
    @chmod(CONTENTAI_LOG, 0600);
}
function contentai_log_lines(int $max = 40): array {
    return is_file(CONTENTAI_LOG) ? array_slice(array_reverse(array_filter(explode("\n", (string)file_get_contents(CONTENTAI_LOG)))), 0, $max) : [];
}
