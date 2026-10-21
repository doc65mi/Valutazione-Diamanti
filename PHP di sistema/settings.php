<?php
declare(strict_types=1);
function settings_defaults(): array {
    return ['mail_enabled'=>false,'mail_from'=>'','mail_to'=>'','recaptcha_enabled'=>false,'recaptcha_site_key'=>'','recaptcha_secret'=>'','recaptcha_hostname'=>'','recaptcha_score'=>0.5,'analytics_enabled'=>false,'analytics_id'=>'','ads_enabled'=>false,'ads_id'=>'','ads_label'=>'','meta_enabled'=>false,'meta_id'=>'','gtm_enabled'=>false,'gtm_id'=>'','legal_company'=>'La Diamanteria','legal_address'=>'Via Vincenzo Monti 47, 20123 Milano','legal_vat'=>'','legal_email'=>'sl@ladiamanteria.com','retention_months'=>12,'site_url'=>'','consent_version'=>1,'menu_hidden'=>['blog'],'mail_from_name'=>'Valutazione Diamanti','mail_transport'=>'php','smtp_host'=>'','smtp_port'=>587,'smtp_secure'=>'tls','smtp_user'=>'','smtp_pass'=>'','smtp_verify'=>true,'mail_check_domain'=>true,'google_verification'=>'','google_file'=>'','bing_verification'=>'','indexnow_enabled'=>false,'indexnow_key'=>'','reviews_enabled'=>false,'reviews_place_id'=>'ChIJFR80gkTBhkcRJGjlclhYteg','reviews_api_key'=>'']+customer_email_defaults();
}
function settings(): array {
    db()->exec('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK(id=1), value TEXT NOT NULL)');
    $v=db()->query('SELECT value FROM settings WHERE id=1')->fetchColumn();return array_replace(settings_defaults(),$v?json_decode($v,true):[]);
}
// Voci del menu (header e footer, IT e EN) che l'amministratore può spegnere. Il link si riconosce dalla destinazione (2.1.27).
const MENU_ITEMS = ['acquistiamo'=>['label'=>'Vendere un diamante','href'=>'acquistiamo.html','page'=>'acquistiamo'],'come-funziona'=>['label'=>'Come funziona','href'=>'index.html#come-funziona','page'=>null],'chisiamo'=>['label'=>'Chi siamo','href'=>'chisiamo.html','page'=>'chisiamo'],'faq'=>['label'=>'FAQ','href'=>'faq.html','page'=>'faq'],'blog'=>['label'=>'Blog','href'=>'../blog/','page'=>null]];
function menu_enabled(string $key): bool { return !in_array($key,(array)settings()['menu_hidden'],true); }
/** Pagine statiche spente dal menu: restano raggiungibili ma escono da sitemap e indicizzazione. */
function hidden_page_slugs(): array { $out=[];foreach(MENU_ITEMS as $key=>$item)if($item['page']&&!menu_enabled($key))$out[]=$item['page'];return $out; }
function menu_save(array $input): array {
    $enabled=$input['enabled']??null;if(!is_array($enabled)||array_diff_key($enabled,MENU_ITEMS)||count($enabled)!==count(MENU_ITEMS))fail('Selezione menu non valida.');
    $hidden=[];foreach($enabled as $key=>$on){if(!is_bool($on))fail('Selezione menu non valida.');if(!$on)$hidden[]=$key;}
    $s=settings();$s['menu_hidden']=$hidden;db()->prepare('INSERT INTO settings(id,value) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET value=excluded.value')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);return $s;
}
const TRACKING_LABELS = ['analytics'=>'Google Analytics 4','ads'=>'Google Ads','meta'=>'Meta Pixel','gtm'=>'Google Tag Manager'];
/** Ricava l'ID anche quando si incolla lo snippet completo o l'ID scritto in minuscolo (es. "aw-123/etichetta"). */
function settings_tracking_ids(array $input): array {
    $patterns=['analytics_id'=>'/\bG-[A-Z0-9]{4,}\b/i','gtm_id'=>'/\bGTM-[A-Z0-9]{4,}\b/i','ads_id'=>'~\bAW-[0-9]{4,}(?:/([A-Za-z0-9_-]{1,100}))?~i'];
    foreach($patterns as $key=>$pattern){
        $v=$input[$key]??'';if(!is_string($v)||strlen($v)>20000)continue;$v=trim($v);
        if($v!==''&&($key==='ads_id'&&preg_match('~\\bAW-[0-9]{4,}/([A-Za-z0-9_-]{1,100})~i',$v,$m)||preg_match($pattern,$v,$m))){$input[$key]=strtoupper(explode('/',$m[0])[0]);if($key==='ads_id'&&!empty($m[1])&&trim((string)($input['ads_label']??''))==='')$input['ads_label']=$m[1];}
    }
    // Search Console e Bing Webmaster Tools (2.1.32): si può incollare il meta tag intero o il contenuto del file HTML di Google.
    foreach(['google_verification','bing_verification'] as $key){$v=$input[$key]??'';if(is_string($v)&&strlen($v)<=2000&&preg_match('/content\s*=\s*["\']([^"\']+)["\']/i',$v,$m))$input[$key]=$m[1];}
    $file=$input['google_file']??'';if(is_string($file)&&strlen($file)<=2000&&preg_match('/\bgoogle[a-z0-9]{8,64}\.html\b/i',$file,$m))$input['google_file']=strtolower($m[0]);
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
    foreach(['google_verification'=>['/^[A-Za-z0-9_-]{10,100}$/','Google Search Console'],'bing_verification'=>['/^[A-Za-z0-9]{8,64}$/','Bing Webmaster Tools']] as $key=>[$pattern,$label]){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>2000)fail('Codice di verifica non valido per '.$label.'.');$v=trim($v);if($v!==''&&!preg_match($pattern,$v))fail('Codice di verifica non valido per '.$label.': incolla il meta tag o solo il valore di content.');$s[$key]=$v;}
    $file=trim((string)($input['google_file']??''));if($file!==''&&!preg_match('/^google[a-z0-9]{8,64}\.html$/',$file))fail('Nome del file di verifica Google non valido: deve essere simile a google1234abcd5678.html.');$s['google_file']=$file;
    if(!is_bool($input['indexnow_enabled']??false))fail('Selezione non valida: IndexNow');$s['indexnow_enabled']=$input['indexnow_enabled']??false;if($s['indexnow_enabled']&&!preg_match('/^[a-f0-9]{32}$/',$s['indexnow_key']))$s['indexnow_key']=bin2hex(random_bytes(16));
    // Recensioni Google (2.1.33): la chiave API non torna mai al browser; campo vuoto = si tiene quella salvata.
    if(!is_bool($input['reviews_enabled']??false))fail('Selezione non valida: recensioni Google');$s['reviews_enabled']=$input['reviews_enabled']??false;
    $place=trim((string)($input['reviews_place_id']??$s['reviews_place_id']));if(preg_match('/place_?id[=:]([A-Za-z0-9_-]+)/i',$place,$m))$place=$m[1];if($place!==''&&!preg_match('/^[A-Za-z0-9_-]{10,300}$/',$place))fail('Place ID non valido: deve essere simile a ChIJFR80gkTBhkcRJGjlclhYteg.');$s['reviews_place_id']=$place;
    $key=$input['reviews_api_key']??'';if(!is_string($key)||strlen($key)>300)fail('Chiave API Google non valida.');$key=trim($key);if($key!==''&&!preg_match('/^[A-Za-z0-9_-]{20,100}$/',$key))fail('Chiave API Google non valida: incolla solo la chiave (inizia di solito con AIza).');if($key!=='')$s['reviews_api_key']=$key;
    if(($input['reviews_clear_key']??false)===true)$s['reviews_api_key']='';
    if($s['reviews_enabled']&&($s['reviews_api_key']===''||$s['reviews_place_id']===''))fail('Per mostrare le recensioni Google servono la chiave API e il Place ID.');
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
/** Impostazioni della scheda Email: invio, SMTP, validazione del modulo e testi al cliente (2.1.28). */
function email_settings_save(array $input): array {
    $s=settings();
    foreach(['mail_enabled','smtp_verify','mail_check_domain'] as $key){if(!is_bool($input[$key]??null))fail('Selezione non valida: '.$key);$s[$key]=$input[$key];}
    foreach(['mail_from','mail_to'] as $key){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>254||strpbrk($v,"\r\n"))fail('Indirizzo email non valido.');$s[$key]=trim($v);if($s[$key]!==''&&!filter_var($s[$key],FILTER_VALIDATE_EMAIL))fail('Indirizzo email non valido: '.($key==='mail_from'?'mittente':'email del gestore').'.');}
    $name=$input['mail_from_name']??'';if(!is_string($name)||mb_strlen($name)>100||preg_match('/[<>"\x00-\x1F]/',$name))fail('Nome del mittente non valido (niente virgolette o simboli < >).');$s['mail_from_name']=trim($name);
    $transport=$input['mail_transport']??'php';if(!in_array($transport,['php','smtp'],true))fail('Metodo di invio non valido.');$s['mail_transport']=$transport;
    $host=$input['smtp_host']??'';if(!is_string($host)||strlen($host)>253||($host!==''&&!preg_match('/^[A-Za-z0-9.-]+$/',trim($host))))fail('Server SMTP non valido: scrivi solo il nome, es. mail.valutazionediamanti.it.');$s['smtp_host']=trim($host);
    $port=$input['smtp_port']??587;if(!is_int($port)||$port<1||$port>65535)fail('Porta SMTP non valida.');$s['smtp_port']=$port;
    $secure=$input['smtp_secure']??'tls';if(!in_array($secure,['tls','ssl','none'],true))fail('Sicurezza SMTP non valida.');$s['smtp_secure']=$secure;
    $user=$input['smtp_user']??'';if(!is_string($user)||strlen($user)>254||preg_match('/[\x00-\x1F]/',$user))fail('Utente SMTP non valido.');$s['smtp_user']=trim($user);
    $pass=$input['smtp_pass']??'';if(!is_string($pass)||strlen($pass)>300||preg_match('/[\x00-\x1F]/',$pass))fail('Password SMTP non valida.');if($pass!=='')$s['smtp_pass']=$pass;
    if(($input['smtp_clear_pass']??false)===true)$s['smtp_pass']='';
    foreach(['it','en'] as $lang){
        $subject=$input['customer_subject_'.$lang]??'';$body=$input['customer_body_'.$lang]??'';
        if(!is_string($subject)||mb_strlen($subject)>200||preg_match('/[\x00-\x1F]/',$subject))fail('Oggetto dell’email al cliente non valido ('.strtoupper($lang).').');
        if(!is_string($body)||mb_strlen($body)>10000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$body))fail('Testo dell’email al cliente non valido ('.strtoupper($lang).'): massimo 10000 caratteri.');
        $s['customer_subject_'.$lang]=trim($subject);$s['customer_body_'.$lang]=str_replace("\r\n","\n",$body);
    }
    if($s['mail_enabled']&&($s['mail_from']===''||$s['mail_to']===''))fail('Compila mittente e email del gestore prima di attivare gli invii.');
    if($s['mail_transport']==='smtp'&&$s['smtp_host']==='')fail('Inserisci il server SMTP o scegli l’invio con la funzione mail del server.');
    db()->prepare('INSERT INTO settings(id,value) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET value=excluded.value')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);return $s;
}
/** Il dominio dell'email del cliente deve esistere e ricevere posta (record MX, o A in mancanza di MX). */
function email_domain_exists(string $email): bool {
    $domain=strtolower(substr(strrchr($email,'@')?:'',1));if($domain==='')return false;
    if(!function_exists('checkdnsrr'))return true;
    $ascii=function_exists('idn_to_ascii')?(idn_to_ascii($domain,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46)?:$domain):$domain;
    return checkdnsrr($ascii.'.','MX')||checkdnsrr($ascii.'.','A')||checkdnsrr($ascii.'.','AAAA');
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
// Cancella una richiesta, i suoi allegati (righe e file) e le email in coda tramite ON DELETE CASCADE (2.1.34).
function delete_request(string $id): bool {
    $q=db()->prepare('SELECT path FROM attachments WHERE request_id=?');$q->execute([$id]);$files=$q->fetchAll();
    db()->beginTransaction();try{db()->prepare('DELETE FROM attachments WHERE request_id=?')->execute([$id]);$q=db()->prepare('DELETE FROM requests WHERE id=?');$q->execute([$id]);$found=$q->rowCount()>0;db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
    foreach($files as $f)foreach(['','-thumb.png'] as $suffix){$p=ROOT.'/uploads/clienti/'.$f['path'].$suffix;if(is_file($p))unlink($p);}return $found;
}
function purge_expired_requests(): int {
    $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-'.settings()['retention_months'].' months')->format('c');
    $q=db()->prepare('SELECT id,path FROM attachments WHERE request_id IN(SELECT id FROM requests WHERE created_at < ?)');$q->execute([$cutoff]);$files=$q->fetchAll();
    db()->beginTransaction();try{db()->prepare('DELETE FROM attachments WHERE request_id IN(SELECT id FROM requests WHERE created_at < ?)')->execute([$cutoff]);$q=db()->prepare('DELETE FROM requests WHERE created_at < ?');$q->execute([$cutoff]);$count=$q->rowCount();db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
    foreach($files as $f)foreach(['','-thumb.png'] as $suffix){$p=ROOT.'/uploads/clienti/'.$f['path'].$suffix;if(is_file($p))unlink($p);}return $count;
}
