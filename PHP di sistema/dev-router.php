<?php
// Development server: php -S 127.0.0.1:8092 "PHP di sistema/dev-router.php"
require_once __DIR__.'/core.php';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(preg_match('~(?:^|/)(?:old-page|data|content|pages-src|partials|tools|files MD|PHP di sistema|\.git|\.codex|\.agents|\.env)(?:/|$)|^/uploads/clienti(?:/|$)|\\\\|/\.~i',$path)){http_response_code(403);exit('Forbidden');}
if(preg_match('#^/public/(it|en)/(.*)$#',$path,$old)){header('Location: /'.$old[1].'/'.$old[2].(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''),true,301);exit;}
if($path==='/'){header('Location: /it/index.html',true,302);exit;}
$legacy=['Admin.dc.html'=>'admin/','Login.dc.html'=>'admin/','ComproDiamanti.html'=>'it/index.html','ComproDiamanti.dc.html'=>'it/index.html','ComproDiamanti-standalone-src.dc.html'=>'it/index.html','Quotazione.dc.html'=>'it/valutazione.html','Grazie.dc.html'=>'it/grazie.html'];
if(isset($legacy[ltrim($path,'/')])){if(str_contains($path,'Admin')||str_contains($path,'Login'))security_headers(true);header('Location: /'.$legacy[ltrim($path,'/')].(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''),true,302);exit;}
if(preg_match('~^/(?:wp-json|wp-admin)(?:/|$)|^/(?:wp-login|xmlrpc)\.php$~',$path)){require ROOT.'/blog/wp-bridge.php';exit;}
if(preg_match('~^/blog(?:/|$)~',$path)){require ROOT.'/blog/index.php';exit;}
security_headers(str_starts_with($path,'/admin')||str_starts_with($path,'/api'));
if(preg_match('#^/(it|en)/#',$path))header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.googletagmanager.com https://www.google.com https://www.gstatic.com https://connect.facebook.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://*.google.com https://*.doubleclick.net https://*.googleadservices.com https://www.facebook.com; frame-src 'self' https://www.google.com https://recaptcha.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
return false;
