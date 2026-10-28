<?php
declare(strict_types=1);
require_once __DIR__.'/blog.php';
function blog_reauthenticate(array $input): void {
    $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$_SESSION['uid']??0]);
    $p=$input['current_password']??null;
    if(!is_string($p)||!password_verify($p,(string)$q->fetchColumn()))fail('La password con cui sei entrato in questo pannello non è corretta (non è quella di Frase o di altri servizi).',403);
}
// Collegamento Frase (2.1.30): fuori dal blocco del blog, perché l'importazione prende il proprio blocco.
function blog_frase_api(string $action,string $method,array $input): never {
    require_once __DIR__.'/frase.php';
    try {
        $c=blog_config();
        if($action==='blog-frase-status'&&$method==='GET'){$wpLog=is_file(PRIVATE_DIR.'/wp-bridge.log')?array_map(fn($l)=>'[WordPress] '.$l,array_slice(array_reverse(array_filter(explode("\n",(string)file_get_contents(PRIVATE_DIR.'/wp-bridge.log')))),0,20)):[];
            respond(['connected'=>($c['frase_api_key']??'')!=='','webhook'=>($c['frase_webhook_id']??'')!=='','url'=>rtrim($c['site_url'],'/').'/blog/frase.php','auto_publish'=>!empty($c['auto_publish']),'wp_set'=>($c['wp_password_hash']??'')!=='','wp_url'=>rtrim($c['site_url'],'/').'/wp-admin','log'=>array_merge(frase_log_lines(20),$wpLog)]);}
        if($action==='blog-frase-list'&&$method==='GET'){
            $list=frase_list($c,(int)($_GET['page']??1));$db=blog_db();
            foreach($list['items'] as &$item){$a=blog_one($db,'SELECT id,status FROM blog_articles WHERE source_id=?',['frase-'.$item['id']]);$item['imported']=$a?($a['status']==='published'?'published':'draft'):null;}
            unset($item);respond($list);
        }
        if($action==='blog-frase-import'&&$method==='POST'){
            if(!is_string($input['id']??null))fail('Articolo non indicato.');
            set_time_limit(90);respond(frase_import($input['id'],'importazione manuale'));
        }
        // Connettore WordPress di Frase (2.1.39): password applicazione per blog/wp-bridge.php, salvata solo come hash.
        if($action==='blog-frase-wp-password'&&$method==='POST'){
            $lock=blog_lock(true);$chars='abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';$p='';for($i=0;$i<24;$i++)$p.=$chars[random_int(0,strlen($chars)-1)];
            $c=blog_config();$c['wp_password_hash']=hash('sha256',$p);blog_save_config($c);audit('blog-frase','wp-password');respond(['password'=>implode(' ',str_split($p,4))]);
        }
        if(in_array($action,['blog-frase-connect','blog-frase-disconnect'],true)&&$method==='POST'){
            // 2.1.37: niente password di conferma. Collegare Frase non tocca le credenziali del sito e la richiesta è già protetta da sessione amministratore e token CSRF.
            $lock=blog_lock(true);
            if($action==='blog-frase-disconnect'){frase_disconnect($c);audit('blog-frase','disconnected');respond(['ok'=>true]);}
            $key=trim((string)($input['api_key']??''));
            if(!preg_match('/^[\x21-\x7e]{16,300}$/D',$key))fail('Incollare la chiave API di Frase (Settings > API).');
            $r=frase_connect($c,$key);audit('blog-frase','connected');respond($r);
        }
        fail('Operazione Frase non disponibile.',404);
    } catch(InvalidArgumentException|RuntimeException $e){fail($e->getMessage());}
    catch(PDOException $e){error_log('Blog frase database: '.$e->getCode());fail('Slug già usato da un altro articolo.',409);}
}
function blog_api(string $action,string $method,array $input): never {
    if(str_starts_with($action,'blog-frase-'))blog_frase_api($action,$method,$input);
    $lock=blog_lock($method!=='GET');$c=blog_config();$db=blog_db();
    try {
        if($action==='blog-list'&&$method==='GET')respond(['articles'=>blog_rows($db,'SELECT id,title,slug,language,status,featured,published_at FROM blog_articles ORDER BY published_at DESC,id DESC'),'blog_enabled'=>menu_enabled('blog')]);
        // Pulsante «In evidenza» dell'elenco (2.1.43): gli articoli in evidenza compaiono per primi in home e nel blog.
        if($action==='blog-feature'&&$method==='POST'){
            $id=(int)($input['id']??0);if(!is_bool($input['featured']??null))fail('Selezione non valida.');
            if(!blog_one($db,'SELECT id FROM blog_articles WHERE id=?',[$id]))fail('Articolo non trovato.',404);
            $db->prepare('UPDATE blog_articles SET featured=? WHERE id=?')->execute([$input['featured']?1:0,$id]);audit('blog-feature',$id.':'.($input['featured']?'1':'0'));seo_indexes();respond(['ok'=>true]);
        }
        if($action==='blog-get'&&$method==='GET'){$a=blog_one($db,'SELECT * FROM blog_articles WHERE id=?',[(int)($_GET['id']??0)]);if(!$a)fail('Articolo non trovato.',404);respond(['article'=>$a]);}
        if($action==='blog-save'&&$method==='POST'){
            $id=(int)($input['id']??0);$old=$id?blog_one($db,'SELECT * FROM blog_articles WHERE id=?',[$id]):null;
            if($id&&!$old)fail('Articolo non trovato.',404);
            if($old&&($input['base_version']??'')!==$old['updated_at'])fail('Articolo modificato da un altro utente. Ricaricare prima di salvare.',409);
            $a=blog_article_data($input,false,$c);if($old)$a['source_id']=$old['source_id'];
            $a['updated_at']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
            $saved=blog_persist_article($db,$a,$id?:null);audit('blog-save',(string)$saved);seo_indexes();respond(['id'=>$saved]);
        }
        if($action==='blog-delete'&&$method==='POST'){
            if(($input['confirm']??false)!==true)fail('Confermare l’eliminazione.');
            $db->prepare('DELETE FROM blog_articles WHERE id=?')->execute([(int)($input['id']??0)]);audit('blog-delete',(string)($input['id']??0));seo_indexes();respond(['ok'=>true]);
        }
        if($action==='blog-config'&&$method==='GET'){
            $visible=$c;unset($visible['webhook_hash'],$visible['db']['password'],$visible['frase_api_key'],$visible['frase_webhook_secret'],$visible['wp_password_hash']);$visible['token_set']=$c['webhook_hash']!=='';
            respond(['config'=>$visible,'contentai_log'=>contentai_log_lines()]);
        }
        // Spazio immagine nel blog pubblico (2.1.70): solo aspetto, nessuna password richiesta.
        if($action==='blog-display'&&$method==='POST'){
            if(!is_bool($input['show_images']??null))fail('Selezione non valida.');$c['show_images']=$input['show_images'];blog_save_config($c);audit('blog-display',$input['show_images']?'images-on':'images-off');respond(['ok'=>true]);
        }
        if(in_array($action,['blog-config','blog-token','blog-database'],true)&&$method==='POST')blog_reauthenticate($input);
        if($action==='blog-config'&&$method==='POST'){
            if(!is_bool($input['auto_publish']??null))fail('Selezione non valida.');$c['auto_publish']=$input['auto_publish'];blog_save_config($c);audit('blog-config','publication');respond(['ok'=>true]);
        }
        if($action==='blog-token'&&$method==='POST'){
            $token=bin2hex(random_bytes(32));$c['webhook_hash']=hash('sha256',$token);blog_save_config($c);audit('blog-token','rotated');respond(['token'=>$token]);
        }
        if($action==='blog-database'&&$method==='POST'){
            if(($input['confirm']??'')!=='TRASFERISCI')fail('Scrivi TRASFERISCI per confermare.');
            $destination=['driver'=>($input['driver']??'')==='mysql'?'mysql':'sqlite'];
            if($destination['driver']==='mysql'){
                $port=(int)($input['port']??3306);if($port<1||$port>65535)fail('Porta non valida.');
                foreach(['host','database','username','password'] as $key)if(!is_string($input[$key]??null))fail('Compilare i campi database.');
                $destination+=['host'=>trim($input['host']),'port'=>$port,'database'=>trim($input['database']),'username'=>trim($input['username']),'password'=>$input['password']];
            }
            if($destination===$c['db'])fail('Questo database è già attivo.');
            blog_db_move($db,$destination,$c);audit('blog-database',$destination['driver']);seo_indexes();respond(['ok'=>true]);
        }
        fail('Operazione blog non disponibile.',404);
    } catch(InvalidArgumentException $e){fail($e->getMessage());}
    catch(PDOException $e){error_log('Blog database error: '.$e->getCode());fail('Operazione database non riuscita: verificare connessione, destinazione vuota e slug univoci.',409);}
    catch(RuntimeException $e){fail($e->getMessage());}
}
