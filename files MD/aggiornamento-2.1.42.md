# Aggiornamento 2.1.42 · Invio diretto a Google e Bing

Version: 2.1.42

Pagine e articoli nuovi o modificati vengono inviati direttamente a Google (Indexing API) e a Bing (Webmaster API),
con un limite giornaliero per motore (10 di default), sulla falsariga del plugin WordPress Indexing-SC.

## Dove si configura

Admin → **Privacy e integrazioni** → **Invio diretto a Google e Bing**. Nel pannello ci sono le istruzioni passo per passo
(riquadri «Come collegare Google» e «Come collegare Bing»), i campi per le credenziali, i pulsanti **Prova il collegamento**
e **Invia ora gli indirizzi in coda** e il registro **Ultimi invii**.

- Google: file JSON di un account di servizio con la *Web Search Indexing API* attiva; l'email dell'account va aggiunta come
  **Proprietario** della proprietà in Search Console.
- Bing: chiave API da Bing Webmaster Tools → Impostazioni → Accesso API; il sito deve essere verificato con lo stesso URL pubblico.

## Come funziona

- Coda per motore alimentata dalla stessa rigenerazione che aggiorna sitemap e IndexNow: entrano solo gli indirizzi nuovi o
  cambiati (pagine: testo della pagina; articoli: data di modifica). Alla prima attivazione entrano tutti, prima gli articoli più recenti.
- Gli articoli tolti dal sito vengono segnalati a Google come `URL_DELETED`; le pagine spente dal menu no (restano raggiungibili).
- Invio automatico al massimo ogni 15 minuti, dopo aver chiuso la risposta al visitatore (PHP-FPM), fino al limite del giorno
  (fuso Europe/Rome). Il pulsante «Invia ora» lancia l'invio subito.
- Errore temporaneo: ritentato fino a 3 volte, poi l'indirizzo è segnato come non riuscito finché non cambia di nuovo.
- Quota esaurita (429 di Google, «Quota» di Bing): il motore riparte il giorno dopo.
- Credenziali o permessi errati (401/403): il motore si ferma per 6 ore e il pannello mostra cosa sistemare; salvare nuove credenziali lo sblocca.
- IndexNow resta attivo come prima.

## Sicurezza

- Credenziali in `data/indexing.json`, stato e registro in `data/indexing-state.json`, token in `data/indexing-token.json`:
  file 0600, fuori da git (`.gitignore`), cartella `data/` negata da `.htaccess`.
- L'admin non riceve mai la chiave privata né la chiave API: vede solo una spunta verde e l'email dell'account di servizio.

## API usate

- Google: `POST https://oauth2.googleapis.com/token` (JWT RS256, scope `https://www.googleapis.com/auth/indexing`),
  `POST https://indexing.googleapis.com/v3/urlNotifications:publish`.
- Bing: `POST https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=…`, per la prova
  `GET …/GetUrlSubmissionQuota?siteUrl=…&apikey=…`.

## File

- Nuovo `PHP di sistema/indexing.php`; agganci in `core.php` (`seo_indexes`, `ensure_published`) e `api/index.php`
  (`indexing`, `indexing-save`, `indexing-test`, `indexing-run`); pannello in `assets/integrations.html` e `assets/admin.js`.
