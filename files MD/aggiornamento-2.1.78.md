# Aggiornamento 2.1.78

Search Console: convalida «Pagina con reindirizzamento» non riuscita.

- Blog inglese (/en/blog/ e articoli): i pulsanti «Request a valuation» puntavano a /en/valutazione.html, che reindirizza con un 301 a /en/free-valuation.html. Ora puntano direttamente all'indirizzo finale.
- sitemap.xml di partenza (quella servita subito dopo un Pull in Plesk, prima che il sito la rigeneri) conteneva ancora i vecchi indirizzi inglesi (/en/acquistiamo.html, /en/valutazione.html…) e /blog/?lang=en, tutti con reindirizzamento: aggiornata con gli indirizzi finali.

Controllo fatto rigenerando il sito in locale e seguendo tutti i link interni, canonical, hreflang e sitemap: nessun altro link punta a indirizzi che reindirizzano.

Restano (ed è corretto) i reindirizzamenti voluti: http → https, valutazionediamanti.it → www, / e /index.html → /it/index.html, vecchie pagine inglesi con nomi italiani → nuovi nomi, /blog/?lang=en → /en/blog/. Google li elenca sempre come «Pagina con reindirizzamento»: non è un errore e non vanno corretti.

File modificati: PHP di sistema/blog-view.php, PHP di sistema/core.php (versione), sitemap.xml.
