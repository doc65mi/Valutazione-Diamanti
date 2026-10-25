# Aggiornamento 2.1.48 · Correzioni dall'audit SEO del 25/10/2026

Version: 2.1.48

## Cosa cambia

- **Title e meta description** uniche per ogni pagina IT/EN e per il blog, con i testi dell'audit (tabella 2.1).
- **Open Graph e Twitter** su tutte le pagine (e sull'elenco del blog): titolo, testo e foto nelle condivisioni WhatsApp.
  Foto: quella indicata in Privacy e integrazioni, altrimenti `uploads/site/og-cover.jpg`, altrimenti la prima foto della pagina.
- **Dati strutturati**: `JewelryStore` con indirizzo, telefono, mappa, P.IVA e ragione sociale al posto dell'Organization generica.
- **FAQ**: 20 domande in 4 gruppi (IT e EN), anche nel JSON-LD FAQPage; in home le prime 4.
- **Acquistiamo**: la frase sulle gemme colorate esce da «Cosa non acquistiamo».
- **Telefono** ovunque nel formato +39 02 466891.
- **Footer**: riga con i dati societari; **privacy**: titolare con ragione sociale, marchio, P.IVA e sedi.
  Nuovi campi in Privacy e integrazioni > Dati del titolare: REA, licenza art. 127 TULPS, immagine per le condivisioni.
- **robots.txt** essenziale (admin, api, Sitemap), **sitemap** con `lastmod` su tutte le pagine.
- **Redirect 301**: radice del sito (era 302), `valutazionediamanti.it` senza www e `comprodiamanti.it` verso www.

I testi già modificati dall'editor restano come sono: si aggiornano solo i campi rimasti uguali al vecchio testo
di partenza (`content/previous-defaults.json`). Le pagine si rigenerano da sole alla prima visita dopo il Pull.
