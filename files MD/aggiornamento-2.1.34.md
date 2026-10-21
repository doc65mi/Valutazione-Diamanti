# Aggiornamento 2.1.34 — Carosello recensioni Google e credits nel footer

- Home (IT/EN): le recensioni Google sono in una sezione a tutta larghezza sotto il riquadro "Recensioni Google". A sinistra: giudizio (Eccellente/Ottimo/Buono), nome della scheda, media con stelle, "Basato su N recensioni", pulsanti "Guarda tutte le recensioni" e "Recensisci su Google". A destra: carosello di schede (foto o iniziale dell'autore, nome, data, logo Google, stelle, testo con "Leggi tutto"), frecce e pallini. 3 schede su desktop, 2 su tablet, 1 su telefono.
- Sul sito compaiono solo le recensioni da 4 stelle in su. Media e numero totale restano quelli della scheda Google.
- Google fornisce al massimo 5 recensioni tramite Places API e non fornisce le risposte del proprietario: quelle restano visibili solo su Google.
- Errore 403 "Requests from referer <empty> are blocked": il sito ora invia il proprio indirizzo come Referer e, se Google blocca ancora, l'admin spiega come cambiare la restrizione della chiave (Indirizzi IP del server oppure Nessuna, con la sola Places API (New) tra le API consentite).
- Footer di tutte le pagine IT/EN: "Credits: Oltre Media" con link a https://www.oltre.media.

Dopo il Pull in Plesk: Admin > Privacy e integrazioni > "Prova il collegamento con Google".
