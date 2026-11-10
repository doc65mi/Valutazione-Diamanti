<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
$videos=purge_expired_videos();
echo 'Video più vecchi di 15 giorni eliminati: '.$videos.PHP_EOL;
echo 'Richieste scadute eliminate: '.purge_expired_requests().PHP_EOL;
