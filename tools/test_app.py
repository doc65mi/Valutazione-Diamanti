"""Integration checks using PHP CGI, including real multipart upload handling.
No network listener or external service keys are required.
"""
import subprocess,os,json,re,uuid,sqlite3,base64,zlib,struct
from pathlib import Path
from urllib.parse import urlencode

ROOT=Path(__file__).resolve().parents[1]
PHP=Path(os.environ.get('CD_PHP_DIR',r'C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64'))/'php-cgi.exe'
created=[];users=[];backups={}
with sqlite3.connect(ROOT/'data/site.sqlite') as initial_db:
    initial_settings=initial_db.execute('SELECT value FROM settings WHERE id=1').fetchone()
class Client:
    def __init__(self):self.cookie='';self.csrf='';self.ip='192.0.2.'+str(10+len(created))
    def call(self,action,body=None,params=None,csrf=True,multipart=None):
        query=urlencode({'action':action,**(params or {})});raw=b'';ct=''
        if multipart is not None:
            data,files=multipart;boundary='----Test'+uuid.uuid4().hex;parts=[]
            for key,val in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{val}\r\n'.encode())
            for filename,mime,blob in files:parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photos[]"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+blob+b'\r\n')
            raw=b''.join(parts)+f'--{boundary}--\r\n'.encode();ct='multipart/form-data; boundary='+boundary
        elif body is not None:raw=json.dumps(body,ensure_ascii=False).encode();ct='application/json'
        env={**os.environ,'REDIRECT_STATUS':'1','GATEWAY_INTERFACE':'CGI/1.1','REQUEST_METHOD':'POST' if body is not None or multipart is not None else 'GET','SCRIPT_FILENAME':str(ROOT/'api/index.php'),'SCRIPT_NAME':'/api/index.php','REQUEST_URI':'/api/index.php?'+query,'QUERY_STRING':query,'SERVER_NAME':'localhost','SERVER_PORT':'80','SERVER_PROTOCOL':'HTTP/1.1','REMOTE_ADDR':self.ip,'CONTENT_TYPE':ct,'CONTENT_LENGTH':str(len(raw)),'HTTP_COOKIE':self.cookie,'HTTP_X_CSRF_TOKEN':self.csrf if csrf else ''}
        result=subprocess.run([str(PHP)],input=raw,env=env,cwd=ROOT,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=30)
        assert result.returncode==0,result.stderr.decode(errors='replace')
        headers,payload=result.stdout.split(b'\r\n\r\n',1);headers=headers.decode();status=re.search(r'Status: (\d+)',headers);status=int(status[1]) if status else 200
        if status>=500:print(result.stderr.decode(errors='replace'))
        cookie=re.search(r'Set-Cookie: (PHPSESSID=[^;\r\n]+)',headers)
        if cookie:self.cookie=cookie[1]
        try:out=json.loads(payload)
        except ValueError:out=payload
        return status,out,headers
    def get_session(self):s,d,_=self.call('session');assert s==200;self.csrf=d['csrf']
    def login(self,name,password):self.get_session();s,d,h=self.call('login',{'username':name,'password':password});assert s==200,(s,d);self.csrf=d['csrf'];return h

