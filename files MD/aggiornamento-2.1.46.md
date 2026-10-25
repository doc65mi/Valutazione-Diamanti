# Aggiornamento 2.1.46 · Avviso «Blog spento» per gli articoli in 404

Version: 2.1.46

## Il problema

Un articolo pubblicato si apriva da amministratore ma dava «Articolo non trovato» (404) dopo il logout.
Causa: la voce **Blog** è spenta in Admin → **Menu** (impostazione predefinita dalla 2.1.27, per tenere il blog
nascosto finché non è pronto). Con il blog spento gli articoli si vedono solo da amministratore collegato.

## Cosa cambia

- Admin → Blog ed Elenco articoli: se il blog è spento compare un avviso giallo con il link alla scheda Menu.
- Sul blog pubblico, l'amministratore collegato vede la fascia «Anteprima amministratore: il blog è spento…»;
  i visitatori non la vedono.
- `blog.css` caricato con `?v=versione` per evitare la cache dopo l'aggiornamento.

## Per mettere online gli articoli

Admin → **Menu** → spunta **Blog** → Salva. Compare il link «Blog» nel menu del sito e gli articoli
pubblicati diventano visibili a tutti (e tornano in sitemap).
