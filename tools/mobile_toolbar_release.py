from pathlib import Path
root=Path(__file__).resolve().parent.parent
p=root/'files MD/struttura.md';s=p.read_text(encoding='utf-8');s+='\n## Release 2.1.12 — toolbar mobile e lingua in header\n- [ ] Inserire barra mobile Valutazione, WhatsApp, Email, Telefono.\n- [ ] Spostare ITA/ENG nell’header e coordinare barre form/editor.\n- [ ] Verificare layout e collegamenti; generare ZIP completo.\n';p.write_text(s,encoding='utf-8')
p=root/'PHP di sistema/core.php';s=p.read_text(encoding='utf-8').replace("'2.1.11'","'2.1.12'")
needle="    $head=$doc->getElementsByTagName('head')->item(0);\n    $css=$doc->createElement('link');"
replacement="""    $header=$doc->getElementsByTagName('header')->item(0);
    $languageNav=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," language-nav ")]')->item(0);
    if($header&&$languageNav){
        $container=$header;foreach($header->childNodes as $child)if($child instanceof DOMElement&&$child->tagName==='div'){$container=$child;break;}
        $languageNav->setAttribute('class','language-nav header-languages');$container->appendChild($languageNav);
    }
    $toolbar=$doc->createElement('nav');$toolbar->setAttribute('class','mobile-contact-toolbar');$toolbar->setAttribute('aria-label',$lang==='en'?'Quick contact':'Contatti rapidi');
    $links=[['valutazione.html',$lang==='en'?'Valuation':'Valutazione','◇'],['https://wa.me/393929664987','WhatsApp','↗'],['mailto:sl@ladiamanteria.com','Email','✉'],['tel:+3902466891',$lang==='en'?'Call':'Telefono','☎']];
    foreach($links as [$href,$label,$icon]){
        $a=$doc->createElement('a');$a->setAttribute('href',$href);
        if(str_starts_with($href,'https:')){$a->setAttribute('target','_blank');$a->setAttribute('rel','noopener');}
        $symbol=$doc->createElement('span',$icon);$symbol->setAttribute('aria-hidden','true');$symbol->setAttribute('class','contact-icon');$a->appendChild($symbol);$a->appendChild($doc->createElement('span',$label));$toolbar->appendChild($a);
    }
    $doc->getElementsByTagName('body')->item(0)->appendChild($toolbar);
    $head=$doc->getElementsByTagName('head')->item(0);
    $css=$doc->createElement('link');"""
assert needle in s;s=s.replace(needle,replacement);p.write_text(s,encoding='utf-8')
p=root/'assets/frontend-ui.js';s=p.read_text(encoding='utf-8').replace("if(node!==brand)menu.append(node);","if(node!==brand&&!node.classList.contains('header-languages'))menu.append(node);");p.write_text(s,encoding='utf-8')
