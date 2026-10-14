# Aggiornamento 2.1.22 — Salvataggio codici Google Analytics / Google Ads

## Problema segnalato

Inserendo i codici in **Admin → Privacy e integrazioni** e premendo "Salva impostazioni e aggiorna il sito" sembrava che il sito si bloccasse: non compariva nulla e bisognava ricaricare la pagina.

## Causa

Il server rispondeva correttamente, ma il messaggio (successo o errore) veniva scritto in cima alla pagina, circa 2.300 px sopra il pulsante di salvataggio: dal punto in cui si clicca non si vedeva nulla. I due errori più frequenti in quel momento erano:

- **sessione scaduta** dopo 30 minuti di inattività (tipico quando si esce dall'admin per copiare i codici dalle console Google): risposta "Sessione non valida. Ricarica la pagina.";
- **valore rifiutato** quando si incolla lo snippet completo (`<script …gtag/js?id=AW-…>`) o l'ID con l'etichetta (`AW-123456789/AbCd`) o in minuscolo.

## Cosa cambia

- Il messaggio dell'admin resta fisso in alto mentre si scorre, quindi è sempre visibile dopo il salvataggio.
- Mentre l'admin è aperto e usato nelle ultime 2 ore, la sessione viene mantenuta attiva (una verifica ogni 5 minuti). Senza attività la sessione scade come prima.
- Se il token di sicurezza non è più valido (per esempio dopo un nuovo accesso in un'altra scheda) l'admin lo rinnova e riprova da solo una volta.
- Se la sessione è davvero scaduta compare "Sessione scaduta: accedi di nuovo. I dati inseriti nel modulo non sono stati persi."; dopo l'accesso si torna alla stessa sezione con i valori ancora compilati.
- Gli ID vengono ricavati anche da snippet incollati o scritti in minuscolo: `G-…`, `AW-…` (con `/etichetta` che compila "Etichetta conversione" se vuota), `GTM-…`, Meta Pixel da `fbq('init','…')`.
- Messaggi di errore chiari: "ID non valido per Google Ads: usa il formato AW-123456789."
- `api/index.php` e `admin/index.php` usano `require_once`: il server di sviluppo (`dev-router.php`) non va più in errore "Cannot redeclare db()".

Nessuna modifica al database, ai contenuti o alle pagine pubbliche.

## Installazione

1. Backup della cartella del sito e di `data/`.
2. Caricare: `PHP di sistema/settings.php`, `PHP di sistema/core.php`, `PHP di sistema/blog.php`, `api/index.php`, `admin/index.php`, `assets/admin.js`, `assets/site.css`.
3. Ricaricare l'admin (Ctrl+F5) e verificare la versione 2.1.22 nel piè di pagina.
