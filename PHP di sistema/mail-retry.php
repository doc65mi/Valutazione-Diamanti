<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
send_queued_emails();
echo "Elaborazione coda email completata.\n";
