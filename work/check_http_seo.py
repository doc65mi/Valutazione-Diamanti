import http.client,json
exec(open('work/check_seo.py',encoding='utf-8-sig').read().split('for base in ')[0])
for path in ['/it/index.html','/it/faq.html','/blog/','/robots.txt','/sitemap.xml']:
 c=http.client.HTTPConnection('127.0.0.1',80,timeout=10);c.request('GET',path,headers={'Host':'compro-diamanti.test'});res=c.getresponse();body=res.read().decode('utf-8');assert res.status==200,(path,res.status);assert 'noindex' not in res.getheader('X-Robots-Tag','');print(path,res.status,'nessun blocco HTTP')
 if path=='/blog/':
  p=Page();p.feed(body);assert p.schema[0]['@graph'][2]['@type']=='Blog'
