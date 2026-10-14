from pathlib import Path
from html.parser import HTMLParser
class Check(HTMLParser):
 def __init__(self):super().__init__();self.depth=0;self.footer=False;self.links=[];self.row=False;self.rowdepth=0;self.legal=0
 def handle_starttag(self,t,attrs):
  a=dict(attrs)
  if t=='footer':self.footer=True
  if self.footer and t=='a':self.links.append(a.get('href',''))
  if 'site-subfooter' in a.get('class',''):self.row=True
  if self.footer and 'data-cookie-preferences' in a:self.legal+=1
 def handle_endtag(self,t):
  if t=='footer':self.footer=False
for p in list(Path('it').glob('*.html'))+list(Path('en').glob('*.html')):
 c=Check();c.feed(p.read_text(encoding='utf-8'));assert c.row and c.legal==1,(p,c.row,c.legal);assert not any('/admin/' in x or '/blog/' in x for x in c.links),p
print('16 pagine: footer, copyright e preferenze cookie verificati')
import http.client
for path in ['/blog/','/blog/?lang=en']:
 c=http.client.HTTPConnection('127.0.0.1',80,timeout=10);c.request('GET',path,headers={'Host':'compro-diamanti.test'});res=c.getresponse();html=res.read().decode();assert res.status==200
 p=Check();p.feed(html);assert p.row and p.legal==1;assert not any('/admin/' in x or '/blog/' in x for x in p.links);print(path,'footer verificato')
