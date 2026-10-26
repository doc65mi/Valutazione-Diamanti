# Aggiornamento 2.1.56 — Recensioni Google in ordine cronologico

- Sul sito compaiono solo le recensioni da 4 e 5 stelle, ordinate dalla più recente alla più vecchia (prima l'ordine era quello "più pertinenti" scelto da Google).
- Places API (New) fornisce al massimo 5 recensioni scelte da Google. Se sulla stessa chiave è abilitata anche la Places API classica, il sito aggiunge le 5 più recenti (reviews_sort=newest), senza doppioni, fino a 10 schede. Se non è abilitata nulla si rompe: restano le 5 di Places API (New), comunque ordinate per data.
- Le copie salvate dalle versioni precedenti (senza data) vengono rilette da Google alla prima visita.

Dopo il Pull in Plesk: Admin > Privacy e integrazioni > "Prova il collegamento con Google" (rilegge e ripubblica subito home e valutazione).
