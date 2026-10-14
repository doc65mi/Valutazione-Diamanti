<?php
declare(strict_types=1);
function mail_queue_schema(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS mail_queue (id INTEGER PRIMARY KEY, request_id TEXT NOT NULL REFERENCES requests(id) ON DELETE CASCADE, kind TEXT NOT NULL, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, reply_to TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", attempts INTEGER NOT NULL DEFAULT 0, updated_at TEXT, UNIQUE(request_id,kind))');
}
function queue_request_emails(string $id,array $f,string $lang,int $attachments): void {
    mail_queue_schema();$s=settings();$company=$s['legal_company'];$manager=$s['mail_to']?:$s['legal_email'];
    $admin=rtrim($s['site_url'],'/').'/admin/';
    $summary="Riferimento: $id\nCliente: {$f['name']}\nEmail: {$f['email']}\nTelefono: {$f['phone']}\nContatto preferito: {$f['pref']}\nOggetto: {$f['cat']}\nMarca: {$f['brand']}\nPietra: {$f['stone']}\nPeso (ct): {$f['ct']}\nCertificato: {$f['cert']}\nCorredo: {$f['kit']}\nAltri oggetti: {$f['others']}\nAllegati: $attachments\n";
    $managerBody="È arrivata una nuova richiesta di valutazione dal sito.\n\n".$summary."\nAccedi all’area amministrativa per esaminare la richiesta e visualizzare gli allegati: $admin\n\nGli allegati sono disponibili solo dopo l’accesso all’admin.\n";
    $customerSubject=$lang==='en'?"Your valuation enquiry — $id":"La tua richiesta di valutazione — $id";
    $customerBody=$lang==='en'?"Dear {$f['name']},\n\nThank you for contacting $company. We have received your valuation enquiry.\n\nYour reference is $id.\n\nA gemmologist will review the information and any photographs or certificates you have submitted, then contact you using your preferred contact method. If necessary, we will arrange an appointment to examine the item in person. The final valuation and any purchase offer are subject to that examination.\n\nThis service is free of charge and carries no obligation. To provide further information, please reply to this email and quote your reference number.\n\nKind regards,\n$company\n{$s['legal_address']}\n":
    "Gentile {$f['name']},\n\ngrazie per aver contattato $company. Abbiamo ricevuto la tua richiesta di valutazione.\n\nIl riferimento della richiesta è $id.\n\nUn nostro gemmologo esaminerà le informazioni e le eventuali foto o certificazioni inviate e ti ricontatterà tramite il canale che hai indicato. Se necessario, concorderemo un appuntamento per esaminare l’oggetto in sede. La valutazione definitiva e l’eventuale offerta di acquisto saranno formulate dopo tale verifica.\n\nIl servizio è gratuito e senza impegno. Per aggiungere informazioni puoi rispondere a questa email indicando il riferimento della richiesta.\n\nCordiali saluti,\n$company\n{$s['legal_address']}\n";
    $q=db()->prepare('INSERT OR IGNORE INTO mail_queue(request_id,kind,recipient,subject,body,reply_to) VALUES(?,?,?,?,?,?)');
    $q->execute([$id,'manager',$manager,"Nuova richiesta di valutazione — $id",$managerBody,$f['email']]);
    $q->execute([$id,'customer',$f['email'],$customerSubject,$customerBody,$manager]);
}
function send_queued_emails(?string $requestId=null,?callable $transport=null): void {
    mail_queue_schema();$s=settings();if(!$s['mail_enabled'])return;
    $lock=fopen(ROOT.'/data/mail.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return;}
    try {
        $sql='SELECT * FROM mail_queue WHERE status IN ("pending","failed") AND attempts<5';$params=[];
        if($requestId!==null){$sql.=' AND request_id=?';$params[]=$requestId;}$sql.=' ORDER BY id LIMIT 50';
        $q=db()->prepare($sql);$q->execute($params);
        foreach($q->fetchAll() as $m){
            $ok=false;
            if(filter_var($s['mail_from'],FILTER_VALIDATE_EMAIL)&&filter_var($m['recipient'],FILTER_VALIDATE_EMAIL)&&filter_var($m['reply_to'],FILTER_VALIDATE_EMAIL)&&!preg_match('/[\r\n]/',$s['mail_from'].$m['recipient'].$m['reply_to'])) {
                $headers=['From'=>$s['mail_from'],'Reply-To'=>$m['reply_to'],'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
                $subject='=?UTF-8?B?'.base64_encode($m['subject']).'?=';
                $encodedBody=chunk_split(base64_encode($m['body']),76,"\r\n");
                $ok=$transport?(bool)$transport($m['recipient'],$subject,$encodedBody,$headers):@mail($m['recipient'],$subject,$encodedBody,$headers);
            }
            db()->prepare('UPDATE mail_queue SET status=?,attempts=attempts+1,updated_at=? WHERE id=?')->execute([$ok?'sent':'failed',gmdate('c'),$m['id']]);
        }
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
