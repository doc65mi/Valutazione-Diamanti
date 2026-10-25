# Aggiornamento 2.1.39 · Pulsante «Publish» di Frase (connettore WordPress)

Version: 2.1.39

Frase pubblica con il pulsante **Publish** solo su WordPress, Webflow, Sanity o Wix (schermata *Connect CMS*).
Il sito non è WordPress, ma risponde come un WordPress su `/wp-json/`: così si usa il connettore WordPress di Frase.

## Configurazione

1. Admin del sito → Blog → **Collegamento Frase** → **Genera / sostituisci password per Frase**. Copia la password (24 caratteri a gruppi di 4): non viene mostrata di nuovo.
2. In Frase: *Connect CMS* → **WordPress**:
   - URL: `https://www.valutazionediamanti.it/wp-admin`
   - Username: un indirizzo email a scelta
   - Application password: quella del punto 1
3. In Frase premi **Publish**: «live» = articolo online, «draft» = bozza nel blog.

## Note

- Ritorna `blog/wp-bridge.php` della prima stesura 2.1.30 (articoli, categorie, tag, copertina, meta SEO Rank Math/Yoast, registro `data/wp-bridge.log`).
- Convive con il collegamento tramite chiave API: se lo stesso articolo arriva da entrambe le strade (stesso slug) viene aggiornato, non duplicato.
- «Ultimi eventi» mostra anche le chiamate del connettore WordPress (righe `[WordPress]`).
- Se il firewall di Plesk blocca `/wp-json/`, `/wp-admin` o `/wp-login.php`, va consentito l'accesso a Frase.
