import http.client
for path in ['/blog/','/blog/?lang=en','/it/index.html','/en/index.html']:
 c=http.client.HTTPConnection('127.0.0.1',80,timeout=10);c.request('GET',path,headers={'Host':'compro-diamanti.test'});res=c.getresponse();html=res.read().decode('utf-8');assert res.status==200 and 'instagram' not in html.lower(),path
print('Pagine e blog IT/EN verificati: nessun Instagram')
