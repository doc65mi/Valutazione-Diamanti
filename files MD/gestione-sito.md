# ComproDiamanti — gestione del sito

Versione applicativa **2.1.4**. Sito autonomo PHP con SQLite, non un plugin WordPress.

## Accesso e requisiti

Servire la cartella tramite Laragon/Apache, PHP 8.3+ e HTTPS in produzione. Estensioni: PDO SQLite, mbstring, DOM, cURL, fileinfo e GD. Il form e l’admin richiedono HTTP/HTTPS: non aprire le pagine con file://.

- Frontend: /public/it/index.html e /public/en/index.html; la root reindirizza all’italiano.
- Backend: /admin/.
- Primo utente: admin. Password casuale in data/first-login.txt, protetto dal web. Cambiarla dalla scheda Account: il file viene eliminato automaticamente.

L’installazione locale è già stata eseguita. Per un’altra installazione o per rigenerare le pagine esistenti: `php "PHP di sistema/install.php"`. Il comando non sovrascrive utenti, password o contenuti.

## Uso dell’admin

**Richieste:** ricerca, filtri, stato, CSV per Excel, miniature immagini e schede PDF cliccabili. Immagini e certificati si aprono in anteprima autenticata. I PDF usano una scheda identificativa, non una rasterizzazione della prima pagina.

**Pagine e traduzioni:** testi, immagini tramite URL e alt, collegamenti, title e description; anteprima, pubblicazione, revisioni e ripristino. IT ed EN si modificano separatamente.

**Account:** cambio password e creazione utenti con pagine assegnate. Gli utenti clienti non accedono a richieste, allegati o impostazioni.

**Privacy e integrazioni:** ragione sociale, indirizzo, identificativo fiscale, email privacy, conservazione, URL pubblico e servizi. Completare i dati societari effettivi prima di pubblicare. Le informative sono modificabili in Pagine.

## Form e allegati

Validazione client/server di selezioni, peso, origine, certificati, email, telefono, contatto preferito, presa visione dell’informativa e upload. Conferma solo dopo il salvataggio riuscito; retry con stesso identificativo senza duplicazioni. Le richieste sono sul server e visibili da altri browser/dispositivi. Non è configurata una notifica email.

Massimo 6 file: JPG, PNG, WebP, PDF; 5 MB ciascuno e 20 MB complessivi. Immagini fino a 16 megapixel, ricodificate in PNG con miniatura. File con nomi casuali in uploads/clienti/, negata all’accesso diretto. Preview e download richiedono amministratore. Non è presente una scansione antivirus PDF.

Configurare PHP con upload_max_filesize almeno 5M, post_max_size almeno 24M, max_file_uploads almeno 6; consigliati 256M di memoria per la ricodifica.

## reCAPTCHA v3

Registrare il dominio con Google e inserire site key, secret key, dominio atteso (senza protocollo/porta/percorso) e soglia nell’admin. Secret key non restituita né pubblicata. Campo secret vuoto mantiene la chiave salvata. Verifica server di esito, azione valuation, hostname, scadenza di due minuti e score (default 0.5). Mancanza di token o risposta non valida rifiuta l’invio; senza configurazione l’integrazione resta spenta.

Le verifiche reali con Google richiedono chiavi e dominio validi; non sono state eseguite con chiavi di produzione.

## Cookie e tracciamenti

| Servizio | Configurazione | Consenso |
|---|---|---|
| Google Analytics 4 | G-... | Analisi |
| Google Ads | AW-... ed etichetta conversione facoltativa | Marketing |
| Meta Pixel | ID numerico | Marketing |
| Google Tag Manager | GTM-... | Analisi e Marketing |

Tutti i servizi sono inizialmente disattivati. Banner con rifiuto, accettazione e scelta granulare; pulsante permanente per rivedere le preferenze. Preferenze ricordate sei mesi, con versione e timestamp. Google Consent Mode v2 inizialmente denied, modalità basic: tag opzionali non caricati prima del consenso.

