from pathlib import Path
import tempfile,shutil,subprocess,os,json,re,base64,zlib,struct
r=Path('.').resolve()
with tempfile.TemporaryDirectory(prefix='image-check-',dir=r/'tools') as folder:
 t=Path(folder).resolve();assert t.is_relative_to(r/'tools')
 for d in ['PHP di sistema','api','content','partials','pages-src']:shutil.copytree(r/d,t/d)
 (t/'data/sessions').mkdir(parents=True);(t/'uploads').mkdir()
 php='C:/laragon/bin/php/php-8.3.16-Win32-vs16-x64/'
 subprocess.run([php+'php.exe','-r',"require 'PHP di sistema/core.php';db()->prepare('INSERT INTO users(username,password_hash,role,pages) VALUES(?,?,?,?)')->execute(['testadmin',password_hash('Fixture-password-123',PASSWORD_DEFAULT),'admin',json_encode(PAGE_SLUGS)]);"],cwd=t,check=True,capture_output=True)
 cookie=''
 def call(action,body=b'',kind='application/json',csrf=''):
  global cookie
  env={**os.environ,'REDIRECT_STATUS':'1','REQUEST_METHOD':'POST' if body else 'GET','SCRIPT_FILENAME':str(t/'api/index.php'),'SCRIPT_NAME':'/api/index.php','REQUEST_URI':'/api/index.php?action='+action,'QUERY_STRING':'action='+action,'SERVER_NAME':'localhost','SERVER_PORT':'80','GATEWAY_INTERFACE':'CGI/1.1','SERVER_PROTOCOL':'HTTP/1.1','REMOTE_ADDR':'192.0.2.66','CONTENT_TYPE':kind if body else '','CONTENT_LENGTH':str(len(body)),'HTTP_COOKIE':cookie,'HTTP_X_CSRF_TOKEN':csrf}
  out=subprocess.run([php+'php-cgi.exe'],env=env,cwd=t,input=body,capture_output=True,check=True).stdout.decode();headers,payload=out.split('\r\n\r\n',1);m=re.search(r'Set-Cookie: (PHPSESSID=[^;\r\n]+)',headers)
  if m:cookie=m[1]
  status=re.search(r'Status: (\d+)',headers);return int(status[1]) if status else 200,json.loads(payload)
 _,ses=call('session');assert 'csrf' in ses,ses;_,login=call('login',json.dumps({'username':'testadmin','password':'Fixture-password-123'}).encode(),csrf=ses['csrf']);csrf=login['csrf']
 def chunk(tag,b):return struct.pack('!I',len(b))+tag+b+struct.pack('!I',zlib.crc32(tag+b)&0xffffffff)
 png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x00\x00'))+chunk(b'IEND',b'')
 boundary='testboundary';slot=next(k for k,v in json.loads((t/'content/home.it.json').read_text(encoding='utf-8-sig'))['slots'].items() if isinstance(v,dict) and 'src' in v)
 body=(f'--{boundary}\r\nContent-Disposition: form-data; name="slot"\r\n\r\n{slot}\r\n--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+png+f'\r\n--{boundary}--\r\n'.encode())
 status,result=call('upload-image&slug=home&lang=it',body,'multipart/form-data; boundary='+boundary,csrf);assert status==201,(status,result);assert (t/result['url'].lstrip('/')).is_file();assert len(list((t/'uploads/site').glob('*.png')))==1
 status,_=call('upload-image&slug=home&lang=it',body,'multipart/form-data; boundary='+boundary,'wrong');assert status==403
 print('Upload multipart autenticato verificato: PNG salvata in uploads/site; CSRF errato rifiutato.')


