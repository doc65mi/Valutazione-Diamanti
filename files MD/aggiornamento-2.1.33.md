# Aggiornamento 2.1.33 — Recensioni Google automatiche

- Admin > Privacy e integrazioni: nuova sezione "Recensioni Google" con attivazione, Place ID (già compilato con quello della scheda), chiave API Google Places (non viene mai mostrata dopo il salvataggio) e pulsante "Prova il collegamento con Google".
- Home (IT/EN): nel riquadro "Recensioni Google" compaiono media, stelle, numero di recensioni e fino a 5 recensioni scelte da Google (3 visibili, le altre con "Mostra altre recensioni"). Testi lunghi con "Leggi tutto", nota "Tradotta da Google" quando serve, link all'autore e alla recensione, dicitura "Recensioni da Google Maps".
- Pagina Valutazione (IT/EN): nel riquadro laterale compaiono media, stelle e numero di recensioni.
- I dati arrivano da Places API (New) e si rileggono al massimo ogni 12 ore per lingua (data/reviews-it.json e data/reviews-en.json, fuori da git). Se Google non risponde si usa la copia precedente per massimo 3 giorni, poi restano solo i link alla scheda come prima.
- Nessun markup schema.org Review/AggregateRating: Google non lo consente per le recensioni della propria attività.
- Con le recensioni spente le pagine restano identiche a prima.

Dopo il Pull in Plesk:
1. In Google Cloud Console abilitare "Places API (New)" e creare una chiave API, limitata alla sola Places API (New) e, se possibile, all'IP del server.
2. Admin > Privacy e integrazioni > Recensioni Google: incollare la chiave, spuntare "Mostra le recensioni Google sul sito" e premere "Salva impostazioni e aggiorna il sito".
3. Premere "Prova il collegamento con Google" per verificare.

Costo: circa 120 letture al mese (2 lingue, ogni 12 ore), ampiamente dentro la quota gratuita mensile di Google Maps Platform.
