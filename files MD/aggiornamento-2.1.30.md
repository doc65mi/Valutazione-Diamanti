# Aggiornamento 2.1.30 · Collegamento Frase

Version: 2.1.30

Frase (next.frase.io) genera gli articoli e li pubblica solo su CMS noti (WordPress, Webflow, Sanity, Wix).
Il sito ora risponde come un WordPress minimale, così il pulsante **Pubblica** e il **Content Calendar** di Frase
mandano gli articoli direttamente nel blog.

## Come collegarlo

1. Admin → Blog → **Collegamento Frase · generazione articoli**: inserisci la password amministratore e premi
   **Genera / sostituisci password Frase**. Copia la password (24 caratteri a gruppi di 4): non viene mostrata di nuovo.
2. In Frase: Integrations → WordPress.
   - URL: `https://www.valutazionediamanti.it/wp-admin`
   - Utente: un indirizzo email a scelta
   - Password: quella generata al punto 1
3. Pubblica un articolo di prova come bozza e controllalo in Admin → Blog.

## Cosa arriva nel blog

- Titolo, testo HTML (ripulito: niente script, iframe o attributi pericolosi), riassunto e slug.
- Stato: «Pubblica» in Frase = online; bozza o programmato = bozza.
- Copertina: l'immagine in evidenza viene ricodificata in WebP (max 1200 px) in `uploads/site/`; senza copertina si usa la prima immagine del testo.
- Categoria (Approfondimenti, Guide o nuove categorie create da Frase), tag e parola chiave principale nel campo parole chiave.
- Meta SEO Rank Math o Yoast → titolo e descrizione per Google.
- Un articolo già pubblicato mantiene il suo indirizzo anche se Frase cambia lo slug.
- Frase può eliminare solo gli articoli che ha creato; gli altri vengono solo rimessi in bozza.

## Diagnostica

Il pulsante **Mostra ultime richieste di Frase** elenca le ultime 40 chiamate (metodo, percorso, esito, nomi dei campi;
mai testi o password), salvate in `data/wp-bridge.log`. Se Frase usa un percorso non ancora gestito, compare lì con esito 404.

## Note tecniche

- Nuovo file `blog/wp-bridge.php`; `.htaccess` instrada `/wp-json/`, `/wp-admin`, `/wp-login.php` e `/xmlrpc.php` verso il ponte.
- Dati di runtime non tracciati da git: `data/wp-media.json`, `data/wp-terms.json`, `data/wp-bridge.log`, hash della password in `data/blog-config.json`.
- Il blog resta spento dal pannello Menu finché non lo riaccendi: gli articoli ricevuti sono visibili solo all'amministratore collegato.
- Se il firewall di Plesk (ModSecurity / fail2ban) blocca le chiamate verso `/wp-json/` o `/wp-login.php`, va consentito l'accesso a Frase.
