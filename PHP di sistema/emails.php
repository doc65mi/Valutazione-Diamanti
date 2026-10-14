<?php
declare(strict_types=1);
function mail_queue_schema(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS mail_queue (id INTEGER PRIMARY KEY, request_id TEXT NOT NULL REFERENCES requests(id) ON DELETE CASCADE, kind TEXT NOT NULL, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, reply_to TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", attempts INTEGER NOT NULL DEFAULT 0, updated_at TEXT, UNIQUE(request_id,kind))');
}
/** Testi predefiniti dell'email di conferma al cliente, modificabili nella scheda Email dell'admin (2.1.28). */
function customer_email_defaults(): array {
    return [
        'customer_subject_it'=>'La tua richiesta di valutazione — {riferimento}',
        'customer_body_it'=>"Gentile {nome},\n\ngrazie per aver contattato {azienda}. Abbiamo ricevuto la tua richiesta di valutazione.\n\nIl riferimento della richiesta è {riferimento}.\n\nUn nostro gemmologo esaminerà le informazioni e le eventuali foto o certificazioni inviate e ti ricontatterà tramite il canale che hai indicato. Se necessario, concorderemo un appuntamento per esaminare l’oggetto in sede. La valutazione definitiva e l’eventuale offerta di acquisto saranno formulate dopo tale verifica.\n\nIl servizio è gratuito e senza impegno. Per aggiungere informazioni puoi rispondere a questa email indicando il riferimento della richiesta.\n\nCordiali saluti,\n{azienda}\n{indirizzo}\n",
        'customer_subject_en'=>'Your valuation enquiry — {reference}',
        'customer_body_en'=>"Dear {name},\n\nThank you for contacting {company}. We have received your valuation enquiry.\n\nYour reference is {reference}.\n\nA gemmologist will review the information and any photographs or certificates you have submitted, then contact you using your preferred contact method. If necessary, we will arrange an appointment to examine the item in person. The final valuation and any purchase offer are subject to that examination.\n\nThis service is free of charge and carries no obligation. To provide further information, please reply to this email and quote your reference number.\n\nKind regards,\n{company}\n{address}\n",
    ];
}
/** Sostituisce i segnaposto (in italiano o in inglese) con i dati della richiesta. */
function email_render(string $template,array $vars): string {
    $map=[];foreach(['nome'=>'name','riferimento'=>'reference','azienda'=>'company','indirizzo'=>'address'] as $it=>$en){$map['{'.$it.'}']=$vars[$it]??'';$map['{'.$en.'}']=$vars[$it]??'';}
    return strtr($template,$map);
}
function customer_email(array $s,string $lang,array $vars): array {
    $d=customer_email_defaults();$lang=$lang==='en'?'en':'it';
    $subject=trim((string)($s['customer_subject_'.$lang]??''))?:$d['customer_subject_'.$lang];
    $body=trim((string)($s['customer_body_'.$lang]??''))!==''?(string)$s['customer_body_'.$lang]:$d['customer_body_'.$lang];
    return [str_replace(["\r","\n"],' ',email_render($subject,$vars)),str_replace("\r\n","\n",email_render($body,$vars))];
}
function queue_request_emails(string $id,array $f,string $lang,int $attachments): void {
    mail_queue_schema();$s=settings();$company=$s['legal_company'];$manager=$s['mail_to']?:$s['legal_email'];
    $admin=rtrim($s['site_url'],'/').'/admin/';
    $summary="Riferimento: $id\nCliente: {$f['name']}\nEmail: {$f['email']}\nTelefono: {$f['phone']}\nContatto preferito: {$f['pref']}\nOggetto: {$f['cat']}\nMarca: {$f['brand']}\nPietra: {$f['stone']}\nPeso (ct): {$f['ct']}\nCertificato: {$f['cert']}\nCorredo: {$f['kit']}\nAltri oggetti: {$f['others']}\nAllegati: $attachments\n";
    $managerBody="È arrivata una nuova richiesta di valutazione dal sito.\n\n".$summary."\nAccedi all’area amministrativa per esaminare la richiesta e visualizzare gli allegati: $admin\n\nGli allegati sono disponibili solo dopo l’accesso all’admin.\n";
    [$customerSubject,$customerBody]=customer_email($s,$lang,['nome'=>$f['name'],'riferimento'=>$id,'azienda'=>$company,'indirizzo'=>$s['legal_address']]);
    $q=db()->prepare('INSERT OR IGNORE INTO mail_queue(request_id,kind,recipient,subject,body,reply_to) VALUES(?,?,?,?,?,?)');
    $q->execute([$id,'manager',$manager,"Nuova richiesta di valutazione — $id",$managerBody,$f['email']]);
    $q->execute([$id,'customer',$f['email'],$customerSubject,$customerBody,$manager]);
}
/** Invia un messaggio con PHP mail() o con SMTP autenticato. Restituisce [esito, errore]. */
function mail_deliver(array $s,string $to,string $subject,string $body,string $replyTo,?callable $transport=null): array {
    $from=$s['mail_from'];
    if(!filter_var($from,FILTER_VALIDATE_EMAIL))return [false,'Indirizzo del mittente mancante o non valido.'];
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($replyTo,FILTER_VALIDATE_EMAIL))return [false,'Indirizzo del destinatario non valido.'];
    if(preg_match('/[\r\n]/',$from.$to.$replyTo.$s['mail_from_name']))return [false,'Indirizzo non valido.'];
    $name=trim((string)$s['mail_from_name']);
    $headers=['From'=>$name!==''?'=?UTF-8?B?'.base64_encode($name).'?= <'.$from.'>':$from,'Reply-To'=>$replyTo,'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
    $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
    $encodedBody=chunk_split(base64_encode(str_replace("\n","\r\n",str_replace("\r\n","\n",$body))),76,"\r\n");
    if($transport)return [(bool)$transport($to,$encodedSubject,$encodedBody,$headers),''];
    if(($s['mail_transport']??'php')==='smtp'){
        $domain=substr(strrchr($from,'@'),1);
        $headers=['Date'=>date(DATE_RFC2822),'Message-ID'=>'<'.bin2hex(random_bytes(12)).'@'.$domain.'>','To'=>'<'.$to.'>','Subject'=>$encodedSubject]+$headers;
        try{smtp_send($s,$from,$to,$headers,$encodedBody);return [true,''];}catch(RuntimeException $e){return [false,$e->getMessage()];}
    }
    // Il mittente di busta (-f) allineato al From migliora la consegna (SPF) sui server Plesk.
    $ok=preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$/',$from)?@mail($to,$encodedSubject,$encodedBody,$headers,'-f'.$from):@mail($to,$encodedSubject,$encodedBody,$headers);
    return [$ok,$ok?'':'Il server non ha accettato il messaggio (funzione mail di PHP). Prova l’invio SMTP con una casella email del dominio.'];
}
function smtp_send(array $s,string $from,string $to,array $headers,string $body): void {
    $host=$s['smtp_host'];$port=(int)$s['smtp_port'];$secure=$s['smtp_secure'];
    if($host===''||$port<1)throw new RuntimeException('Server SMTP non configurato.');
    $verify=(bool)($s['smtp_verify']??true);
    $ctx=stream_context_create(['ssl'=>['peer_name'=>$host,'verify_peer'=>$verify,'verify_peer_name'=>$verify,'allow_self_signed'=>!$verify]]);
    $fp=@stream_socket_client(($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$errstr,10,STREAM_CLIENT_CONNECT,$ctx);
    if(!$fp)throw new RuntimeException("Connessione al server SMTP $host:$port non riuscita".($errstr?": $errstr":'.').($secure==='ssl'&&!$errstr?' Controlla porta e certificato.':''));
    stream_set_timeout($fp,20);
    $read=function() use($fp): string {$all='';while(($line=fgets($fp,1024))!==false){$all.=$line;if(strlen($line)<4||$line[3]===' ')break;}return $all;};
    $cmd=function(?string $line,array $codes,string $step) use($fp,$read): string {
        if($line!==null)fwrite($fp,$line."\r\n");$reply=$read();
        if(!in_array((int)substr($reply,0,3),$codes,true))throw new RuntimeException("Errore SMTP ($step): ".(trim($reply)!==''?trim(preg_replace('/\s+/',' ',$reply)):'nessuna risposta dal server.'));
        return $reply;
    };
    try{
        $cmd(null,[220],'connessione');$ehlo='EHLO '.(preg_replace('/[^A-Za-z0-9.-]/','',(string)gethostname())?:'localhost');
        $caps=$cmd($ehlo,[250],'EHLO');
        if($secure==='tls'){
            $cmd('STARTTLS',[220],'STARTTLS');
            $method=STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|(defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')?STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT:0);
            if(!@stream_socket_enable_crypto($fp,true,$method))throw new RuntimeException('Connessione cifrata (STARTTLS) non riuscita: controlla il nome del server o disattiva la verifica del certificato.');
            $caps=$cmd($ehlo,[250],'EHLO');
        }
        if($s['smtp_user']!==''){
            if(preg_match('/AUTH[ =][^\r\n]*\bPLAIN\b/i',$caps))$cmd('AUTH PLAIN '.base64_encode("\0".$s['smtp_user']."\0".$s['smtp_pass']),[235],'autenticazione');
            else{$cmd('AUTH LOGIN',[334],'autenticazione');$cmd(base64_encode($s['smtp_user']),[334],'autenticazione');$cmd(base64_encode($s['smtp_pass']),[235],'autenticazione: utente o password non corretti');}
        }
        $cmd('MAIL FROM:<'.$from.'>',[250],'mittente');$cmd('RCPT TO:<'.$to.'>',[250,251],'destinatario');$cmd('DATA',[354],'DATA');
        $message='';foreach($headers as $k=>$v)$message.="$k: $v\r\n";$message.="\r\n".$body;
        $message=preg_replace('/^\./m','..',$message);
        $cmd(rtrim($message,"\r\n")."\r\n.",[250],'invio del messaggio');
        @fwrite($fp,"QUIT\r\n");
    } finally {fclose($fp);}
}
function send_queued_emails(?string $requestId=null,?callable $transport=null): void {
    mail_queue_schema();$s=settings();if(!$s['mail_enabled'])return;
    $lock=fopen(ROOT.'/data/mail.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return;}
    try {
        $sql='SELECT * FROM mail_queue WHERE status IN ("pending","failed") AND attempts<5';$params=[];
        if($requestId!==null){$sql.=' AND request_id=?';$params[]=$requestId;}$sql.=' ORDER BY id LIMIT 50';
        $q=db()->prepare($sql);$q->execute($params);
        foreach($q->fetchAll() as $m){
            [$ok,$err]=mail_deliver($s,$m['recipient'],$m['subject'],$m['body'],$m['reply_to'],$transport);
            if(!$ok&&$err!=='')error_log('Email '.$m['kind'].' '.$m['request_id'].': '.$err);
            db()->prepare('UPDATE mail_queue SET status=?,attempts=attempts+1,updated_at=? WHERE id=?')->execute([$ok?'sent':'failed',gmdate('c'),$m['id']]);
        }
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
/** Email di prova dalla scheda Email: verifica la configurazione o mostra il testo di conferma con dati d'esempio. */
function send_test_email(string $to,string $kind): void {
    $s=settings();if(!filter_var($to,FILTER_VALIDATE_EMAIL))fail('Inserisci un indirizzo valido per la prova.');
    $replyTo=$s['mail_to']?:$s['legal_email'];
    if($kind==='customer_it'||$kind==='customer_en'){
        [$subject,$body]=customer_email($s,substr($kind,-2),['nome'=>'Mario Rossi','riferimento'=>'R-000000000000','azienda'=>$s['legal_company'],'indirizzo'=>$s['legal_address']]);
        $subject='[Prova] '.$subject;
    } else {
        $how=($s['mail_transport']??'php')==='smtp'?'SMTP '.$s['smtp_host'].':'.$s['smtp_port']:'funzione mail di PHP del server';
        $subject='Email di prova — Valutazione Diamanti';
        $body="Questa è un’email di prova inviata dall’area amministrativa del sito.\n\nMittente: {$s['mail_from']}\nMetodo di invio: $how\n\nSe la ricevi (controlla anche la cartella Spam), la configurazione funziona.\n";
    }
    [$ok,$err]=mail_deliver($s,$to,$subject,$body,$replyTo);
    if(!$ok)fail('Invio di prova non riuscito. '.$err);
}
