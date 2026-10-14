from pathlib import Path
import json
from bs4 import BeautifulSoup

root = Path(__file__).resolve().parent.parent
for lang in ['it', 'en']:
    def tr(it, en): return en if lang == 'en' else it
    p = root / 'pages-src' / f'valutazione.{lang}.html'
    soup = BeautifulSoup(p.read_text(encoding='utf-8'), 'html.parser')
    main = soup.main
    main['class'] = ['quote-main', 'quote-restored']
    column = soup.new_tag('div', attrs={'class': 'quote-column'})
    for child in list(main.contents): column.append(child.extract())
    main.append(column)
    intro = soup.new_tag('div', attrs={'class': 'quote-intro'})
    column.insert(0, intro)
    for tag in list(column.children):
        if getattr(tag, 'name', None) and (tag.name == 'h1' or 'kicker' in tag.get('class', []) or 'trust-line' in tag.get('class', []) or tag.get('data-om-slot') == 'valutazione-contenuto-007'):
            intro.append(tag.extract())
    hint = column.find('p', attrs={'data-om-slot': 'valutazione-contenuto-011'})
    if hint: hint.extract()
    progress = column.find(class_='progress')
    step = column.find(id='step-label')
    progress.insert_before(step.extract())
    for select in soup.select('select'):
        select['class'] = ['choice-native']
        select['tabindex'] = '-1'
        group = soup.new_tag('div', attrs={'class': 'choice-grid', 'data-choice-for': select['name'], 'role': 'group'})
        for opt in select.select('option[value]'):
            if not opt['value']: continue
            button = soup.new_tag('button', attrs={'type': 'button', 'class': 'choice-card', 'data-value': opt['value'], 'aria-pressed': 'false'})
            button.string = opt.get_text()
            group.append(button)
        # Outside the label to keep button clicks from activating the native select.
        select.parent.insert_after(group)
    upload = soup.find(id='photos').parent
    upload['class'] = ['upload-zone']
    upload.find('span').string = tr('Scatta o carica una foto', 'Take or upload a photograph')
    aside = BeautifulSoup(f'''<aside class="quote-aside"><section class="how-card"><h2>{tr('Come funziona','How it works')}</h2><ol><li><strong>{tr('Richiedi la valutazione','Request a valuation')}.</strong> {tr('Invia il modulo o scrivici su WhatsApp.','Complete the form or contact us on WhatsApp.')}</li><li><strong>{tr('Valutazione professionale','Expert assessment')}.</strong> {tr('Un gemmologo esamina la richiesta.','A gemmologist reviews your enquiry.')}</li><li><strong>{tr('Offerta diretta','Direct offer')}.</strong> {tr('Senza aste né intermediari.','No auctions or intermediaries.')}</li><li><strong>{tr('Pagamento','Payment')}.</strong> {tr('Con bonifico tracciabile.','By traceable bank transfer.')}</li></ol></section><img src="https://diamond-shine-guide.lovable.app/__l5e/assets-v1/0ad06598-6c3d-430f-b1d8-aaa6800911a7/showroom-2.jpg" alt="{tr('Ingresso della sede La Diamanteria','Entrance to La Diamanteria')}" loading="lazy"><p><strong>La Diamanteria · Via Vincenzo Monti 47, Milano</strong><br>{tr('Cinque generazioni di Diamantaires. Dal 2009 · compratore diretto.','Five generations of diamantaires. Direct buying in Milan since 2009.')}</p><section class="aside-card"><h3>{tr('Cosa non valutiamo','Items we do not value')}</h3><p>{tr('Diamanti inferiori a 1 carato per singola pietra o creati in laboratorio.','Diamonds under 1 carat per stone or laboratory-grown diamonds.')}</p><p>{tr('Diamanti con certificati AIG, GRA, GWLAB o ALGT.','Diamonds with AIG, GRA, GWLAB or ALGT certificates.')}</p><p>{tr('Orologi di marchi non trattati.','Watches from brands we do not buy.')}</p><p>{tr('Le pietre preziose vengono valutate anche sotto i 2 carati.','We also value coloured gemstones weighing less than 2 carats.')}</p></section><section class="aside-card"><p class="kicker">{tr('Recensioni Google','Google reviews')}</p><p>{tr('Le recensioni sono pubblicate direttamente sul nostro profilo Google.','Reviews are published directly on our Google profile.')}</p><a href="https://www.google.com/maps/place/?q=place_id:ChIJFR80gkTBhkcRJGjlclhYteg" target="_blank" rel="noopener">{tr('Leggi le recensioni su Google ↗','Read our Google reviews ↗')}</a></section></aside>''', 'html.parser')
    for i, tag in enumerate(aside.select('h2,h3,p,strong,a,img'), start=1):
        # Keep container paragraphs that contain a strong tag out of the editor.
        if tag.name == 'p' and tag.find('strong'): continue
        tag['data-om-slot'] = f'valutazione-ripristino-{i:03}'
        tag['data-om-type'] = 'image' if tag.name == 'img' else 'button' if tag.name == 'a' else 'text'
        tag['data-om-role'] = 'admin'
        tag['data-om-label'] = tag.get('alt', tag.get_text())[:80]
        tag['data-om-max'] = '5000'
    main.append(aside.aside)
    p.write_text(str(soup), encoding='utf-8')
    content_path = root / 'content' / f'valutazione.{lang}.json'
    c = json.loads(content_path.read_text(encoding='utf-8-sig'))
    for tag in soup.select('[data-om-slot]'):
        slot = tag['data-om-slot']
        if slot not in c['slots']:
            c['slots'][slot] = {'src': tag['src'], 'alt': tag['alt']} if tag.name == 'img' else {'href': tag['href'], 'label': tag.get_text()} if tag.name == 'a' else tag.get_text()
    c['slots']['valutazione-contenuto-030'] = tr('Scatta o carica una foto', 'Take or upload a photograph')
    content_path.write_text(json.dumps(c, ensure_ascii=False, indent=2), encoding='utf-8')
    layout = root / 'partials' / f'valutazione.{lang}.html'
    text = layout.read_text(encoding='utf-8-sig').replace('</head>', '<link rel="stylesheet" href="../../assets/quote.css"></head>')
    doc = BeautifulSoup(text, 'html.parser')
    header = doc.find('header')
    header['class'] = ['quote-header']
    brand = header.find('a')
    brand['class'] = ['quote-brand']
    subtitle = doc.new_tag('small'); subtitle.string = 'by La Diamanteria'; brand.append(subtitle)
    tel = doc.new_tag('a', href='tel:+3902466891', attrs={'class': 'quote-phone'}); tel.string = '+39 02 466 891'; header.append(tel)
    layout.write_text(str(doc), encoding='utf-8')
