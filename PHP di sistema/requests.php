<?php
declare(strict_types=1);
// 2.1.76: il modulo propone diamante, solitario, zaffiro, rubino, smeraldo, spilla, orecchini, collana, bracciale e orologio;
// 'multi-stone', 'gemstone' e 'other' restano validi per i link e le richieste precedenti.
// 2.1.80: aggiunta «Oreficeria usata» (used-gold), senza pietra né certificato.
const CATEGORIES=['loose-diamond','solitaire','sapphire','ruby','emerald','brooch','earrings','necklace','bracelet','used-gold','watch','multi-stone','gemstone','other'];
const GEM_CATEGORIES=['sapphire','ruby','emerald'];
// 2.1.80: Jacob & Co non è più tra i marchi trattati (le richieste precedenti restano leggibili).
const BRANDS=['Rolex','Patek Philippe','Audemars Piguet','Cartier','Vacheron Constantin','Richard Mille','Piaget'];
// 2.1.80: laboratori accettati; per diamante e solitario con GIA, HRD o IGI serve il numero o il file del certificato.
const CERTS=['GIA','HRD','IGI','IGI-Italia','CISGEM','other-lab','none'];
const CERT_REQUIRED=['GIA','HRD','IGI'];
const SHAPES=['round','oval','pear','heart','marquise','emerald-cut','radiant','cushion','princess','other'];
const GOLD_KARATS=['24kt','22kt','18kt','14kt','9kt','unknown'];
/** Numero decimale scritto con la virgola o con il punto («1,52»), null se vuoto o non valido. */
function request_decimal(string $v,float $min,float $max): ?float {
    $v=str_replace(',','.',trim($v)); if($v===''||!preg_match('/^\d{1,6}(\.\d{1,2})?$/',$v)) return null;
    $n=(float)$v; return $n>=$min&&$n<=$max?$n:null;
}
/** Controlla un'immagine o un PDF caricato; restituisce il MIME reale. */
function request_check_document(string $tmp,callable $error): string {
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true)) $error('Sono ammessi JPG, PNG, WebP e PDF.','Only JPG, PNG, WebP and PDF files are accepted.');
    if($mime!=='application/pdf') {
        $info=@getimagesize($tmp); if(!$info||$info[0]*$info[1]>16000000) $error('Immagine non valida o troppo grande.','The image is invalid or too large.');
        $check=@imagecreatefromstring(file_get_contents($tmp));if(!$check)$error('Immagine danneggiata o non leggibile.','The image is damaged or unreadable.');imagedestroy($check);
    } elseif(file_get_contents($tmp,false,null,0,5)!=='%PDF-') $error('PDF non valido.','Invalid PDF file.');
    return $mime;
}
/** Controlla un video MP4 o MOV (contenitore ISO/QuickTime); restituisce il MIME da salvare. */
function request_check_video(string $tmp,string $name,callable $error): string {
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); $head=(string)file_get_contents($tmp,false,null,0,12); $box=substr($head,4,4);
    $iso=in_array($box,['ftyp','moov','mdat','wide','free','skip','pnot'],true);
    if(!$iso||!in_array($mime,['video/mp4','video/quicktime','video/x-m4v','video/3gpp','application/octet-stream'],true)) $error('Il video deve essere in formato MP4 o MOV.','The video must be an MP4 or MOV file.');
    $qt=$mime==='video/quicktime'||str_contains(substr($head,8,4),'qt')||preg_match('/\.mov$/i',$name);
    return $qt?'video/quicktime':'video/mp4';
}
function submit_request(): never {
    $lang=$_POST['language']??'it'; if(!in_array($lang,['it','en'],true)) $lang='it';
    $error=function(string $it,string $en): never { fail(($_POST['language']??'it')==='en'?$en:$it); };
    // 2.1.80: oltre post_max_size PHP scarta tutto il modulo: lo diciamo chiaramente invece di chiedere di ricaricarlo.
    if(!$_POST&&(int)($_SERVER['CONTENT_LENGTH']??0)>0) $error('Gli allegati superano il limite del server. Riduci il video o invialo su WhatsApp.','Your attachments exceed the server limit. Please send a smaller video or send it on WhatsApp.');
    if(!empty($_POST['website'])) $error('Richiesta non valida.','Invalid request.');
    $key=$_POST['request_key']??'';
    if(!is_string($key)||!preg_match('/^[a-f0-9-]{32,64}$/',$key)) $error('Ricarica il modulo prima di inviarlo.','Please reload the form before submitting.');
    // Bind retries to the visitor's session; knowledge of a key alone reveals nothing.
    $key=hash('sha256',session_id().':'.$key);
    $q=db()->prepare('SELECT id FROM requests WHERE request_key=?'); $q->execute([$key]);
    if($id=$q->fetchColumn()) respond(['ok'=>true,'id'=>$id]);
    rate('request',12,3600); verify_recaptcha(); purge_expired_requests();
    $f=[];
    foreach(['cat'=>40,'multi'=>5,'others'=>1500,'stone'=>30,'brand'=>60,'ct'=>20,'color'=>10,'clarity'=>10,'gem_quality'=>120,'origin'=>20,'cert'=>30,'cert_no'=>40,'cert_lab'=>60,'shape'=>20,'ct_exact'=>10,'gold_karat'=>10,'gold_weight'=>10,'kit'=>30,'name'=>120,'phone'=>40,'email'=>254,'pref'=>20] as $field=>$max) {
        $v=$_POST[$field]??''; if(!is_string($v)||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v)) $error('Controlla i campi del modulo.','Please check the form fields.');
        $f[$field]=trim(strip_tags($v));
    }
    if(!in_array($f['cat'],CATEGORIES,true)||!in_array($f['multi'],['yes','no'],true)) $error('Seleziona il tipo di oggetto e indica se ne hai altri.','Select your item type and tell us whether you have other items.');
    // 2.1.55: la descrizione degli altri oggetti è facoltativa.
    if($f['multi']!=='yes') $f['others']='';
    if($f['cat']==='watch') {
        if(!in_array($f['brand'],BRANDS,true)||!in_array($f['kit'],['full-set','papers','none'],true)) $error('Seleziona un marchio trattato e i documenti disponibili.','Select a watch brand we buy and the documents available.');
        foreach(['stone','ct','color','clarity','gem_quality','origin','cert','cert_no','cert_lab','shape','ct_exact','gold_karat','gold_weight'] as $k) $f[$k]='';
    } elseif($f['cat']==='used-gold') {
        // 2.1.80: oreficeria usata: titolo dell'oro obbligatorio (anche «Non lo so»), peso in grammi facoltativo; niente pietra né certificato.
        if(!in_array($f['gold_karat'],GOLD_KARATS,true)) $error('Seleziona il titolo dell’oro (oppure «Non lo so»).','Please select the gold fineness (or “I am not sure”).');
        if($f['gold_weight']!==''){$g=request_decimal($f['gold_weight'],0.1,99999);if($g===null)$error('Indica il peso in grammi con un numero (es. 25).','Please enter the weight in grams as a number (e.g. 25).');$f['gold_weight']=rtrim(rtrim(number_format($g,2,'.',''),'0'),'.');}
        foreach(['stone','brand','kit','ct','color','clarity','gem_quality','origin','cert','cert_no','cert_lab','shape','ct_exact'] as $k) $f[$k]='';
    } else {
        $f['gold_karat']=$f['gold_weight']='';
        $gem=$f['cat']==='gemstone'||in_array($f['cat'],GEM_CATEGORIES,true);
        // 2.1.76: per zaffiro, rubino e smeraldo la pietra è la voce scelta e al passo 3 si chiede solo il peso.
        if(in_array($f['cat'],GEM_CATEGORIES,true)){$f['stone']=$f['cat'];$f['gem_quality']='';}
        // 2.1.55: stesse fasce di peso per diamanti e pietre preziose; colore, purezza e numero di certificato facoltativi.
        // 2.1.67: «pietre piccole, sotto 1 ct» solo per i gioielli con più pietre.
        $small=in_array($f['cat'],['multi-stone','earrings','necklace','bracelet','brooch','other'],true);
        // 2.1.80: forma e carati esatti facoltativi per i diamanti; i carati esatti decidono la fascia di peso.
        if(!$gem){
            if($f['shape']!==''&&!in_array($f['shape'],SHAPES,true)) $error('Seleziona una forma valida.','Please select a valid shape.');
            if($f['ct_exact']!==''){
                $c=request_decimal($f['ct_exact'],0.01,999.99); if($c===null) $error('Indica i carati esatti con un numero (es. 1,52).','Please enter the exact carat weight as a number (e.g. 1.52).');
                if($c<1&&!$small) $error('Valutiamo diamanti da 1 carato in su.','We assess diamonds of one carat or more.');
                $f['ct']=$c<1?'small':($c<2?'1-2':($c<3?'2-3':($c<5?'3-5':'over-5'))); $f['ct_exact']=number_format($c,2,'.','');
            }
        }
        if(!in_array($f['ct'],array_merge(['1-2','2-3','3-5','over-5','unknown'],$small?['small']:[]),true)) $error('Seleziona un peso valido. Acquistiamo pietre da 1 carato in su.','Select a valid weight. We buy stones of one carat or more.');
        if($f['color']!==''&&!in_array($f['color'],['D','E','F','G','H','I','J','K','L','M','N-Z','fancy','unknown'],true)) $error('Seleziona un colore valido.','Please select a valid colour.');
        if($f['clarity']!==''&&!in_array($f['clarity'],['FL','IF','VVS1','VVS2','VS1','VS2','SI1','SI2','I1','I2','I3','unknown'],true)) $error('Seleziona una purezza valida.','Please select a valid clarity grade.');
        if($gem&&!in_array($f['stone'],['ruby','sapphire','emerald','other'],true)) $error('Seleziona il tipo di pietra.','Please select the gemstone type.');
        if(!$gem&&$f['origin']!==''&&!in_array($f['origin'],['natural','unknown'],true)) $error('Valutiamo solo diamanti naturali. Indica l’origine.','We assess natural diamonds only. Please select the origin.');
        if(!in_array($f['cert'],CERTS,true)) $error('Seleziona un certificato riconosciuto o “Nessun certificato”.','Select an accepted grading report or “No report”.');
        if($f['cert']==='none') $f['cert_no']='';
        if($f['cert']==='other-lab'){if(mb_strlen($f['cert_lab'])<2)$error('Indica il nome del laboratorio.','Please enter the name of the laboratory.');} else $f['cert_lab']='';
        if($f['cert_no']!==''&&!preg_match('/^[A-Za-z0-9 .\/-]{1,40}$/',$f['cert_no'])) $error('Il numero del certificato può contenere solo lettere, numeri, spazi, punti, trattini e /.','The report number may only contain letters, digits, spaces, dots, hyphens and /.');
        $f['brand']=$f['kit']=''; if($gem){$f['origin']=$f['color']=$f['clarity']=$f['shape']=$f['ct_exact']='';} else $f['stone']=$f['gem_quality']='';
    }
    if(mb_strlen($f['name'])<2||!preg_match('/\p{L}/u',$f['name'])) $error('Inserisci il tuo nome.','Please enter your name.');
    if(!filter_var($f['email'],FILTER_VALIDATE_EMAIL)) $error('Inserisci un indirizzo email valido.','Please enter a valid email address.');
    if(settings()['mail_check_domain']&&!email_domain_exists($f['email'])) $error('Il dominio dell’indirizzo email non esiste o non riceve posta. Controlla di averlo scritto correttamente.','The domain of this email address does not exist or cannot receive email. Please check it is spelt correctly.');
    if($f['phone']!==''&&(!preg_match('/^\+?[0-9 ().-]{7,40}$/',$f['phone'])||strlen(preg_replace('/\D/','',$f['phone']))<7||strlen(preg_replace('/\D/','',$f['phone']))>15)) $error('Inserisci un numero di telefono valido.','Please enter a valid telephone number.');
    if(!in_array($f['pref'],['WhatsApp','phone','email'],true)||($f['pref']==='email'&&$f['email']==='')||($f['pref']!=='email'&&$f['phone']==='')) $error('Compila il contatto corrispondente alla tua preferenza.','Please provide the contact details for your preferred method.');
    if(($_POST['consent']??'')!=='1') $error('Conferma l’informativa sul trattamento dei dati.','Please acknowledge the information about how we use your data.');
    $f['consent_at']=gmdate('c'); $f['privacy_version']='2.1.4';
    $files=$_FILES['photos']??null; $pending=[]; $total=0;
    // 2.1.80: file del certificato (passo 4), conteggiato nei 20 MB complessivi delle foto.
    $certFile=$_FILES['cert_file']??null; $hasCertFile=false;
    if($certFile&&!is_array($certFile['name']??null)&&($certFile['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE&&$f['cert']!==''&&$f['cert']!=='none'){
        if($certFile['error']!==UPLOAD_ERR_OK) $error('Caricamento del certificato non riuscito. Controlla le dimensioni del file.','The report upload failed. Please check the file size.');
        $size=(int)$certFile['size']; $total+=$size;
        if($size<1||$size>5*1024*1024) $error('Il file del certificato può essere al massimo di 5 MB.','The report file can be up to 5 MB.');
        $mime=request_check_document($certFile['tmp_name'],$error);
        $pending[]=['tmp'=>$certFile['tmp_name'],'mime'=>$mime,'name'=>($lang==='en'?'Report':'Certificato').' · '.mb_substr(basename(str_replace('\\','/',(string)$certFile['name'])),0,160),'size'=>$size]; $hasCertFile=true;
    } elseif($certFile&&is_array($certFile['name']??null)) $error('Allegati non validi.','Invalid attachments.');
    if(in_array($f['cat'],['loose-diamond','solitaire'],true)&&in_array($f['cert'],CERT_REQUIRED,true)&&$f['cert_no']===''&&!$hasCertFile)
        $error('Per un diamante con certificato GIA, IGI o HRD indica il numero del certificato oppure carica la foto o il PDF.','For a diamond with a GIA, IGI or HRD report, please enter the report number or upload a photo or PDF of the report.');
    if($files && is_array($files['name']??null)) {
        if(count($files['name'])>6) $error('Puoi allegare al massimo 6 file.','You can attach up to 6 files.');
        foreach($files['name'] as $i=>$name) {
            $code=$files['error'][$i]; if($code===UPLOAD_ERR_NO_FILE) continue;
            if($code!==UPLOAD_ERR_OK) $error('Caricamento non riuscito. Controlla le dimensioni del file.','Upload failed. Please check the file size.');
            $size=(int)$files['size'][$i]; $total+=$size;
            if($size<1||$size>5*1024*1024||$total>20*1024*1024) $error('Limite: 5 MB per file e 20 MB complessivi.','Limit: 5 MB per file and 20 MB in total.');
            $tmp=$files['tmp_name'][$i]; $mime=request_check_document($tmp,$error);
            $pending[]=['tmp'=>$tmp,'mime'=>$mime,'name'=>mb_substr(basename(str_replace('\\','/',$name)),0,180),'size'=>$size];
        }
    } elseif($files) $error('Allegati non validi.','Invalid attachments.');
    // 2.1.80: un video breve MP4 o MOV, cancellato automaticamente dopo 15 giorni (purge_expired_videos).
    $video=$_FILES['video']??null;
    if($video&&!is_array($video['name']??null)&&($video['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        if(in_array($video['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) $error('Il video supera il limite del server. Invialo su WhatsApp oppure caricane uno più breve.','The video exceeds the server limit. Please send it on WhatsApp or upload a shorter one.');
        if($video['error']!==UPLOAD_ERR_OK) $error('Caricamento del video non riuscito. Riprova oppure invialo su WhatsApp.','The video upload failed. Please try again or send it on WhatsApp.');
        $size=(int)$video['size']; if($size<1||$size>VIDEO_MAX) $error('Il video può essere al massimo di 100 MB.','The video can be up to 100 MB.');
        $mime=request_check_video($video['tmp_name'],(string)$video['name'],$error);
        $pending[]=['tmp'=>$video['tmp_name'],'mime'=>$mime,'name'=>mb_substr(basename(str_replace('\\','/',(string)$video['name'])),0,180),'size'=>$size];
    } elseif($video&&is_array($video['name']??null)) $error('Allegati non validi.','Invalid attachments.');
    $id='R-'.strtoupper(bin2hex(random_bytes(6))); $written=[];
    db()->beginTransaction();
    try {
        $q=db()->prepare('INSERT INTO requests(id,request_key,created_at,language,payload) VALUES(?,?,?,?,?)'); $q->execute([$id,$key,gmdate('c'),$lang,json_encode($f,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        foreach($pending as $file) {
            $fid=bin2hex(random_bytes(16)); $path=ROOT.'/uploads/clienti/'.$fid; $written[]=$path;
            if($file['mime']==='application/pdf'||str_starts_with($file['mime'],'video/')) { if(!move_uploaded_file($file['tmp'],$path)) throw new RuntimeException('Upload failed'); }
            else {
                $bytes=file_get_contents($file['tmp']); $img=@imagecreatefromstring($bytes); if(!$img) throw new RuntimeException('Invalid image');
                imagealphablending($img,false); imagesavealpha($img,true); if(!imagepng($img,$path)) throw new RuntimeException('Image conversion failed');
                $scale=min(1,360/imagesx($img),240/imagesy($img)); $thumb=imagecreatetruecolor(max(1,(int)(imagesx($img)*$scale)),max(1,(int)(imagesy($img)*$scale)));
                imagealphablending($thumb,false); imagesavealpha($thumb,true); imagecopyresampled($thumb,$img,0,0,0,0,imagesx($thumb),imagesy($thumb),imagesx($img),imagesy($img));
                $written[]=$path.'-thumb.png'; if(!imagepng($thumb,$path.'-thumb.png')) throw new RuntimeException('Thumbnail conversion failed'); imagedestroy($thumb); imagedestroy($img); $file['mime']='image/png';
            }
            $q=db()->prepare('INSERT INTO attachments(id,request_id,name,path,mime,size) VALUES(?,?,?,?,?,?)'); $q->execute([$fid,$id,$file['name'],$fid,$file['mime'],filesize($path)]);
        }
        queue_request_emails($id,$f,$lang,count($pending),count(array_filter($pending,fn($x)=>str_starts_with($x['mime'],'video/'))));
        audit('request',$id); db()->commit();
        try{send_queued_emails($id);}catch(Throwable $mailError){error_log('Email queue processing failed for '.$id);}
        respond(['ok'=>true,'id'=>$id],201);
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack(); foreach($written as $path) if(is_file($path)) unlink($path);
        // Concurrent retry after a lost response.
        $q=db()->prepare('SELECT id FROM requests WHERE request_key=?'); $q->execute([$key]); if($existing=$q->fetchColumn()) respond(['ok'=>true,'id'=>$existing]);
        throw $e;
    }
}
