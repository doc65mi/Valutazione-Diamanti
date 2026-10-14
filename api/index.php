<?php
declare(strict_types=1);
require __DIR__.'/../PHP di sistema/core.php';
security_headers(true); session_start_secure();
$action=$_GET['action']??'me'; $method=$_SERVER['REQUEST_METHOD'];
try {
    if($method!=='GET') csrf();
    $input=[];
    if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) {
        $raw=file_get_contents('php://input',false,null,0,1048577); if(strlen($raw)>1048576) fail('Richiesta troppo grande.',413);
        $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR); if(!is_array($input)) fail('Richiesta non valida.');
    }
    if($action==='session'&&$method==='GET') respond(['csrf'=>$_SESSION['csrf'],'version'=>APP_VERSION]);
    if($action==='config'&&$method==='GET') respond(public_settings());
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
    if($action==='settings'&&$method==='GET'){admin_user();$s=settings();$s['recaptcha_secret_set']=$s['recaptcha_secret']!=='';unset($s['recaptcha_secret']);respond(['settings'=>$s]);}
    if($action==='settings'&&$method==='POST'){
        admin_user();$lock=fopen(ROOT.'/data/build.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Build occupata.');
        try{
            db()->beginTransaction();settings_save($input);foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));seo_indexes();audit('settings','site');db()->commit();respond(['ok'=>true]);
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
    if($action==='users'&&$method==='GET') { admin_user(); respond(['users'=>db()->query('SELECT id,username,role,pages FROM users')->fetchAll()]); }
    if($action==='users'&&$method==='POST') {
        admin_user(); $name=$input['username']??''; $p=$input['password']??''; $role=$input['role']??'client'; $pages=$input['pages']??[];
        if(!is_string($name)||!preg_match('/^[a-zA-Z0-9._-]{3,50}$/',$name)||!is_string($p)||strlen($p)<12||strlen($p)>200||!in_array($role,['admin','client'],true)||!is_array($pages)||array_diff($pages,PAGE_SLUGS)) fail('Controlla nome, password, ruolo e pagine assegnate.');
        db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)')->execute([$name,password_hash($p,PASSWORD_DEFAULT),$role,json_encode($pages)]); audit('user-create',$name); respond(['ok'=>true]);
    }
    if($action==='retry-emails'&&$method==='POST'){admin_user();if(!settings()['mail_enabled'])fail('Abilita prima gli invii email in Privacy e integrazioni.');$id=$input['id']??'';if(!is_string($id)||!preg_match('/^R-[A-F0-9]{12}$/',$id))fail('Richiesta non valida.');send_queued_emails($id);respond(['ok'=>true]);}
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
