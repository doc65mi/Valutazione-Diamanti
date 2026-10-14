# Aggiornamento 2.1.24 — Caricamento immagini dall'editor del sito

## Il problema

Caricando un'immagine dall'editor delle pagine (Modifica pagina → clic su un'immagine → "Carica una nuova immagine") l'immagine non compariva e non restava salvata. Le cause, riprodotte in locale:

1. **Permessi del file.** Su Plesk PHP-FPM può creare i file con permessi **600**: l'immagine veniva salvata in `uploads/site/` ma nginx (che serve i file statici con un altro utente) non poteva leggerla e rispondeva 403. È lo stesso problema delle pagine risolto nella 2.1.22.
2. **Foto troppo grandi per PHP.** Il limite predefinito di Plesk è `upload_max_filesize = 2M`: una foto da telefono (3-8 MB) veniva rifiutata con un generico "Caricamento immagine non riuscito" o, oltre `post_max_size`, con "Campo immagine non valido".

## Cosa cambia

- `PHP di sistema/image-upload.php`: l'immagine viene scritta in un file temporaneo, riceve permessi **644** e poi viene rinominata; la cartella `uploads/site` viene riportata a 755. Le immagini più larghe di 2000 px vengono ridotte e salvate in **WebP** (PNG solo se il server non supporta WebP): file molto più leggeri delle vecchie PNG. Messaggi chiari quando il file supera i limiti del server ("Immagine troppo grande per il server: massimo X MB").
- `assets/frontend-editor.js`: prima dell'invio il browser riduce le foto oltre 1,5 MB a 2000 px (WebP, o JPEG su browser che non lo supportano), così passano anche con il limite di 2 MB. Dopo il caricamento la finestra mostra l'**anteprima**; se il server non riesce a mostrarla compare un avviso sui permessi. Una risposta non valida del server non produce più "Unexpected token".
- `assets/frontend-ui.css`: stile dell'anteprima.
- Versione 2.1.24 in `core.php` e `blog.php`.

## Dopo il caricamento su Plesk

1. Caricare i file mantenendo le cartelle.
2. **File Manager → httpdocs/uploads/site**: selezionare le immagini caricate in precedenza (file `.png` con nomi lunghi) → *Modifica permessi* → spuntare **Lettura** per *Gruppo* e *Altri* (644). La cartella `site` deve essere 755.
3. Nel browser ricaricare la pagina con **Ctrl+F5** per prendere il nuovo `frontend-editor.js`.
4. Facoltativo: **Siti web e domini → Impostazioni PHP**, `upload_max_filesize` 8M e `post_max_size` 16M.

Nessuna modifica al database.
