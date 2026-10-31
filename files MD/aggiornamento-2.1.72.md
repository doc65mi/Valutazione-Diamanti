# Aggiornamento 2.1.72 — Recensioni Google più recenti

Version: 2.1.72

## Il problema
Sul sito le recensioni sono già filtrate (solo 4 e 5 stelle) e ordinate dalla più recente alla più vecchia,
ma Google consegna solo 5 recensioni scelte da lui («più pertinenti»), anche di 4-8 anni fa.
Le più recenti arrivano solo dalla **Places API classica** con `reviews_sort=newest`, che sulla chiave attuale non risulta abilitata.

## Cosa cambia
- `PHP di sistema/reviews.php`: registra il motivo per cui Google rifiuta la lettura «più recenti» (non esposto al pubblico).
- Admin › Privacy e integrazioni › Recensioni: mostra quante recensioni sono pubblicate e se le «più recenti» funzionano,
  con le istruzioni per attivarle; il testo d'aiuto ora chiede di abilitare sia Places API (New) sia Places API.

## Cosa fare in Google Cloud
1. API e servizi › Libreria › cerca **Places API** (non «New») › Abilita.
2. Credenziali › chiave usata dal sito › Limitazioni delle API › aggiungi **Places API** accanto a Places API (New).
3. Admin › Privacy e integrazioni › Recensioni › «Prova il collegamento con Google».
