from pathlib import Path
from html.parser import HTMLParser
import json,xml.etree.ElementTree as ET,urllib.request
r=Path.cwd()
class Page(HTMLParser):
 def __init__(self): super().__init__();self.schema=[];self.capture=False;self.buf='';self.h1=0;self.canon=0;self.robots=[]
 def handle_starttag(self,t,a):
  a=dict(a)
  if t=='h1':self.h1+=1
  if t=='link' and a.get('rel')=='canonical':self.canon+=1
  if t=='meta' and a.get('name')=='robots':self.robots.append(a.get('content'))
  if t=='script' and a.get('type')=='application/ld+json':self.capture=True;self.buf=''
 def handle_data(self,d):
  if self.capture:self.buf+=d
 def handle_endtag(self,t):
  if t=='script' and self.capture:self.schema.append(json.loads(self.buf));self.capture=False
for file in list((r/'it').glob('*.html'))+list((r/'en').glob('*.html')):
 p=Page();p.feed(file.read_text(encoding='utf-8'));assert len(p.schema)==1 and p.canon==1,(file,p.canon);assert p.h1==1,(file,p.h1)
 if file.stem=='faq':
  fa=p.schema[0]['@graph'][2];assert fa['@type']=='FAQPage' and fa['mainEntity'];print(file.parent.name,'FAQ',len(fa['mainEntity']))
 if file.stem!='grazie':assert not any('noindex' in x for x in p.robots),file
xml=ET.parse(r/'sitemap.xml');urls=[x.text for x in xml.findall('.//{*}loc')];assert len(urls)==16 and len(set(urls))==16;assert all(x.startswith('https://comprodiamanti.piuper.site/') for x in urls);print('Sitemap:',len(urls),'URL pubblici')
for base in ['http://compro-diamanti.test','https://comprodiamanti.piuper.site']:
 for path in ['/it/index.html','/it/faq.html','/blog/','/robots.txt','/sitemap.xml']:
  try:
   response=urllib.request.urlopen(base+path,timeout=12);data=response.read().decode('utf-8');assert 'noindex' not in response.headers.get('X-Robots-Tag','');print(base+path,response.status,'X-Robots:',response.headers.get('X-Robots-Tag','assente'))
   if path=='/blog/':p=Page();p.feed(data);assert p.schema[0]['@graph'][2]['@type']=='Blog'
  except (urllib.error.URLError,TimeoutError) as e:print('Non raggiungibile:',base,str(e));break
print('Controlli completati')
