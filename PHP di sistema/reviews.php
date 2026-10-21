<?php
declare(strict_types=1);
// Recensioni Google automatiche (2.1.33): media, numero e ultime recensioni dalla scheda Google tramite Places API (New).
// Il sito non salva recensioni nel database: la risposta di Google resta in data/ per poche ore e poi si rilegge.
// Nessun markup Review/AggregateRating: Google non lo ammette per le recensioni della propria attività.
const REVIEWS_TTL = 43200;        // 12 ore tra una lettura e l'altra
const REVIEWS_STALE = 259200;     // se Google non risponde si mostra la copia precedente al massimo per 3 giorni
const REVIEWS_RETRY = 900;        // dopo un errore si riprova dopo 15 minuti

function reviews_cache_path(string $lang): string { return ROOT.'/data/reviews-'.$lang.'.json'; }

/** Chiede a Google la scheda; restituisce i dati già ripuliti o lancia un'eccezione con il messaggio di Google. */
function reviews_fetch_google(string $lang): array {
    $s=settings();
    if($s['reviews_api_key']===''||$s['reviews_place_id']==='')throw new RuntimeException('Inserisci chiave API e Place ID.');
    if(!function_exists('curl_init'))throw new RuntimeException('Estensione PHP cURL non disponibile sul server.');
    $ch=curl_init('https://places.googleapis.com/v1/places/'.rawurlencode($s['reviews_place_id']).'?languageCode='.$lang);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER=>['X-Goog-Api-Key: '.$s['reviews_api_key'],'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews','Accept: application/json']]);
    $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
    if($body===false)throw new RuntimeException('Google non raggiungibile: '.$error);
    $v=json_decode((string)$body,true);
    if($http!==200||!is_array($v))throw new RuntimeException('Google ha risposto con codice '.$http.(is_array($v)&&isset($v['error']['message'])?': '.mb_substr((string)$v['error']['message'],0,300):'.'));
    $reviews=[];
    foreach(array_slice((array)($v['reviews']??[]),0,5) as $r){
        $text=trim((string)($r['text']['text']??''));if($text==='')continue;
        $author=$r['authorAttribution']??[];
        $reviews[]=['author'=>mb_substr((string)($author['displayName']??''),0,100),'author_url'=>reviews_url($author['uri']??''),'rating'=>max(1,min(5,(int)($r['rating']??0))),
            'time'=>mb_substr((string)($r['relativePublishTimeDescription']??''),0,60),'text'=>mb_substr($text,0,2000),'url'=>reviews_url($r['googleMapsUri']??''),
            'translated'=>isset($r['originalText']['languageCode'],$r['text']['languageCode'])&&$r['originalText']['languageCode']!==$r['text']['languageCode']];
    }
    return ['rating'=>round((float)($v['rating']??0),1),'count'=>(int)($v['userRatingCount']??0),'url'=>reviews_url($v['googleMapsUri']??''),'reviews'=>$reviews];
}
/** Solo link https verso Google: tutto il resto viene scartato. */
function reviews_url(mixed $url): string {
    $url=is_string($url)?$url:'';$host=strtolower((string)parse_url($url,PHP_URL_HOST));
    return str_starts_with($url,'https://')&&preg_match('/(^|\.)(google\.[a-z.]+|goo\.gl)$/',$host)?$url:'';
}
/** Dati per le pagine pubbliche: copia in cache se recente, altrimenti nuova lettura da Google (una sola alla volta). */
function reviews_public(string $lang): ?array {
    $s=settings();if(!$s['reviews_enabled'])return null;
    $path=reviews_cache_path($lang);$cache=is_file($path)?json_decode((string)file_get_contents($path),true):null;
    if(!is_array($cache)||($cache['place_id']??'')!==$s['reviews_place_id'])$cache=null;
    $age=$cache?time()-(int)($cache['fetched_at']??0):PHP_INT_MAX;
    $retry=$cache&&isset($cache['failed_at'])&&time()-(int)$cache['failed_at']<REVIEWS_RETRY;
    if($age>REVIEWS_TTL&&!$retry){
        $lock=fopen(ROOT.'/data/reviews.lock','c');
        if($lock&&flock($lock,LOCK_EX|LOCK_NB)){
            try{$cache=['place_id'=>$s['reviews_place_id'],'fetched_at'=>time(),'data'=>reviews_fetch_google($lang)];}
            catch(Throwable $e){error_log('Recensioni Google: '.$e->getMessage());$cache=($cache??['place_id'=>$s['reviews_place_id'],'fetched_at'=>0,'data'=>null]);$cache['failed_at']=time();$cache['error']=$e->getMessage();}
            finally{atomic_write($path,json_encode($cache,JSON_UNESCAPED_UNICODE));flock($lock,LOCK_UN);fclose($lock);}
            $age=time()-(int)$cache['fetched_at'];
        }elseif($lock)fclose($lock);
    }
    if(!$cache||empty($cache['data'])||$age>REVIEWS_STALE||$cache['data']['count']<1)return null;
    return $cache['data'];
}
/** Prova dall'admin: legge subito da Google, aggiorna la cache e riporta l'esito. */
function reviews_test(): array {
    $s=settings();$out=[];
    foreach(['it','en'] as $lang){
        $data=reviews_fetch_google($lang);
        atomic_write(reviews_cache_path($lang),json_encode(['place_id'=>$s['reviews_place_id'],'fetched_at'=>time(),'data'=>$data],JSON_UNESCAPED_UNICODE));
        $out[$lang]=$data;
    }
    return ['rating'=>$out['it']['rating'],'count'=>$out['it']['count'],'shown'=>count($out['it']['reviews'])];
}
/** Stato per l'admin: ultima lettura riuscita ed eventuale errore. */
function reviews_status(): array {
    $c=is_file(reviews_cache_path('it'))?json_decode((string)file_get_contents(reviews_cache_path('it')),true):null;
    if(!is_array($c))return ['fetched_at'=>null,'error'=>null,'rating'=>null,'count'=>null];
    return ['fetched_at'=>!empty($c['fetched_at'])?gmdate('c',(int)$c['fetched_at']):null,'error'=>$c['error']??null,'rating'=>$c['data']['rating']??null,'count'=>$c['data']['count']??null];
}
