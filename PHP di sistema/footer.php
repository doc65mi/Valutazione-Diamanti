<?php
declare(strict_types=1);
function tidy_footer(DOMDocument $doc,string $lang): void {
    $xp=new DOMXPath($doc);
    foreach(iterator_to_array($xp->query('//footer//a')) as $a){
        $label=mb_strtolower(trim($a->textContent));
        if(in_array($label,['blog','area riservata','reserved area'],true)||str_contains($a->getAttribute('href'),'/admin/'))$a->parentNode->removeChild($a);
    }
    $legal=$xp->query('//footer[contains(concat(" ",normalize-space(@class)," ")," legal-footer ")]')->item(0);
    $copyright=null;
    foreach($xp->query('//footer//span') as $span)if(str_contains($span->textContent,'©')){$copyright=$span;break;}
    if($copyright){
        $copyright->textContent=preg_replace('/\s*·\s*(?:Cookie|Cookies)\s*·?\s*$/u','',trim($copyright->textContent));
        $row=$copyright->parentNode;$row->setAttribute('class',trim($row->getAttribute('class').' site-subfooter'));
        if($legal){$nav=$doc->createElement('nav');$nav->setAttribute('class','subfooter-links');$nav->setAttribute('aria-label',$lang==='en'?'Legal information':'Informazioni legali');while($legal->firstChild)$nav->appendChild($legal->firstChild);$row->appendChild($nav);$legal->parentNode->removeChild($legal);}
    }elseif($legal){
        $legal->setAttribute('class','legal-footer site-subfooter');$copy=$doc->createElement('span',$lang==='en'?'© 2026 Valutazione Diamanti by La Diamanteria · All rights reserved.':'© 2026 Valutazione Diamanti by La Diamanteria · Tutti i diritti riservati.');$legal->insertBefore($copy,$legal->firstChild);
    }
}
