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
    // Credits dell'agenzia in fondo al footer, IT/EN e blog (2.1.36).
    $row=$copyright?$copyright->parentNode:$legal;
    // Dati societari (2.1.48, audit): marchio, ragione sociale, sede legale, P.IVA, REA e licenza da Privacy e integrazioni.
    if($row&&!$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," site-legal-info ")]')->length){
        // 2.1.62: centrata, su due righe: dati societari con indirizzo, poi la licenza di Pubblica Sicurezza.
        $info=$doc->createElement('p');$info->setAttribute('class','site-legal-info');$info->setAttribute('style','text-align:center;margin-left:auto;margin-right:auto');
        foreach(legal_footer_lines($lang) as $i=>$line){if($i)$info->appendChild($doc->createElement('br'));$info->appendChild($doc->createTextNode($line));}
        $row->parentNode->insertBefore($info,$row->nextSibling);
    }
    if($row&&!$xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," site-credits ")]',$row)->length){
        $credits=$doc->createElement('span','Credits: ');$credits->setAttribute('class','site-credits');
        $a=$doc->createElement('a','Oltre Media');$a->setAttribute('href','https://www.oltre.media');$a->setAttribute('target','_blank');$a->setAttribute('rel','noopener');$credits->appendChild($a);$row->appendChild($credits);
    }
}
/** Riga dei dati societari nel footer: compare solo quello che è compilato in Dati del titolare. */
function legal_footer_lines(string $lang): array {
    $s=settings();$en=$lang==='en';$company=trim($s['legal_company']);
    $text=$en?'Valutazione Diamanti is a service of La Diamanteria':'Valutazione Diamanti è un servizio di La Diamanteria';
    if($company!==''&&$company!=='La Diamanteria')$text.=$en?', a brand of '.$company:', marchio di '.$company;
    $parts=[];
    if(legal_has_registered_office())$parts[]=($en?'Registered office: ':'Sede legale: ').trim($s['legal_address']);
    foreach(['legal_vat'=>$en?'VAT no. ':'P.IVA ','legal_rea'=>'REA '] as $key=>$label)if(trim((string)($s[$key]??''))!=='')$parts[]=$label.trim($s[$key]);
    $parts[]='Via Vincenzo Monti 47, 20123 Milano · +39 02 466 891';
    // Licenza di Pubblica Sicurezza (2.1.61), sulla seconda riga: la frase c'è sempre, il numero solo se compilato in Dati del titolare.
    $license=trim((string)($s['legal_license']??''));
    $licenseLine=($en?'Holders of a Public Security licence, authorised by the Questura (Police Headquarters)':'Titolari di licenza di Pubblica Sicurezza, autorizzati dalla Questura').($license!==''?($en?' (art. 127 TULPS no. ':' (art. 127 TULPS n. ').$license.')':'');
    return [rtrim($text,'.').'. '.implode(' · ',$parts),$licenseLine];
}
/** Vero quando l'indirizzo dei Dati del titolare è una sede legale diversa dallo showroom di Via Vincenzo Monti 47. */
function legal_has_registered_office(): bool { $a=trim(settings()['legal_address']);return $a!==''&&!preg_match('/vincenzo\s+monti\s*,?\s*47/i',$a); }
/** Titolare del trattamento per l'informativa privacy (segnaposto {{legal.controller}}). */
function legal_controller(string $lang): string {
    $s=settings();$en=$lang==='en';$company=trim($s['legal_company'])?:'La Diamanteria';$text=$company;
    if($company!=='La Diamanteria')$text.=$en?', trading as La Diamanteria':', che opera con il marchio La Diamanteria';
    if(legal_has_registered_office())$text.=($en?', with registered office at ':', con sede legale in ').trim($s['legal_address']);
    $vat=trim($s['legal_vat']);if($vat!=='')$text.=($en?', VAT no. ':', P.IVA ').$vat;
    $text.=legal_has_registered_office()?($en?', and operating office at Via Vincenzo Monti 47, 20123 Milan':', e sede operativa in Via Vincenzo Monti 47, 20123 Milano'):($en?', based at Via Vincenzo Monti 47, 20123 Milan':', con sede in Via Vincenzo Monti 47, 20123 Milano');
    return $text;
}