def check(condition,label):assert condition,label;print('PASS',label[0] if isinstance(label,tuple) else label)
try:
    anonymous=Client();anonymous.get_session()
    s,d,_=anonymous.call('requests');check(s==401,'admin data blocked without authentication')
    s,d,_=anonymous.call('login',{'username':'admin','password':'incorrect'});check(s==401,'incorrect credentials rejected')
    s,d,_=anonymous.call('request',multipart=({'language':'it','request_key':uuid.uuid4().hex},[]),csrf=False);check(s==403,'CSRF required for public form')
    password=re.search(r'Password: (.+)',(ROOT/'data/first-login.txt').read_text())[1]
    admin=Client();h=admin.login('admin',password);check('HttpOnly' in h and 'SameSite=Strict' in h,'server login uses protected session cookie')
    s,d,h=admin.call('me');check(d['user']['role']=='admin' and 'noindex, nofollow' in h,'admin noindex HTTP header')
    s,c,_=anonymous.call('config');check('recaptcha_secret' not in c and not c['analytics_enabled'],'public config excludes secret and defaults tracking off')
    data={'language':'en','request_key':uuid.uuid4().hex,'cat':'solitaire','multi':'no','ct':'1-2','cert':'GIA','name':'Integration Test','phone':'+39 333 1234567','email':'test@example.invalid','pref':'email','consent':'1'}
    bad={**data,'email':'not-an-email'};s,d,_=anonymous.call('request',multipart=(bad,[]));check(s==422,'invalid email rejected on server')
    bad={**data,'pref':'WhatsApp','phone':''};s,d,_=anonymous.call('request',multipart=(bad,[]));check(s==422,'preferred contact must have matching contact details')
    bad={**data,'cert':'AIG'};s,d,_=anonymous.call('request',multipart=(bad,[]));check(s==422,'ineligible certificate rejected')
    bad={**data,'origin':'lab-grown'};s,d,_=anonymous.call('request',multipart=(bad,[]));check(s==422,'laboratory-grown diamond rejected')
    s,d,_=anonymous.call('request',multipart=(data,[('evil.php','image/jpeg',b'<?php echo "bad";')]));check(s==422,'spoofed upload MIME rejected')
    def chunk(kind,data):return struct.pack('>I',len(data))+kind+data+struct.pack('>I',zlib.crc32(kind+data)&0xffffffff)
    png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x00\x00'))+chunk(b'IEND',b'')
    pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'
    s,d,_=anonymous.call('request',multipart=(data,[('photo.png','image/png',png),('certificate.pdf','application/pdf',pdf)]));check(s==201,(s,d));rid=d['id'];created.append(rid)
    s,d,_=anonymous.call('request',multipart=(data,[]));check(s==200 and d['id']==rid,'retry is idempotent')
    s,d,_=admin.call('requests');row=next(r for r in d['requests'] if r['id']==rid);check(len(row['attachments'])==2,'admin receives request and both attachments')
    image=next(f for f in row['attachments'] if f['mime']=='image/png');document=next(f for f in row['attachments'] if f['mime']=='application/pdf')
    for f in row['attachments']:check((ROOT/'uploads/clienti'/f['id']).exists(),'attachment stored in uploads/clienti')
    s,d,_=anonymous.call('attachment',params={'id':image['id'],'view':'preview'});check(s==401,'attachment preview blocked for unauthenticated visitors')
    s,d,h=admin.call('attachment',params={'id':image['id'],'view':'thumb'});check(s==200 and d.startswith(b'\x89PNG') and 'inline' in h,'image thumbnail served through authenticated endpoint')
    s,d,h=admin.call('attachment',params={'id':document['id'],'view':'preview'});check(s==200 and d.startswith(b'%PDF') and 'inline' in h,'certificate PDF can be previewed')
    s,d,_=admin.call('status',{'id':rid,'status':'Contattato'});check(s==200,'request status saved')
    params={'slug':'faq','lang':'en'};s,d,_=admin.call('page',params=params);old=d['content'];schema=d['schema'];backups['faq.en']=old
    edited=json.loads(json.dumps(old));edited['base_version']=old['version'];sid=next(k for k,v in schema.items() if v['type']=='text' and v['role']!='admin');edited['slots'][sid]='Edited text <script>test</script>'
    s,d,_=admin.call('save',edited,params);check(s==200,('content editing and static publication succeed',s,d))
    output=(ROOT/'en/faq.html').read_text();check('Edited text test' in output and 'data-om-' not in output,'published text sanitised and OM markup removed')
    s,d,_=admin.call('save',edited,params);check(s==409,'stale content version rejected')
    s,d,_=admin.call('revisions',params=params);revision=d['revisions'][0]['id']
    s,d,_=admin.call('restore',{'revision':revision,'base_version':old['version']+1},params);check(s==200 and d['content']['slots']==old['slots'],'revision restores public content')
    bad_edit=json.loads(json.dumps(d['content']));bad_edit['base_version']=d['content']['version'];button=next(k for k,v in schema.items() if v['type']=='button');bad_edit['slots'][button]['href']='javascript:alert(1)'
    s,d,_=admin.call('save',bad_edit,params);check(s==422,'unsafe editable link rejected')
    username='test_'+uuid.uuid4().hex[:10];users.append(username);s,d,_=admin.call('users',{'username':username,'password':'Test-only-password-123','role':'client','pages':['faq']});check(s==200,'assigned-page client user created')
    client=Client();client.login(username,'Test-only-password-123');s,d,_=client.call('page',params={'slug':'home','lang':'it'});check(s==403,'client cannot access unassigned page');s,d,_=client.call('requests');check(s==403,'client cannot access customer requests');s,d,_=client.call('settings');check(s==403,'client cannot access tracking secrets or settings')
    s,d,_=client.call('page',params=params);edit=d['content'];edit['base_version']=edit['version'];private_slot=next(k for k,v in d['schema'].items() if v['role']=='admin' and v['type']=='text');edit['slots'][private_slot]='Denied';s,d,_=client.call('save',edit,params);check(s==403,'client cannot change admin-only layout fields')
    s,d,_=admin.call('settings');config=d['settings'];config['recaptcha_enabled']=True;config['recaptcha_site_key']='test-site-key-123456';config['recaptcha_secret']='test-secret-key-123456';config['recaptcha_hostname']='localhost';s,d,_=admin.call('settings',config);check(s==200,'reCAPTCHA configuration saved')
    s,d,_=anonymous.call('request',multipart=({**data,'request_key':uuid.uuid4().hex},[]));check(s==422,'enabled reCAPTCHA fails closed when token is missing')
    config['recaptcha_enabled']=False;config['recaptcha_clear_secret']=True;config['recaptcha_secret']='';s,d,_=admin.call('settings',config);check(s==200,'test integration settings reset')
    s,d,_=admin.call('logout',{});check(s==200,'logout succeeds');s,d,_=admin.call('requests');check(s==401,'logout invalidates admin access')
    for file in list((ROOT/'it').glob('*.html')) + list((ROOT/'en').glob('*.html')):
        source=file.read_text();check('data-om-' not in source and '<x-dc>' not in source,file.relative_to(ROOT).as_posix()+' is static')
    print('All integration checks passed.')
