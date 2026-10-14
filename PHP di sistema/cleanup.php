<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
echo 'Richieste scadute eliminate: '.purge_expired_requests().PHP_EOL;
