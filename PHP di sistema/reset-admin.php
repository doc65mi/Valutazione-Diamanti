<?php
declare(strict_types=1);
// Recovery is available only to an operator with server command-line access.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--reset-admin',$argv,true)){fwrite(STDERR,"Uso: php reset-admin.php --reset-admin\n");exit(1);}
require __DIR__.'/core.php';
foreach(['data','data/sessions'] as $dir)if(!is_dir(ROOT.'/'.$dir)&&!mkdir(ROOT.'/'.$dir,0700,true))throw new RuntimeException('Directory dati non scrivibile.');
$password=bin2hex(random_bytes(16));
$hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
$q=db()->prepare('SELECT id FROM users WHERE username=?');$q->execute(['admin']);$id=$q->fetchColumn();
db()->beginTransaction();
try{
    if($id){$q=db()->prepare('UPDATE users SET password_hash=?,role=?,pages=? WHERE id=?');$q->execute([$hash,'admin',json_encode(PAGE_SLUGS),$id]);}
    else{$q=db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)');$q->execute(['admin',$hash,'admin',json_encode(PAGE_SLUGS)]);}
    atomic_write(ROOT.'/data/first-login.txt',"Utente: admin\nPassword: $password\nCambia la password dopo l’accesso e rimuovi questo file.\n");
    @chmod(ROOT.'/data/first-login.txt',0600);
    db()->commit();
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
echo "Accesso admin ripristinato. Leggi data/first-login.txt dal file manager del server.\n";
echo "Richieste, allegati e contenuti sono conservati. Dopo tentativi ripetuti attendi 15 minuti prima di accedere.\n";
