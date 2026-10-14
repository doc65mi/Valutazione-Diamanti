<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/core.php';
foreach(['data/sessions','uploads/clienti','it','en'] as $dir) if(!is_dir(ROOT.'/'.$dir)) mkdir(ROOT.'/'.$dir,0700,true);
if(!db()->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
    $password=bin2hex(random_bytes(12));
    $q=db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)');
    $q->execute(['admin',password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT),'admin',json_encode(PAGE_SLUGS)]);
    atomic_write(ROOT.'/data/first-login.txt',"Utente: admin\nPassword: $password\nCambia la password al primo accesso e cancella questo file.\n");
    echo "Amministratore creato. Credenziali in data/first-login.txt (cartella protetta).\n";
}
foreach(PAGE_SLUGS as $slug) foreach(['it','en'] as $lang) publish($slug,$lang,content($slug,$lang));
seo_indexes();
echo 'Build completata. Versione '.APP_VERSION."\n";
