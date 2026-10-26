# Aggiornamento 2.1.60 — Invio diretto a Google: indirizzi EN e errore 403

- L'invio diretto a Google e Bing usava per le pagine inglesi i vecchi indirizzi italiani (es. /en/acquistiamo.html), che dal 2.1.52 sono solo rimandi noindex. Ora invia gli indirizzi veri (/en/sell-diamonds.html, /en/free-valuation.html, /en/about-us.html, /en/privacy-policy.html, /en/cookie-policy.html), gli stessi di sitemap e canonical.
- Errore 403 «Failed to verify the URL ownership»: il messaggio nel pannello dice quale proprietà Search Console aprire (Dominio valutazionediamanti.it oppure prefisso URL https://www.valutazionediamanti.it/) e che serve l'autorizzazione «Proprietario»: «Completo» o «Limitato» non bastano.
- Pannello Indicizzazioni: mostra con quale indirizzo vengono inviate le pagine; guida di Search Console più precisa.
- Controllo indicizzabilità: pagine IT/EN, valutazione, FAQ, blog e articoli sono index con canonical e hreflang; restano noindex amministrazione, API, pagina di ringraziamento, vecchi indirizzi EN e risultati di ricerca del blog.
