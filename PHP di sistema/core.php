<?php
declare(strict_types=1);
const APP_VERSION = '2.1.48';
const ROOT = __DIR__ . '/..';
const PAGE_SLUGS = ['home', 'acquistiamo', 'chisiamo', 'faq', 'valutazione', 'grazie', 'privacy', 'cookie'];
require_once __DIR__.'/settings.php';
require_once __DIR__.'/seo.php';
require_once __DIR__.'/indexing.php';
require_once __DIR__.'/footer.php';
require_once __DIR__.'/emails.php';
require_once __DIR__.'/reviews.php';
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
// Le pagine pubbliche accettano script HTTPS per i codici di tracciamento (2.1.44); l'amministrazione resta stretta.
const CSP_PRIVATE = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";
const CSP_PUBLIC = "default-src 'self'; script-src 'self' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; img-src 'self' https: data:; connect-src 'self' https:; frame-src https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";
function security_headers(bool $private = false): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: '.($private?CSP_PRIVATE:CSP_PUBLIC));
    if ($private) { header('X-Robots-Tag: noindex, nofollow, noarchive'); header('Cache-Control: no-store'); }
}
function respond(array $value, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
function fail(string $message, int $status = 422): never { respond(['error'=>$message], $status); }
function csrf(): void { if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Sessione non valida. Ricarica la pagina. / Please reload the page.',403); }
function user(): array {
    $q=db()->prepare('SELECT id,username,role,pages,password_hash FROM users WHERE id=?'); $q->execute([$_SESSION['uid'] ?? 0]);
    $u=$q->fetch(); if (!$u||!hash_equals(hash('sha256',$u['password_hash']),$_SESSION['credential_stamp']??'')) fail('Accesso richiesto.',401); unset($u['password_hash']); $u['pages']=json_decode($u['pages'],true); return $u;
}
// Vero solo per un amministratore già collegato; non apre sessioni per i visitatori (2.1.27).
function admin_session(): bool {
    if(empty($_COOKIE[session_name()]))return false; session_start_secure();
    $q=db()->prepare('SELECT role,password_hash FROM users WHERE id=?'); $q->execute([$_SESSION['uid'] ?? 0]); $u=$q->fetch();
    return $u&&$u['role']==='admin'&&hash_equals(hash('sha256',$u['password_hash']),$_SESSION['credential_stamp']??'');
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
// 2.1.29: il Pull di Plesk riporta alla versione del repository tutti i file tracciati, compresi content/*.json: le modifiche
// fatte dall'editor del sito andavano perse. Ora i testi modificati vivono in data/content (fuori da git); content/ resta il testo di partenza.
function page_path(string $slug,string $lang): string { if(!in_array($slug,PAGE_SLUGS,true)||!in_array($lang,['it','en'],true)) fail('Pagina non valida.',404); $dir=ROOT.'/data/content'; if(!is_dir($dir)) @mkdir($dir,0700,true); return $dir.'/'.$slug.'.'.$lang.'.json'; }
function content(string $slug,string $lang): array {
    $live=page_path($slug,$lang); $c=json_decode(file_get_contents(ROOT.'/content/'.$slug.'.'.$lang.'.json'),true,512,JSON_THROW_ON_ERROR);
    if(is_file($live))$c=content_upgrade(json_decode(file_get_contents($live),true,512,JSON_THROW_ON_ERROR),$c,$slug.'.'.$lang);
    if($slug==='valutazione') {
        $doc=template_doc($slug,$lang);$xp=new DOMXPath($doc);
        foreach($xp->query('//*[@data-om-slot]') as $el) {
            $id=$el->getAttribute('data-om-slot');
            if(!str_starts_with($id,'valutazione-ripristino-')||array_key_exists($id,$c['slots']))continue;
            $type=$el->getAttribute('data-om-type');
            $c['slots'][$id]=$type==='image'?['src'=>$el->getAttribute('src'),'alt'=>$el->getAttribute('alt')]:($type==='button'?['href'=>$el->getAttribute('href'),'label'=>$el->textContent]:$el->textContent);
        }
    }
    // 2.1.47: sul sito restano solo le immagini caricate qui (uploads/site). Gli indirizzi esterni rimasti dal vecchio prototipo
    // (diamond-shine-guide.lovable.app) diventano «nessuna immagine»: la pagina non le carica più e l'editor chiede di caricarne una.
    foreach($c['slots'] as $id=>$v)if(is_array($v)&&array_key_exists('src',$v)&&!site_image($v['src']))$c['slots'][$id]['src']='';
    return $c;
}
// Testi di partenza aggiornati (2.1.48, audit SEO): nelle pagine già modificate dal sito i campi rimasti uguali al vecchio testo
// di partenza (content/previous-defaults.json) prendono quello nuovo e i campi nuovi del template (le FAQ aggiunte) si aggiungono.
// Quello che è stato cambiato dall'editor resta com'è.
function content_upgrade(array $c,array $default,string $key): array {
    static $old;$old??=json_decode((string)@file_get_contents(ROOT.'/content/previous-defaults.json'),true)?:[];$prev=$old[$key]??[];
    foreach($default['slots'] as $id=>$v)if(!array_key_exists($id,$c['slots'])||in_array($c['slots'][$id],$prev['slots'][$id]??[],true))$c['slots'][$id]=$v;
    foreach(['title','description'] as $m)if(in_array($c['meta'][$m]??null,$prev['meta'][$m]??[],true))$c['meta'][$m]=$default['meta'][$m];
    return $c;
}
function site_image(string $src): bool { return $src==='' || !preg_match('~^([a-z][a-z0-9+.-]*:|//)~i',$src); }
// tempnam() crea il file con permessi 0600: le pagine pubbliche vanno rese leggibili (0644), altrimenti

// nginx su Plesk (che serve i file statici con un altro utente) risponde 403 a tutto il sito (2.1.22).

function atomic_write(string $path,string $text,int $mode=0600): void {
    $tmp=tempnam(dirname($path),'.write-'); if($tmp===false) throw new RuntimeException('Scrittura non disponibile.');
    try { if(file_put_contents($tmp,$text)===false || !chmod($tmp,$mode) || !rename($tmp,$path)) throw new RuntimeException('Salvataggio non riuscito.'); } finally { if(is_file($tmp)) unlink($tmp); }
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
            if(is_array($v)&&is_string($v['src']??null)&&$v['src']!==''&&!site_image($v['src'])) fail('Carica l’immagine con il pulsante «Carica una nuova immagine»: non si possono usare immagini di altri siti. '.$s['label']);
            if(!is_array($v)||!is_string($v['src']??null)||($v['src']!==''&&!safe_url($v['src'],true))||!is_string($v['alt']??null)||trim($v['alt'])===''||mb_strlen($v['alt'])>250) fail('Inserisci un URL immagine valido e un testo alternativo: '.$s['label']);
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
        if($type==='image') { $el->setAttribute('alt',$v['alt']); if($v['src']!=='')$el->setAttribute('src',$v['src']); else { $el->removeAttribute('src'); $el->setAttribute('hidden','hidden'); $el->setAttribute('data-cd-missing','1'); } }
        if($type==='button') { $el->setAttribute('href',$v['href']); $el->textContent=$v['label']; }
    }
    // Presentazione foto in home (2.1.47): se la foto in primo piano non c'è, passa in primo piano la prima foto caricata.
    foreach(iterator_to_array($xp->query('//img[@data-cd-missing and contains(@style,"opacity:1")]')) as $gone)
        foreach($xp->query('following-sibling::img[not(@data-cd-missing)]',$gone) as $next){$swap=$gone->getAttribute('style');$gone->setAttribute('style',$next->getAttribute('style'));$next->setAttribute('style',$swap);break;}
    // Guide e approfondimenti (2.1.35, 2.1.43): sezione tolta se spenta; se accesa mostra gli ultimi articoli del blog, prima quelli in evidenza.
    $guidesOn=(bool)settings()['home_guides_enabled'];
    foreach(iterator_to_array($xp->query('//*[@data-om-section="guide"]')) as $section){
        if(!$guidesOn||!home_guide_cards($xp,$section,$lang)){$section->parentNode->removeChild($section);continue;}
        foreach($xp->query('.//a',$section) as $a)if(str_contains($a->getAttribute('href'),'lovable.app')){$a->setAttribute('href',$lang==='en'?'../blog/?lang=en':'../blog/');$a->removeAttribute('target');}
    }
    $legal=settings();
    $replacements=[];foreach(['company','address','vat','email'] as $key)$replacements['{{legal.'.$key.'}}']=$legal['legal_'.$key];$replacements['{{legal.retention}}']=(string)$legal['retention_months'];$replacements['{{legal.controller}}']=legal_controller($lang);
    foreach($xp->query('//text()') as $node)$node->nodeValue=strtr($node->nodeValue,$replacements);
    $legal['site_url']=seo_base();
    if($legal['site_url']) {
        $name=$slug==='home'?'index':$slug;$head=$doc->getElementsByTagName('head')->item(0);
        $link=$doc->createElement('link');$link->setAttribute('rel','canonical');$link->setAttribute('href',rtrim($legal['site_url'],'/').'/'.$lang.'/'.$name.'.html');$head->appendChild($link);
        foreach($xp->query('//link[@rel="alternate"]') as $alt){$l=$alt->getAttribute('hreflang');$alt->setAttribute('href',rtrim($legal['site_url'],'/').'/'.($l==='x-default'?'it':$l).'/'.$name.'.html');}
    }
    $doc->getElementsByTagName('title')->item(0)->textContent=$c['meta']['title'];
    $description=$xp->query('//meta[@name="description"]')->item(0);
    if(!$description){$description=$doc->createElement('meta');$description->setAttribute('name','description');$doc->getElementsByTagName('head')->item(0)->appendChild($description);}
    $description->setAttribute('content',$c['meta']['description']);
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
    $icon=$doc->createElement('link');$icon->setAttribute('rel','icon');$icon->setAttribute('type','image/svg+xml');$icon->setAttribute('href','../assets/logo.svg');$head->appendChild($icon);
    $css=$doc->createElement('link');$css->setAttribute('rel','stylesheet');$css->setAttribute('href','../assets/frontend-ui.css');$head->appendChild($css);
    foreach(['frontend-ui.js','frontend-editor.js'] as $file){$script=$doc->createElement('script');$script->setAttribute('src','../assets/'.$file);$script->setAttribute('defer','defer');$head->appendChild($script);}
    $mainNav=$xp->query('//header//nav[not(contains(@class,"language-nav"))]')->item(0);
    if($mainNav&&menu_enabled('blog')){$blogLink=$doc->createElement('a','Blog');$blogLink->setAttribute('href','../blog/');$blogLink->setAttribute('class','site-blog-link');if($lang==='en')$blogLink->setAttribute('href','../blog/?lang=en');$mainNav->appendChild($blogLink);}
    hide_menu_items($xp);

    tidy_footer($doc,$lang);
    seo_verification_meta($doc);
    // Recensioni Google (2.1.33): il riquadro si riempie dal browser; se il servizio è spento il segnaposto sparisce.
    $reviewBoxes=iterator_to_array($xp->query('//*[@data-google-reviews]'));
    if($reviewBoxes&&settings()['reviews_enabled']){$script=$doc->createElement('script');$script->setAttribute('src','../assets/reviews.js');$script->setAttribute('defer','defer');$head->appendChild($script);}
    else foreach($reviewBoxes as $box)$box->parentNode->removeChild($box);
    $url=seo_base().'/'.$lang.'/'.($slug==='home'?'index':$slug).'.html';
    seo_open_graph($doc,$lang,$url,$c['meta']['title'],$c['meta']['description']);
    seo_markup($doc,$lang,$url,$c['meta']['title'],$c['meta']['description'],$slug==='faq'?'FAQPage':($slug==='chisiamo'?'AboutPage':'WebPage'));
    if($slug==='grazie'||in_array($slug,hidden_page_slugs(),true)){$meta=$doc->createElement('meta');$meta->setAttribute('name','robots');$meta->setAttribute('content','noindex,follow');$head->appendChild($meta);}
    return str_replace('../../assets/', '../assets/', $doc->saveHTML());
}
// Schede della sezione Guide in home (2.1.43): la prima scheda del template fa da modello (data, titolo, testo, «Leggi →»)
// e viene ripetuta per i 3 articoli pubblicati più recenti nella lingua della pagina, quelli in evidenza per primi.
// Senza blog attivo o senza articoli restituisce false e la sezione sparisce. Le schede non sono modificabili dall'editor.
function home_guide_cards(DOMXPath $xp,DOMElement $section,string $lang): bool {
    if(!menu_enabled('blog'))return false;
    $cards=iterator_to_array($xp->query('.//a[span]',$section));if(!$cards)return false;
    require_once __DIR__.'/blog.php';
    try{$posts=blog_rows(blog_db(),"SELECT slug,title,excerpt,published_at FROM blog_articles WHERE status='published' AND language=? ORDER BY featured DESC,published_at DESC,id DESC LIMIT 3",[$lang]);}
    catch(Throwable $e){error_log('Guide in home: blog non leggibile: '.$e->getMessage());return false;}
    if(!$posts)return false;
    $model=$cards[0];$grid=$model->parentNode;foreach($cards as $card)$card->parentNode->removeChild($card);
    while($grid->firstChild)$grid->removeChild($grid->firstChild);
    $months=$lang==='en'?['January','February','March','April','May','June','July','August','September','October','November','December']:['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
    foreach($posts as $p){
        $card=$model->cloneNode(true);$spans=iterator_to_array($xp->query('./span',$card));
        $card->setAttribute('href','../blog/articolo/'.rawurlencode($p['slug']).($lang==='en'?'?lang=en':''));$card->removeAttribute('target');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',substr((string)$p['published_at'],0,10));
        $excerpt=trim((string)$p['excerpt']);if(mb_strlen($excerpt)>200)$excerpt=rtrim(mb_substr($excerpt,0,197)).'…';
        foreach([$d?$d->format('j').' '.$months[(int)$d->format('n')-1].' '.$d->format('Y'):'',(string)$p['title'],$excerpt] as $i=>$text)if(isset($spans[$i]))$spans[$i]->textContent=$text;
        if(isset($spans[2])&&$excerpt==='')$card->removeChild($spans[2]);
        foreach(iterator_to_array($xp->query('.//*[@data-om-slot]',$card)) as $el)foreach(['data-om-slot','data-om-type','data-om-label','data-om-max','data-cd-slot'] as $attr)$el->removeAttribute($attr);
        $grid->appendChild($card);
    }
    return true;
}
// Toglie da header e footer i link delle voci spente nel pannello Menu (2.1.27).
function hide_menu_items(DOMXPath $xp): void {
    $off=[];foreach(MENU_ITEMS as $key=>$item)if(!menu_enabled($key))$off[]=$item['href'];if(!$off)return;
    foreach(iterator_to_array($xp->query('//header//a|//footer//a')) as $a)if(in_array($a->getAttribute('href'),$off,true))$a->parentNode->removeChild($a);
}
/** Data di ultima modifica della pagina per la sitemap: salvataggio dall'editor, altrimenti il testo di partenza. */
function page_lastmod(string $slug,string $lang,array $c): string {
    if(preg_match('/^\d{4}-\d{2}-\d{2}/',(string)($c['updated_at']??''),$m))return $m[0];
    return gmdate('Y-m-d',(int)@filemtime(ROOT.'/content/'.$slug.'.'.$lang.'.json')?:time());
}
function publish(string $slug,string $lang,array $c): void { $name=$slug==='home'?'index':$slug; atomic_write(ROOT.'/'.$lang.'/'.$name.'.html',build_html($slug,$lang,$c),0644); }
function seo_indexes(): void {
    // La home mostra gli ultimi articoli (2.1.43): ogni modifica al blog passa di qui e la rigenera.
    foreach(['it','en'] as $lang)publish('home',$lang,content('home',$lang));
    $s=settings();$base=seo_base();$xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    $llms="# Valutazione Diamanti\n\nDirect buying and free valuations of natural diamonds, jewellery and selected watches by La Diamanteria in Milan.\n\n## Pages\n";
    $hiddenPages=hidden_page_slugs();foreach(PAGE_SLUGS as $slug)if($slug!=='grazie'&&!in_array($slug,$hiddenPages,true))foreach(['it','en'] as $lang){$name=$slug==='home'?'index':$slug;$url=$base.'/'.$lang.'/'.$name.'.html';$c=content($slug,$lang);if($base)$xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1).'</loc><lastmod>'.page_lastmod($slug,$lang,$c).'</lastmod></url>';$llms.='- ['.$c['meta']['title'].']('.$url.")\n";}
    require_once __DIR__.'/blog.php';
    $blogOn=menu_enabled('blog');$posts=[];
    if($base&&$blogOn){$posts=blog_seo_entries();$blogDay=max(array_map(fn($p)=>substr((string)$p['updated_at'],0,10),$posts)?:[gmdate('Y-m-d')]);
        $xml.='<url><loc>'.htmlspecialchars($base.'/blog/',ENT_XML1).'</loc><lastmod>'.$blogDay.'</lastmod></url><url><loc>'.htmlspecialchars($base.'/blog/?lang=en',ENT_XML1).'</loc><lastmod>'.$blogDay.'</lastmod></url>';
        foreach($posts as $post){
            $xml.='<url><loc>'.htmlspecialchars(blog_article_url(['base_url'=>$base.'/blog'],$post),ENT_XML1).'</loc>';
            if(preg_match('/^\d{4}-\d{2}-\d{2}/',(string)$post['updated_at'],$day))$xml.='<lastmod>'.$day[0].'</lastmod>';
            if(blog_valid_url((string)$post['image_url'],false))$xml.='<image:image><image:loc>'.htmlspecialchars($post['image_url'],ENT_XML1).'</image:loc></image:image>';
            $xml.='</url>';
        }
    }
    if($blogOn)$llms.='- [Blog]('.$base.'/blog/)' . "\n";
    if($base&&!empty($posts)){$llms.="\n## Blog articles\n";foreach(array_slice($posts,0,100) as $post)$llms.='- ['.trim(str_replace(['[',']','(',')',"\r","\n"],' ',(string)$post['title'])).']('.blog_article_url(['base_url'=>$base.'/blog'],$post).")\n";}
    atomic_write(ROOT.'/sitemap.xml',$xml.'</urlset>',0644);atomic_write(ROOT.'/llms.txt',$llms,0644);
    // robots.txt essenziale (2.1.48, audit): le cartelle di sistema rispondono già 403, qui restano solo amministrazione e API.
    $robots="User-agent: *\nDisallow: /admin/\nDisallow: /api/\nAllow: /\n";
    if($base)$robots.="\nSitemap: ".$base."/sitemap.xml\n";atomic_write(ROOT.'/robots.txt',$robots,0644);
    seo_verification_files();
    mark_published();
    try{indexnow_submit(sitemap_entries());}catch(Throwable $e){error_log('IndexNow non raggiunto: '.$e->getMessage());}
    try{indexing_sync();}catch(Throwable $e){error_log('Coda Google/Bing non aggiornata: '.$e->getMessage());}
}
// Dopo un Pull le pagine in it/ en/ e sitemap, robots, llms tornano alla versione del repository: al primo accesso
// all'API (ogni pagina pubblica la chiama) si rigenerano dai testi in data/content (2.1.29).
function published_stamp(): string {
    clearstatcache(); $s=APP_VERSION; $files=['sitemap.xml','robots.txt','llms.txt'];
    foreach(PAGE_SLUGS as $slug) foreach(['it','en'] as $lang) $files[]=$lang.'/'.($slug==='home'?'index':$slug).'.html';
    foreach($files as $f) $s.='|'.$f.':'.@filemtime(ROOT.'/'.$f).':'.@filesize(ROOT.'/'.$f);
    return hash('sha256',$s);
}
function mark_published(): void { @file_put_contents(ROOT.'/data/published.stamp',published_stamp()); }
function ensure_published(): void {
    indexing_tick();
    $file=ROOT.'/data/published.stamp'; if(is_file($file)&&trim((string)file_get_contents($file))===published_stamp()) return;
    $lock=fopen(ROOT.'/data/build.lock','c'); if(!$lock) return; if(!flock($lock,LOCK_EX|LOCK_NB)) { fclose($lock); return; }
    try { foreach(PAGE_SLUGS as $slug) foreach(['it','en'] as $lang) publish($slug,$lang,content($slug,$lang)); seo_indexes(); }
    catch(Throwable $e) { error_log('Rigenerazione pagine non riuscita: '.$e->getMessage()); }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
