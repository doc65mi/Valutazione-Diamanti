<?php
declare(strict_types=1);
const APP_VERSION = '2.1.21';
const ROOT = __DIR__ . '/..';
const PAGE_SLUGS = ['home', 'acquistiamo', 'chisiamo', 'faq', 'valutazione', 'grazie', 'privacy', 'cookie'];
require_once __DIR__.'/settings.php';
require_once __DIR__.'/seo.php';
require_once __DIR__.'/footer.php';
require_once __DIR__.'/emails.php';
function db(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('sqlite:' . ROOT . '/data/site.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
        $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, pages TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS requests (id TEXT PRIMARY KEY, request_key TEXT UNIQUE NOT NULL, created_at TEXT NOT NULL, status TEXT NOT NULL DEFAULT "Nuova", language TEXT NOT NULL, payload TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS attachments (id TEXT PRIMARY KEY, request_id TEXT NOT NULL REFERENCES requests(id), name TEXT NOT NULL, path TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS rate_limits (bucket TEXT PRIMARY KEY, count INTEGER NOT NULL, expires INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL, user_id INTEGER, action TEXT NOT NULL, target TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS revisions (id INTEGER PRIMARY KEY, page TEXT NOT NULL, language TEXT NOT NULL, version INTEGER NOT NULL, content TEXT NOT NULL, created_at TEXT NOT NULL, user_id INTEGER NOT NULL);');
    }
    return $db;
}
function session_start_secure(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    session_save_path(ROOT . '/data/sessions');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Strict','path'=>'/']);
    session_start();
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 1800) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['last'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function security_headers(bool $private = false): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    if ($private) { header('X-Robots-Tag: noindex, nofollow, noarchive'); header('Cache-Control: no-store'); }
}
function respond(array $value, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
function fail(string $message, int $status = 422): never { respond(['error'=>$message], $status); }
function csrf(): void { if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Sessione non valida. Ricarica la pagina. / Please reload the page.',403); }
function user(): array {
    $q=db()->prepare('SELECT id,username,role,pages,password_hash FROM users WHERE id=?'); $q->execute([$_SESSION['uid'] ?? 0]);
    $u=$q->fetch(); if (!$u||!hash_equals(hash('sha256',$u['password_hash']),$_SESSION['credential_stamp']??'')) fail('Accesso richiesto.',401); unset($u['password_hash']); $u['pages']=json_decode($u['pages'],true); return $u;
}
function can_page(array $u,string $slug): void { if ($u['role'] !== 'admin' && !in_array($slug,$u['pages'],true)) fail('Pagina non assegnata.',403); }
function admin_user(): array { $u=user(); if($u['role']!=='admin') fail('Permesso richiesto.',403); return $u; }
function audit(string $action, string $target): void { $q=db()->prepare('INSERT INTO audit(created_at,user_id,action,target) VALUES(?,?,?,?)'); $q->execute([gmdate('c'),$_SESSION['uid'] ?? null,$action,$target]); }
function rate(string $purpose,int $limit,int $window): void {
    $bucket=hash('sha256',$purpose . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $q=db()->prepare('INSERT INTO rate_limits(bucket,count,expires) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET count=CASE WHEN expires < ? THEN 1 ELSE count+1 END, expires=CASE WHEN expires < ? THEN excluded.expires ELSE expires END RETURNING count');
    $now=time(); $q->execute([$bucket,$now+$window,$now,$now]); if((int)$q->fetchColumn()>$limit) fail('Troppi tentativi. Riprova più tardi. / Too many attempts. Please try again later.',429);
    db()->prepare('DELETE FROM rate_limits WHERE expires < ?')->execute([$now-86400]);
}
function page_path(string $slug,string $lang): string { if(!in_array($slug,PAGE_SLUGS,true)||!in_array($lang,['it','en'],true)) fail('Pagina non valida.',404); return ROOT . '/content/' . $slug . '.' . $lang . '.json'; }
function content(string $slug,string $lang): array {
    $c=json_decode(file_get_contents(page_path($slug,$lang)),true,512,JSON_THROW_ON_ERROR);
    if($slug==='valutazione') {
        $doc=template_doc($slug,$lang);$xp=new DOMXPath($doc);
        foreach($xp->query('//*[@data-om-slot]') as $el) {
            $id=$el->getAttribute('data-om-slot');
            if(!str_starts_with($id,'valutazione-ripristino-')||array_key_exists($id,$c['slots']))continue;
            $type=$el->getAttribute('data-om-type');
            $c['slots'][$id]=$type==='image'?['src'=>$el->getAttribute('src'),'alt'=>$el->getAttribute('alt')]:($type==='button'?['href'=>$el->getAttribute('href'),'label'=>$el->textContent]:$el->textContent);
        }
    }
    return $c;
}
function atomic_write(string $path,string $text): void {
    $tmp=tempnam(dirname($path),'.write-'); if($tmp===false) throw new RuntimeException('Scrittura non disponibile.');
    try { if(file_put_contents($tmp,$text)===false || !rename($tmp,$path)) throw new RuntimeException('Salvataggio non riuscito.'); } finally { if(is_file($tmp)) unlink($tmp); }
}
function template_doc(string $slug,string $lang): DOMDocument {
    page_path($slug,$lang); $doc=new DOMDocument('1.0','UTF-8');
    $fragment=file_get_contents(ROOT . '/pages-src/' . $slug . '.' . $lang . '.html');
    $layout=file_get_contents(ROOT . '/partials/' . $slug . '.' . $lang . '.html');
    libxml_use_internal_errors(true); $doc->loadHTML('<?xml encoding="UTF-8">' . str_replace('{{content}}',$fragment,$layout),LIBXML_NONET); libxml_clear_errors();
    foreach(iterator_to_array($doc->childNodes) as $node) if($node->nodeType===XML_PI_NODE) $doc->removeChild($node);
    return $doc;
}
function slot_schema(string $slug,string $lang): array {
    $doc=template_doc($slug,$lang); $xp=new DOMXPath($doc); $out=[];
    foreach($xp->query('//*[@data-om-slot]') as $el) $out[$el->getAttribute('data-om-slot')]=['type'=>$el->getAttribute('data-om-type'),'label'=>$el->getAttribute('data-om-label'),'role'=>$el->getAttribute('data-om-role'),'max'=>(int)($el->getAttribute('data-om-max')?:5000)];
    return $out;
}
function safe_url(string $url, bool $image=false): bool {
    if(!$image && $url==='../../admin/') return true;
    if($url==='' || preg_match('/[\x00-\x20\\\\]/',$url)) return false;
    if(preg_match('~^https?://~i',$url)) return (bool)filter_var($url,FILTER_VALIDATE_URL);
    if(!$image && preg_match('~^(mailto:[^<>]+@[^<>]+|tel:\+?[0-9 -]+|#[a-zA-Z0-9_-]+)$~',$url)) return true;
    return !preg_match('~^(//|[^/]*:)|(^|/)\.\.(/|$)~',$url) && !str_contains($url,'<') && !str_contains($url,'>');
}
function validate_content(array $input,array $schema,array $previous,array $u): array {
    if(!is_array($input['slots'] ?? null) || count($input['slots'])!==count($previous['slots']) || array_diff_key($input['slots'],$previous['slots'])) fail('I campi della pagina non corrispondono al template.');
    $out=$previous;
    foreach($schema as $id=>$s) {
        $v=$input['slots'][$id] ?? null;
        if($u['role']!=='admin' && ($s['role']??'')==='admin') { if($v!==$previous['slots'][$id]) fail('Questo campo è riservato all’amministratore.',403); continue; }
        if($s['type']==='text') {
            if(!is_string($v)||mb_strlen($v)>$s['max']||str_contains($v,"\0")) fail('Testo non valido: '.$s['label']);
            $out['slots'][$id]=trim(strip_tags($v));
        } elseif($s['type']==='image') {
            if(!is_array($v)||!is_string($v['src']??null)||!safe_url($v['src'],true)||!is_string($v['alt']??null)||trim($v['alt'])===''||mb_strlen($v['alt'])>250) fail('Inserisci un URL immagine valido e un testo alternativo: '.$s['label']);
            $out['slots'][$id]=['src'=>$v['src'],'alt'=>strip_tags($v['alt'])];
        } elseif($s['type']==='button') {
            if(!is_array($v)||!is_string($v['href']??null)||!safe_url($v['href'])||!is_string($v['label']??null)||mb_strlen($v['label'])>300) fail('Collegamento non valido: '.$s['label']);
            $out['slots'][$id]=['href'=>$v['href'],'label'=>strip_tags($v['label'])];
        } else fail('Tipo di campo non supportato.');
    }
    foreach(['title'=>100,'description'=>300] as $key=>$max) {
        $v=$input['meta'][$key]??''; if(!is_string($v)||trim($v)===''||mb_strlen($v)>$max) fail('Compila correttamente '.$key.'.'); $out['meta'][$key]=strip_tags(trim($v));
    }
    $out['version']=$previous['version']+1; $out['updated_at']=gmdate('c'); $out['updated_by']=$u['id']; return $out;
}
function build_html(string $slug,string $lang,array $c): string {
    $doc=template_doc($slug,$lang); $xp=new DOMXPath($doc);
    foreach($xp->query('//*[@data-om-slot]') as $el) {
        $el->setAttribute('data-cd-slot',$el->getAttribute('data-om-slot'));
        $v=$c['slots'][$el->getAttribute('data-om-slot')]; $type=$el->getAttribute('data-om-type');
        if($type==='text') $el->textContent=$v;
        if($type==='image') { $el->setAttribute('src',$v['src']); $el->setAttribute('alt',$v['alt']); }
        if($type==='button') { $el->setAttribute('href',$v['href']); $el->textContent=$v['label']; }
    }
    $legal=settings();
    $replacements=[];foreach(['company','address','vat','email'] as $key)$replacements['{{legal.'.$key.'}}']=$legal['legal_'.$key];$replacements['{{legal.retention}}']=(string)$legal['retention_months'];
    foreach($xp->query('//text()') as $node)$node->nodeValue=strtr($node->nodeValue,$replacements);
    $legal['site_url']=seo_base();
    if($legal['site_url']) {
        $name=$slug==='home'?'index':$slug;$head=$doc->getElementsByTagName('head')->item(0);
        $link=$doc->createElement('link');$link->setAttribute('rel','canonical');$link->setAttribute('href',rtrim($legal['site_url'],'/').'/'.$lang.'/'.$name.'.html');$head->appendChild($link);
        foreach($xp->query('//link[@rel="alternate"]') as $alt){$l=$alt->getAttribute('hreflang');$alt->setAttribute('href',rtrim($legal['site_url'],'/').'/'.($l==='x-default'?'it':$l).'/'.$name.'.html');}
    }
    $doc->getElementsByTagName('title')->item(0)->textContent=$c['meta']['title'];
    foreach($xp->query('//meta[@name="description"]') as $el) $el->setAttribute('content',$c['meta']['description']);
    foreach($xp->query('//*') as $el) { $remove=[]; foreach($el->attributes as $a) if(str_starts_with($a->name,'data-om-')) $remove[]=$a->name; foreach($remove as $a) $el->removeAttribute($a); }
    $header=$doc->getElementsByTagName('header')->item(0);
    $languageNav=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," language-nav ")]')->item(0);
    if($header&&$languageNav){
        $container=$header;foreach($header->childNodes as $child)if($child instanceof DOMElement&&$child->tagName==='div'){$container=$child;break;}
        $languageNav->setAttribute('class','language-nav header-languages');$container->appendChild($languageNav);
    }
    $toolbar=$doc->createElement('nav');$toolbar->setAttribute('class','mobile-contact-toolbar');$toolbar->setAttribute('aria-label',$lang==='en'?'Quick contact':'Contatti rapidi');
    $links=[['valutazione.html',$lang==='en'?'Valuation':'Valutazione','◇'],['https://wa.me/393929664987','WhatsApp',''],['mailto:sl@ladiamanteria.com','Email','✉'],['tel:+3902466891',$lang==='en'?'Call':'Telefono','☎']];
    foreach($links as [$href,$label,$icon]){
        $a=$doc->createElement('a');$a->setAttribute('href',$href);
        if(str_starts_with($href,'https:')){$a->setAttribute('target','_blank');$a->setAttribute('rel','noopener');}
        $symbol=$doc->createElement('span',$icon);$symbol->setAttribute('aria-hidden','true');$symbol->setAttribute('class','contact-icon');$a->appendChild($symbol);$a->appendChild($doc->createElement('span',$label));$toolbar->appendChild($a);
    }
    $doc->getElementsByTagName('body')->item(0)->appendChild($toolbar);
    $head=$doc->getElementsByTagName('head')->item(0);
    $css=$doc->createElement('link');$css->setAttribute('rel','stylesheet');$css->setAttribute('href','../assets/frontend-ui.css');$head->appendChild($css);
    foreach(['frontend-ui.js','frontend-editor.js'] as $file){$script=$doc->createElement('script');$script->setAttribute('src','../assets/'.$file);$script->setAttribute('defer','defer');$head->appendChild($script);}
    $mainNav=$xp->query('//header//nav[not(contains(@class,"language-nav"))]')->item(0);
    if($mainNav){$blogLink=$doc->createElement('a','Blog');$blogLink->setAttribute('href','../blog/');$blogLink->setAttribute('class','site-blog-link');if($lang==='en')$blogLink->setAttribute('href','../blog/?lang=en');$mainNav->appendChild($blogLink);}
    $footer=$doc->getElementsByTagName('footer')->item(0);

    tidy_footer($doc,$lang);
    $url=seo_base().'/'.$lang.'/'.($slug==='home'?'index':$slug).'.html';
    seo_markup($doc,$lang,$url,$c['meta']['title'],$c['meta']['description'],$slug==='faq'?'FAQPage':($slug==='chisiamo'?'AboutPage':'WebPage'));
    if($slug==='grazie'){$meta=$doc->createElement('meta');$meta->setAttribute('name','robots');$meta->setAttribute('content','noindex,follow');$head->appendChild($meta);}
    return str_replace('../../assets/', '../assets/', $doc->saveHTML());
}
function publish(string $slug,string $lang,array $c): void { $name=$slug==='home'?'index':$slug; atomic_write(ROOT.'/'.$lang.'/'.$name.'.html',build_html($slug,$lang,$c)); }
function seo_indexes(): void {
    $s=settings();$base=seo_base();$xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    $llms="# ComproDiamanti.it\n\nDirect buying and free valuations of natural diamonds, jewellery and selected watches by La Diamanteria in Milan.\n\n## Pages\n";
    foreach(PAGE_SLUGS as $slug)if($slug!=='grazie')foreach(['it','en'] as $lang){$name=$slug==='home'?'index':$slug;$url=$base.'/'.$lang.'/'.$name.'.html';$c=content($slug,$lang);if($base)$xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1).'</loc></url>';$llms.='- ['.$c['meta']['title'].']('.$url.")\n";}
    require_once __DIR__.'/blog.php';
    if($base){$xml.='<url><loc>'.htmlspecialchars($base.'/blog/',ENT_XML1).'</loc></url><url><loc>'.htmlspecialchars($base.'/blog/?lang=en',ENT_XML1).'</loc></url>';$posts=blog_seo_entries();
        foreach($posts as $post){
            $xml.='<url><loc>'.htmlspecialchars(blog_article_url(['base_url'=>$base.'/blog'],$post),ENT_XML1).'</loc>';
            if(preg_match('/^\d{4}-\d{2}-\d{2}/',(string)$post['updated_at'],$day))$xml.='<lastmod>'.$day[0].'</lastmod>';
            if(blog_valid_url((string)$post['image_url'],false))$xml.='<image:image><image:loc>'.htmlspecialchars($post['image_url'],ENT_XML1).'</image:loc></image:image>';
            $xml.='</url>';
        }
    }
    $llms.='- [Blog]('.$base.'/blog/)' . "\n";
    if($base&&!empty($posts)){$llms.="\n## Blog articles\n";foreach(array_slice($posts,0,100) as $post)$llms.='- ['.trim(str_replace(['[',']','(',')',"\r","\n"],' ',(string)$post['title'])).']('.blog_article_url(['base_url'=>$base.'/blog'],$post).")\n";}
    atomic_write(ROOT.'/sitemap.xml',$xml.'</urlset>');atomic_write(ROOT.'/llms.txt',$llms);
    $robots="User-agent: *\nDisallow: /admin/\nDisallow: /api/\nDisallow: /data/\nDisallow: /uploads/clienti/\nDisallow: /pages-src/\nDisallow: /partials/\nDisallow: /content/\nDisallow: /tools/\nDisallow: /old-page/\nDisallow: /Login.dc.html\nDisallow: /Admin.dc.html\n";
    if($base)$robots.='Sitemap: '.$base."/sitemap.xml\n";atomic_write(ROOT.'/robots.txt',$robots);
}
