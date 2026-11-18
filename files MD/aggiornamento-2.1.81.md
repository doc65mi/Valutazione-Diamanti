# Aggiornamento 2.1.81 — Conversione Google Ads «form»

**Version: 2.1.81**

## Cosa cambia
- Conversione Google Ads configurata: nome **form**, ID **AW-1018519688**, etichetta **e3yICKjC4QMQiMHV5QM**, valore **1,00 EUR**.
  Si applica da sola, una volta, alle impostazioni salvate sul sito (Google Ads viene anche abilitato). Poi resta modificabile da
  Amministrazione › Privacy e integrazioni › Tracciamenti › Google Ads.
- L'evento di conversione ora invia anche `value` e `currency` (come lo snippet di Google Ads), oltre a `transaction_id` = numero
  della richiesta, così Google non conta doppioni se la pagina di ringraziamento viene ricaricata.
- Nuovi campi in Google Ads: «Nome conversione», «Valore della conversione», «Valuta».
- Nel campo ID si può scrivere anche solo il numero (1018519688): diventa AW-1018519688.
- Cambiando l'ID Ads il banner cookie viene richiesto di nuovo ai visitatori (versione del consenso +1).

## Dove parte la conversione
Solo sulla pagina di ringraziamento (it/grazie.html, en/thank-you.html) dopo un invio riuscito del modulo, una volta per richiesta.
Con la modalità di consenso «avanzata» parte anche prima del consenso (ping senza cookie, Consent Mode v2).

## Da fare dopo il Pull in Plesk
1. Pull now in Plesk.
2. Aprire una pagina del sito (rigenera le pagine e applica la configurazione).
3. Non incollare lo snippet `gtag('event','conversion'…)` in «Altri codici di tracciamento»: farebbe partire la conversione su ogni pagina.
4. Verificare con Google Tag Assistant un invio di prova del modulo.
