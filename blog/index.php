<?php
declare(strict_types=1);
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/blog.php';
require_once __DIR__.'/../PHP di sistema/blog-view.php';
security_headers();
try{
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
    $lock=blog_lock();$db=blog_db();$c=blog_config();
    $lang=($_GET['lang']??'it')==='en'?'en':'it';
    $slug=(string)($_GET['slug']??'');
    if($slug===''&&preg_match('~/blog/articolo/([a-z0-9-]+)/?$~D',parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),$m))$slug=$m[1];
    $article=null;$status=200;
    if($slug!==''){
        $article=blog_one($db,"SELECT * FROM blog_articles WHERE slug=? AND status='published'",[$slug]);
        if(!$article)$status=404;else $lang=$article['language'];
    }elseif(!preg_match('~/blog(?:/|/index\.php)?$~D',parse_url($_SERVER['REQUEST_URI']??'/blog/',PHP_URL_PATH)))$status=404;
    // Blog spento dal pannello Menu: 404 per i visitatori, visibile (noindex) solo all'amministratore collegato (2.1.27).
    if(!menu_enabled('blog')){header('X-Robots-Tag: noindex, nofollow');header('Cache-Control: no-store');if(!admin_session()){$article=null;$status=404;}}
    http_response_code($status);
    echo blog_render_site($db,$c,$lang,$article,$status);
}catch(Throwable $e){error_log('Blog render: '.get_class($e));http_response_code(503);echo 'Blog temporaneamente non disponibile.';}
