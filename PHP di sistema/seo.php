<?php
declare(strict_types=1);
function seo_base(): string { return rtrim(settings()['site_url'] ?: 'https://comprodiamanti.piuper.site','/'); }
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
    $graph=[$org,['@type'=>'WebSite','@id'=>$base.'/#website','url'=>$base.'/','name'=>'ComproDiamanti.it','publisher'=>['@id'=>$org['@id']]],$page];
    if($article){$post=['@type'=>'BlogPosting','@id'=>$url.'#article','url'=>$url,'mainEntityOfPage'=>['@id'=>$page['@id']],'headline'=>$article['title'],'description'=>$article['excerpt'],'datePublished'=>(new DateTimeImmutable($article['published_at']))->format(DATE_ATOM),'dateModified'=>(new DateTimeImmutable($article['updated_at']))->format(DATE_ATOM),'inLanguage'=>$lang,'articleSection'=>$article['category'],'author'=>['@id'=>$org['@id']],'publisher'=>['@id'=>$org['@id']]];if($article['image_url'])$post['image']=['@type'=>'ImageObject','url'=>$article['image_url'],'caption'=>$article['image_alt']];
        if(trim((string)($article['keywords']??''))!=='')$post['keywords']=trim($article['keywords']);
        $post['wordCount']=str_word_count(strip_tags($article['content']));$graph[]=$post;
        $faq=blog_faq_items($article['faq_json']??null);
        if($faq)$graph[]=['@type'=>'FAQPage','@id'=>$url.'#faq','url'=>$url,'inLanguage'=>$lang,'isPartOf'=>['@id'=>$page['@id']],'mainEntity'=>array_map(fn($item)=>['@type'=>'Question','name'=>$item['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$item['answer']]],$faq)];}
    $script=$doc->createElement('script');$script->setAttribute('type','application/ld+json');$script->appendChild($doc->createTextNode(json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)));$head->appendChild($script);
}
