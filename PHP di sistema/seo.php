<?php
declare(strict_types=1);
function seo_base(): string { return rtrim(settings()['site_url'] ?: 'https://www.valutazionediamanti.it','/'); }
function seo_markup(DOMDocument $doc,string $lang,string $url,string $title,string $description,string $type='WebPage',?array $article=null): void {
    $xp=new DOMXPath($doc);$head=$doc->getElementsByTagName('head')->item(0);
    foreach(iterator_to_array($xp->query('//script[@type="application/ld+json"]')) as $n)$n->parentNode->removeChild($n);
    $base=seo_base();$org=['@type'=>'Organization','@id'=>$base.'/#organization','name'=>settings()['legal_company'],'url'=>$base.'/'];
    $page=['@type'=>$type,'@id'=>$url.'#page','url'=>$url,'name'=>$title,'description'=>$description,'inLanguage'=>$lang,'isPartOf'=>['@id'=>$base.'/#website']];
    if($type==='FAQPage'){
        $questions=[];
        foreach($xp->query('//main//button[@data-faq-toggle]') as $button){
            $question=trim($xp->evaluate('string(.//span[1])',$button));$answers=[];
            foreach($button->parentNode->childNodes as $node)if($node instanceof DOMElement&&$node->tagName!=='button')$answers[]=trim($node->textContent);
            $answer=trim(implode("\n",$answers));if($question!==''&&$answer!=='')$questions[]=['@type'=>'Question','name'=>$question,'acceptedAnswer'=>['@type'=>'Answer','text'=>$answer]];
        }
        $page['mainEntity']=$questions;
    }
    $graph=[$org,['@type'=>'WebSite','@id'=>$base.'/#website','url'=>$base.'/','name'=>'Valutazione Diamanti','publisher'=>['@id'=>$org['@id']]],$page];
    if($article){$post=['@type'=>'BlogPosting','@id'=>$url.'#article','url'=>$url,'mainEntityOfPage'=>['@id'=>$page['@id']],'headline'=>$article['title'],'description'=>$article['excerpt'],'datePublished'=>(new DateTimeImmutable($article['published_at']))->format(DATE_ATOM),'dateModified'=>(new DateTimeImmutable($article['updated_at']))->format(DATE_ATOM),'inLanguage'=>$lang,'articleSection'=>$article['category'],'author'=>['@id'=>$org['@id']],'publisher'=>['@id'=>$org['@id']]];if($article['image_url'])$post['image']=['@type'=>'ImageObject','url'=>$article['image_url'],'caption'=>$article['image_alt']];
        if(trim((string)($article['keywords']??''))!=='')$post['keywords']=trim($article['keywords']);
        $post['wordCount']=str_word_count(strip_tags($article['content']));$graph[]=$post;
        $faq=blog_faq_items($article['faq_json']??null);
        if($faq)$graph[]=['@type'=>'FAQPage','@id'=>$url.'#faq','url'=>$url,'inLanguage'=>$lang,'isPartOf'=>['@id'=>$page['@id']],'mainEntity'=>array_map(fn($item)=>['@type'=>'Question','name'=>$item['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$item['answer']]],$faq)];}
    $script=$doc->createElement('script');$script->setAttribute('type','application/ld+json');$script->appendChild($doc->createTextNode(json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)));$head->appendChild($script);
}
// Collegamento a Google Search Console e Bing Webmaster Tools (2.1.32): meta tag in ogni pagina pubblica,
// file di verifica nella radice del sito e notifica IndexNow (Bing, Yandex, Seznam) dei contenuti nuovi.
function seo_verification_meta(DOMDocument $doc): void {
    $s=settings();$head=$doc->getElementsByTagName('head')->item(0);if(!$head)return;
    foreach(['google-site-verification'=>$s['google_verification'],'msvalidate.01'=>$s['bing_verification']] as $name=>$value){
        if($value==='')continue;$meta=$doc->createElement('meta');$meta->setAttribute('name',$name);$meta->setAttribute('content',$value);$head->appendChild($meta);
    }
}
/** Scrive (o toglie) il file HTML di Google, BingSiteAuth.xml e la chiave IndexNow nella radice. Sono file fuori da git. */
function seo_verification_files(): void {
    $s=settings();
    foreach(glob(ROOT.'/google*.html')?:[] as $old)if(basename($old)!==$s['google_file']&&str_starts_with((string)@file_get_contents($old,false,null,0,40),'google-site-verification:'))@unlink($old);
    if($s['google_file']!=='')atomic_write(ROOT.'/'.$s['google_file'],'google-site-verification: '.$s['google_file'],0644);
    if($s['bing_verification']!=='')atomic_write(ROOT.'/BingSiteAuth.xml','<?xml version="1.0"?>'."\n".'<users><user>'.$s['bing_verification'].'</user></users>'."\n",0644);elseif(is_file(ROOT.'/BingSiteAuth.xml'))@unlink(ROOT.'/BingSiteAuth.xml');
    $key=$s['indexnow_enabled']?$s['indexnow_key']:'';
    foreach(glob(ROOT.'/indexnow-*.txt')?:[] as $old)if(basename($old)!=='indexnow-'.$key.'.txt')@unlink($old);
    if($key!=='')atomic_write(ROOT.'/indexnow-'.$key.'.txt',$key,0644);
}
function indexnow_status(): array { $v=@json_decode((string)@file_get_contents(ROOT.'/data/indexnow.json'),true);return is_array($v)?$v:['sent'=>[],'last'=>null]; }
/** Invia a IndexNow gli indirizzi indicati; con $onlyChanged manda solo quelli nuovi o con lastmod cambiato dall'ultimo invio. */
function indexnow_submit(array $entries,bool $onlyChanged=true): ?array {
    $s=settings();if(!$s['indexnow_enabled']||!preg_match('/^[a-f0-9]{32}$/',$s['indexnow_key'])||!function_exists('curl_init'))return null;
    $base=seo_base();$host=(string)parse_url($base,PHP_URL_HOST);$state=indexnow_status();$sent=(array)($state['sent']??[]);
    $urls=[];foreach($entries as $url=>$lastmod)if(!$onlyChanged||($sent[$url]??null)!==$lastmod)$urls[]=$url;
    if(!$urls||$host==='')return null;
    // Dopo un invio fallito l'avviso automatico aspetta un'ora, per non rallentare ogni salvataggio.
    $last=$state['last']??null;if($onlyChanged&&is_array($last)&&empty($last['ok'])&&time()-(int)strtotime((string)$last['at'])<3600)return null;
    $body=json_encode(['host'=>$host,'key'=>$s['indexnow_key'],'keyLocation'=>$base.'/indexnow-'.$s['indexnow_key'].'.txt','urlList'=>array_slice($urls,0,10000)],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $ch=curl_init('https://api.indexnow.org/indexnow');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=utf-8'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8]);
    curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $ok=in_array($http,[200,202],true);
    if($ok)foreach($urls as $url)$sent[$url]=$entries[$url];
    $state=['sent'=>$sent,'last'=>['at'=>gmdate('c'),'count'=>count($urls),'http'=>$http,'ok'=>$ok]];
    @file_put_contents(ROOT.'/data/indexnow.json',json_encode($state,JSON_UNESCAPED_SLASHES));
    return $state['last'];
}
/** Indirizzi della sitemap con il loro lastmod (stringa vuota se assente). */
function sitemap_entries(): array {
    $out=[];if(preg_match_all('~<url><loc>([^<]+)</loc>(?:<lastmod>([^<]+)</lastmod>)?~',(string)@file_get_contents(ROOT.'/sitemap.xml'),$m,PREG_SET_ORDER))foreach($m as $row)$out[html_entity_decode($row[1],ENT_XML1)]=$row[2]??'';
    return $out;
}
