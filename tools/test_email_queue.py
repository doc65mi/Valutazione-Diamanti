from pathlib import Path
import tempfile,shutil,subprocess
root=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='email-test-',dir=root/'tools') as folder:
    target=Path(folder).resolve();assert target.is_relative_to(root/'tools')
    (target/'PHP di sistema').mkdir();(target/'data').mkdir()
    for name in ['core.php','settings.php','emails.php']:
        shutil.copyfile(root/'PHP di sistema'/name,target/'PHP di sistema'/name)
    code=r'''<?php
require __DIR__.'/PHP di sistema/core.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$s=settings();$s['mail_enabled']=true;$s['mail_from']='sender@example.invalid';$s['mail_to']='manager@example.invalid';
db()->prepare('INSERT INTO settings(id,value) VALUES(1,?)')->execute([json_encode($s)]);
$f=['name'=>'Mario Rossi','email'=>'customer@example.invalid','phone'=>'123456789','pref'=>'email','cat'=>'watch','brand'=>'Rolex','stone'=>'','ct'=>'','cert'=>'','kit'=>'none','others'=>''];
$id='R-ABCDEF123456';db()->prepare('INSERT INTO requests(id,request_key,created_at,language,payload) VALUES(?,?,?,?,?)')->execute([$id,'test',gmdate('c'),'it',json_encode($f)]);
queue_request_emails($id,$f,'it',2);queue_request_emails($id,$f,'it',2);
check(db()->query('SELECT count(*) FROM mail_queue')->fetchColumn()==2,'two emails queued without duplicates');
$calls=[];send_queued_emails($id,function($to,$subject,$body,$headers)use(&$calls){$calls[]=$to;check(!str_contains($headers['From'],"\n"),'safe sender');return $to==='manager@example.invalid';});
check(count($calls)===2,'manager and customer dispatched');
check(db()->query('SELECT status FROM mail_queue WHERE kind="customer"')->fetchColumn()==='failed','failed email retained');
$calls=[];send_queued_emails($id,function($to,$subject,$body,$headers)use(&$calls){$calls[]=$to;check(str_contains(base64_decode($body),'gratuito e senza impegno'),'professional Italian customer acknowledgement');return true;});
check($calls===['customer@example.invalid'],'retry does not resend successful manager email');
check(db()->query('SELECT count(*) FROM mail_queue WHERE status="sent"')->fetchColumn()==2,'both accepted by test transport');
db()->exec('DELETE FROM requests');check(db()->query('SELECT count(*) FROM mail_queue')->fetchColumn()==0,'retention deletes queued personal data with request');
'''
    (target/'test.php').write_text(code,encoding='utf-8')
    result=subprocess.run(['C:/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php.exe',str(target/'test.php')],cwd=target,capture_output=True,text=True)
    print(result.stdout);assert result.returncode==0,result.stderr
