# Aggiornamento 2.1.28 — Scheda Email nell'admin e pagina di ringraziamento

## Pagina di ringraziamento (IT/EN)

Esiste già ed è quella a cui il modulo porta dopo un invio riuscito:

- https://www.valutazionediamanti.it/it/grazie.html
- https://www.valutazionediamanti.it/en/grazie.html

È esclusa da Google (noindex) e dalla sitemap, mostra il riferimento della richiesta e invia la conversione Google Ads (ID + etichetta), Meta "Lead" e l'evento GTM `valuation_submitted` una sola volta per richiesta, solo con il consenso Marketing. I testi si modificano in **Pagine e traduzioni → "Grazie per la richiesta"** (IT e EN).

## Nuova scheda "Email" nell'amministrazione

- **Invio**: abilitazione, nome ed email del mittente, email del gestore.
- **Metodo di invio**: SMTP autenticato con una casella del dominio (consigliato su Plesk) oppure funzione mail del server (comportamento precedente, resta il predefinito finché non si cambia).
- **Server SMTP**: server, porta, STARTTLS/SSL, utente, password (non viene mai mostrata), verifica certificato.
- **Email di prova**: salva e invia una prova di configurazione o un'anteprima della conferma al cliente (IT o EN, con dati d'esempio); l'eventuale errore del server compare in cima alla pagina.
- **Validazione del modulo e anti-spam**: nuova verifica che il dominio dell'email del cliente esista e riceva posta (MX); campo nascosto anti-bot ora presente nel modulo (il controllo sul server c'era ma il campo mancava); restano il limite di 12 invii l'ora per IP e reCAPTCHA v3 (scheda Privacy e integrazioni).
- **Email di conferma al cliente**: oggetto e testo modificabili in italiano e inglese, con segnaposto {nome}/{name}, {riferimento}/{reference}, {azienda}/{company}, {indirizzo}/{address}. Un campo svuotato torna al testo predefinito.

Le impostazioni email sono state tolte dalla scheda Privacy e integrazioni (dove resta un rimando).

## File modificati

`PHP di sistema/emails.php`, `settings.php`, `requests.php`, `core.php`, `blog.php` (versione), `api/index.php`, `admin/index.php`, `assets/admin.js`, `assets/integrations.html`, `assets/email-settings.html` (nuovo), `pages-src/valutazione.it.html`, `pages-src/valutazione.en.html`.

## Dopo il caricamento su Plesk

1. Premere **Pull now**.
2. In Admin → **Privacy e integrazioni** premere una volta **Salva impostazioni e aggiorna il sito** (rigenera il modulo con il campo anti-bot).
3. In Admin → **Email** configurare l'invio e premere **Salva e invia email di prova**.

Nessuna modifica manuale al database.