La revoca aggiorna il consenso, cancella i cookie accessibili e ricarica la pagina per fermare i tag già caricati. Il sito non può cancellare cookie sul dominio di terzi. Ads e Meta ricevono una conversione solo dopo invio riuscito con consenso Marketing; GTM riceve valuation_submitted con entrambe le categorie. Non vengono passati dati del form o allegati ai tag diretti. Analytics usa URL senza query, senza Signals o personalizzazione pubblicitaria.

Configurare separatamente il contenitore GTM, rispettare il consenso ed evitare duplicazioni. La ricezione reale degli eventi va verificata negli account sul dominio pubblico. Le informative descrivono questa implementazione e vanno completate con dati societari e riesaminate se cambiano servizi/tag.

Riferimenti: [reCAPTCHA v3](https://developers.google.com/recaptcha/docs/v3), [verifica token](https://developers.google.com/recaptcha/docs/verify), [Consent Mode](https://developers.google.com/tag-platform/security/guides/consent?consentmode=basic), [Garante — cookie](https://www.garanteprivacy.it/web/guest/home/docweb/-/docweb-display/docweb/9677876).

## Struttura e sicurezza

pages-src contiene frammenti main OM; partials contiene layout/header/footer; content contiene JSON IT/EN; public contiene HTML statico senza attributi OM o runtime Design Component. data contiene SQLite e sessioni; uploads/clienti gli allegati; PHP di sistema la logica server; tools/originals i prototipi originali; files MD la documentazione.

Profilo OM implementato: testi, immagini, link, meta SEO, ruoli, anteprima, revisioni e pubblicazione statica. Non comprende i moduli generali opzionali della specifica: richtext avanzato, repeater aggiungibili, libreria media CMS, audit SEO automatico.

Login e admin: meta robots e X-Robots-Tag noindex/nofollow/noarchive; esclusi dalla sitemap. CSRF sulle scritture, rate limit, password hash, rigenerazione sessione, scadenza dopo 30 minuti di inattività, cookie HttpOnly/SameSite=Strict e Secure con HTTPS.

Apache richiede AllowOverride All, mod_headers e mod_rewrite. Su Nginx/IIS tradurre le regole: non leggono .htaccess. Negare sempre data, content, pages-src, partials, tools, files MD, PHP di sistema e uploads/clienti.

Server di sviluppo: `php -S 127.0.0.1:8092 "PHP di sistema/dev-router.php"`. Non usare il server PHP senza il router, perché non legge .htaccess.

Compilare l’URL pubblico nell’admin per canonical e sitemap assoluti. La conferma è esclusa; Privacy/Cookie sono incluse. llms.txt viene rigenerato.

Pulizia giornaliera: pianificare `php "PHP di sistema/cleanup.php"`. La pulizia avviene anche all’invio di una richiesta o consultando il backend. Il periodo di conservazione è configurabile. Salvare backup coerenti di SQLite, content e uploads/clienti; usare l’API backup SQLite o fermare le scritture. Non copiare solo il file SQLite attivo in modalità WAL. Proteggere i backup e allinearne la conservazione.

## Verifiche

- tools/test_app.py: PHP CGI, upload multipart reali, SQLite, login/logout, CSRF, validazione, MIME, preview, revisioni, conflitti e permessi; reCAPTCHA senza token rifiutato.
- tools/test_browser.cjs: Chrome headless isolato con API PHP CGI; invio completo, okpage, admin, miniature e preview IT/EN; mobile 320/375/430; blocco tag, consenso granulare, revoca; nessun errore JS.
- PHP lint, Node syntax check e validazione delle regole Apache: superati.

I fornitori esterni sono intercettati nei test. I dati sintetici sono eliminati. I generatori Python sono strumenti di bootstrap: non usare --force-bootstrap sui contenuti modificati, perché ricrea i JSON. Usare install.php per rigenerare senza perdita dei contenuti.
