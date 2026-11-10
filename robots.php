<?php
// robots.txt servito dal sito (2.1.79): .htaccess manda qui /robots.txt, così vale il testo del sito anche se qualcosa
// sul server sovrascrive il file robots.txt. L'intestazione X-Robots-Source permette di riconoscerlo dall'amministrazione.
declare(strict_types=1);
require_once __DIR__.'/PHP di sistema/core.php';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Source: valutazione-diamanti '.APP_VERSION);
header('Cache-Control: public, max-age=3600');
echo robots_text();
