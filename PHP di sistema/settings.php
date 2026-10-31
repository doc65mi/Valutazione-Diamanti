<?php
declare(strict_types=1);
function settings_defaults(): array {
    return ['mail_enabled'=>false,'mail_from'=>'','mail_to'=>'','recaptcha_enabled'=>false,'recaptcha_site_key'=>'','recaptcha_secret'=>'','recaptcha_hostname'=>'','recaptcha_score'=>0.5,'analytics_enabled'=>false,'analytics_id'=>'','ads_enabled'=>false,'ads_id'=>'','ads_label'=>'','meta_enabled'=>false,'meta_id'=>'','gtm_enabled'=>false,'gtm_id'=>'','clarity_enabled'=>false,'clarity_id'=>'','custom_codes'=>[],'legal_company'=>'La Diamanteria','legal_address'=>'Via Vincenzo Monti 47, 20123 Milano','legal_vat'=>'','legal_rea'=>'','legal_license'=>'','og_image'=>'','store_geo'=>'','store_hours'=>'','store_email'=>'info@valutazionediamanti.it','legal_email'=>'info@valutazionediamanti.it','retention_months'=>12,'legal_hosting'=>'','log_retention_days'=>30,'form_provider'=>'site','form_provider_name'=>'','gtm_load_analytics'=>false,'gtm_load_ads'=>false,'gtm_load_meta'=>false,'gtm_load_clarity'=>false,'gtm_load_other'=>'','cookie_banner_services'=>true,'site_url'=>'','consent_version'=>1,'menu_hidden'=>['blog'],'home_guides_enabled'=>false,'mail_from_name'=>'Valutazione Diamanti','mail_transport'=>'php','smtp_host'=>'','smtp_port'=>587,'smtp_secure'=>'tls','smtp_user'=>'','smtp_pass'=>'','smtp_verify'=>true,'mail_check_domain'=>true,'google_verification'=>'','google_file'=>'','bing_verification'=>'','indexnow_enabled'=>false,'indexnow_key'=>'','reviews_enabled'=>false,'reviews_place_id'=>'ChIJFR80gkTBhkcRJGjlclhYteg','reviews_api_key'=>'']+customer_email_defaults();
}
function settings(): array {
    db()->exec('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK(id=1), value TEXT NOT NULL)');
    $v=db()->query('SELECT value FROM settings WHERE id=1')->fetchColumn();$s=array_replace(settings_defaults(),$v?json_decode($v,true):[]);
    // 2.1.61: la vecchia email sl@ladiamanteria.com lascia il posto a info@valutazionediamanti.it (contatto e privacy, anche nel JSON-LD).
    // Mittente SMTP e email del gestore (mail_from, mail_to) restano quelle impostate.
    foreach(['legal_email','store_email'] as $k)if(strcasecmp(trim((string)$s[$k]),'sl@ladiamanteria.com')===0)$s[$k]='info@valutazionediamanti.it';
    // Orari e coordinate della sede di partenza per i dati strutturati, finché non vengono compilati dall'area amministrativa.
    if(trim((string)$s['store_hours'])==='')$s['store_hours']='Mo-Fr 09:30-13:30; Mo-Fr 14:30-19:00';
    if(trim((string)$s['store_geo'])==='')$s['store_geo']='45.4685, 9.1680';
    return $s;
}
// Voci del menu (header e footer, IT e EN) che l'amministratore può spegnere. Il link si riconosce dalla destinazione (2.1.27).
const MENU_ITEMS = ['acquistiamo'=>['label'=>'Vendere un diamante','href'=>'acquistiamo.html','page'=>'acquistiamo'],'come-funziona'=>['label'=>'Come funziona','href'=>'index.html#come-funziona','page'=>null],'chisiamo'=>['label'=>'Chi siamo','href'=>'chisiamo.html','page'=>'chisiamo'],'faq'=>['label'=>'FAQ','href'=>'faq.html','page'=>'faq'],'blog'=>['label'=>'Blog','href'=>'../blog/','page'=>null]];
function menu_enabled(string $key): bool { return !in_array($key,(array)settings()['menu_hidden'],true); }
/** Pagine statiche spente dal menu: restano raggiungibili ma escono da sitemap e indicizzazione. */
function hidden_page_slugs(): array { $out=[];foreach(MENU_ITEMS as $key=>$item)if($item['page']&&!menu_enabled($key))$out[]=$item['page'];return $out; }
// Sezione «Guide e approfondimenti» della home: spenta finché il blog non è pronto, si accende dal pannello Menu (2.1.35).
const HOME_GUIDES_LABEL = 'Sezione «Guide e approfondimenti» in home';
function menu_save(array $input): array {
    $enabled=$input['enabled']??null;$guides=is_array($enabled)?($enabled['guide']??false):false;if(is_array($enabled))unset($enabled['guide']);if(!is_bool($guides))fail('Selezione menu non valida.');if(!is_array($enabled)||array_diff_key($enabled,MENU_ITEMS)||count($enabled)!==count(MENU_ITEMS))fail('Selezione menu non valida.');
    $hidden=[];foreach($enabled as $key=>$on){if(!is_bool($on))fail('Selezione menu non valida.');if(!$on)$hidden[]=$key;}
    $s=settings();$s['menu_hidden']=$hidden;$s['home_guides_enabled']=$guides;db()->prepare('INSERT INTO settings(id,value) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET value=excluded.value')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);return $s;
}
const TRACKING_LABELS = ['analytics'=>'Google Analytics 4','ads'=>'Google Ads','meta'=>'Meta Pixel','gtm'=>'Google Tag Manager','clarity'=>'Microsoft Clarity'];
const CUSTOM_CODE_CATEGORIES = ['analytics','marketing','necessary'];
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
    // Microsoft Clarity (2.1.44): va bene lo snippet intero o il link clarity.ms/tag/ID.
    $clarity=$input['clarity_id']??'';
    if(is_string($clarity)&&strlen($clarity)<=20000&&preg_match('~clarity\.ms/tag/([a-z0-9]{6,20})|["\']clarity["\']\s*,\s*["\']script["\']\s*,\s*["\']([a-z0-9]{6,20})["\']~i',$clarity,$m))$input['clarity_id']=$m[1]!==''?$m[1]:$m[2];
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
    foreach(['recaptcha_site_key','recaptcha_hostname','analytics_id','ads_id','ads_label','meta_id','gtm_id','legal_company','legal_address','legal_vat','legal_rea','legal_license','legal_hosting','form_provider_name','gtm_load_other','og_image','store_geo','store_hours','store_email','legal_email','site_url'] as $key){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>500||preg_match('/[<>\x00-\x1F]/',$v))fail(isset(TRACKING_LABELS[substr($key,0,-3)])?'ID non valido per '.TRACKING_LABELS[substr($key,0,-3)].': inserisci solo l’ID, non lo snippet di codice.':'Valore non valido: '.$key);$s[$key]=trim($v);}
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
    // Dati della sede per Google (2.1.53): coordinate come le copia Google Maps e orari nel formato schema.org.
    if($s['store_email']!==''&&!filter_var($s['store_email'],FILTER_VALIDATE_EMAIL))fail('Email di contatto non valida.');
    if($s['store_geo']!==''){if(!preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/',$s['store_geo'],$m)||abs((float)$m[1])>90||abs((float)$m[2])>180)fail('Coordinate non valide: incollale come le copia Google Maps, es. 45.4685, 9.1660.');$s['store_geo']=$m[1].', '.$m[2];}
    if($s['store_hours']!==''){$rows=array_filter(array_map('trim',preg_split('/[;\n]+/',$s['store_hours'])));foreach($rows as $row)if(!store_hours_row($row))fail('Orari non validi: scrivili come Mo-Fr 09:30-18:30; Sa 10:00-13:00 (giorni in inglese abbreviato, separati da punto e virgola).');$s['store_hours']=implode('; ',$rows);}
    if($s['og_image']!==''&&!safe_url($s['og_image'],true))fail('Immagine per le condivisioni non valida: usa l’indirizzo di un’immagine caricata sul sito, es. /uploads/site/og-cover.jpg.');
    $score=$input['recaptcha_score']??0.5;if(!is_numeric($score)||(float)$score<0||(float)$score>1)fail('La soglia reCAPTCHA deve essere tra 0 e 1.');$s['recaptcha_score']=(float)$score;
    $months=$input['retention_months']??12;if(!is_int($months)||$months<1||$months>60)fail('Conservazione: da 1 a 60 mesi.');$s['retention_months']=$months;
    $logDays=$input['log_retention_days']??$s['log_retention_days'];if(!is_int($logDays)||$logDays<1||$logDays>365)fail('Conservazione log tecnici: da 1 a 365 giorni.');$s['log_retention_days']=$logDays;
    foreach(['recaptcha_site_key','recaptcha_secret'] as $key)if($s[$key]!==''&&!preg_match('/^[a-zA-Z0-9_-]{10,300}$/',$s[$key]))fail('Chiave reCAPTCHA non valida.');
    if($s['recaptcha_hostname']!==''&&!preg_match('/^[a-zA-Z0-9.-]+$/',$s['recaptcha_hostname']))fail('Inserisci il dominio reCAPTCHA senza protocollo o percorso.');
    if($s['recaptcha_enabled']&&(!$s['recaptcha_site_key']||!$s['recaptcha_secret']||!$s['recaptcha_hostname']))fail('Per attivare reCAPTCHA servono entrambe le chiavi e il dominio.');
    if(!is_bool($input['clarity_enabled']??false))fail('Selezione non valida: Microsoft Clarity');$s['clarity_enabled']=$input['clarity_enabled']??false;
    $clarity=$input['clarity_id']??$s['clarity_id'];if(!is_string($clarity)||strlen($clarity)>500)fail('ID non valido per Microsoft Clarity.');$clarity=strtolower(trim($clarity));if($clarity!==''&&!preg_match('/^[a-z0-9]{6,20}$/',$clarity))fail('ID non valido per Microsoft Clarity: incolla l’ID del progetto (es. abcd1234ef) o lo snippet intero.');$s['clarity_id']=$clarity;
    // Tool dei cookie (2.1.73): cosa carica Tag Manager, chi gestisce il modulo, elenco dei servizi nel banner.
    foreach(['gtm_load_analytics','gtm_load_ads','gtm_load_meta','gtm_load_clarity','cookie_banner_services'] as $key){$v=$input[$key]??$s[$key];if(!is_bool($v))fail('Selezione non valida: '.$key);$s[$key]=$v;}
    $fp=$input['form_provider']??$s['form_provider'];if(!in_array($fp,['site','external'],true))fail('Gestione del modulo non valida.');$s['form_provider']=$fp;
    if($fp==='external'&&$s['form_provider_name']==='')fail('Indica il nome del fornitore esterno che gestisce il modulo (es. Typeform, Jotform).');
    foreach(['form_provider_name','gtm_load_other'] as $key)if(strlen($s[$key])>200)fail('Testo troppo lungo: '.$key.' (massimo 200 caratteri).');
    if(array_key_exists('custom_codes',$input))$s['custom_codes']=custom_codes_input($input['custom_codes']);
    foreach(['analytics','ads','meta','gtm','clarity'] as $service)if($s[$service.'_enabled']&&!$s[$service.'_id'])fail('Inserisci l’ID per '.TRACKING_LABELS[$service].'.');
    // Ogni strumento nuovo o spento richiede di nuovo il consenso ai visitatori; modificare solo il codice no.
    $consentKeys=['analytics_enabled','ads_enabled','meta_enabled','gtm_enabled','clarity_enabled','analytics_id','ads_id','meta_id','gtm_id','clarity_id','gtm_load_analytics','gtm_load_ads','gtm_load_meta','gtm_load_clarity','gtm_load_other'];
    $old=settings();if(array_intersect_key($s,array_flip($consentKeys))!==array_intersect_key($old,array_flip($consentKeys))||custom_codes_consent($s['custom_codes'])!==custom_codes_consent($old['custom_codes']))$s['consent_version']=$old['consent_version']+1;
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
/**
 * Altri codici di tracciamento (2.1.44): incollati dall'amministratore (es. Hotjar, LinkedIn, TikTok).
 * Il codice arriva in base64 perché ModSecurity blocca le richieste con <script>; si carica solo dopo il consenso della sua categoria.
 */
function custom_codes_input(mixed $list): array {
    if(!is_array($list)||!array_is_list($list)||count($list)>20)fail('Altri codici: al massimo 20 codici.');
    $out=[];$ids=[];
    foreach($list as $i=>$c){
        $n='Altri codici, riga '.($i+1).': ';
        if(!is_array($c))fail($n.'dati non validi.');
        $id=$c['id']??'';if(!is_string($id)||!preg_match('/^[a-f0-9]{8}$/',$id)||isset($ids[$id]))$id=bin2hex(random_bytes(4));$ids[$id]=true;
        $name=$c['name']??'';if(!is_string($name)||mb_strlen(trim($name))<1||mb_strlen($name)>80||preg_match('/[<>\x00-\x1F]/',$name))fail($n.'scrivi un nome (es. Hotjar), al massimo 80 caratteri, senza < >.');
        $category=$c['category']??'';if(!in_array($category,CUSTOM_CODE_CATEGORIES,true))fail($n.'categoria non valida.');
        $position=$c['position']??'';if(!in_array($position,['head','body'],true))fail($n.'posizione non valida.');
        if(!is_bool($c['enabled']??null))fail($n.'selezione non valida.');
        $code=base64_decode((string)($c['code_b64']??''),true);if($code===false||!mb_check_encoding($code,'UTF-8')||strlen($code)>30000)fail($n.'codice non valido o troppo lungo (massimo 30000 caratteri).');
        $code=trim(str_replace("\r\n","\n",$code));if($c['enabled']&&$code==='')fail($n.'incolla il codice o disattiva la riga.');
        $out[]=['id'=>$id,'name'=>trim($name),'category'=>$category,'position'=>$position,'enabled'=>$c['enabled'],'code'=>$code];
    }
    return $out;
}
/** Quello che il visitatore vede nelle preferenze cookie: se cambia, il consenso va chiesto di nuovo. */
function custom_codes_consent(array $list): array {
    return array_values(array_map(fn($c)=>[$c['name'],$c['category']],array_filter($list,fn($c)=>$c['enabled']&&$c['category']!=='necessary')));
}
function custom_code_find(string $id): ?array {
    foreach(settings()['custom_codes'] as $c)if($c['enabled']&&$c['id']===$id)return $c;
    return null;
}
/** Gli script in linea non possono girare con la CSP del sito ('unsafe-inline' è vietato): li serve l'API come file JS. */
function custom_code_inline_script(string $id,int $n): ?string {
    $c=custom_code_find($id);if(!$c)return null;
    preg_match_all('~<script\b([^>]*)>(.*?)</script\s*>~is',$c['code'],$m,PREG_SET_ORDER);
    $inline=array_values(array_filter($m,fn($s)=>!preg_match('~\ssrc\s*=~i',' '.$s[1])));
    return isset($inline[$n])?$inline[$n][2]:null;
}
function public_settings(): array {
    $s=settings();$keys=['recaptcha_enabled','recaptcha_site_key','analytics_enabled','analytics_id','ads_enabled','ads_id','ads_label','meta_enabled','meta_id','gtm_enabled','gtm_id','clarity_enabled','clarity_id','consent_version'];$out=array_intersect_key($s,array_flip($keys));
    $out['custom_codes']=[];foreach($s['custom_codes'] as $c)if($c['enabled']&&$c['code']!=='')$out['custom_codes'][]=['id'=>$c['id'],'name'=>$c['name'],'category'=>$c['category'],'position'=>$c['position'],'html'=>$c['code'],'v'=>substr(sha1($c['code']),0,10)];
    $out['cookie_info']=['it'=>cookie_disclosure('it',$s),'en'=>cookie_disclosure('en',$s)];
    return $out;
}
/** Servizi effettivamente attivi e informazioni per banner cookie, cookie policy e informativa privacy (2.1.73).
 *  Un'unica fonte, così banner, preferenze, pagina Cookie e informativa dicono sempre la stessa cosa. */
function cookie_disclosure(string $lang,?array $s=null): array {
    $s=$s??settings();$en=$lang==='en';$t=fn(string $it,string $eng)=>$en?$eng:$it;$services=[];
    $add=function(string $name,string $cat,string $note)use(&$services){$services[]=['name'=>$name,'category'=>$cat,'note'=>$note];};
    if(!empty($s['recaptcha_enabled']))$add('Google reCAPTCHA v3','necessary',$t('protezione antispam del modulo di valutazione (Google Ireland Ltd)','spam protection for the valuation form (Google Ireland Ltd)'));
    if(!empty($s['analytics_enabled']))$add('Google Analytics 4','analytics',$t('statistiche su visite e utilizzo del sito, senza Google Signals (Google Ireland Ltd)','statistics on visits and website usage, without Google Signals (Google Ireland Ltd)'));
    if(!empty($s['clarity_enabled']))$add('Microsoft Clarity','analytics',$t('mappe di calore e registrazioni anonime della navigazione, con i campi del modulo mascherati (Microsoft Ireland)','heatmaps and anonymous session recordings, with form fields masked (Microsoft Ireland)'));
    if(!empty($s['ads_enabled']))$add('Google Ads','marketing',$t('misurazione delle conversioni delle campagne pubblicitarie Google (Google Ireland Ltd)','conversion measurement for Google advertising campaigns (Google Ireland Ltd)'));
    if(!empty($s['meta_enabled']))$add('Meta Pixel','marketing',$t('misurazione delle campagne su Facebook e Instagram (Meta Platforms Ireland Ltd)','measurement of Facebook and Instagram campaigns (Meta Platforms Ireland Ltd)'));
    if(!empty($s['gtm_enabled'])){$loads=[];foreach(TRACKING_LABELS as $k=>$label)if($k!=='gtm'&&!empty($s['gtm_load_'.$k]))$loads[]=$label;$other=trim((string)($s['gtm_load_other']??''));if($other!=='')$loads[]=$other;
        $add('Google Tag Manager','both',($loads?$t('carica: ','loads: ').implode(', ',$loads):$t('contenitore di tag','tag container')).$t('; parte solo con il consenso ad Analisi e Marketing (Google Ireland Ltd)','; runs only with consent to both Analytics and Marketing (Google Ireland Ltd)'));}
    foreach((array)($s['custom_codes']??[]) as $code)if(!empty($code['enabled'])&&($code['code']??'')!=='')$add((string)$code['name'],in_array($code['category']??'',['necessary','analytics','marketing'],true)?$code['category']:'marketing','');
    $inactive=[];foreach(['recaptcha'=>'Google reCAPTCHA','analytics'=>'Google Analytics','ads'=>'Google Ads','gtm'=>'Google Tag Manager','meta'=>'Meta Pixel','clarity'=>'Microsoft Clarity'] as $k=>$label)if(empty($s[$k.'_enabled'])&&!(!empty($s['gtm_enabled'])&&!empty($s['gtm_load_'.$k])))$inactive[]=$label;
    $hostName=trim((string)($s['legal_hosting']??''));$hosting=$hostName!==''?$hostName:$t('il fornitore di hosting del sito, nominato responsabile del trattamento','the website’s hosting provider, acting as data processor');
    $form=($s['form_provider']??'site')==='external'&&trim((string)($s['form_provider_name']??''))!==''
        ?$t('Il modulo di valutazione è gestito da ','The valuation form is managed by ').trim((string)$s['form_provider_name']).$t(', che agisce come responsabile del trattamento.',', acting as data processor.')
        :$t('Il modulo di valutazione è gestito direttamente da questo sito, senza fornitori esterni: i dati restano sul server di hosting','The valuation form is managed directly by this website, with no external provider: data stays on the hosting server').($hostName!==''?' ('.$hostName.')':'').$t(' e le notifiche partono dal nostro server di posta.',' and notifications are sent from our mail server.');
    $days=max(1,(int)($s['log_retention_days']??30));
    return ['services'=>$services,'inactive'=>$inactive,'form'=>$form,'hosting'=>$hosting,'log_days'=>$days,
        'logs'=>$t('I log tecnici del server (indirizzo IP, data, pagina richiesta, browser) sono conservati per al massimo '.$days.' giorni a fini di sicurezza e poi cancellati.','Technical server logs (IP address, date, requested page, browser) are kept for up to '.$days.' days for security purposes and then deleted.'),
        'banner_services'=>!empty($s['cookie_banner_services'])];
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
const SCHEMA_DAYS = ['Mo'=>'Monday','Tu'=>'Tuesday','We'=>'Wednesday','Th'=>'Thursday','Fr'=>'Friday','Sa'=>'Saturday','Su'=>'Sunday'];
/** Una riga di orario («Mo-Fr 09:30-18:30» o «Sa 10:00-13:00») come OpeningHoursSpecification, null se non valida. */
function store_hours_row(string $row): ?array {
    if(!preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)(?:-(Mo|Tu|We|Th|Fr|Sa|Su))?\s+([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/',$row,$m))return null;
    $keys=array_keys(SCHEMA_DAYS);$from=array_search($m[1],$keys,true);$to=$m[2]!==''?array_search($m[2],$keys,true):$from;if($to<$from)return null;
    $days=[];for($i=$from;$i<=$to;$i++)$days[]=SCHEMA_DAYS[$keys[$i]];
    return ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>$days,'opens'=>$m[3].':'.$m[4],'closes'=>$m[5].':'.$m[6]];
}
