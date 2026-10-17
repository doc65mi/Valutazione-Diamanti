# Aggiornamento 2.1.31 · Pubblicazione diretta da ContentAI Pro

Version: 2.1.31

Il ricevitore degli articoli di ContentAI Pro (`blog/contentai.php`, dalla 2.1.21) ora è configurabile e verificabile
dall'admin, e può pubblicare gli articoli direttamente online.

## Cosa cambia

- Admin → Blog: il riquadro **Collegamento ContentAI Pro e pubblicazione automatica** (prima chiamato «Soro») mostra
  l'indirizzo giusto (`https://www.valutazionediamanti.it/blog/contentai.php`, prima indicava il vecchio `webhook.php`)
  e i dati da inserire in ContentAI Pro.
- Nuovo pulsante **Ultimi eventi**: registro `data/contentai.log` (non tracciato da git, permessi 0600) con ogni invio
  ricevuto: ping, articolo nuovo o aggiornato, bozza o pubblicato, rifiuti con il motivo (token non generato, token
  errato, header Authorization non inoltrato dal server, slug non valido, evento sconosciuto) e i nomi dei campi ricevuti.
  Mai token né testi degli articoli.

## Come collegarlo

1. Admin → Blog → Collegamento ContentAI Pro: inserisci la password amministratore e premi **Genera / sostituisci token**.
   Il token si vede una sola volta: copialo direttamente in ContentAI Pro (rigenerarlo revoca il precedente).
2. Spunta **Pubblica subito i nuovi articoli ricevuti** e premi **Salva preferenza** (vale anche per Frase).
3. In ContentAI Pro, Siti & Tenant: dominio `www.valutazionediamanti.it`. Content Studio → Destinazione di pubblicazione:
   - URL `https://www.valutazionediamanti.it/blog/contentai.php`, metodo `POST`
   - Header `Authorization: Bearer <token>`
   - Segreto firma HMAC vuoto, schema URL `/blog/articolo/{slug}`
4. **Invia test**: in Ultimi eventi deve comparire «ping ricevuto». Poi invia un articolo.

## Regole

- Articolo nuovo: online subito con «Pubblica subito» attivo, altrimenti bozza.
- Articolo già ricevuto: testo, meta, FAQ e copertina aggiornati; slug, stato, data e primo piano restano quelli del sito.
- Se il blog è spento in Admin → Menu, gli articoli pubblicati restano invisibili ai visitatori finché non lo riaccendi.

## Verifiche

Su copia isolata (PHP 8.3, SQLite, HTTPS simulato): ping, token errato, header assente, articolo nuovo pubblicato
direttamente, slug non valido, evento sconosciuto; ogni caso scritto correttamente nel registro.
Non verificato: invio reale da ContentAI Pro al sito online.
