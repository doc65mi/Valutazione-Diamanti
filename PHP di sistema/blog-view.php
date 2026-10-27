<?php
declare(strict_types=1);
function blog_date(string $date,string $lang): string{return (new DateTimeImmutable($date))->format($lang==='en'?'d M Y':'d/m/Y');}
function blog_card(array $c,array $a,string $lang,bool $large=false): void{?>
<article class="cd-post <?=$large?'cd-post-lead':''?>"><a class="cd-cover" href="<?=blog_h(blog_article_url($c,$a))?>" tabindex="-1" aria-hidden="true"><?php if($a['image_url']):?><img src="<?=blog_h($a['image_url'])?>" alt="<?=blog_h($a['image_alt'])?>" loading="lazy" referrerpolicy="no-referrer"><?php else:?><span aria-hidden="true">◇</span><?php endif?></a><div class="cd-post-body"><span class="cd-kicker"><?=blog_h($a['category'])?></span><h3><a href="<?=blog_h(blog_article_url($c,$a))?>"><?=blog_h($a['title'])?></a></h3><p><?=blog_h(blog_excerpt((string)$a['excerpt'],220,(string)($a['content']??'')))?></p><div class="cd-post-meta"><time datetime="<?=blog_h($a['published_at'])?>"><?=blog_h(blog_date($a['published_at'],$lang))?></time><a href="<?=blog_h(blog_article_url($c,$a))?>"><?=$lang==='en'?'Read article':'Leggi articolo'?> ↗</a></div></div></article>
<?php }
/** FAQ e collegamenti Pillar/Cluster dell'articolo. Tutto passa da blog_h(): nessun HTML dai dati. */
function blog_article_extras(PDO $db,array $article,string $lang): void{
    $en=$lang==='en';$c=blog_config();$faq=blog_faq_items($article['faq_json']??null);
    if($faq){?><section class="cd-faq" aria-labelledby="cd-faq-title"><h2 id="cd-faq-title"><?=$en?'Frequently asked questions':'Domande frequenti'?></h2><?php foreach($faq as $item):?><h3><?=blog_h($item['question'])?></h3><p><?=blog_h($item['answer'])?></p><?php endforeach?></section><?php }
    $related=[];$role=(string)($article['role']??'');$pillar=(string)($article['pillar_slug']??'');
    $siblings="SELECT slug,language,title FROM blog_articles WHERE status='published' AND role='CLUSTER' AND language=? AND pillar_slug=? AND slug<>? ORDER BY published_at DESC,id DESC LIMIT 6";
    if($role==='CLUSTER'&&$pillar!==''){
        $main=blog_one($db,"SELECT slug,language,title FROM blog_articles WHERE status='published' AND slug=? AND language=?",[$pillar,$article['language']]);
        if($main)$related[]=[$main,$en?'Complete guide':'Guida completa'];
        foreach(blog_rows($db,$siblings,[$article['language'],$pillar,$article['slug']]) as $row)$related[]=[$row,''];
    }elseif($role==='PILLAR'){
        foreach(blog_rows($db,$siblings,[$article['language'],$article['slug'],$article['slug']]) as $row)$related[]=[$row,''];
    }
    if($related){?><nav class="cd-related" aria-labelledby="cd-related-title"><h2 id="cd-related-title"><?=$en?'Keep reading':'Continua a leggere'?></h2><ul><?php foreach($related as [$row,$tag]):?><li><?php if($tag!==''):?><span class="cd-kicker"><?=blog_h($tag)?></span> <?php endif?><a href="<?=blog_h(blog_article_url($c,$row))?>"><?=blog_h($row['title'])?></a></li><?php endforeach?></ul></nav><?php }
}
function blog_fragment(PDO $db,array $c,string $lang,?array $article,int $status): string{
    $en=$lang==='en';$q=mb_substr(trim((string)($_GET['q']??'')),0,100);$page=max(1,min(100000,(int)($_GET['pagina']??1)));
    $home=blog_home_url($c,$lang);
    $all=blog_rows($db,"SELECT slug,language,title,published_at FROM blog_articles WHERE status='published' AND language=? ORDER BY published_at DESC,id DESC",[$lang]);
    ob_start();?>
<main class="cd-blog" id="blog-content"><?php if(!menu_enabled('blog')&&admin_session()):?><p class="cd-admin-notice" role="status"><strong><?=$en?'Administrator preview.':'Anteprima amministratore.'?></strong> <?=$en?'The blog is switched off in Admin › Menu: visitors get «Article not found». Tick «Blog» there to publish it.':'Il blog è spento in Admin › Menu: i visitatori vedono «Articolo non trovato». Spunta «Blog» nel pannello Menu per metterlo online.'?></p><?php endif?>
<?php if($status===404):?><section class="cd-empty"><span class="cd-kicker">404</span><h1><?=$en?'Article not found':'Articolo non trovato'?></h1><p><?=$en?'This content is not available or has not been published.':'Questo contenuto non è disponibile o non è ancora pubblicato.'?></p><a class="button" href="<?=blog_h($home)?>"><?=$en?'Back to the blog':'Torna al blog'?></a></section>
<?php else:?>
<?php if(!$article):?><section class="cd-blog-hero"><span class="cd-kicker">VALUTAZIONE DIAMANTI · BY LA DIAMANTERIA</span><h1><?=$en?'The world of diamonds,<br><em>with a clearer perspective.</em>':'Il mondo dei diamanti,<br><em>con uno sguardo più chiaro.</em>'?></h1><p><?=$en?'Guides, insights and stories to understand diamonds and jewellery.':'Guide, approfondimenti e storie per conoscere diamanti e gioielli.'?></p></section><?php else:?><div class="cd-breadcrumb"><a href="<?=blog_h($home)?>">Blog</a><span> / <?=blog_h($article['category'])?></span></div><?php endif?>
<div class="cd-blog-layout"><div class="cd-blog-primary">
<?php if($article):?><article class="cd-article"><span class="cd-kicker"><?=blog_h($article['category'])?></span><h1><?=blog_h($article['title'])?></h1><p class="cd-article-meta"><time datetime="<?=blog_h($article['published_at'])?>"><?=blog_h(blog_date($article['published_at'],$lang))?></time> · <?=max(1,(int)ceil(str_word_count(strip_tags($article['content']))/200))?> <?=$en?'min read':'min di lettura'?></p><p class="cd-standfirst"><?=blog_h(blog_excerpt((string)$article['excerpt'],400,(string)$article['content']))?></p><?php if($article['image_url']):?><img class="cd-article-image" src="<?=blog_h($article['image_url'])?>" alt="<?=blog_h($article['image_alt'])?>" width="1200" height="800" fetchpriority="high" decoding="async" referrerpolicy="no-referrer"><?php endif?><div class="cd-prose"><?=$article['content']?></div><?php blog_article_extras($db,$article,$lang);?><div class="cd-article-end"><a href="<?=blog_h($home)?>">← <?=$en?'All articles':'Tutti gli articoli'?></a><a href="<?=blog_h($c['site_url'].'/'.$lang.'/valutazione.html')?>"><?=$en?'Request a valuation':'Richiedi una valutazione'?> ↗</a></div></article>
<?php else:
    $featured=blog_rows($db,"SELECT * FROM blog_articles WHERE status='published' AND language=? ORDER BY featured DESC,published_at DESC,id DESC LIMIT 3",[$lang]);
    if($featured&&$q===''&&$page===1):?><section><div class="cd-section-title"><h2><?=$en?'Featured articles':'In primo piano'?></h2><span><?=$en?'OUR SELECTION':'LA NOSTRA SELEZIONE'?></span></div><div class="cd-featured"><?php foreach($featured as $i=>$a)blog_card($c,$a,$lang,$i===0);?></div></section><?php endif;
    $where="status='published' AND language=?";$params=[$lang];if($q!==''){$where.=' AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)';$params=[...$params,'%'.$q.'%','%'.$q.'%','%'.$q.'%'];}
    $total=(int)blog_one($db,"SELECT COUNT(*) n FROM blog_articles WHERE $where",$params)['n'];$pages=max(1,(int)ceil($total/6));$page=min($page,$pages);$offset=($page-1)*6;
    $posts=blog_rows($db,"SELECT * FROM blog_articles WHERE $where ORDER BY published_at DESC,id DESC LIMIT 6 OFFSET $offset",$params);
    ?><section class="cd-latest"><div class="cd-section-title"><h2><?=$q!==''?($en?'Search: ':'Ricerca: ').blog_h($q):($en?'All articles':'Tutti gli approfondimenti')?></h2><span><?=$total?> <?=$en?'ARTICLES':'ARTICOLI'?></span></div><?php if(!$posts):?><div class="cd-empty"><span aria-hidden="true">◇</span><h3><?=$q!==''?($en?'No articles found':'Nessun articolo trovato'):($en?'Our blog is coming soon':'Il nostro blog sta per iniziare')?></h3><p><?=$q!==''?($en?'Try a different search.':'Prova a cercare con parole diverse.'):($en?'You will find our articles and guides here.':'Qui troverai i nostri articoli e le guide dedicate ai diamanti.')?></p></div><?php else:?><div class="cd-post-grid"><?php foreach($posts as $a)blog_card($c,$a,$lang);?></div><?php endif?>
<?php if($pages>1):?><nav class="cd-pagination" aria-label="<?=$en?'Article pages':'Pagine articoli'?>"><?php if($page>1):?><a href="<?=blog_h($home.'?'.http_build_query(['q'=>$q,'pagina'=>$page-1]))?>">← <?=$en?'Previous':'Precedenti'?></a><?php endif?><span><?=$page?> / <?=$pages?></span><?php if($page<$pages):?><a href="<?=blog_h($home.'?'.http_build_query(['q'=>$q,'pagina'=>$page+1]))?>"><?=$en?'Next':'Successivi'?> →</a><?php endif?></nav><?php endif?></section>
<?php endif?></div><aside class="cd-blog-sidebar" aria-label="<?=$en?'Article archive':'Archivio articoli'?>"><section><span class="cd-kicker"><?=$en?'FIND AN ARTICLE':'CERCA UN APPROFONDIMENTO'?></span><form method="get" action="<?=blog_h($home)?>" class="cd-search"><label for="blog-search" class="cd-sr-only"><?=$en?'Search the blog':'Cerca nel blog'?></label><input id="blog-search" name="q" value="<?=blog_h($q)?>" placeholder="<?=$en?'Search articles…':'Cerca nel blog…'?>" maxlength="100"><button aria-label="<?=$en?'Search':'Cerca'?>">→</button></form></section><section class="cd-archive"><h2><?=$en?'Published articles':'Articoli pubblicati'?></h2><ol><?php foreach($all as $i=>$a):?><li><span class="cd-number"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><div><a href="<?=blog_h(blog_article_url($c,$a))?>"><?=blog_h($a['title'])?></a><time datetime="<?=blog_h($a['published_at'])?>"><?=blog_h(blog_date($a['published_at'],$lang))?></time></div></li><?php endforeach?></ol><?php if(!$all):?><p><?=$en?'No published articles yet.':'Nessun articolo pubblicato.'?></p><?php endif?></section><section class="cd-blog-cta"><span class="cd-kicker">LA DIAMANTERIA · MILANO</span><h2><?=$en?'Every diamond<br>deserves attention.':'Ogni diamante<br>merita attenzione.'?></h2><p><?=$en?'Discover our direct purchasing and free valuation service.':'Scopri il nostro servizio di acquisto diretto e valutazione gratuita.'?></p><a class="button" href="<?=blog_h($c['site_url'].'/'.$lang.'/valutazione.html')?>"><?=$en?'Request a valuation':'Richiedi una valutazione'?> ↗</a></section></aside></div>
<?php endif?></main>
<?php return ob_get_clean(); }
function blog_render_site(PDO $db,array $c,string $lang,?array $article,int $status=200): string{
    // Reuse the actual published site's layout, brand, contacts, consent and mobile toolbar.
    $html=file_get_contents(ROOT.'/'.$lang.'/index.html');$doc=new DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);$xp=new DOMXPath($doc);
    foreach(iterator_to_array($doc->childNodes) as $node)if($node->nodeType===XML_PI_NODE)$doc->removeChild($node);
    $body=$doc->getElementsByTagName('body')->item(0);$body->setAttribute('data-page','blog');$body->setAttribute('class','public-page blog-page');
    foreach($xp->query('//*[@data-cd-slot]') as $node)$node->removeAttribute('data-cd-slot');
    foreach(iterator_to_array($xp->query('//script[contains(@src,"frontend-editor")]')) as $node)$node->parentNode->removeChild($node);
    foreach($xp->query('//*[@href or @src or @action]') as $node)foreach(['href','src','action'] as $attr){
        if(!$node->hasAttribute($attr))continue;$value=$node->getAttribute($attr);
        if($value===''||preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|/)~i',$value))continue;
        if(str_starts_with($value,'../../admin'))$absolute=$c['site_url'].'/admin/'.substr($value,12);
        elseif(str_starts_with($value,'../'))$absolute=$c['site_url'].'/'.preg_replace('~^(?:\.\./)+~','',$value);
        elseif(str_starts_with($value,'#'))$absolute=$c['site_url'].'/'.$lang.'/index.html'.$value;
        else $absolute=$c['site_url'].'/'.$lang.'/'.$value;
        $node->setAttribute($attr,$absolute);
    }
    $main=$doc->getElementsByTagName('main')->item(0);$fragment=new DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$fragment->loadHTML('<?xml encoding="UTF-8">'.blog_fragment($db,$c,$lang,$article,$status),LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);$replacement=$doc->importNode($fragment->getElementsByTagName('main')->item(0),true);$main->parentNode->replaceChild($replacement,$main);
    // 2.1.41: CSS, script, immagini, link e moduli del sito diventano relativi alla radice. Con l'URL pubblico impostato su un host
    // diverso da quello aperto (www/senza www, http/https) la Content-Security-Policy 'self' bloccava i fogli di stile: pagina senza grafica.
    $siteBase=rtrim($c['site_url'],'/');$rootPath=blog_prefix();
    foreach($xp->query('//*[@href or @src or @action]') as $node)foreach(['href','src','action'] as $attr){
        $value=$node->getAttribute($attr);
        if($siteBase!==''&&($value===$siteBase||str_starts_with($value,$siteBase.'/')||str_starts_with($value,$siteBase.'?')))$node->setAttribute($attr,$rootPath.(substr($value,strlen($siteBase))?:'/'));
    }
    $title=$article?$article['title']:($lang==='en'?'Blog: guides to selling diamonds and jewellery':'Blog: guide per vendere diamanti e gioielli');if($status===404)$title=$lang==='en'?'Article not found':'Articolo non trovato';
    $pageTitle=($article&&trim((string)($article['meta_title']??''))!=='')?trim($article['meta_title']):$title;
    // 2.1.63 (audit): lo stesso titolo completo per <title>, og:title e twitter:title.
    $fullTitle=stripos($pageTitle,'Valutazione Diamanti')!==false?$pageTitle:$pageTitle.' | Valutazione Diamanti';
    $doc->getElementsByTagName('title')->item(0)->textContent=$fullTitle;
    $description=$article?(trim((string)($article['meta_description']??''))!==''?trim($article['meta_description']):blog_excerpt((string)$article['excerpt'],160,(string)$article['content'])):($lang==='en'?'Practical guides for selling a diamond: certificates, the 4Cs, realisable value, inheritance and how to prepare for the appointment.':'Guide pratiche per chi vuole vendere un diamante: certificati, 4C, valore di realizzo, eredità e come prepararsi all\'appuntamento.');
    foreach($xp->query('//meta[@name="description"]') as $node)$node->setAttribute('content',$description);
    foreach(iterator_to_array($xp->query('//link[@rel="canonical" or @rel="alternate"]|//meta[starts-with(@property,"og:")]|//meta[starts-with(@name,"twitter:")]')) as $node)$node->parentNode->removeChild($node);
    $head=$doc->getElementsByTagName('head')->item(0);$link=$doc->createElement('link');$link->setAttribute('rel','canonical');$link->setAttribute('href',$article?blog_article_url($c,$article):blog_home_url($c,$lang));$head->appendChild($link);
    // hreflang tra blog italiano e inglese (2.1.63); gli articoli sono in una lingua sola e non hanno una traduzione collegata.
    if(!$article&&$status===200)foreach(['it'=>'it','en'=>'en','x-default'=>'it'] as $hl=>$target){$alt=$doc->createElement('link');$alt->setAttribute('rel','alternate');$alt->setAttribute('hreflang',$hl);$alt->setAttribute('href',blog_home_url($c,$target));$head->appendChild($alt);}
    $css=$doc->createElement('link');$css->setAttribute('rel','stylesheet');$css->setAttribute('href',$rootPath.'/assets/blog.css?v='.APP_VERSION);$head->appendChild($css);
    if($status===200&&$article){
        $og=['og:type'=>'article','og:site_name'=>'Valutazione Diamanti by La Diamanteria','og:title'=>$fullTitle,'og:description'=>$description,'og:url'=>$link->getAttribute('href'),'og:locale'=>$lang==='en'?'en_GB':'it_IT','article:published_time'=>(new DateTimeImmutable($article['published_at']))->format(DATE_ATOM)];
        // Senza copertina l'articolo usa l'immagine 1200×630 del blog (2.1.63).
        seo_og_meta($doc,$og,$article['image_url']?:seo_share_image(null,'blog',$lang),$article['image_url']?($article['image_alt']?:$article['title']):$fullTitle);
    }
    elseif($status===200)seo_open_graph($doc,$lang,$link->getAttribute('href'),$fullTitle,$description,'blog');
    if($status===404||!empty($_GET['q'])||!empty($_GET['pagina'])){$meta=$doc->createElement('meta');$meta->setAttribute('name','robots');$meta->setAttribute('content','noindex,follow');$head->appendChild($meta);}
    foreach($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," language-nav ")]//a') as $node){$target=$node->getAttribute('lang')==='en'?'en':'it';$node->setAttribute('href',blog_home_url($c,$target));if($target===$lang)$node->setAttribute('aria-current','page');else $node->removeAttribute('aria-current');}
    foreach($xp->query('//header//a[contains(@class,"site-blog-link")]') as $node)$node->setAttribute('aria-current','page');
    seo_verification_meta($doc);phone_text_nodes($xp);
    if($status===200)seo_markup($doc,$lang,$link->getAttribute('href'),$title,$description,$article?'WebPage':'Blog',$article);
    else foreach(iterator_to_array($xp->query('//script[@type="application/ld+json"]')) as $node)$node->parentNode->removeChild($node);
    return $doc->saveHTML();
}
