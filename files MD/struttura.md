# Struttura e release â€” ComproDiamanti

Versione attuale: **2.1.16** â€” 8 ottobre 2026.

Sito autonomo PHP 8.3+ con SQLite. Frontend italiano/inglese, admin italiano. Non Ã¨ un plugin WordPress.

## Struttura

pages-src: frammenti OM; partials: layout riservati; content: JSON IT/EN; public: pagine statiche; api/admin/PHP di sistema: backend; data: SQLite e sessioni protetti; uploads/clienti: allegati e miniature; assets: CSS/JS/consenso; tools: bootstrap/test/originali; files MD: specifiche/documentazione.

## Todolist

- [x] Analisi prototipi e specifica OM
- [x] Conversione pagine statiche e traduzione IT/EN
- [x] Autenticazione, sessioni, CSRF e rate limit
- [x] SQLite e ruoli con pagine assegnate
- [x] Form validato e conferma dopo salvataggio
- [x] Upload reali, miniature e preview immagini/PDF
- [x] Editor testi/immagini/link, SEO, anteprima e revisioni
- [x] Noindex login/admin, robots e sitemap
- [x] Tre testi mobile su una linea senza pulsanti, font condensed
- [x] reCAPTCHA v3 configurabile e verifica server
- [x] Privacy/Cookie IT/EN e tracciamenti selezionabili
- [x] Test PHP CGI, Chrome headless, consenso e sicurezza
- [x] Documentazione e rimozione dati sintetici

## Registro release

### 2.1.0

Sostituiti login demo/localStorage con PHP/SQLite; editor OM, form e frontend IT/EN.

### 2.1.1

Richiesta aggiuntiva: noindex per login e admin via meta/header; robots e sitemap.

### 2.1.2

Richiesta aggiuntiva: allegati in uploads/clienti, ricodifica immagini, miniature, preview autenticata immagini/PDF.

### 2.1.3

Richiesta aggiuntiva: tre testi sulla stessa riga mobile, senza stile pulsante, font condensed; verificati 320/375/430 px.

### 2.1.4

Richiesta aggiuntiva: reCAPTCHA v3, Privacy/Cookie IT/EN, Google Analytics/Ads/Meta Pixel/GTM configurabili, consenso granulare e revoca. Conservazione configurabile, manutenzione, sitemap e llms.txt.

Verificati upload immagini/PDF, invio e idempotenza, autenticazione, permessi, CSRF, MIME, editor, versioni concorrenti, revisioni, noindex, blocco tracciamenti e flusso completo browser. Chiavi e ricezione eventi reali richiedono configurazione sul dominio pubblico.

## Correzione 2.1.5 â€” ingresso standard e Plesk

Segnalazione: pagine non visibili su Plesk e in locale.

- [x] Aggiungere index.html/index.php nella root senza dipendenza da rewrite
- [x] Correggere DirectoryIndex e verificare gli ingressi legacy
- [ ] Verificare navigazione via server e apertura HTML locale
- [x] Preparare archivio aggiornamento senza sovrascrivere dati server
- [x] Documentare impostazioni Plesk e conservazione dei dati

Diagnosi locale confermata: compro-diamanti.test assente da hosts e virtual host; admin/index.php e API session rispondono HTTP 200 tramite localhost. Corretto inoltre il redirect per installazioni in sottocartella che produceva /C:/laragon/... nell'URL.

