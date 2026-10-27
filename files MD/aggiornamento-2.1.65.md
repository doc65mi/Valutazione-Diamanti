# Aggiornamento 2.1.65

Version: 2.1.65

## robots.txt riconoscibile
- Il robots.txt scritto dal sito ora comincia con `# robots.txt di Valutazione Diamanti, generato dal sito (versione …)`.
- Se online si vede ancora il testo WordPress (wp-admin, woocommerce, Crawl-delay), quello non è il file del sito: lo manda il server prima di arrivare a httpdocs.

## Come verificare in Plesk
1. File Manager › httpdocs › robots.txt: deve contenere la riga «generato dal sito». Se c'è, il file del sito è giusto.
2. Aprire https://www.valutazionediamanti.it/robots.txt?v=2 (il parametro evita la cache del browser) e anche https://valutazionediamanti.it/robots.txt senza www.
3. Se la versione senza www mostra WordPress: in Plesk il dominio senza www punta a un'altra cartella o abbonamento (vecchio sito). Va impostato come alias / Dominio preferito www.
4. Se anche la www mostra WordPress: Plesk › valutazionediamanti.it › Impostazioni Apache e nginx › Direttive nginx aggiuntive (cercare `robots`), e Hosting › Impostazioni di hosting › Document root (deve essere httpdocs).
