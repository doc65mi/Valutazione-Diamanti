<?php
declare(strict_types=1);
// Recensioni Google automatiche (2.1.33): media, numero e ultime recensioni dalla scheda Google tramite Places API (New).
// Il sito non salva recensioni nel database: la risposta di Google resta in data/ per poche ore e poi si rilegge.
// Nessun markup Review/AggregateRating: Google non lo ammette per le recensioni della propria attività.
const REVIEWS_TTL = 43200;        // 12 ore tra una lettura e l'altra
const REVIEWS_STALE = 259200;     // se Google non risponde si mostra la copia precedente al massimo per 3 giorni
const REVIEWS_RETRY = 900;        // dopo un errore si riprova dopo 15 minuti
const REVIEWS_MIN_RATING = 4;     // sul sito solo recensioni da 4 stelle in su (2.1.36); media e totale restano quelli di Google

function reviews_cache_path(string $lang): string { return ROOT.'/data/reviews-'.$lang.'.json'; }

/** Chiede a Google la scheda; restituisce i dati già ripuliti o lancia un'eccezione con il messaggio di Google. */
function reviews_fetch_google(string $lang): array {
    $s=settings();
    if($s['reviews_api_key']===''||$s['reviews_place_id']==='')throw new RuntimeException('Inserisci chiave API e Place ID.');
    if(!function_exists('curl_init'))throw new RuntimeException('Estensione PHP cURL non disponibile sul server.');
    $ch=curl_init('https://places.googleapis.com/v1/places/'.rawurlencode($s['reviews_place_id']).'?languageCode='.$lang);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER=>['X-Goog-Api-Key: '.$s['reviews_api_key'],'X-Goog-FieldMask: displayName,rating,userRatingCount,googleMapsUri,reviews','Accept: application/json',
        // Se la chiave è limitata ai referrer HTTP, Google accetta la richiesta solo con il Referer del sito (2.1.36).
        'Referer: '.seo_base().'/']]);
    $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
    if($body===false)throw new RuntimeException('Google non raggiungibile: '.$error);
    $v=json_decode((string)$body,true);
    if($http===403&&str_contains((string)$body,'referer'))throw new RuntimeException('Google ha bloccato la chiave per via della restrizione "Siti web" (referrer HTTP). In Google Cloud Console > API e servizi > Credenziali apri la chiave e in "Limitazioni delle applicazioni" scegli "Indirizzi IP" con l’IP del server, oppure "Nessuna"; in "Limitazioni delle API" lascia solo Places API (New). Il sito chiede le recensioni dal server, non dal browser.');
    if($http!==200||!is_array($v))throw new RuntimeException('Google ha risposto con codice '.$http.(is_array($v)&&isset($v['error']['message'])?': '.mb_substr((string)$v['error']['message'],0,300):'.'));
    $reviews=[];
    foreach(array_slice((array)($v['reviews']??[]),0,5) as $r){
        if((int)($r['rating']??0)<REVIEWS_MIN_RATING)continue;
        // Testo originale dell'autore (2.1.71, checklist di lancio): niente traduzioni automatiche né «Tradotta da Google».
        $text=trim((string)($r['originalText']['text']??$r['text']['text']??''));
        $author=$r['authorAttribution']??[];
        $reviews[]=['author'=>mb_substr((string)($author['displayName']??''),0,100),'author_url'=>reviews_url($author['uri']??''),'photo'=>reviews_photo($author['photoUri']??''),'rating'=>max(1,min(5,(int)($r['rating']??0))),
            'time'=>mb_substr((string)($r['relativePublishTimeDescription']??''),0,60),'text'=>mb_substr($text,0,2000),'url'=>reviews_url($r['googleMapsUri']??''),
            'translated'=>false,
            'ts'=>(int)strtotime((string)($r['publishTime']??''))];
    }
    // Places API (New) dà solo le 5 recensioni "più pertinenti": se la chiave abilita anche la Places API classica
    // si aggiungono le 5 più recenti (reviews_sort=newest). Se non è abilitata si resta con le prime (2.1.57).
    foreach(reviews_fetch_newest($lang) as $r){$dup=false;foreach($reviews as $x)if($x['author']===$r['author']&&abs($x['ts']-$r['ts'])<86400){$dup=true;break;}if(!$dup)$reviews[]=$r;}
    $reviews=reviews_sorted($reviews);
    return ['name'=>mb_substr((string)($v['displayName']['text']??''),0,150),'rating'=>round((float)($v['rating']??0),1),'count'=>(int)($v['userRatingCount']??0),'url'=>reviews_url($v['googleMapsUri']??''),
        'write_url'=>'https://search.google.com/local/writereview?placeid='.rawurlencode($s['reviews_place_id']),'reviews'=>$reviews];
}
/** Le 5 recensioni più recenti dalla Places API classica; array vuoto se la chiave non la abilita. */
function reviews_fetch_newest(string $lang): array {
    $s=settings();
    $ch=curl_init('https://maps.googleapis.com/maps/api/place/details/json?'.http_build_query(['place_id'=>$s['reviews_place_id'],'fields'=>'reviews','reviews_sort'=>'newest','reviews_no_translations'=>'true','language'=>$lang,'key'=>$s['reviews_api_key']]));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/json','Referer: '.seo_base().'/']]);
    $v=json_decode((string)curl_exec($ch),true);curl_close($ch);
    if(!is_array($v)||($v['status']??'')!=='OK')return [];
    $out=[];
    foreach((array)($v['result']['reviews']??[]) as $r){
        if((int)($r['rating']??0)<REVIEWS_MIN_RATING)continue;
        $out[]=['author'=>mb_substr((string)($r['author_name']??''),0,100),'author_url'=>reviews_url($r['author_url']??''),'photo'=>reviews_photo($r['profile_photo_url']??''),'rating'=>max(1,min(5,(int)$r['rating'])),
            'time'=>mb_substr((string)($r['relative_time_description']??''),0,60),'text'=>mb_substr(trim((string)($r['text']??'')),0,2000),'url'=>'','translated'=>false,'ts'=>(int)($r['time']??0)];
    }
    return $out;
}
/** Solo 4 e 5 stelle, dalla più recente alla più vecchia (2.1.57). */
function reviews_sorted(array $list): array {
    $list=array_values(array_filter($list,fn($r)=>(int)($r['rating']??0)>=REVIEWS_MIN_RATING));
    usort($list,fn($a,$b)=>(int)($b['ts']??0)<=>(int)($a['ts']??0));
    return array_slice($list,0,10);
}
/** Solo link https verso Google: tutto il resto viene scartato. */
function reviews_url(mixed $url): string {
    $url=is_string($url)?$url:'';$host=strtolower((string)parse_url($url,PHP_URL_HOST));
    return str_starts_with($url,'https://')&&preg_match('/(^|\.)(google\.[a-z.]+|goo\.gl)$/',$host)?$url:'';
}
/** Foto profilo dell'autore: solo immagini https di Google. */
function reviews_photo(mixed $url): string {
    $url=is_string($url)?$url:'';$host=strtolower((string)parse_url($url,PHP_URL_HOST));
    return str_starts_with($url,'https://')&&preg_match('/(^|\.)googleusercontent\.com$/',$host)?$url:'';
}
/** Solo la copia già salvata, senza chiamare Google: serve alla costruzione delle pagine statiche. */
function reviews_cached(string $lang): ?array {
    $s=settings();if(!$s['reviews_enabled'])return null;$path=reviews_cache_path($lang);
    $c=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    if(!is_array($c)||($c['place_id']??'')!==$s['reviews_place_id']||empty($c['data'])||time()-(int)($c['fetched_at']??0)>REVIEWS_STALE||(int)($c['data']['count']??0)<1)return null;
    return reviews_min($c['data']);
}
/** Anche le copie salvate prima della 2.1.36 mostrano solo le recensioni da 4 stelle in su. */
function reviews_min(array $d): array { $d['reviews']=reviews_sorted((array)($d['reviews']??[]));return $d; }
/** Dati per le pagine pubbliche: copia in cache se recente, altrimenti nuova lettura da Google (una sola alla volta). */
function reviews_public(string $lang): ?array {
    $s=settings();if(!$s['reviews_enabled'])return null;
    $path=reviews_cache_path($lang);$cache=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    if(!is_array($cache)||($cache['place_id']??'')!==$s['reviews_place_id'])$cache=null;
    $age=$cache?time()-(int)($cache['fetched_at']??0):PHP_INT_MAX;
    if($cache&&!empty($cache['data']['reviews'])&&!isset($cache['data']['reviews'][0]['ts']))$age=max($age,REVIEWS_TTL+1); // copia senza date: si rilegge subito
    $retry=$cache&&isset($cache['failed_at'])&&time()-(int)$cache['failed_at']<REVIEWS_RETRY;
    if($age>REVIEWS_TTL&&!$retry){
        $lock=fopen(ROOT.'/data/reviews.lock','c');
        if($lock&&flock($lock,LOCK_EX|LOCK_NB)){
            try{$cache=['place_id'=>$s['reviews_place_id'],'fetched_at'=>time(),'data'=>reviews_fetch_google($lang)];$fresh=true;}
            catch(Throwable $e){error_log('Recensioni Google: '.$e->getMessage());$cache=($cache??['place_id'=>$s['reviews_place_id'],'fetched_at'=>0,'data'=>null]);$cache['failed_at']=time();$cache['error']=$e->getMessage();}
            finally{atomic_write($path,json_encode($cache,JSON_UNESCAPED_UNICODE));flock($lock,LOCK_UN);fclose($lock);}
            $age=time()-(int)$cache['fetched_at'];
            if(!empty($fresh))reviews_republish($lang);
        }elseif($lock)fclose($lock);
    }
    if(!$cache||empty($cache['data'])||$age>REVIEWS_STALE||$cache['data']['count']<1)return null;
    return reviews_min($cache['data']);
}
// Le recensioni sono scritte anche nell'HTML statico (2.1.51): si vedono senza JavaScript e dai motori di ricerca;
// reviews.js poi aggiunge frecce e pallini e le aggiorna con i dati più recenti.
const REVIEWS_G_SVG='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>';
function reviews_render(DOMDocument $doc,DOMElement $box,array $d,string $lang): void {
    $en=$lang==='en';
    $el=function(string $tag,string $cls='',?string $text=null)use($doc):DOMElement{$e=$doc->createElement($tag);if($cls!=='')$e->setAttribute('class',$cls);if($text!==null)$e->appendChild($doc->createTextNode($text));return $e;};
    $link=function(string $href,string $cls,string $text)use($el):DOMElement{$a=$el($href!==''?'a':'span',$cls,$text);if($href!==''){$a->setAttribute('href',$href);$a->setAttribute('target','_blank');$a->setAttribute('rel','noopener nofollow');}return $a;};
    $g=function(string $cls='')use($doc,$el):DOMElement{$s=$el('span','gr-g'.($cls!==''?' '.$cls:''));$f=$doc->createDocumentFragment();$f->appendXML(REVIEWS_G_SVG);$s->appendChild($f);return $s;};
    $num=fn(float $v)=>$en?number_format($v,1,'.',''):number_format($v,1,',','');
    // Stelle disegnate dal CSS (2.1.71): nel testo della pagina non compaiono «★★★★★★★★★★» (10 stelle per chi legge il testo).
    $stars=function(float $v,string $cls='')use($el,$num,$en):DOMElement{$s=$el('span','gr-stars'.($cls!==''?' '.$cls:''));$s->setAttribute('role','img');$s->setAttribute('aria-label',$num($v).($en?' out of 5 stars':' stelle su 5'));$b=$el('span','gr-stars-base');$b->setAttribute('aria-hidden','true');$s->appendChild($b);$f=$el('span','gr-stars-fill');$f->setAttribute('aria-hidden','true');$f->setAttribute('style','width:'.max(0,min(100,round($v/5*100))).'%');$s->appendChild($f);return $s;};
    $plural=fn(int $n)=>$en?($n===1?'1 review':$n.' reviews'):($n===1?'1 recensione':$n.' recensioni');
    while($box->firstChild)$box->removeChild($box->firstChild);
    if($box->getAttribute('data-google-reviews')==='full'){
        $inner=$el('div','gr-inner');$side=$el('div','gr-side');$shop=$el('span','gr-shop');$f=$doc->createDocumentFragment();$f->appendXML('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16l1.5 5a3 3 0 0 1-5 2.2A3 3 0 0 1 12 12a3 3 0 0 1-4.5-.8A3 3 0 0 1 2.5 9L4 4zm1 9.5c.7.3 1.4.4 2.2.3.9 0 1.7-.3 2.3-.8.7.5 1.6.8 2.5.8s1.8-.3 2.5-.8c.6.5 1.4.8 2.3.8.8 0 1.5 0 2.2-.3V20H5v-6.5z"/></svg>');$shop->appendChild($f);$side->appendChild($shop);$info=$el('div','gr-side-info');
        $r=(float)$d['rating'];$levels=$en?['Excellent','Very good','Good']:['Eccellente','Ottimo','Buono'];$level=$r>=4.5?$levels[0]:($r>=4?$levels[1]:($r>=3.5?$levels[2]:''));
        if($level!=='')$info->appendChild($el('strong','gr-level',$level));if(($d['name']??'')!=='')$info->appendChild($el('span','gr-name',$d['name']));
        $row=$el('div','gr-score-row');$row->appendChild($el('strong','gr-score',$num($r)));$row->appendChild($stars($r));$info->appendChild($row);
        $based=$el('span','gr-based',($en?'Based on ':'Basato su ').$plural((int)$d['count']));$based->appendChild($g());$info->appendChild($based);
        $actions=$el('div','gr-actions');$actions->appendChild($link((string)($d['url']??''),'gr-btn',$en?'See all reviews':'Guarda tutte le recensioni'));$w=$link((string)($d['write_url']??''),'gr-btn gr-btn-light',$en?'Review us on ':'Recensisci su ');$w->appendChild($g());$actions->appendChild($w);$info->appendChild($actions);
        $side->appendChild($info);$inner->appendChild($side);
        if($d['reviews']){
            $main=$el('div','gr-main');$car=$el('div','gr-carousel');$track=$el('div','gr-track');$track->setAttribute('tabindex','0');
            foreach($d['reviews'] as $rv){
                $c=$el('article','gr-card');$head=$el('div','gr-card-head');$initial=mb_strtoupper(mb_substr(trim((string)$rv['author'])?:'G',0,1));
                $av=$el('span','gr-avatar gr-avatar-initial',$initial);$av->setAttribute('style','background:'.['#5C6BC0','#5E35B1','#00897B','#6D4C41','#7A6236'][mb_ord($initial)%5]);$head->appendChild($av);
                $who=$el('div','gr-who');$who->appendChild($link((string)($rv['author_url']??''),'gr-author',(string)($rv['author']?:'Google')));$who->appendChild($el('span','gr-time',(string)$rv['time']));$head->appendChild($who);$head->appendChild($g('gr-card-g'));
                $c->appendChild($head);$c->appendChild($stars((float)$rv['rating'],'gr-stars-card'));
                if(($rv['text']??'')!==''){$c->appendChild($el('p','gr-text'.(mb_strlen($rv['text'])>180?' gr-clamp':''),$rv['text']));}
                $track->appendChild($c);
            }
            $car->appendChild($track);$main->appendChild($car);$inner->appendChild($main);
        }
        $box->appendChild($inner);
    }else{
        $row=$el('div','gr-summary');$row->appendChild($el('strong','gr-score',$num((float)$d['rating'])));$row->appendChild($stars((float)$d['rating']));$row->appendChild($link((string)($d['url']??''),'gr-count',$plural((int)$d['count'])));$box->appendChild($row);
    }
    // 2.1.63 (audit, trasparenza): si dice che le recensioni mostrate sono una selezione da 4 e 5 stelle.
    $box->appendChild($el('p','gr-source',$en?'Reviews from Google Maps. A selection of 4 and 5-star reviews is shown here; the average and total include every review.':'Recensioni da Google Maps. Qui una selezione da 4 e 5 stelle; media e totale comprendono tutte le recensioni.'));
    $box->removeAttribute('hidden');
}
/** Dopo una nuova lettura da Google aggiorna le pagine statiche con il riquadro recensioni (se la build è libera). */
function reviews_republish(string $lang): void {
    $lock=fopen(ROOT.'/data/build.lock','c');if(!$lock)return;if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
    try{foreach(['home','valutazione'] as $slug)publish($slug,$lang,content($slug,$lang));}catch(Throwable $e){error_log('Recensioni Google, pagine non aggiornate: '.$e->getMessage());}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
/** Prova dall'admin: legge subito da Google, aggiorna la cache e riporta l'esito. */
function reviews_test(): array {
    $s=settings();$out=[];
    foreach(['it','en'] as $lang){
        $data=reviews_fetch_google($lang);
        atomic_write(reviews_cache_path($lang),json_encode(['place_id'=>$s['reviews_place_id'],'fetched_at'=>time(),'data'=>$data],JSON_UNESCAPED_UNICODE));
        $out[$lang]=$data;
    }
    foreach(['it','en'] as $lang)reviews_republish($lang);
    return ['rating'=>$out['it']['rating'],'count'=>$out['it']['count'],'shown'=>count($out['it']['reviews'])];
}
/** Stato per l'admin: ultima lettura riuscita ed eventuale errore. */
function reviews_status(): array {
    $c=is_file(reviews_cache_path('it'))?json_decode((string)file_get_contents(reviews_cache_path('it')),true):null;
    if(!is_array($c))return ['fetched_at'=>null,'error'=>null,'rating'=>null,'count'=>null];
    return ['fetched_at'=>!empty($c['fetched_at'])?gmdate('c',(int)$c['fetched_at']):null,'error'=>$c['error']??null,'rating'=>$c['data']['rating']??null,'count'=>$c['data']['count']??null];
}
