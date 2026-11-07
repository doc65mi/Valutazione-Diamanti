# Aggiornamento 2.1.77 — tracciamento Google Ads

Version: 2.1.77

## Problemi trovati
1. **Tag Google Ads invisibile prima del consenso.** Il tag (gtag.js con AW-…) veniva caricato solo dopo che il visitatore accettava i cookie Marketing. Chi rifiuta o ignora il banner (la maggioranza) non generava alcun segnale: Google Ads e Tag Assistant segnalavano il tag come «non rilevato» e le conversioni non venivano né contate né stimate.
2. **gclid cancellato dall'indirizzo.** Il tag riceveva `page_location` senza la query string: il parametro `gclid` (e `gbraid`/`wbraid`) dell'annuncio veniva tolto, quindi il clic non poteva essere collegato alla conversione.
3. **Conversione senza etichetta = nessuna conversione.** Il campo «Etichetta conversione» era indicato come facoltativo, ma senza etichetta l'evento di conversione non parte mai.

## Correzioni
- **Consent Mode v2 avanzata** (nuova impostazione, predefinita): i tag Google (GA4 e Ads) si caricano subito con consenso negato e inviano solo ping senza cookie; al consenso passano a «granted». Impostazione in Amministrazione › Privacy e integrazioni › Tracciamenti › Google Ads › «Modalità di consenso Google» (avanzata / di base).
- Stato di consenso iniziale preso dalla scelta già salvata (niente ritardo per chi ha già accettato), `wait_for_update`, `ads_data_redaction` e `url_passthrough` (il gclid sopravvive tra home → valutazione → grazie anche senza cookie).
- `page_location` conserva i parametri di campagna (gclid, gbraid, wbraid, dclid, gad_source, gad_campaignid, _gl, utm_*) e scarta tutto il resto.
- Conversione Ads con `transaction_id` = numero richiesta (niente doppioni); parte una sola volta sulla pagina di ringraziamento dopo un invio riuscito.
- Testo del campo etichetta chiarito; banner e cookie policy spiegano i segnali senza cookie.

## Da fare per chi gestisce la campagna
- In Privacy e integrazioni › Tracciamenti: Google Ads abilitato, ID `AW-…` e **etichetta di conversione** compilati (si può incollare lo snippet `send_to: 'AW-…/…'`), poi Salva.
- In Google Ads l'azione di conversione deve essere di tipo «Sito web» con tag Google (non importata da GA4, altrimenti va segnato l'evento `generate_lead` come evento chiave in GA4).
- Verifica con Tag Assistant (tagassistant.google.com) sulla pagina di ringraziamento dopo un invio di prova.
