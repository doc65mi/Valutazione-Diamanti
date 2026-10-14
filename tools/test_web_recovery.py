from pathlib import Path
import tempfile,shutil,subprocess,os,re,json,urllib.parse,sqlite3
root=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='web-reset-',dir=root/'tools') as directory:
    target=Path(directory).resolve();assert target.is_relative_to(root/'tools')
    shutil.copytree(root/'PHP di sistema',target/'PHP di sistema');(target/'admin').mkdir();shutil.copyfile(root/'admin/reset-password.php',target/'admin/reset-password.php');(target/'data/sessions').mkdir(parents=True)
    cookie=''
    def request(fields=None):
        global cookie
        body=urllib.parse.urlencode(fields or {}).encode()
        env={**os.environ,'REDIRECT_STATUS':'1','REQUEST_METHOD':'POST' if fields else 'GET','SCRIPT_FILENAME':str(target/'admin/reset-password.php'),'SCRIPT_NAME':'/admin/reset-password.php','REQUEST_URI':'/admin/reset-password.php','SERVER_NAME':'localhost','SERVER_PORT':'80','GATEWAY_INTERFACE':'CGI/1.1','SERVER_PROTOCOL':'HTTP/1.1','REMOTE_ADDR':'192.0.2.33','CONTENT_TYPE':'application/x-www-form-urlencoded','CONTENT_LENGTH':str(len(body)),'HTTP_COOKIE':cookie}
        result=subprocess.run(['C:/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php-cgi.exe'],env=env,cwd=target,input=body,capture_output=True,check=True)
        output=result.stdout.decode('utf-8');match=re.search(r'Set-Cookie: (PHPSESSID=[^;\r\n]+)',output)
        if match:cookie=match[1]
        return output
    page=request();assert 'noindex' in page
    csrf=re.search(r'name="csrf" value="([^"]+)"',page)[1]
    token=json.loads((target/'data/admin-recovery.json').read_text())['code']
    bad=request({'csrf':csrf,'code':'bad','password':'New-safe-password-123','repeat':'New-safe-password-123'});assert 'Codice di recupero non valido' in bad
    wrongcsrf=request({'csrf':'bad','code':token,'password':'New-safe-password-123','repeat':'New-safe-password-123'});assert 'Sessione scaduta' in wrongcsrf
    good=request({'csrf':csrf,'code':token,'password':'New-safe-password-123','repeat':'New-safe-password-123'});assert 'Password aggiornata' in good
    assert not (target/'data/admin-recovery.json').exists();assert (target/'data/admin-recovery-disabled').exists()
    assert 'recupero via browser è disabilitato' in request()
    db=sqlite3.connect(target/'data/site.sqlite');assert db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1;db.close()
    print('Reset browser verificato: noindex, CSRF, codice errato, successo e blocco riutilizzo.')
