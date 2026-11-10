# Aggiornamento 2.1.79 – robots.txt WordPress online

Online `/robots.txt` mostrava un robots WordPress (wp-admin, wp-content, WooCommerce, Crawl-delay 5, commenti in italiano)
mentre `sitemap.xml` e `llms.txt` erano quelli del sito: il testo WordPress non viene dal codice del sito.

## Cosa cambia
- `robots.php` (nuovo) consegna il robots.txt del sito; `.htaccess` gli manda `/robots.txt`. Un file robots.txt
  sovrascritto sul disco non conta più, purché la richiesta arrivi ad Apache.
- Risposta con intestazione `X-Robots-Source: valutazione-diamanti <versione>`.
- Admin › Privacy e integrazioni › Servizi indicizzazione › «Controlla robots.txt online»: legge il robots.txt servito
  davvero, dice se è quello del sito o WordPress, e ripristina il file su disco se era stato sovrascritto.
- `robots_text()` in core.php è l'unico punto con il testo.

## Se dopo il Pull il controllo dice ancora «WordPress»
La richiesta viene risposta da nginx/Plesk prima del sito. In Plesk (dominio valutazionediamanti.it):
1. Siti web e domini › strumento **robots.txt** / **SEO Toolkit**: svuota o elimina il robots personalizzato.
2. **Impostazioni Apache e nginx** › «Direttive nginx aggiuntive» e «Direttive aggiuntive» Apache: togli `location = /robots.txt`
   o regole che citano robots. Lì, disattiva «Servi i file statici direttamente tramite nginx» oppure togli `txt` dall'elenco.
3. **WordPress Toolkit**: se elenca un sito su questo dominio, Scollega/Rimuovi (solo la registrazione) e disattiva la gestione robots.
4. **Operazioni pianificate**: cerca script che copiano robots.txt.
5. File Manager: nella cartella sopra httpdocs non deve esserci un'altra installazione WordPress collegata al dominio.
