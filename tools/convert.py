import json,re,html
from pathlib import Path
from html.parser import HTMLParser

class Node:
    def __init__(self,tag='',attrs=None): self.tag=tag; self.attrs=dict(attrs or []); self.children=[]
class Parser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.root=Node(); self.stack=[self.root]
    def handle_starttag(self,tag,attrs):
        n=Node(tag,attrs); self.stack[-1].children.append(n)
        if tag not in ['img','input','meta','link','br','hr','source']: self.stack.append(n)
    def handle_endtag(self,tag):
        for i in range(len(self.stack)-1,0,-1):
            if self.stack[i].tag==tag: self.stack=self.stack[:i]; break
    def handle_data(self,data): self.stack[-1].children.append(data)

def get(ctx,key):
    v=ctx
    for part in key.strip().split('.'): v=v.get(part,'') if isinstance(v,dict) else ''
    return v
def subst(s,ctx): return re.sub(r'\{\{(.*?)\}\}',lambda m: str(get(ctx,m[1])).lower() if isinstance(get(ctx,m[1]),bool) else str(get(ctx,m[1])),s)
def render(n,ctx):
    if isinstance(n,str): return html.escape(subst(n,ctx),quote=False)
    if n.tag=='helmet': return ''
    if n.tag=='sc-if': return ''.join(render(c,ctx) for c in n.children) if get(ctx,n.attrs.get('value','').strip('{} ')) else ''
    if n.tag=='sc-for':
        arr=get(ctx,n.attrs.get('list','').strip('{} ')) or []
        return ''.join(''.join(render(c,{**ctx,n.attrs['as']:v}) for c in n.children) for v in arr)
    attrs={k:subst(v or '',ctx) for k,v in n.attrs.items() if not k.startswith(('on','hint-','style-'))}
    # Real links replace prototype click handlers.
    handler=n.attrs.get('onclick','').strip('{} ')
    routes={'goHome':'index.html','goAcq':'acquistiamo.html','goFaq':'faq.html','startBlank':'valutazione.html'}
    tag=n.tag
    if handler=='n.go':
        label=get(ctx,'n.label'); attrs['href']={'Vendere un diamante':'acquistiamo.html','Come funziona':'index.html#come-funziona','Chi siamo':'chisiamo.html','FAQ':'faq.html'}.get(label,'index.html'); tag='a'
    elif handler in routes: attrs['href']=routes[handler]; tag='a'
    elif handler.endswith('.start'): attrs['href']='valutazione.html'; tag='a'
    elif handler in ['heroPrev','heroNext','hs.go']: return ''
    elif handler=='q.toggle': attrs['data-faq-toggle']=''; attrs['aria-expanded']='true'
    if tag=='img': attrs['loading']='lazy'; attrs['decoding']='async'
    out=''.join(render(c,ctx) for c in n.children)
    if tag=='': return out
    return '<'+tag+''.join(' '+k+'="'+html.escape(v,quote=True)+'"' for k,v in attrs.items())+'>'+out+('' if tag in ['img','input','meta','link','br','hr'] else '</'+tag+'>')

source=(Path('tools/originals/ComproDiamanti.dc.html') if Path('tools/originals/ComproDiamanti.dc.html').exists() else Path('ComproDiamanti.dc.html')).read_text(encoding='utf-8')
p=Parser(); p.feed(re.search(r'<x-dc>(.*?)</x-dc>',source,re.S)[1])
values=json.loads(Path('tools/snapshots.json').read_text())
texts=set()
for slug,ctx in values.items():
    # All FAQ answers must be in public HTML, with progressive disclosure handled by JS.
    for key in ['faqs','faqsAll']:
        for q in ctx[key]: q['open']=True; q['sign']='−'
    body=render(p.root,ctx)
    parsed=Parser(); parsed.feed(body)
    def collect(n):
        if isinstance(n,str):
            if n.strip(): texts.add(n.strip())
        else:
            for c in n.children: collect(c)
            for a in ['alt','aria-label','placeholder']:
                if n.attrs.get(a): texts.add(n.attrs[a])
    collect(parsed.root)
    Path('tools/'+slug+'.html').write_text(body,encoding='utf-8')
Path('tools/texts.json').write_text(json.dumps(sorted(texts),ensure_ascii=False,indent=2),encoding='utf-8')
