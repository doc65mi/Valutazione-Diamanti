<?php
declare(strict_types=1);
const CATEGORIES=['solitaire','multi-stone','earrings','necklace','bracelet','brooch','loose-diamond','gemstone','watch','other'];
const BRANDS=['Rolex','Patek Philippe','Audemars Piguet','Cartier','Vacheron Constantin','Richard Mille','Piaget','Jacob & Co'];
function submit_request(): never {
    $lang=$_POST['language']??'it'; if(!in_array($lang,['it','en'],true)) $lang='it';
    $error=function(string $it,string $en): never { fail(($_POST['language']??'it')==='en'?$en:$it); };
    if(!empty($_POST['website'])) $error('Richiesta non valida.','Invalid request.');
    $key=$_POST['request_key']??'';
    if(!is_string($key)||!preg_match('/^[a-f0-9-]{32,64}$/',$key)) $error('Ricarica il modulo prima di inviarlo.','Please reload the form before submitting.');
    // Bind retries to the visitor's session; knowledge of a key alone reveals nothing.
    $key=hash('sha256',session_id().':'.$key);
    $q=db()->prepare('SELECT id FROM requests WHERE request_key=?'); $q->execute([$key]);
    if($id=$q->fetchColumn()) respond(['ok'=>true,'id'=>$id]);
    rate('request',12,3600); verify_recaptcha(); purge_expired_requests();
    $f=[];
    foreach(['cat'=>40,'multi'=>5,'others'=>1500,'stone'=>30,'brand'=>60,'ct'=>20,'origin'=>20,'cert'=>30,'kit'=>30,'name'=>120,'phone'=>40,'email'=>254,'pref'=>20] as $field=>$max) {
        $v=$_POST[$field]??''; if(!is_string($v)||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v)) $error('Controlla i campi del modulo.','Please check the form fields.');
        $f[$field]=trim(strip_tags($v));
    }
    if(!in_array($f['cat'],CATEGORIES,true)||!in_array($f['multi'],['yes','no'],true)) $error('Seleziona il tipo di oggetto e indica se ne hai altri.','Select your item type and tell us whether you have other items.');
    if($f['multi']==='yes'&&$f['others']==='') $error('Descrivi brevemente gli altri oggetti.','Please briefly describe your other items.');
    if($f['cat']==='watch') {
        if(!in_array($f['brand'],BRANDS,true)||!in_array($f['kit'],['full-set','papers','none'],true)) $error('Seleziona un marchio trattato e i documenti disponibili.','Select a watch brand we buy and the documents available.');
        foreach(['stone','ct','origin','cert'] as $k) $f[$k]='';
    } else {
        $gem=$f['cat']==='gemstone';
        $weights=$gem?['under-1','1-2','2-5','over-5','unknown']:['1-2','2-3','3-5','over-5','unknown'];
        if(!in_array($f['ct'],$weights,true)) $error('Seleziona un peso valido. Acquistiamo diamanti da 1 carato per pietra.','Select a valid weight. We buy diamonds of one carat or more per stone.');
        if($gem&&!in_array($f['stone'],['ruby','sapphire','emerald','other'],true)) $error('Seleziona il tipo di pietra.','Please select the gemstone type.');
        if(!$gem&&$f['origin']!==''&&!in_array($f['origin'],['natural','unknown'],true)) $error('Valutiamo solo diamanti naturali. Indica l’origine.','We assess natural diamonds only. Please select the origin.');
        if(!in_array($f['cert'],['GIA','HRD','IGI','none'],true)) $error('Seleziona un certificato riconosciuto o “Nessun certificato”.','Select an accepted grading report or “No report”.');
        $f['brand']=$f['kit']=''; if($gem) $f['origin']=''; else $f['stone']='';
    }
    if(mb_strlen($f['name'])<2||!preg_match('/\p{L}/u',$f['name'])) $error('Inserisci il tuo nome.','Please enter your name.');
    if(!filter_var($f['email'],FILTER_VALIDATE_EMAIL)) $error('Inserisci un indirizzo email valido.','Please enter a valid email address.');
    if(settings()['mail_check_domain']&&!email_domain_exists($f['email'])) $error('Il dominio dell’indirizzo email non esiste o non riceve posta. Controlla di averlo scritto correttamente.','The domain of this email address does not exist or cannot receive email. Please check it is spelt correctly.');
    if($f['phone']!==''&&(!preg_match('/^\+?[0-9 ().-]{7,40}$/',$f['phone'])||strlen(preg_replace('/\D/','',$f['phone']))<7||strlen(preg_replace('/\D/','',$f['phone']))>15)) $error('Inserisci un numero di telefono valido.','Please enter a valid telephone number.');
    if(!in_array($f['pref'],['WhatsApp','phone','email'],true)||($f['pref']==='email'&&$f['email']==='')||($f['pref']!=='email'&&$f['phone']==='')) $error('Compila il contatto corrispondente alla tua preferenza.','Please provide the contact details for your preferred method.');
    if(($_POST['consent']??'')!=='1') $error('Conferma l’informativa sul trattamento dei dati.','Please acknowledge the information about how we use your data.');
    $f['consent_at']=gmdate('c'); $f['privacy_version']='2.1.4';
    $files=$_FILES['photos']??null; $pending=[]; $total=0;
    if($files && is_array($files['name']??null)) {
        if(count($files['name'])>6) $error('Puoi allegare al massimo 6 file.','You can attach up to 6 files.');
        foreach($files['name'] as $i=>$name) {
            $code=$files['error'][$i]; if($code===UPLOAD_ERR_NO_FILE) continue;
            if($code!==UPLOAD_ERR_OK) $error('Caricamento non riuscito. Controlla le dimensioni del file.','Upload failed. Please check the file size.');
            $size=(int)$files['size'][$i]; $total+=$size;
            if($size<1||$size>5*1024*1024||$total>20*1024*1024) $error('Limite: 5 MB per file e 20 MB complessivi.','Limit: 5 MB per file and 20 MB in total.');
            $tmp=$files['tmp_name'][$i]; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true)) $error('Sono ammessi JPG, PNG, WebP e PDF.','Only JPG, PNG, WebP and PDF files are accepted.');
            if($mime!=='application/pdf') {
                $info=@getimagesize($tmp); if(!$info||$info[0]*$info[1]>16000000) $error('Immagine non valida o troppo grande.','The image is invalid or too large.');
                $check=@imagecreatefromstring(file_get_contents($tmp));if(!$check)$error('Immagine danneggiata o non leggibile.','The image is damaged or unreadable.');imagedestroy($check);
            } elseif(file_get_contents($tmp,false,null,0,5)!=='%PDF-') $error('PDF non valido.','Invalid PDF file.');
            $pending[]=['tmp'=>$tmp,'mime'=>$mime,'name'=>mb_substr(basename(str_replace('\\','/',$name)),0,180),'size'=>$size];
        }
    } elseif($files) $error('Allegati non validi.','Invalid attachments.');
    $id='R-'.strtoupper(bin2hex(random_bytes(6))); $written=[];
    db()->beginTransaction();
    try {
        $q=db()->prepare('INSERT INTO requests(id,request_key,created_at,language,payload) VALUES(?,?,?,?,?)'); $q->execute([$id,$key,gmdate('c'),$lang,json_encode($f,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        foreach($pending as $file) {
            $fid=bin2hex(random_bytes(16)); $path=ROOT.'/uploads/clienti/'.$fid; $written[]=$path;
            if($file['mime']==='application/pdf') { if(!move_uploaded_file($file['tmp'],$path)) throw new RuntimeException('Upload failed'); }
            else {
                $bytes=file_get_contents($file['tmp']); $img=@imagecreatefromstring($bytes); if(!$img) throw new RuntimeException('Invalid image');
                imagealphablending($img,false); imagesavealpha($img,true); if(!imagepng($img,$path)) throw new RuntimeException('Image conversion failed');
                $scale=min(1,360/imagesx($img),240/imagesy($img)); $thumb=imagecreatetruecolor(max(1,(int)(imagesx($img)*$scale)),max(1,(int)(imagesy($img)*$scale)));
                imagealphablending($thumb,false); imagesavealpha($thumb,true); imagecopyresampled($thumb,$img,0,0,0,0,imagesx($thumb),imagesy($thumb),imagesx($img),imagesy($img));
                $written[]=$path.'-thumb.png'; if(!imagepng($thumb,$path.'-thumb.png')) throw new RuntimeException('Thumbnail conversion failed'); imagedestroy($thumb); imagedestroy($img); $file['mime']='image/png';
            }
            $q=db()->prepare('INSERT INTO attachments(id,request_id,name,path,mime,size) VALUES(?,?,?,?,?,?)'); $q->execute([$fid,$id,$file['name'],$fid,$file['mime'],filesize($path)]);
        }
        queue_request_emails($id,$f,$lang,count($pending));
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
