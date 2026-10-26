# Aggiornamento 2.1.53 · Coordinate e orari nei dati per Google

Version: 2.1.53

- Privacy e integrazioni > Dati del titolare: nuovi campi **Coordinate della sede** (come le copia Google Maps, es. 45.4685, 9.1660)
  e **Orari di apertura** (es. `Mo-Fr 09:30-18:30; Sa 10:00-13:00`). Finiscono nel JSON-LD JewelryStore come `geo` e
  `openingHoursSpecification`; i campi vuoti non vengono scritti.

## robots.txt ancora «WordPress» sul sito

Il sito genera `robots.txt` con le sole righe admin/api e Sitemap (da 2.1.48). Se online compare ancora il modello WordPress
(woocommerce, Crawl-delay) lo serve Plesk, non il sito: Siti web e domini → valutazionediamanti.it → **robots.txt** (strumenti SEO):
togliere il contenuto personalizzato oppure incollare:

```
User-agent: *
Disallow: /admin/
Disallow: /api/
Allow: /

Sitemap: https://www.valutazionediamanti.it/sitemap.xml
```

Controllare anche che in Strumenti e impostazioni non ci sia un robots.txt globale e che WordPress Toolkit non gestisca il dominio.