`nVerifica HTTP reale: homepage, redirect legacy, admin e API su localhost rispondono 200. Dominio .test ancora da attivare: scrittura hosts negata da Windows; virtual host aggiunto ma verifica con --resolve restituisce 404, richiede ricaricamento Laragon. Test browser nuovo ingresso non completato.

## Release 2.1.6 â€” URL pubblici diretti
- [x] Pubblicare in it/en e aggiornare asset, API, canonical e sitemap.
- [x] Reindirizzare i vecchi URL public e verificare HTTP e PHP.
- [x] Documentare email e configurazione reCAPTCHA.

Verificati su Apache: /it/valutazione.html HTTP 200, vecchio /public/it/index.html HTTP 301 verso /it/index.html, API config HTTP 200; sintassi PHP core valida.

## Release 2.1.7 — ripristino preventivatore allegato
- [x] Ripristinare layout a due colonne e scelte a schede.
- [x] Mantenere backend e contenuti modificabili IT/EN.
- [x] Verificare selezioni, validazione e responsive.

## Release 2.1.8 — archivio pagine precedenti
- [x] Spostare HTML legacy e public in old-page, senza eliminare file.
- [x] Bloccare accesso web ad old-page e conservare redirect legacy.
- [x] Verificare URL attuali e vecchi.

Verifiche 2.1.7/2.1.8: test integrazione completi superati (form, allegati, autorizzazione, editor, reCAPTCHA), browser IT/EN e responsive 320/375/430/1440; Apache Syntax OK; old-page 403 e redirect legacy verificati. Nuovi campi laterali integrati senza sovrascrivere contenuti preesistenti sul server.

## Release 2.1.9 — contatti e origine diamante
- [x] Rimuovere origine dal form IT/EN e aggiornare validazione server.
- [x] Sistemare griglia contatti, consenso e riepilogo del passaggio 6.
- [x] Verificare invio diamante senza origine e layout desktop/mobile.

Verifiche 2.1.9: invio di diamante senza campo origine con allegati superato; validazione e sicurezza backend superate. Passaggio 6 IT/EN verificato nel browser su desktop e mobile: nome a tutta larghezza, contatto preferito raggruppato, telefono/email allineati, consenso e riepilogo leggibili.

## Release 2.1.10 — tabella richieste
- [x] Convertire elenco in tabella con dettagli espandibili.
- [x] Conservare stato, ricerca, filtri, CSV e anteprime allegati.
- [x] Verificare tabella e aggiornamento stato nel browser.

Verifica browser 2.1.10 con dati dimostrativi: tabella, apertura dettagli, allegato PDF, aggiornamento stato, filtro, ricerca e assenza di overflow pagina a 375px. Sintassi JavaScript valida.

## Release 2.1.11 — editor frontend e mobile
- [x] Editor sulle pagine pubbliche dopo autenticazione, con salvataggio API e controllo permessi.
- [x] Menu mobile e barre frontend/admin responsive.
- [x] Verificare editing, salvataggio, visibilità visitatori e layout mobile.

Verifiche 2.1.11: browser editor frontend (modifica testo, invio payload e versione, menu/barra a 375px, assenza editor visitatore a 320px); regressione tabella admin mobile; integrazione reale PHP completa superata (autenticazione, permessi, CSRF, pubblicazione, revisioni, form e allegati). Test browser editor usa risposte API dimostrative per non alterare contenuti reali.

## Distribuzione completa 2.1.11
- [x] Preparare ZIP completo dei file applicativi e istruzioni di installazione/aggiornamento.
- [x] Escludere dati locali, credenziali, sessioni, log, allegati e old-page.
- [x] Rendere ripetibile la generazione ZIP completo per ogni aggiornamento.

## Release 2.1.12 — toolbar mobile e lingua in header
- [x] Inserire barra mobile Valutazione, WhatsApp, Email, Telefono.
- [x] Spostare ITA/ENG nell’header e coordinare barre form/editor.
- [x] Verificare layout e collegamenti; generare ZIP completo.

Verifiche 2.1.12 nel browser: homepage, preventivatore e cookie IT/EN a 320/375/1440px; quattro collegamenti, toolbar nascosta desktop, selettore lingue in header, nessun overflow orizzontale.

## Release 2.1.13 — email e preferenze privacy
- [x] Rimuovere icona WhatsApp mobile, conservare collegamento testuale.
- [x] Label per riaprire preferenze in basso a destra sopra toolbar.
- [x] Email gestore/cliente IT/EN, coda e stato invio nel backend.
- [x] Verificare funzionalità e generare ZIP completo.

Verifiche 2.1.13: integrazione PHP form/upload/editor/permessi completa; coda email con trasporto di prova (due messaggi, idempotenza, errore, retry parziale, cancellazione dati); test browser toolbar e label privacy. Consegna email reale dipende dal servizio mail Plesk e dagli indirizzi configurati; nessun invio reale durante i test.

## Release 2.1.14 — recupero accesso admin online
- [x] Diagnosi messaggio credenziali errate e separazione database locale/online.
- [x] Comando CLI per creare/ripristinare admin senza eliminare dati.
- [x] Verificare recupero e generare ZIP completo.

Verifica isolata 2.1.14: creazione admin, reset password senza duplicazione utente, password_verify riuscita, richiesta esistente conservata. Sito remoto non accessibile tramite strumento web; recupero da eseguire sul server.

## Release 2.1.15 — reset admin via pagina PHP
- [x] Pagina browser con codice privato monouso, scadenza, CSRF e rate limit.
- [x] Verifica reset e blocco riutilizzo in ambiente isolato.
- [x] Documentazione e ZIP completo.

Verifiche 2.1.15: reset browser isolato con PHP CGI (noindex, rifiuto CSRF e codice errato, creazione admin, consumo codice e blocco successivo); integrazione completa PHP superata. Sessioni legate all’hash credenziali per invalidare accessi precedenti dopo reset.

## Release 2.1.16 — immagini frontend
- [x] Upload autenticato in uploads/site con MIME, dimensioni e ricodifica.
- [x] Pulsante upload editor, verifica e ZIP completo.

Verifica 2.1.16 su copia temporanea: upload multipart autenticato con PNG valido, file pubblico in uploads/site e CSRF errato rifiutato. Sintassi JS/PHP valida. Installer non eseguito sull’installazione corrente. ZIP completo generato.


## Release 2.1.17 — blog integrato
- [x] Riutilizzare layout, navigazione, contatti, cookie e toolbar esistenti.
- [x] Gestire articoli nella scheda Blog del backend corrente con gli stessi account e permessi.
- [x] Homepage con tre articoli in primo piano, sidebar, ricerca, archivio e pagine dedicate.
- [x] SQLite blog protetto e opzione di trasferimento a MariaDB dal backend.
- [x] Webhook autenticato e idempotente predisposto; formato reale Soro da verificare.
- [x] Aggiornare generatore pagine, sitemap e pacchetto completo.
- [x] 29 controlli di integrazione su copia isolata, senza dati originali.
- [ ] Verifica MariaDB reale, contratto Soro e distribuzione sul server remoto.

### Consegna pacchetto completo — 2.1.17
Per ogni aggiornamento consegnare sempre lo ZIP completo del progetto, oltre a eventuali pacchetti incrementali. Archivio: tools/distributions/comprodiamanti-2.1.17-completo.zip. Inclusi sito, blog e backend; esclusi database locali, credenziali e file privati dei clienti.

### Release 2.1.18 — SEO
Sitemap, canonical e dati strutturati JSON-LD generati dai contenuti pubblicati; verifica FAQ e consegna ZIP completo.

### Release 2.1.19 — Footer e sub-footer
Rimozione Blog e Area riservata dal footer; Privacy, Cookie e Preferenze cookie accanto al copyright, in entrambe le lingue e nel blog. Consegna ZIP completo.

### Release 2.1.20 — Rimozione Instagram
Eliminati i collegamenti Instagram dai template e contenuti pubblici IT/EN, incluso il footer condiviso dal blog. ZIP completo a ogni consegna.

### Release 2.1.21 — Collegamento ContentAI Pro
Endpoint blog/contentai.php (Bearer, HTTPS, bozza + approvazione dal backend), campi blog aggiuntivi con migrazione additiva, FAQ + FAQPage, Open Graph, articoli correlati Pillar/Cluster, copertina WebP, sitemap con lastmod/immagini e llms.txt con articoli. Dettagli: files MD/aggiornamento-2.1.21.md. ZIP completo a ogni consegna.
