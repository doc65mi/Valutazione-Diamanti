<?php
declare(strict_types=1);
require __DIR__.'/../PHP di sistema/core.php';
security_headers(true);header('Cache-Control: no-store');session_start_secure();
$error='';$success=false;$disabled=is_file(ROOT.'/data/admin-recovery-disabled');
$tokenPath=ROOT.'/data/admin-recovery.json';
$lock=fopen(ROOT.'/data/admin-recovery.lock','c');
if(!$lock||!flock($lock,LOCK_EX)){http_response_code(503);exit('Recupero temporaneamente non disponibile.');}
try{
    $disabled=is_file(ROOT.'/data/admin-recovery-disabled');
    $token=is_file($tokenPath)?json_decode(file_get_contents($tokenPath),true):null;
    if(!$disabled&&$_SERVER['REQUEST_METHOD']==='GET'&&(!$token||($token['expires']??0)<time())){
        $token=['code'=>bin2hex(random_bytes(24)),'expires'=>time()+1800];
        atomic_write($tokenPath,json_encode($token));@chmod($tokenPath,0600);
    }
    if($_SERVER['REQUEST_METHOD']==='POST'&&!$disabled){
        $received=$_POST['csrf']??'';
        if(!is_string($received)||!hash_equals($_SESSION['csrf'],$received))$error='Sessione scaduta. Ricarica la pagina.';
        else{
            rate('admin-recovery',5,900);
            $code=$_POST['code']??'';$password=$_POST['password']??'';$repeat=$_POST['repeat']??'';
            if(!$token||($token['expires']??0)<time()||!is_string($code)||!hash_equals($token['code'],trim($code)))$error='Codice di recupero non valido o scaduto.';
            elseif(!is_string($password)||strlen($password)<12||strlen($password)>200)$error='La password deve contenere da 12 a 200 caratteri.';
            elseif(!is_string($repeat)||!hash_equals($password,$repeat))$error='Le password non coincidono.';
            else{
                $hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
                $q=db()->prepare('SELECT id FROM users WHERE username=?');$q->execute(['admin']);$id=$q->fetchColumn();
                db()->beginTransaction();
                if($id)db()->prepare('UPDATE users SET password_hash=?,role=?,pages=? WHERE id=?')->execute([$hash,'admin',json_encode(PAGE_SLUGS),$id]);
                else db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)')->execute(['admin',$hash,'admin',json_encode(PAGE_SLUGS)]);
                // This marker prevents a new web recovery code from being created after success.
                atomic_write(ROOT.'/data/admin-recovery-disabled',gmdate('c'));
                db()->commit();
                @unlink($tokenPath);@unlink(ROOT.'/data/first-login.txt');
                $_SESSION=[];session_regenerate_id(true);$success=true;$disabled=true;
            }
        }
    }
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('Admin recovery failed: '.$e->getMessage());$error='Recupero non riuscito. Controlla i permessi della cartella data e riprova.';}
finally{flock($lock,LOCK_UN);fclose($lock);}
function recovery_escape(string $text):string{return htmlspecialchars($text,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Recupero accesso admin</title><link rel="stylesheet" href="../assets/site.css"></head><body><main class="panel narrow"><h1>Recupero accesso admin</h1>
<?php if($success): ?>
<p>Password aggiornata. Accedi con utente <strong>admin</strong> e la password che hai appena scelto.</p><p><a href="index.php">Vai al login</a></p>
<?php elseif($disabled): ?>
<p>Il recupero via browser è disabilitato. Per un nuovo recupero, rimuovi il file <code>data/admin-recovery-disabled</code> dal File Manager di Plesk e ricarica questa pagina.</p><p><a href="index.php">Vai al login</a></p>
<?php else: ?>
<p>Nel File Manager di Plesk apri <code>data/admin-recovery.json</code> e copia il valore <code>code</code>. Il codice è valido per 30 minuti e si può usare una sola volta.</p>
<?php if($error): ?><p role="alert" class="error"><?=recovery_escape($error)?></p><?php endif ?>
<form method="post"><input type="hidden" name="csrf" value="<?=recovery_escape($_SESSION['csrf'])?>"><label>Codice di recupero<input name="code" required maxlength="48" autocomplete="off"></label><label>Nuova password<input type="password" name="password" required minlength="12" maxlength="200" autocomplete="new-password"></label><label>Ripeti password<input type="password" name="repeat" required minlength="12" maxlength="200" autocomplete="new-password"></label><button type="submit">Reimposta password admin</button></form>
<?php endif ?></main></body></html>
