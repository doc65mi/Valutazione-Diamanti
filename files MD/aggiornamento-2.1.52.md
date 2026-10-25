# Aggiornamento 2.1.52 · Indirizzi inglesi per le pagine EN

Version: 2.1.52

- Le pagine inglesi hanno indirizzi in inglese: /en/sell-diamonds.html, /en/free-valuation.html, /en/about-us.html,
  /en/privacy-policy.html, /en/cookie-policy.html, /en/thank-you.html. Home e FAQ restano /en/index.html e /en/faq.html.
- Menu, pulsanti, card, cambio lingua, hreflang, canonical, sitemap, modulo (pagina di ringraziamento) e banner cookie usano i nuovi indirizzi.
- Vecchi indirizzi: redirect 301 in .htaccess; in più al vecchio file resta una pagina di rimando (per i file serviti da nginx).
- Per avere il 301 anche da nginx su Plesk, in «Impostazioni Apache e nginx» → «Direttive aggiuntive di nginx»:

```
rewrite ^/en/acquistiamo\.html$ /en/sell-diamonds.html permanent;
rewrite ^/en/valutazione\.html$ /en/free-valuation.html permanent;
rewrite ^/en/chisiamo\.html$ /en/about-us.html permanent;
rewrite ^/en/privacy\.html$ /en/privacy-policy.html permanent;
rewrite ^/en/cookie\.html$ /en/cookie-policy.html permanent;
rewrite ^/en/grazie\.html$ /en/thank-you.html permanent;
```
