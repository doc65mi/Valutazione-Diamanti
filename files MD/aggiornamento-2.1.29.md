# Aggiornamento 2.1.29 — Il Pull da GitHub non cancella più le modifiche fatte dal sito

## Il problema

Il Pull di Plesk riporta alla versione di GitHub **tutti i file presenti nel repository**. Tra questi c'erano anche i testi delle pagine (`content/*.json`) e le pagine pubblicate (`it/*.html`, `en/*.html`, `sitemap.xml`, `robots.txt`, `llms.txt`). Così ogni Pull cancellava le modifiche fatte con l'editor del sito, comprese le immagini scelte.

Non venivano toccati invece il database (`data/site.sqlite`: utenti, richieste, articoli del blog, impostazioni, revisioni) e i file delle immagini in `uploads/site/`, che non sono nel repository. Le immagini caricate quindi sono ancora sul server: è il collegamento nella pagina che tornava quello vecchio.

## Cosa cambia

- I testi modificati dall'editor vengono salvati in `data/content/` (fuori dal repository, il Pull non li tocca). `content/` resta solo il testo di partenza, usato finché una pagina non viene modificata.
- Dopo ogni Pull, al primo accesso al sito le pagine pubbliche, la sitemap, robots.txt e llms.txt vengono rigenerate automaticamente dai testi in `data/content/`.
- `.gitignore`: esclusi `data/content/` e `data/published.stamp`.
- Versione 2.1.29 in `core.php` e `blog.php`.

Nota: da ora in poi un testo di pagina già modificato dal sito non cambia più con un Pull; le modifiche ai testi vanno fatte dall'editor.

## Cosa fare su Plesk (una sola volta)

1. **Prima del Pull**, in File Manager: dentro `httpdocs/data` creare la cartella `content`, poi selezionare tutti i file `.json` di `httpdocs/content` e copiarli in `httpdocs/data/content`. Così le modifiche fatte dopo l'ultimo Pull non vanno perse.
2. Premere **Pull now**.
3. Aprire una pagina del sito: le pagine si rigenerano da sole.

## Recuperare quello che è andato perso

- Le immagini caricate sono ancora in `httpdocs/uploads/site` (ordinate per data in File Manager). Per rimetterle: editor → clic sulla foto → nel campo **src** scrivere `/uploads/site/NOMEFILE` → Applica → Salva.
- Le versioni precedenti di ogni pagina sono nel database: Admin → scegliere la pagina → sezione **Revisioni precedenti** → "Ripristina versione" su quella giusta.

Nessuna modifica al database.