finally:
    db=sqlite3.connect(ROOT/'data/site.sqlite')
    if initial_settings:db.execute('UPDATE settings SET value=? WHERE id=1',initial_settings)
    else:db.execute('DELETE FROM settings WHERE id=1')
    for rid in created:
        for (path,) in db.execute('SELECT path FROM attachments WHERE request_id=?',(rid,)):
            for suffix in ['','-thumb.png']:
                file=ROOT/'uploads/clienti'/(path+suffix)
                if file.exists():file.unlink()
        db.execute('DELETE FROM mail_queue WHERE request_id=?',(rid,));db.execute('DELETE FROM attachments WHERE request_id=?',(rid,));db.execute('DELETE FROM requests WHERE id=?',(rid,));db.execute('DELETE FROM audit WHERE target=?',(rid,))
    for name in users:
        row=db.execute('SELECT id FROM users WHERE username=?',(name,)).fetchone()
        if row:db.execute('DELETE FROM audit WHERE user_id=?',(row[0],));db.execute('DELETE FROM users WHERE id=?',(row[0],))
    for key,c in backups.items():
        slug,lang=key.split('.');(ROOT/'content'/(key+'.json')).write_text(json.dumps(c,ensure_ascii=False,indent=2),encoding='utf-8');db.execute('DELETE FROM revisions WHERE page=? AND language=? AND version>=?',(slug,lang,c['version']))
    db.execute('DELETE FROM rate_limits WHERE expires>?',(0,));db.commit();db.close()
    subprocess.run([str(PHP.with_name('php.exe')),str(ROOT/'PHP di sistema/install.php')],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
