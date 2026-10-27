# Aggiornamento 2.1.63

Version: 2.1.63

Correzioni dalla verifica del sito del 27/10/2026.

## Pagine di riserva nel repository
- I file `it/*.html` ed `en/*.html` tracciati erano ancora quelli della 2.1.23 (fasce di peso con «2–5 ct», email sl@ladiamanteria.com, privacy senza link). Dopo ogni Pull di Plesk restavano online finché qualcuno non apriva una pagina con JavaScript. Ora sono rigenerati con la versione attuale; al primo accesso il sito li riscrive comunque con i testi modificati dall'editor.

## Modulo di valutazione
- Fasce di peso: 1–2, 2–3, 3–5, oltre 5 ct, «Non lo so» (la fascia 2–5 era già stata tolta nella 2.1.55, restava solo nella pagina vecchia).
- Consenso: link «Leggi l'informativa privacy» accanto alla casella.
- Un solo campo anti-spam (prima erano due con lo stesso nome), con `aria-hidden` e `inert`: gli screen reader non lo leggono e non si raggiunge con la tastiera.
- Email del titolare: ovunque info@valutazionediamanti.it (già dalla 2.1.61).

## Blog
- Estratti tagliati a parola intera con «…», anche quelli già salvati tronchi (es. «da solo a stabi»), in blog, home e meta description.
- `og:title` e `twitter:title` uguali al `<title>` (con «| Valutazione Diamanti»).
- Blog inglese su `/en/blog/` e `/en/blog/<slug>`. I vecchi `/blog/?lang=en` e `/blog/articolo/<slug>?lang=en` rimandano con un 301. hreflang IT/EN/x-default sull'elenco del blog. Sitemap aggiornata.
- Se cambi lo slug di un articolo (es. in inglese per gli articoli EN), il vecchio indirizzo rimanda con un 301 al nuovo.

## Open Graph
- Immagini 1200×630 per ogni pagina, in italiano e inglese (`assets/og/`), con logo e colori del sito: home, cosa acquistiamo, valutazione, chi siamo, FAQ, privacy, cookie, blog. Gli articoli senza copertina usano quella del blog. Script: `tools/og_images.py`.
- `summary_large_image` solo con immagini orizzontali di almeno 1000 px, altrimenti `summary`.
- Se in Privacy e integrazioni è scelta un'immagine Open Graph, quella ha la precedenza su tutte le pagine.

## Testi e navigazione
- «Worldwide» nelle pagine italiane diventa «Nel mondo».
- «Bonifico istantaneo» diventa «bonifico tracciabile», come nel resto del sito (anche nella meta description).
- Home: la seconda «Un solo interlocutore, un processo più lineare.» diventa «Nessuna asta e nessun intermediario: tratti direttamente con chi acquista.».
- Testi alternativi diversi per le tre foto della galleria in home (prima erano uguali a quelle della presentazione).
- Intestazione di valutazione e grazie: «Vendere un diamante», «Come funziona» e Blog come nella home. Blog anche nel footer, dopo le FAQ (se acceso nel pannello Menu).
- Telefono sempre +39 02 466 891, anche nelle meta description.
- Cookie policy: l'elenco «Servizi configurati» è scritto nella pagina, si vede anche senza JavaScript.
- Recensioni Google: sotto il riquadro si dice che è una selezione da 4 e 5 stelle e che media e totale comprendono tutte le recensioni.

## Indirizzo principale
- `/` risponde con un 301 verso `/it/index.html` anche da `.htaccess` (oltre che da `index.php`).
