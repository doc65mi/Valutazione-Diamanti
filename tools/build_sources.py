import json,re,html,sys
from pathlib import Path
from convert import Node,Parser
if any(Path('content').glob('*.json')) and '--force-bootstrap' not in sys.argv:
    raise SystemExit('Bootstrap gia eseguito. Usa install.php per rigenerare senza perdere i contenuti. --force-bootstrap sovrascrive i JSON.')

def repair(s):
    def fix(m):
        try:return m[0].encode('cp1252').decode('utf8')
        except UnicodeError:return m[0]
    return re.sub(r'(?:Ã.|Â.|â..|ð...)+',fix,s)

translations={}
for line in Path('tools/translations.txt').read_text(encoding='utf-8').splitlines():
    if '|' in line:
        it,en=line.split('|',1);translations[it]=en
untranslated=set()
def translate(s,lang):
    s=repair(s)
    if lang=='it' or not s.strip(): return s
    stripped=s.strip()
    if stripped in translations:return s.replace(stripped,translations[stripped])
    if re.search(r'\b(?:il|la|di|con|per|una|gli|che|del|da|sono)\b',stripped,re.I):untranslated.add(stripped)
    return s

void=['img','input','meta','link','br','hr','source']
def prepare(slug,lang,body):
    p=Parser();p.feed(body);slots={};counter=0
    def visit(n):
        nonlocal counter
        if isinstance(n,str):return translate(n,lang)
        n.attrs={k:repair(v or '') for k,v in n.attrs.items()}
        if n.tag=='img':
            counter+=1;sid=f'{slug}-contenuto-{counter:03}';n.attrs.update({'data-om-slot':sid,'data-om-type':'image','data-om-label':translate(n.attrs.get('alt','Immagine'),lang)})
            slots[sid]={'src':n.attrs['src'],'alt':translate(n.attrs.get('alt','Immagine'),lang)}
        if n.tag=='a':
            href=n.attrs.get('href','')
            if href in ['Login.dc.html','Admin.dc.html']:n.attrs['href']='../../admin/'
            if lang=='en' and 'wa.me/' in href and '?text=' in href:n.attrs['href']=href.split('?')[0]+'?text=Hello%2C%20I%20would%20like%20a%20free%20valuation%20of%20a%20diamond%2C%20a%20piece%20of%20jewellery%20or%20a%20watch.'
        for k in ['alt','aria-label','placeholder']: 
            if k in n.attrs:n.attrs[k]=translate(n.attrs[k],lang)
        if n.tag in ['script','style','select','option','input','textarea']:return n
        if n.children and all(isinstance(c,str) for c in n.children) and ''.join(n.children).strip():
            original=''.join(n.children);value=translate(original,lang).strip();counter+=1;sid=f'{slug}-contenuto-{counter:03}'
            n.attrs.update({'data-om-slot':sid,'data-om-type':'text','data-om-label':value[:90],'data-om-max':'5000'})
            # Simple links have editable labels and destinations.
            if n.tag=='a':n.attrs['data-om-type']='button';slots[sid]={'label':value,'href':n.attrs.get('href','#')}
            else:slots[sid]=value
            n.children=[value]
        else:
            children=[]
            for c in n.children:
                if isinstance(c,str) and c.strip():
                    leading=re.match(r'^\s*',c)[0];trailing=re.search(r'\s*$',c)[0]
                    wrapper=Node('span');wrapper.children=[c.strip()];children.extend([leading,visit(wrapper),trailing])
                else:children.append(visit(c))
            n.children=children
        return n
    def serialize(n):
        if isinstance(n,str):return html.escape(n,quote=False)
        if not n.tag:return ''.join(serialize(c) for c in n.children)
        return '<'+n.tag+''.join(' '+k+'="'+html.escape(v,quote=True)+'"' for k,v in n.attrs.items())+'>'+''.join(serialize(c) for c in n.children)+('' if n.tag in void else '</'+n.tag+'>')
    body=serialize(visit(p.root))
    name='index' if slug=='home' else slug
    titles={'home':('Vendi diamanti e gioielli a Milano | ComproDiamanti','Sell diamonds and jewellery in Milan | ComproDiamanti'),'acquistiamo':('Cosa acquistiamo | ComproDiamanti','What we buy | ComproDiamanti'),'chisiamo':('Chi siamo | La Diamanteria','About us | La Diamanteria'),'faq':('Domande frequenti | ComproDiamanti','Frequently asked questions | ComproDiamanti'),'valutazione':('Valutazione gratuita | ComproDiamanti','Free valuation | ComproDiamanti'),'grazie':('Grazie per la richiesta | ComproDiamanti','Thank you for your enquiry | ComproDiamanti'),'privacy':('Informativa privacy | ComproDiamanti','Privacy policy | ComproDiamanti'),'cookie':('Informativa cookie | ComproDiamanti','Cookie policy | ComproDiamanti')}
    title=titles[slug][lang=='en'];description=('Acquisto diretto e valutazione gratuita di diamanti, gioielli e orologi a Milano. La Diamanteria, dal 2009.' if lang=='it' else 'Free valuations and direct buying of diamonds, jewellery and luxury watches in Milan. La Diamanteria, established in 2009.')
    language_nav=f'<nav class="language-nav" aria-label="'+('Lingua' if lang=='it' else 'Language')+f'"><a href="../it/{name}.html" lang="it" hreflang="it"'+(' aria-current="page"' if lang=='it' else '')+f'>ITA</a><a href="../en/{name}.html" lang="en" hreflang="en"'+(' aria-current="page"' if lang=='en' else '')+'>ENG</a></nav>'
    script='form.js' if slug=='valutazione' else 'site.js'
    legal_footer='<footer class="legal-footer"><a href="privacy.html">Privacy</a> · <a href="cookie.html">Cookie</a> · <button type="button" data-cookie-preferences>'+('Preferenze cookie' if lang=='it' else 'Cookie preferences')+'</button></footer>'
    if slug=='valutazione':body=body.replace('<details><summary>','<p><a href="privacy.html">'+('Leggi l’informativa privacy completa' if lang=='it' else 'Read the full privacy policy')+'</a></p><details><summary>',1)
    doc=f'<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{html.escape(title)}</title><meta name="description" content="{description}"><link rel="alternate" hreflang="it" href="../it/{name}.html"><link rel="alternate" hreflang="en" href="../en/{name}.html"><link rel="alternate" hreflang="x-default" href="../it/{name}.html"><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Manrope:wght@400;500;600;700&family=Roboto+Condensed:wght@400;500&display=swap" rel="stylesheet"><link rel="stylesheet" href="../../assets/site.css"><script src="../../assets/consent.js" defer></script><script src="../../assets/{script}" defer></script>'+('<meta name="robots" content="noindex, follow">' if slug=='grazie' else '')+f'</head><body class="public-page" data-page="{slug}">{body}{language_nav}{legal_footer}</body></html>'
    # Main fragment contract: layout currently kept alongside the annotated fragment for direct DOM generation.
    doc=doc.replace('<main>','<main data-om-page="'+slug+'" data-om-spec="1.00">',1).replace('<main class="quote-main"','<main data-om-page="'+slug+'" data-om-spec="1.00" class="quote-main"',1)
    # The editable source is a main fragment; the document shell is a private layout.
    doc=re.sub(r'<main(?:\s[^>]*)?>',lambda m:m[0] if 'data-om-page=' in m[0] else m[0][:-1]+f' data-om-page="{slug}" data-om-spec="1.00">',doc,count=1)
    match=re.search(r'<main\b.*?</main>',doc,re.S)
    fragment=match[0]
    layout=doc[:match.start()]+'{{content}}'+doc[match.end():]
    layout=re.sub(r'data-om-slot="([^"]+)"',r'data-om-slot="\1" data-om-role="admin"',layout)
    Path('partials').mkdir(exist_ok=True)
    Path('pages-src/'+slug+'.'+lang+'.html').write_text(fragment,encoding='utf-8')
    Path('partials/'+slug+'.'+lang+'.html').write_text(layout,encoding='utf-8')
    c={'spec':'1.00','slug':slug,'language':lang,'version':1,'meta':{'title':title,'description':description},'slots':slots}
    Path('content/'+slug+'.'+lang+'.json').write_text(json.dumps(c,ensure_ascii=False,indent=2),encoding='utf-8')
for slug in ['home','acquistiamo','chisiamo','faq','valutazione','grazie','privacy','cookie']:
    for lang in ['it','en']:
        body=Path('tools/'+slug+('.'+lang if slug in ['valutazione','grazie','privacy','cookie'] else '')+'.html').read_text(encoding='utf-8')
        prepare(slug,lang,body)
print('Testi da verificare:',json.dumps(sorted(untranslated),ensure_ascii=True))
