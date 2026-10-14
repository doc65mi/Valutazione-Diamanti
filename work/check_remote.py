import urllib.request
from html.parser import HTMLParser
for path in ['/it/index.html','/it/faq.html','/blog/','/robots.txt','/sitemap.xml']:
 res=urllib.request.urlopen('https://comprodiamanti.piuper.site'+path,timeout=15);html=res.read().decode('utf-8');print(path,res.status,'X-Robots:',res.headers.get('X-Robots-Tag','assente'))
 if path.endswith('.txt') or path.endswith('.xml'):print(html[:2500])
 else:
  class P(HTMLParser):
   def handle_starttag(self,t,a):
    a=dict(a)
    if t=='meta' and a.get('name','').lower() in ['robots','googlebot']:print(a)
  P().feed(html)
