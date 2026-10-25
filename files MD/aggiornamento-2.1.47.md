# Aggiornamento 2.1.47 · Solo immagini caricate sul sito

Version: 2.1.47

## Il problema

Alcune immagini puntavano ancora al vecchio prototipo `diamond-shine-guide.lovable.app` (showroom-1/2/4 in home
e in Valutazione, le tre immagini Silvera in Chi siamo). La home le scaricava anche se non erano visibili.

## Cosa cambia

- Sul sito restano solo le immagini caricate dall'editor (cartella `uploads/site`). Ogni indirizzo esterno
  rimasto, anche nei testi già salvati in `data/content`, diventa «nessuna immagine»: la pagina non lo carica.
- Ai visitatori l'immagine mancante non si vede. In **Modifica pagina** compare come riquadro tratteggiato:
  cliccalo e usa «Carica una nuova immagine».
- Presentazione foto in home: se la foto in primo piano manca, va in primo piano la prima foto caricata.
- L'editor non accetta più indirizzi di immagini di altri siti: si caricano dal pulsante.
- Testi di partenza (`content/`, `pages-src/`) ripuliti dagli indirizzi Lovable.

Le pagine si rigenerano da sole alla prima visita dopo il Pull.
