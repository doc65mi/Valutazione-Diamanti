<?php
// Rigenera le pagine di riserva it/*.html e en/*.html tracciate in git (servite dopo un Pull finché il sito non le riscrive).
// Va lanciato con una cartella data/ pulita: usa i testi di partenza in content/ e il Blog acceso, come sul sito online.
// Uso: php tools/fallback_pages.php   (poi non committare sitemap.xml, llms.txt, robots.txt)
require __DIR__.'/../PHP di sistema/core.php';
$s=settings();$s['menu_hidden']=[];db()->prepare('INSERT INTO settings(id,value) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET value=excluded.value')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
foreach(PAGE_SLUGS as $slug)foreach(['it','en'] as $lang)publish($slug,$lang,content($slug,$lang));
echo "Pagine di riserva rigenerate con il Blog acceso.\n";
