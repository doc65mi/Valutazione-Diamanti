<?php
declare(strict_types=1);
require_once __DIR__.'/../PHP di sistema/core.php';
security_headers(true); session_start_secure();
$action=$_GET['action']??'me'; $method=$_SERVER['REQUEST_METHOD'];
try {
    ensure_published();
    if($method!=='GET') csrf();
    $input=[];
    if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) {
        $raw=file_get_contents('php://input',false,null,0,1048577); if(strlen($raw)>1048576) fail('Richiesta troppo grande.',413);
        $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR); if(!is_array($input)) fail('Richiesta non valida.');
    }
    if($action==='session'&&$method==='GET') respond(['csrf'=>$_SESSION['csrf'],'version'=>APP_VERSION]);
    if($action==='config'&&$method==='GET') respond(public_settings());
    // Recensioni Google per le pagine pubbliche (2.1.33): dati già filtrati, letti da Google al massimo ogni 12 ore.
    if($action==='reviews'&&$method==='GET'){$lang=($_GET['lang']??'it')==='en'?'en':'it';session_write_close();$data=reviews_public($lang);header('Cache-Control: public, max-age=1800');header_remove('Pragma');header_remove('Expires');respond(['reviews'=>$data]);}
    if($action==='login'&&$method==='POST') {
        rate('login',8,900); $q=db()->prepare('SELECT * FROM users WHERE username=?'); $q->execute([is_string($input['username']??null)?$input['username']:'']); $u=$q->fetch();
        $password=is_string($input['password']??null)?$input['password']:'';
        $fallback='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=password_verify($password,$u['password_hash']??$fallback);
        if(!$u||!$valid) fail('Utente o password non corretti.',401);
        session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['credential_stamp']=hash('sha256',$u['password_hash']); $_SESSION['csrf']=bin2hex(random_bytes(32)); audit('login',(string)$u['id']); respond(['csrf'=>$_SESSION['csrf']]);
    }
    if($action==='logout'&&$method==='POST') { user(); $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }
    if($action==='request'&&$method==='POST') {
        require __DIR__.'/../PHP di sistema/requests.php'; submit_request();
    }
    $u=user();
    if(str_starts_with($action,'blog-')){admin_user();require_once __DIR__.'/../PHP di sistema/blog-api.php';blog_api($action,$method,$input);}
    if($action==='settings'&&$method==='GET'){admin_user();$s=settings();$s['recaptcha_secret_set']=$s['recaptcha_secret']!=='';$s['smtp_pass_set']=$s['smtp_pass']!=='';unset($s['recaptcha_secret'],$s['smtp_pass']);$s['indexnow_last']=indexnow_status()['last'];$s['reviews_api_key_set']=$s['reviews_api_key']!=='';unset($s['reviews_api_key']);$s['reviews_status']=reviews_status();foreach(customer_email_defaults() as $k=>$v)if(trim((string)$s[$k])==='')$s[$k]=$v;respond(['settings'=>$s]);}
    if($action==='email-settings'&&$method==='POST'){admin_user();email_settings_save($input);audit('settings','email');respond(['ok'=>true]);}
    if($action==='email-test'&&$method==='POST'){admin_user();rate('email-test',20,3600);$kind=$input['kind']??'test';if(!in_array($kind,['test','customer_it','customer_en'],true))fail('Tipo di prova non valido.');send_test_email(is_string($input['to']??null)?trim($input['to']):'',$kind);audit('email-test',$kind);respond(['ok'=>true]);}
    if($action==='settings'&&$method==='POST'){
        admin_user();$lock=fopen(ROOT.'/data/build.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Build occupata.');
        try{
            db()->beginTransaction();settings_save($input);foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));seo_indexes();audit('settings','site');db()->commit();respond(['ok'=>true]);
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));seo_indexes();throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    if($action==='reviews-test'&&$method==='POST'){admin_user();rate('reviews-test',20,3600);try{$r=reviews_test();}catch(RuntimeException $e){fail($e->getMessage());}audit('reviews-test','google');respond(['ok'=>true]+$r);}
    // IndexNow (2.1.32): invio manuale di tutti gli indirizzi della sitemap.
    if($action==='indexnow-submit'&&$method==='POST'){admin_user();rate('indexnow',10,3600);if(!settings()['indexnow_enabled'])fail('Attiva IndexNow e salva le impostazioni prima dell’invio.');$r=indexnow_submit(sitemap_entries(),false);if(!$r)fail('Nessun indirizzo da inviare: controlla l’URL pubblico del sito.');if(!$r['ok'])fail('IndexNow ha risposto con codice '.$r['http'].'. Riprova tra qualche minuto.');audit('indexnow','site');respond(['ok'=>true,'last'=>$r]);}
    // Invio diretto a Google (Indexing API) e Bing (SubmitUrl) con limite giornaliero (2.1.42). Le credenziali non tornano mai al browser.
    if($action==='indexing'&&$method==='GET'){admin_user();respond(['indexing'=>indexing_public()]);}
    if($action==='indexing-save'&&$method==='POST'){admin_user();indexing_save($input);audit('indexing','save');respond(['ok'=>true,'indexing'=>indexing_public()]);}
    if($action==='indexing-test'&&$method==='POST'){admin_user();rate('indexing-test',20,3600);$r=indexing_test();audit('indexing','test');respond(['ok'=>true,'results'=>$r]);}
    if($action==='indexing-run'&&$method==='POST'){admin_user();rate('indexing-run',20,3600);$c=indexing_config();if(!indexing_active($c,'google')&&!indexing_active($c,'bing'))fail('Attiva Google o Bing e salva prima di inviare.');try{$r=indexing_run(true);}catch(RuntimeException $e){fail($e->getMessage());}if(!empty($r['busy']))fail('Un invio è già in corso: riprova tra un minuto.');audit('indexing','run');respond(['ok'=>true,'summary'=>$r,'indexing'=>indexing_public()]);}
    // Pannello Menu: accende e spegne le voci di header e footer, poi ripubblica tutte le pagine e la sitemap (2.1.27).
    if($action==='menu'&&$method==='GET'){admin_user();$items=[];foreach(MENU_ITEMS as $key=>$item)$items[]=['key'=>$key,'label'=>$item['label'],'enabled'=>menu_enabled($key)];$items[]=['key'=>'guide','label'=>HOME_GUIDES_LABEL,'enabled'=>(bool)settings()['home_guides_enabled']];respond(['items'=>$items]);}
    if($action==='menu'&&$method==='POST'){
        admin_user();$lock=fopen(ROOT.'/data/build.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Build occupata.');
        try{
            db()->beginTransaction();menu_save($input);foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));seo_indexes();audit('menu','site');db()->commit();respond(['ok'=>true]);
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));seo_indexes();throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    if($action==='me'&&$method==='GET') respond(['user'=>$u,'csrf'=>$_SESSION['csrf']]);
    if($action==='upload-image'&&$method==='POST'){require __DIR__.'/../PHP di sistema/image-upload.php';upload_site_image($u);}
    if($action==='password'&&$method==='POST') {
        rate('password',8,900); $q=db()->prepare('SELECT password_hash FROM users WHERE id=?'); $q->execute([$u['id']]);
        if(!is_string($input['current']??null)||!password_verify($input['current'],$q->fetchColumn())) fail('Password attuale non corretta.');
        $p=$input['password']??null; if(!is_string($p)||strlen($p)<12||strlen($p)>200) fail('Usa una password da 12 a 200 caratteri.');
        $newHash=password_hash($p,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash,$u['id']]);$_SESSION['credential_stamp']=hash('sha256',$newHash);
        if(is_file(ROOT.'/data/first-login.txt')) unlink(ROOT.'/data/first-login.txt'); audit('password',(string)$u['id']); respond(['ok'=>true]);
    }
    // Elenco account con ultimo accesso (2.1.34).
    if($action==='users'&&$method==='GET') { admin_user(); respond(['users'=>db()->query("SELECT u.id,u.username,u.role,u.pages,(SELECT MAX(created_at) FROM audit WHERE action='login' AND target=CAST(u.id AS TEXT)) AS last_login FROM users u ORDER BY u.username")->fetchAll()]); }
    // Nuova password per un account scelto dall'amministratore (2.1.34): le sessioni aperte con la vecchia password decadono.
    if($action==='user-password'&&$method==='POST') {
        admin_user(); rate('user-password',20,900); $id=filter_var($input['id']??null,FILTER_VALIDATE_INT); $p=$input['password']??null;
        if(!is_int($id)) fail('Account non valido.'); if(!is_string($p)||strlen($p)<12||strlen($p)>200) fail('Usa una password da 12 a 200 caratteri.');
        $newHash=password_hash($p,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);$q=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([$newHash,$id]);if(!$q->rowCount())fail('Account non trovato.',404);
        if($id===(int)$u['id'])$_SESSION['credential_stamp']=hash('sha256',$newHash); audit('user-password',(string)$id); respond(['ok'=>true]);
    }
    if($action==='users'&&$method==='POST') {
        admin_user(); $name=$input['username']??''; $p=$input['password']??''; $role=$input['role']??'client'; $pages=$input['pages']??[];
        if(!is_string($name)||!preg_match('/^[a-zA-Z0-9._-]{3,50}$/',$name)||!is_string($p)||strlen($p)<12||strlen($p)>200||!in_array($role,['admin','client'],true)||!is_array($pages)||array_diff($pages,PAGE_SLUGS)) fail('Controlla nome, password, ruolo e pagine assegnate.');
        db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)')->execute([$name,password_hash($p,PASSWORD_DEFAULT),$role,json_encode($pages)]); audit('user-create',$name); respond(['ok'=>true]);
    }
    if($action==='retry-emails'&&$method==='POST'){admin_user();if(!settings()['mail_enabled'])fail('Abilita prima gli invii nella scheda Email.');$id=$input['id']??'';if(!is_string($id)||!preg_match('/^R-[A-F0-9]{12}$/',$id))fail('Richiesta non valida.');send_queued_emails($id);respond(['ok'=>true]);}
    // Cancellazione definitiva di una richiesta con allegati ed email in coda (2.1.34).
    if($action==='request-delete'&&$method==='POST'){admin_user();$id=$input['id']??'';if(!is_string($id)||!preg_match('/^R-[A-F0-9]{12}$/',$id))fail('Richiesta non valida.');if(!delete_request($id))fail('Richiesta non trovata.',404);audit('request-delete',$id);respond(['ok'=>true]);}
    if($action==='requests'&&$method==='GET') {
        mail_queue_schema();
        admin_user(); purge_expired_requests(); $rows=db()->query('SELECT * FROM requests ORDER BY created_at DESC LIMIT 1000')->fetchAll();
        foreach($rows as &$row) { $row['payload']=json_decode($row['payload'],true); unset($row['request_key']); $q=db()->prepare('SELECT id,name,size,mime FROM attachments WHERE request_id=?'); $q->execute([$row['id']]); $row['attachments']=$q->fetchAll();$mq=db()->prepare('SELECT kind,status,attempts FROM mail_queue WHERE request_id=?');$mq->execute([$row['id']]);$row['emails']=$mq->fetchAll(); }
        respond(['requests'=>$rows]);
    }
    if($action==='status'&&$method==='POST') {
        admin_user(); $s=$input['status']??''; if(!in_array($s,['Nuova','Contattato','Appuntamento','Chiusa'],true)) fail('Stato non valido.');
        $q=db()->prepare('UPDATE requests SET status=? WHERE id=?'); $q->execute([$s,$input['id']??'']); if(!$q->rowCount()) fail('Richiesta non trovata.',404); audit('status',$input['id']); respond(['ok'=>true]);
    }
    if($action==='attachment'&&$method==='GET') {
        admin_user(); $q=db()->prepare('SELECT * FROM attachments WHERE id=?'); $q->execute([$_GET['id']??'']); $f=$q->fetch(); if(!$f) fail('Allegato non trovato.',404);
        $path=ROOT.'/uploads/clienti/'.$f['path']; $inline=($_GET['view']??'')==='preview';
        if(($_GET['view']??'')==='thumb' && $f['mime']!=='application/pdf') { $path.='-thumb.png'; $inline=true; }
        if(!is_file($path)) fail('Allegato non disponibile.',404);
        header('Content-Type: '.$f['mime']); header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="attachment"; filename*=UTF-8\'\''.rawurlencode($f['name'])); header('Content-Length: '.filesize($path)); audit($inline?'preview':'download',$f['id']); session_write_close(); readfile($path); exit;
    }
    if($action==='pages'&&$method==='GET') {
        $pages=[]; foreach(PAGE_SLUGS as $slug) if($u['role']==='admin'||in_array($slug,$u['pages'],true)) foreach(['it','en'] as $lang) { $c=content($slug,$lang); $pages[]=['slug'=>$slug,'language'=>$lang,'version'=>$c['version'],'title'=>$c['meta']['title']]; } respond(['pages'=>$pages]);
    }
    if(in_array($action,['page','save','preview','revisions','restore'],true)) {
        $slug=$_GET['slug']??''; $lang=$_GET['lang']??'it'; $path=page_path($slug,$lang); can_page($u,$slug);
        if($action==='page'&&$method==='GET') respond(['content'=>content($slug,$lang),'schema'=>slot_schema($slug,$lang)]);
        if($action==='revisions'&&$method==='GET') { $q=db()->prepare('SELECT id,version,created_at FROM revisions WHERE page=? AND language=? ORDER BY id DESC LIMIT 30'); $q->execute([$slug,$lang]); respond(['revisions'=>$q->fetchAll()]); }
        if(in_array($action,['save','preview','restore'],true)&&$method==='POST') {
            $lock=fopen(ROOT.'/data/build.lock','c'); if(!$lock||!flock($lock,LOCK_EX)) throw new RuntimeException('Build occupata.');
            try {
                $previous=content($slug,$lang); if(($input['base_version']??null)!==$previous['version']) respond(['error'=>'La pagina è stata modificata da un altro utente. Ricaricala prima di salvare.','content'=>$previous],409);
                if($action==='restore') { $q=db()->prepare('SELECT content FROM revisions WHERE id=? AND page=? AND language=?'); $q->execute([$input['revision']??0,$slug,$lang]); $r=$q->fetchColumn(); if(!$r) fail('Revisione non trovata.',404); $input=json_decode($r,true); }
                $next=validate_content($input,slot_schema($slug,$lang),$previous,$u); $html=build_html($slug,$lang,$next);
                if($action==='preview') respond(['html'=>$html]);
                db()->beginTransaction();
                $q=db()->prepare('INSERT INTO revisions(page,language,version,content,created_at,user_id) VALUES(?,?,?,?,?,?)'); $q->execute([$slug,$lang,$previous['version'],json_encode($previous,JSON_UNESCAPED_UNICODE),gmdate('c'),$u['id']]);
                try {
                    atomic_write($path,json_encode($next,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); publish($slug,$lang,$next); seo_indexes(); audit('publish',$slug.'.'.$lang); db()->commit();
                } catch(Throwable $e) { atomic_write($path,json_encode($previous,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); publish($slug,$lang,$previous); if(db()->inTransaction()) db()->rollBack(); throw $e; }
                respond(['content'=>$next]);
            } finally { flock($lock,LOCK_UN); fclose($lock); }
        }
    }
    fail('Operazione non disponibile.',404);
} catch(JsonException $e) { fail('Dati non validi.',400); }
catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); error_log((string)$e); fail('Operazione non riuscita. Riprova. / Something went wrong. Please try again.',500); }
