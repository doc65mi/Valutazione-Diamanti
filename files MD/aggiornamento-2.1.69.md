# Aggiornamento 2.1.69

Version: 2.1.69

## reCAPTCHA visibile nel modulo di valutazione
- Il reCAPTCHA v3 era configurato e attivo (chiave del sito pubblicata correttamente), ma lo script di Google veniva caricato solo nel momento in cui si premeva «Invia». Per questo, aprendo il modulo/preventivatore, non compariva il badge reCAPTCHA né la connessione a google.com.
- Ora `assets/consent.js` carica reCAPTCHA appena si apre una pagina con il modulo (`#valuation-form`): il badge compare subito e Google raccoglie i segnali di navigazione, quindi il punteggio è più affidabile. All'invio il token viene generato come prima e verificato dal server (nessuna modifica a `verify_recaptcha`).
- Sulle altre pagine reCAPTCHA non viene caricato.
- I riferimenti a `consent.js` nelle pagine hanno `?v=2.1.69`, così i browser non usano la copia vecchia in cache.

## CSP delle cartelle it/ ed en/
- `it/.htaccess` ed `en/.htaccess` avevano ancora la CSP della 2.1.21, più restrittiva di quella della radice (2.1.44) e prevalente sulle pagine pubbliche: bloccava ad esempio Microsoft Clarity e gli «Altri codici di tracciamento». Ora usano la stessa CSP della radice.

## Dopo il Pull
- Apri l'admin o la pagina del modulo: le pagine si rigenerano con la nuova versione.
