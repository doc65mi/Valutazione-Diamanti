# Aggiornamento 2.1.70

Version: 2.1.70

## Spazio immagine del blog come opzione
- Nel blog pubblico (elenco articoli e pagina dell'articolo) lo spazio immagine non viene più mostrato: niente riquadro scuro con il segnaposto ◇ nelle schede e niente immagine di copertina sopra il testo dell'articolo. La scheda in primo piano occupa tutta la larghezza.
- Nuova opzione in Admin › Blog, riquadro «Aspetto del blog»: «Mostra lo spazio immagine (copertina) nell'elenco e negli articoli». Di default è disattivata; salvandola il blog cambia subito (non serve password).
- L'immagine resta salvata nell'articolo e continua a essere usata per l'anteprima di condivisione sui social (og:image).
- L'impostazione è salvata in `data/` (blog-config.json, chiave `show_images`), quindi il Pull di Plesk non la sovrascrive.

File modificati: `PHP di sistema/blog-view.php`, `PHP di sistema/blog-api.php`, `PHP di sistema/blog.php`, `PHP di sistema/core.php`, `assets/blog-admin.html`, `assets/blog-admin.js`, `assets/blog.css`.
