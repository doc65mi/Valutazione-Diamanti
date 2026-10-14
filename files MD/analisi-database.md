# Analisi database — versione 2.1.22

## Dove sono salvati i dati oggi

| Dato | Dove |
|---|---|
| Utenti admin, richieste di valutazione, metadati allegati, coda email, revisioni pagine, audit, impostazioni (integrazioni, dati legali), limiti tentativi | `data/site.sqlite` (SQLite, modalità WAL, attesa lock 5 s) |
| Articoli del blog | `data/blog.sqlite` oppure MariaDB/MySQL (già selezionabile da Admin → Blog → Database) |
| Testi delle pagine | file JSON in `content/`, pubblicati come HTML statico in `it/` ed `en/` |
| Allegati dei clienti e miniature | file in `uploads/clienti/` |
| Sessioni admin | file in `data/sessions/` |

## SQLite è sufficiente?

Sì, per questo sito. Il carico è fatto di molte letture e poche scritture (un invio del modulo, un salvataggio admin): SQLite in WAL gestisce senza problemi migliaia di richieste al giorno con pochi amministratori. I file attuali pesano decine di KB e la conservazione delle richieste è limitata (1–60 mesi), quindi non crescono senza limite.

Passare a MariaDB conviene solo se:

- il sito gira su **più server** o container (SQLite vuole un disco locale, non NFS/cartelle di rete);
- servono **molti utenti admin che scrivono insieme** o integrazioni esterne che leggono le richieste (CRM, report);
- l'hosting fa già **backup e replica gestiti** di MariaDB e si vuole un solo punto di backup.

Oggi solo il blog è predisposto per MariaDB; spostare anche `site.sqlite` richiederebbe adattare le query (es. `ON CONFLICT`, `RETURNING`).

## È sicuro?

SQLite non è meno sicuro di MariaDB: è un file, quindi la sicurezza dipende da chi può leggerlo. Punti da verificare sul server:

1. **Il file non deve essere scaricabile.** La protezione è in `data/.htaccess`. Su Plesk con "Serve static files directly by nginx" attivo le regole `.htaccess` possono essere ignorate: verificare che `https://<dominio>/data/site.sqlite` risponda 403/404. Meglio ancora spostare `data/` fuori dalla document root.
2. **Permessi**: cartella `data/` scrivibile solo dall'utente PHP del dominio.
3. **Backup coerenti**: con WAL non basta copiare `site.sqlite`; usare `sqlite3 site.sqlite ".backup backup.sqlite"` (o fermare le scritture) e conservare i backup protetti, insieme a `content/` e `uploads/clienti/`.

## Raccomandazione

Restare su SQLite, con le tre verifiche sopra e un backup giornaliero automatico. Rivalutare MariaDB se il sito passa a più server o se le richieste vanno condivise con altri sistemi.
