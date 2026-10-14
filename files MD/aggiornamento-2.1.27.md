# Aggiornamento 2.1.27 — Accendere e spegnere le voci del menu dall'Admin

## Cosa cambia

Nell'Admin c'è una nuova scheda **Menu** con una casella per ogni voce: Vendere un diamante, Come funziona, Chi siamo, FAQ, Blog. Togliendo la spunta e premendo **Salva e aggiorna il sito**:

- la voce sparisce dal menu in alto e dal footer, in italiano e in inglese;
- se la voce è una pagina (Vendere un diamante, Chi siamo, FAQ), la pagina esce dalla sitemap e da llms.txt e riceve `noindex`; resta raggiungibile dai link nel testo delle altre pagine;
- se è il Blog, la voce sparisce, il blog e i suoi articoli escono dalla sitemap e i visitatori ricevono "non trovato" (404). L'amministratore collegato continua a vederlo (con `noindex`) e gli articoli inviati da ContentAI continuano a essere ricevuti.

Il **Blog parte spento**: finché non lo si riaccende dalla scheda Menu resta nascosto.

## File modificati

- `PHP di sistema/settings.php`: elenco delle voci (`MENU_ITEMS`), impostazione `menu_hidden` (predefinito: blog spento), salvataggio.
- `PHP di sistema/core.php`: rimozione delle voci spente da header e footer, `noindex` e sitemap; controllo amministratore collegato.
- `api/index.php`: azione `menu` (lettura e salvataggio, poi ripubblicazione di tutte le pagine).
- `admin/index.php`, `assets/admin.js`: scheda Menu.
- `blog/index.php`: 404 per i visitatori quando il blog è spento.
- Versione 2.1.27 in `core.php` e `blog.php`.

## Dopo il caricamento su Plesk

1. Premere **Pull now**.
2. Entrare nell'Admin e salvare una volta (scheda **Menu** → **Salva e aggiorna il sito**): le pagine statiche vengono rigenerate senza la voce Blog.

Nessuna modifica alla struttura del database.
