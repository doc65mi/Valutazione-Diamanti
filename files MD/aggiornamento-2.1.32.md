# Aggiornamento 2.1.32 — Google Search Console e Bing Webmaster Tools

- Admin > Privacy e integrazioni: nuova sezione "Google Search Console e Bing Webmaster Tools".
- Meta tag `google-site-verification` e `msvalidate.01` inseriti in tutte le pagine IT/EN e nel blog (si può incollare il tag intero).
- File di verifica nella radice del sito: file HTML di Google (googleXXXX.html) e BingSiteAuth.xml, rigenerati a ogni salvataggio e fuori da git.
- IndexNow (facoltativo): chiave generata dal sito, avviso automatico a Bing degli indirizzi nuovi o modificati della sitemap, pulsante per inviare tutto.
- robots.txt continua a indicare https://www.valutazionediamanti.it/sitemap.xml.

Dopo il Pull in Plesk: aprire Admin > Privacy e integrazioni, incollare i codici e premere "Salva impostazioni e aggiorna il sito".
