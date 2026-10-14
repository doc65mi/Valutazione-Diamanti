<?php
declare(strict_types=1);
function settings_defaults(): array {
    return ['mail_enabled'=>false,'mail_from'=>'','mail_to'=>'','recaptcha_enabled'=>false,'recaptcha_site_key'=>'','recaptcha_secret'=>'','recaptcha_hostname'=>'','recaptcha_score'=>0.5,'analytics_enabled'=>false,'analytics_id'=>'','ads_enabled'=>false,'ads_id'=>'','ads_label'=>'','meta_enabled'=>false,'meta_id'=>'','gtm_enabled'=>false,'gtm_id'=>'','legal_company'=>'La Diamanteria','legal_address'=>'Via Vincenzo Monti 47, 20123 Milano','legal_vat'=>'','legal_email'=>'sl@ladiamanteria.com','retention_months'=>12,'site_url'=>'','consent_version'=>1];
}
function settings(): array {
    db()->exec('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK(id=1), value TEXT NOT NULL)');
    $v=db()->query('SELECT value FROM settings WHERE id=1')->fetchColumn();return array_replace(settings_defaults(),$v?json_decode($v,true):[]);
}
const TRACKING_LABELS = ['analytics'=>'Google Analytics 4','ads'=>'Google Ads','meta'=>'Meta Pixel','gtm'=>'Google Tag Manager'];
/** Ricava l'ID anche quando si incolla lo snippet completo o l'ID scritto in minuscolo (es. "aw-123/etichetta"). */
function settings_tracking_ids(array $input): array {
    $patterns=['analytics_id'=>'/\bG-[A-Z0-9]{4,}\b/i','gtm_id'=>'/\bGTM-[A-Z0-9]{4,}\b/i','ads_id'=>'~\bAW-[0-9]{4,}(?:/([A-Za-z0-9_-]{1,100}))?~i'];
    foreach($patterns as $key=>$pattern){
        $v=$input[$key]??'';if(!is_string($v)||strlen($v)>20000)continue;$v=trim($v);
        if($v!==''&&preg_match($pattern,$v,$m)){$input[$key]=strtoupper(explode('/',$m[0])[0]);if($key==='ads_id'&&!empty($m[1])&&trim((string)($input['ads_label']??''))==='')$input['ads_label']=$m[1];}
    }
    $meta=$input['meta_id']??'';
    if(is_string($meta)&&strlen($meta)<=20000&&preg_match('/fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"]?([0-9]{5,30})/',$meta,$m))$input['meta_id']=$m[1];
    return $input;
}
function settings_save(array $input): array {
    $input=settings_tracking_ids($input);
    $s=settings();
    if(isset($input['mail_enabled'])){if(!is_bool($input['mail_enabled']))fail('Selezione email non valida.');$s['mail_enabled']=$input['mail_enabled'];}
    foreach(['mail_from','mail_to'] as $key)if(isset($input[$key])){if(!is_string($input[$key])||strlen($input[$key])>254||strpbrk($input[$key],"\r\n"))fail('Indirizzo email non valido.');$s[$key]=trim($input[$key]);}
    foreach(['mail_from','mail_to'] as $key)if($s[$key]!==''&&!filter_var($s[$key],FILTER_VALIDATE_EMAIL))fail('Indirizzo email non valido: '.$key);
    if($s['mail_enabled']&&($s['mail_from']===''||$s['mail_to']===''))fail('Compila mittente e destinatario delle notifiche.');
    foreach(['analytics','ads','meta','gtm','recaptcha'] as $service){ $key=$service.'_enabled';if(!is_bool($input[$key]??null))fail('Selezione non valida: '.$service);$s[$key]=$input[$key]; }
    foreach(['recaptcha_site_key','recaptcha_hostname','analytics_id','ads_id','ads_label','meta_id','gtm_id','legal_company','legal_address','legal_vat','legal_email','site_url'] as $key){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>500||preg_match('/[<>\x00-\x1F]/',$v))fail(isset(TRACKING_LABELS[substr($key,0,-3)])?'ID non valido per '.TRACKING_LABELS[substr($key,0,-3)].': inserisci solo l’ID, non lo snippet di codice.':'Valore non valido: '.$key);$s[$key]=trim($v);}
    $secret=$input['recaptcha_secret']??'';if(!is_string($secret)||strlen($secret)>300)fail('Chiave segreta non valida.');if($secret!=='')$s['recaptcha_secret']=trim($secret);
    if(($input['recaptcha_clear_secret']??false)===true)$s['recaptcha_secret']='';
    foreach(['analytics_id'=>['/^G-[A-Z0-9]+$/','G-XXXXXXXXXX'],'ads_id'=>['/^AW-[0-9]+$/','AW-123456789'],'meta_id'=>['/^[0-9]{5,30}$/','solo cifre, es. 123456789012345'],'gtm_id'=>['/^GTM-[A-Z0-9]+$/','GTM-XXXXXXX']] as $key=>[$pattern,$example])if($s[$key]!==''&&!preg_match($pattern,$s[$key]))fail('ID non valido per '.TRACKING_LABELS[substr($key,0,-3)].': usa il formato '.$example.'.');
    if($s['ads_label']!==''&&!preg_match('/^[A-Za-z0-9_-]{1,100}$/',$s['ads_label']))fail('Etichetta conversione non valida.');
    if(!filter_var($s['legal_email'],FILTER_VALIDATE_EMAIL)||$s['legal_company']===''||$s['legal_address']==='')fail('Compila titolare, indirizzo e contatto privacy.');
    if($s['site_url']!==''&&(!filter_var($s['site_url'],FILTER_VALIDATE_URL)||!preg_match('~^https?://~',$s['site_url'])||strpbrk($s['site_url'],'?#')))fail('Inserisci l’URL pubblico del sito senza query o frammenti.');
    $score=$input['recaptcha_score']??0.5;if(!is_numeric($score)||(float)$score<0||(float)$score>1)fail('La soglia reCAPTCHA deve essere tra 0 e 1.');$s['recaptcha_score']=(float)$score;
    $months=$input['retention_months']??12;if(!is_int($months)||$months<1||$months>60)fail('Conservazione: da 1 a 60 mesi.');$s['retention_months']=$months;
    foreach(['recaptcha_site_key','recaptcha_secret'] as $key)if($s[$key]!==''&&!preg_match('/^[a-zA-Z0-9_-]{10,300}$/',$s[$key]))fail('Chiave reCAPTCHA non valida.');
    if($s['recaptcha_hostname']!==''&&!preg_match('/^[a-zA-Z0-9.-]+$/',$s['recaptcha_hostname']))fail('Inserisci il dominio reCAPTCHA senza protocollo o percorso.');
    if($s['recaptcha_enabled']&&(!$s['recaptcha_site_key']||!$s['recaptcha_secret']||!$s['recaptcha_hostname']))fail('Per attivare reCAPTCHA servono entrambe le chiavi e il dominio.');
    foreach(['analytics','ads','meta','gtm'] as $service)if($s[$service.'_enabled']&&!$s[$service.'_id'])fail('Inserisci l’ID per '.TRACKING_LABELS[$service].'.');
    $old=settings();if(array_intersect_key($s,array_flip(['analytics_enabled','ads_enabled','meta_enabled','gtm_enabled','analytics_id','ads_id','meta_id','gtm_id']))!==array_intersect_key($old,array_flip(['analytics_enabled','ads_enabled','meta_enabled','gtm_enabled','analytics_id','ads_id','meta_id','gtm_id'])))$s['consent_version']=$old['consent_version']+1;
    db()->prepare('INSERT INTO settings(id,value) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET value=excluded.value')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);return $s;
}
function public_settings(): array {
    $s=settings();$keys=['recaptcha_enabled','recaptcha_site_key','analytics_enabled','analytics_id','ads_enabled','ads_id','ads_label','meta_enabled','meta_id','gtm_enabled','gtm_id','consent_version'];return array_intersect_key($s,array_flip($keys));
}
function verify_recaptcha(): void {
    $s=settings();if(!$s['recaptcha_enabled'])return;
    $token=$_POST['recaptcha_token']??'';
    $error=($_POST['language']??'it')==='en'?'Security verification failed. Please try again.':'Verifica di sicurezza non riuscita. Riprova.';
    if(!is_string($token)||strlen($token)<10||strlen($token)>4096)fail($error);
    $ch=curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$s['recaptcha_secret'],'response'=>$token]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10]);
    $response=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $v=$response?json_decode($response,true):null;
    if($http!==200||!is_array($v)||empty($v['success'])||($v['action']??'')!=='valuation'||($v['hostname']??'')!==$s['recaptcha_hostname']||!is_numeric($v['score']??null)||(float)$v['score']<$s['recaptcha_score'])fail($error);
    $timestamp=strtotime($v['challenge_ts']??'');if(!$timestamp||time()-$timestamp>120||$timestamp>time()+10)fail($error);
}
function purge_expired_requests(): int {
    $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-'.settings()['retention_months'].' months')->format('c');
    $q=db()->prepare('SELECT id,path FROM attachments WHERE request_id IN(SELECT id FROM requests WHERE created_at < ?)');$q->execute([$cutoff]);$files=$q->fetchAll();
    db()->beginTransaction();try{db()->prepare('DELETE FROM attachments WHERE request_id IN(SELECT id FROM requests WHERE created_at < ?)')->execute([$cutoff]);$q=db()->prepare('DELETE FROM requests WHERE created_at < ?');$q->execute([$cutoff]);$count=$q->rowCount();db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
    foreach($files as $f)foreach(['','-thumb.png'] as $suffix){$p=ROOT.'/uploads/clienti/'.$f['path'].$suffix;if(is_file($p))unlink($p);}return $count;
}
