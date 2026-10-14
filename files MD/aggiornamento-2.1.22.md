# Aggiornamento 2.1.22 — Salvataggio codici Google Analytics / Google Ads

## Problema segnalato

Inserendo i codici in **Admin → Privacy e integrazioni** e premendo "Salva impostazioni e aggiorna il sito" sembrava che il sito si bloccasse: non compariva nulla e bisognava ricaricare la pagina.

## Causa

Il server rispondeva correttamente, ma il messaggio (successo o errore) veniva scritto in cima alla pagina, circa 2.300 px sopra il pulsante di salvataggio: dal punto in cui si clicca non si vedeva nulla. I due errori più frequenti in quel momento erano:

- **sessione scaduta** dopo 30 minuti di inattività (tipico quando si esce dall'admin per copiare i codici dalle console Google): risposta "Sessione non valida. Ricarica la pagina.";
- **valore rifiutato** quando si incolla lo snippet completo (`<script …gtag/js?id=AW-…>`) o l'ID con l'etichetta (`AW-123456789/AbCd`) o in minuscolo.

## Causa del blocco del sito online (trovata nel log nginx di Plesk)

`openat() "…/httpdocs/it/index.html" failed (13: Permission denied)`: salvando le impostazioni il sito rigenera tutte le pagine statiche (`it/`, `en/`, `sitemap.xml`, `llms.txt`, `robots.txt`). La scrittura usava un file temporaneo creato con permessi `0600` (leggibile solo dall'utente PHP); nginx, che su Plesk serve i file statici con un altro utente, non poteva più leggerli e tutto il sito pubblico rispondeva 403. Il codice Google Ads non c'entrava: succedeva con qualsiasi salvataggio delle impostazioni (e, per la singola pagina, con il salvataggio dall'editor).

Ora le pagine pubbliche vengono scritte con permessi `0644`. I file riservati (contenuti JSON, credenziali) restano `0600`.

## Cosa cambia

- Pagine pubbliche, sitemap, llms.txt e robots.txt rigenerati con permessi `0644`.

- Il messaggio dell'admin resta fisso in alto mentre si scorre, quindi è sempre visibile dopo il salvataggio.
- Mentre l'admin è aperto e usato nelle ultime 2 ore, la sessione viene mantenuta attiva (una verifica ogni 5 minuti). Senza attività la sessione scade come prima.
- Se il token di sicurezza non è più valido (per esempio dopo un nuovo accesso in un'altra scheda) l'admin lo rinnova e riprova da solo una volta.
- Se la sessione è davvero scaduta compare "Sessione scaduta: accedi di nuovo. I dati inseriti nel modulo non sono stati persi."; dopo l'accesso si torna alla stessa sezione con i valori ancora compilati.
- Gli ID vengono ricavati anche da snippet incollati o scritti in minuscolo: `G-…`, `AW-…` (con `/etichetta` che compila "Etichetta conversione" se vuota), `GTM-…`, Meta Pixel da `fbq('init','…')`.
- L'ID viene estratto già nel browser, prima dell'invio: lo snippet `<script>` non arriva più al server. Su Plesk il firewall applicativo (ModSecurity) blocca le richieste che contengono `<script>` e, con Fail2Ban attivo, può bannare per alcuni minuti l'IP di chi le invia: da quell'IP tutto il sito sembra "in crash".
- Messaggi di errore chiari: "ID non valido per Google Ads: usa il formato AW-123456789."
- `api/index.php` e `admin/index.php` usano `require_once`: il server di sviluppo (`dev-router.php`) non va più in errore "Cannot redeclare db()".

Nessuna modifica al database, ai contenuti o alle pagine pubbliche.

## Se il sito online smette di rispondere dopo il salvataggio

In Plesk controllare:

- **Domini → sito → Log**: righe `ModSecurity: Access denied` o errori PHP all'ora del salvataggio;
- **Strumenti e impostazioni → Blocco indirizzi IP (Fail2Ban) → IP bannati**: se c'è il proprio IP, sbloccarlo;
- che le cartelle `it/`, `en/`, `data/` e i file `sitemap.xml`, `llms.txt`, `robots.txt` siano scrivibili dall'utente PHP del dominio (il salvataggio rigenera tutte le pagine).

## Ripristino immediato sul server (prima dell'aggiornamento)

Plesk → File Manager → `httpdocs`: selezionare i file `.html` in `it/` ed `en/` più `sitemap.xml`, `llms.txt`, `robots.txt` → **Modifica permessi** → `644` (lettura per tutti). Oppure via SSH dalla cartella `httpdocs`:

```sh
chmod 644 it/*.html en/*.html sitemap.xml llms.txt robots.txt
```

## Installazione

1. Backup della cartella del sito e di `data/`.
2. Caricare: `PHP di sistema/settings.php`, `PHP di sistema/core.php`, `PHP di sistema/blog.php`, `api/index.php`, `admin/index.php`, `assets/admin.js`, `assets/site.css`.
3. Ricaricare l'admin (Ctrl+F5) e verificare la versione 2.1.22 nel piè di pagina.
