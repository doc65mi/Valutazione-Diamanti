<?php
declare(strict_types=1);
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/blog.php';
security_headers(true);
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');fail('Usare POST.',405);}
    if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')fail('HTTPS richiesto.',400);
    $lock=blog_lock(true);$c=blog_config();
    $auth=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    if(!$c['webhook_hash']||!preg_match('/^Bearer (\S+)$/D',$auth,$m)||!hash_equals($c['webhook_hash'],hash('sha256',$m[1])))fail('Autenticazione non valida.',401);
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))fail('Usare application/json.',415);
    $raw=file_get_contents('php://input',false,null,0,700001);if(strlen($raw)>700000)fail('Contenuto troppo grande.',413);
    $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($input))fail('Oggetto JSON richiesto.');
    $db=blog_db();$a=blog_article_data($input,true,$c);$old=blog_one($db,'SELECT * FROM blog_articles WHERE source_id=?',[$a['source_id']]);
    if($old)foreach(['slug','status','featured','published_at','language'] as $field)$a[$field]=$old[$field];
    $a['updated_at']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    $id=blog_persist_article($db,$a,$old?(int)$old['id']:null);audit('blog-webhook',(string)$id);seo_indexes();
    respond(['id'=>$id,'status'=>$a['status'],'url'=>$a['status']==='published'?blog_article_url($c,$a):null],$old?200:201);
}catch(JsonException|InvalidArgumentException $e){fail('Dati articolo non validi.',422);}
catch(PDOException $e){error_log('Blog webhook database: '.$e->getCode());fail('Slug o identificatore in conflitto.',409);}
catch(Throwable $e){error_log('Blog webhook: '.get_class($e));fail('Ricezione temporaneamente non disponibile.',503);}
