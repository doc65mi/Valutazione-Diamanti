<?php
declare(strict_types=1);
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/blog.php';
require_once __DIR__.'/../PHP di sistema/blog-view.php';
security_headers();
try{
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
    $lock=blog_lock();$db=blog_db();$c=blog_config();
    // Blog inglese su /en/blog/ (2.1.63): i vecchi indirizzi ?lang=en e gli slug cambiati rispondono con un 301.
    $path=(string)parse_url($_SERVER['REQUEST_URI']??'/blog/',PHP_URL_PATH);$enPath=(bool)preg_match('~/en/blog(?:/|$)~',$path);$prefix=blog_prefix();
    $lang=$enPath||($_GET['lang']??'it')==='en'?'en':'it';
    $slug=(string)($_GET['slug']??'');
    if($slug===''&&preg_match('~/(?:blog/articolo|en/blog)/([a-z0-9-]+)/?$~D',$path,$m))$slug=$m[1];
    $article=null;$status=200;$moved=null;
    if($slug!==''){
        $article=blog_one($db,"SELECT * FROM blog_articles WHERE slug=? AND status='published'",[$slug]);
        if(!$article)foreach(blog_rows($db,"SELECT slug,language,old_slugs FROM blog_articles WHERE status='published' AND old_slugs LIKE ?",['%'.$slug.'%']) as $row)if(in_array($slug,preg_split('/\s+/',trim((string)$row['old_slugs'])),true)){$moved=$row;break;}
        if($article){$lang=$article['language'];$want=$lang==='en'?$prefix.'/en/blog/'.$article['slug']:$prefix.'/blog/articolo/'.$article['slug'];if(rtrim($path,'/')!==$want||isset($_GET['lang']))$moved=$article;}
        elseif(!$moved)$status=404;
    }elseif(!preg_match('~/blog(?:/|/index\.php)?$~D',$path))$status=404;
    elseif(!$enPath&&$lang==='en'){$query=array_filter(['q'=>(string)($_GET['q']??''),'pagina'=>(string)($_GET['pagina']??'')],'strlen');header('Location: '.$prefix.'/en/blog/'.($query?'?'.http_build_query($query):''),true,301);exit;}
    if($moved&&menu_enabled('blog')){header('Location: '.($moved['language']==='en'?$prefix.'/en/blog/':$prefix.'/blog/articolo/').rawurlencode($moved['slug']),true,301);exit;}
    if($moved&&!$article)$status=404;
    // Blog spento dal pannello Menu: 404 per i visitatori, visibile (noindex) solo all'amministratore collegato (2.1.27).
    if(!menu_enabled('blog')){header('X-Robots-Tag: noindex, nofollow');header('Cache-Control: no-store');if(!admin_session()){$article=null;$status=404;}}
    http_response_code($status);
    echo blog_render_site($db,$c,$lang,$article,$status);
}catch(Throwable $e){error_log('Blog render: '.get_class($e));http_response_code(503);echo 'Blog temporaneamente non disponibile.';}
