# Aggiornamento 2.1.71

Version: 2.1.71

Checklist di lancio del 31/10/2026, parte «Per Domenico — Sito».

## Modifiche al sito
- **D1 Modulo**: provato in locale da mobile fino all'invio con 6 foto: arriva alla pagina di ringraziamento con il numero di richiesta.
- **D6 HTTPS**: `http://www.valutazionediamanti.it` va con un 301 su `https://www.` (prima c'era solo il 301 da senza www a www).
- **D8 Velocità**: la prima foto della home si carica subito (`fetchpriority="high"`), le altre restano «lazy»; le nuove immagini caricate dall'editor sono ridotte a 1600 px di larghezza (prima 2000).
- **D9 Etichetta «Website»**: il campo anti-spam non ha più testo nella pagina (era già nascosto alla vista, ma chi legge il testo lo trovava).
- **D10 Colore e purezza della pietra**: il campo c'è ed è visibile solo scegliendo «Pietra preziosa». Ora i gruppi di domande di orologi e pietre preziose partono nascosti già nell'HTML, quindi il titolo non compare più da solo.
- **D11 Campi obbligatori**: asterisco sui campi obbligatori, nota «I campi con * sono obbligatori» e messaggio chiaro sopra il pulsante (es. «Compila il campo obbligatorio: Email.»).
- **D12 Conversione**: a invio riuscito la pagina di ringraziamento mette nel dataLayer `{event:'valuation_submitted', form_id:'valutazione', lead_id:'R-…', language:'it'|'en'}`, una sola volta per richiesta. Con GA4 attivo e consenso Analisi parte anche l'evento `generate_lead`; con Google Ads (ID + etichetta) e consenso Marketing parte la conversione Ads, come prima.
- **D13 Marchi orologi**: in home e in «Cosa acquistiamo» al posto di «Solo i marchi trattati» c'è l'elenco: Rolex, Patek Philippe, Audemars Piguet, Cartier, Vacheron Constantin, Richard Mille, Piaget e Jacob & Co.
- **D14 Gioielli con diamanti**: «Solitari e gioielli con pietra principale da 1 ct in su. Per i gioielli con più pietre sotto 1 ct c'è un percorso dedicato nel modulo.»
- **D15 Versione EN**: le recensioni mostrano il testo originale dell'autore, senza «Translated by Google»; le stelle sono disegnate dal CSS (chi leggeva il testo vedeva 10 stelle); il pulsante WhatsApp rotondo ha un testo nascosto, così non compare l'URL in chiaro; il segno «−/+» delle FAQ è solo grafico.
- **D17 Privacy**: sotto «Accesso ai dati e fornitori» l'informativa elenca da sola i servizi attivi (da Privacy e integrazioni), dice che il modulo è gestito dal sito senza fornitori esterni, nomina l'hosting e la durata dei log tecnici. Data aggiornata al 31 ottobre 2026. Nuovi campi in Admin › Privacy e integrazioni › Dati del titolare: «Fornitore di hosting» e «Conservazione log tecnici del server (giorni)» (30 di default).

I testi cambiano anche nelle pagine già modificate dall'editor, solo dove il testo era rimasto quello di partenza.

## Già a posto (verificato nel codice)
- D2: nessun noindex sulle pagine pubbliche (solo grazie e pagine spente dal menu); canonical e hreflang IT/EN presenti; robots.txt senza blocchi.
- D3: `/sitemap.xml` con pagine IT, EN e blog.
- D5: «Preferenze cookie» nel footer apre le preferenze; Google, Meta e Clarity partono solo dopo il consenso, con Consent Mode v2 (tutto «denied» di default).
- D7: la barra contatti su mobile ha già `mailto:info@valutazionediamanti.it` e `tel:+3902466891`.

## Da fare a mano
- **Plesk**: premere «Pull now»; poi in Admin › Privacy e integrazioni compilare «Fornitore di hosting» e controllare i giorni dei log rispetto a Plesk › Registri › Rotazione dei log, e salvare.
- **Plesk › Hosting e DNS › Hosting**: «Dominio preferito» = www.valutazionediamanti.it e «Reindirizzamento 301 permanente da HTTP a HTTPS» attivo; certificato Let's Encrypt che copre sia `valutazionediamanti.it` sia `www.`.
- **comprodiamanti.it (D4)**: dove è registrato il dominio, impostare un redirect 301 (non 302) di `comprodiamanti.it` e `www.comprodiamanti.it` verso `https://www.valutazionediamanti.it/`. In alternativa aggiungerlo in Plesk come alias di dominio di valutazionediamanti.it: la regola nel `.htaccess` fa già il 301.
- **Search Console (D3)**: proprietà di dominio `valutazionediamanti.it` (verifica DNS) oppure prefisso URL con il codice in Admin › Privacy e integrazioni › Servizi indicizzazione; poi Sitemap › aggiungi `https://www.valutazionediamanti.it/sitemap.xml`.
- **Velocità (D8)**: test su https://pagespeed.web.dev per home e `/it/valutazione.html` da mobile; se le foto della sede sono pesanti, ricaricarle dall'editor (vengono ridotte e convertite in WebP).
- **Conversione (D12)**: dire ad Alessandro di usare in GTM un trigger «Evento personalizzato» `valuation_submitted` (oppure la pagina `/it/grazie.html` e `/en/thank-you.html`). GTM parte solo con consenso a Analisi e Marketing.
- **D16 Refusi recensioni**: «e'accurata», «I"ve», «Team ,amazing» sono scritti dagli autori su Google: il sito non può correggerli (non è consentito modificare le recensioni). Si possono solo segnalare o chiedere all'autore.
