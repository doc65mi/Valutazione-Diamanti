# Aggiornamento 2.1.25 — Editor del sito: si modificava la foto sbagliata dell'hero

## Il problema

Nella home, la foto grande accanto al titolo è in realtà composta da tre immagini sovrapposte nello stesso punto: è visibile solo la prima ("Vetrine e lampadario"), le altre due sono trasparenti (opacità 0) ma stanno sopra. Cliccando sulla foto con l'editor si apriva quindi l'ultima, invisibile ("Ambiente interno e vetrine dello showroom"): il caricamento riusciva, ma l'immagine cambiata era quella che non si vede, quindi sembrava che non si visualizzasse né si salvasse.

## Cosa cambia

- `assets/frontend-editor.js`: quando il clic cade su un'immagine trasparente, l'editor apre l'immagine visibile che si trova nello stesso punto.
- Versione 2.1.25 in `core.php` e `blog.php`.

## Dopo il caricamento su Plesk

1. Premere **Pull now** (oppure caricare i file).
2. Nel browser ricaricare con **Ctrl+F5**, aprire l'editor, cliccare la foto, caricare l'immagine, premere **Applica** e poi **Salva**.

Nessuna modifica al database.
