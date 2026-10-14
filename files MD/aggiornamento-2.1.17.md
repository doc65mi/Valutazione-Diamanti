# Blog integrato nel sito — 2.1.17

La sezione blog è parte del progetto Compro-diamanti esistente. Non richiede un’applicazione separata, un nuovo account o un installer diverso.

## Navigazione e layout

- Voce Blog nel menu e nel footer delle pagine italiane e inglesi.
- `/blog/`: tre articoli in primo piano, archivio paginato, ricerca e sidebar destra con i pubblicati.
- `/blog/articolo/slug`: pagina articolo con layout del sito, contatti, gestione cookie e toolbar mobile. L’indirizzo alternativo `/blog/index.php?slug=slug` funziona anche senza riscrittura URL.
- `/blog/?lang=en`: archivio in inglese; gli articoli sono separati per lingua, non tradotti automaticamente.
- Il rendering riutilizza la homepage pubblicata per mantenere identità, header e footer effettivi. L’editor frontend della homepage non viene caricato sulle pagine blog.
- Bozze escluse dalle pagine pubbliche e dalla sitemap principale, che viene aggiornata al salvataggio e alla ricezione dei contenuti.

## Backend esistente

Accedere a `/admin/` con il proprio account amministratore e selezionare **Blog**. Gli utenti con ruolo cliente non hanno accesso alle operazioni blog. L’autenticazione, i controlli CSRF, il timeout, la gestione password e i log riutilizzano le funzioni già presenti nel sito.

Gestione articoli: titolo, slug, lingua, categoria, descrizione, HTML sanificato, URL copertina e descrizione, bozza/pubblicato, data e primo piano. Il controllo versione blocca il salvataggio di una modifica obsoleta. La selezione in primo piano viene completata con gli articoli più recenti fino a tre; con meno di tre pubblicati mostra quelli disponibili. Non sono inclusi programmazione futura, versioni degli articoli o redirect automatici dopo cambio slug. Le immagini sono inserite mediante URL HTTPS.

## Persistenza e MariaDB

Gli articoli sono inizialmente salvati in `data/blog.sqlite`, protetto dalle regole della cartella `data`. Account, richieste, impostazioni del sito e loro SQLite esistente non vengono trasferiti o sostituiti.

La configurazione opzionale del blog è in `data/blog-config.json`, mai distribuita nel pacchetto. Nella sezione Database del backend si può impostare una connessione PDO MariaDB/MySQL e trasferire **soltanto gli articoli**. Richiede pdo_mysql, database dedicato UTF-8 con tabelle InnoDB, destinazione vuota, password amministratore attuale e conferma `TRASFERISCI`. La copia è transazionale, ogni campo viene confrontato prima del commit, quindi viene salvata la connessione. Un lock comune protegge letture e scritture durante il passaggio.

Eseguire backup prima del trasferimento. L’origine resta intatta; non è una replica o sincronizzazione. Il ritorno allo SQLite originale non vuoto viene rifiutato: archiviare e proteggere il vecchio file prima di predisporre una destinazione vuota. Se il commit riesce ma il salvataggio configurazione fallisce, l’origine resta attiva e la destinazione conserva la copia: completare il cambio manualmente dopo verifica. Per grandi archivi serve una migrazione dedicata; questa versione carica gli articoli in memoria. Per MariaDB remoto usare rete privata/tunnel gestito dal server; non vengono configurati certificati TLS del DB.

## Soro

Nel backend Blog → Collegamento Soro si genera un token casuale visualizzato una volta. Una rigenerazione revoca quello precedente. L’applicazione memorizza soltanto l’hash. Nessun collegamento Soro viene attivato automaticamente.

Endpoint HTTPS: `https://comprodiamanti.piuper.site/blog/webhook.php`.

```http
POST /blog/webhook.php
Content-Type: application/json
Authorization: Bearer TOKEN
```

```json
{
  "id": "id-stabile-articolo-servizio",
  "title": "Titolo articolo",
  "content": "<h2>Introduzione</h2><p>Testo HTML.</p>",
  "slug": "titolo-articolo",
  "excerpt": "Descrizione breve",
  "language": "it",
  "category": "Guide",
  "image_url": "https://esempio.it/copertina.jpg",
  "image_alt": "Descrizione immagine"
}
```

Obbligatori: id, title e content. Data facoltativa `published_at` nel formato YYYY-MM-DD, non futura. Il payload è limitato a 700 KB e l’HTML a 500 KB. L’identificatore esterno evita duplicati: 201 indica creazione, 200 aggiornamento. Gli aggiornamenti mantengono slug, lingua, stato, primo piano e data scelti nel backend; aggiornano testo, titolo, descrizione e immagini e possono sovrascrivere revisioni manuali di questi campi. Eliminando un articolo, un nuovo invio dello stesso id può ricrearlo.

I contenuti nuovi rimangono in bozza, salvo attivazione esplicita della pubblicazione automatica. Non inserire il token in URL o codice frontend. Il web server deve inoltrare Authorization e indicare HTTPS in modo attendibile a PHP; l’app non si fida di header proxy arbitrari.

Soro dichiara webhook per integrazioni personalizzate, ma non è stato fornito il contratto effettivo dell’account: il formato documentato è quello implementato dal sito. Occorre confrontarlo con un invio campione o la documentazione Soro e, se diverso, completare l’adattatore prima dell’attivazione. Non è dichiarata una connessione Soro già collaudata.

## Aggiornamento online

Il progetto locale è aggiornato. Per trasferire la release sul server usare il pacchetto di aggiornamento, mantenendo la struttura delle cartelle. Il pacchetto non contiene `data`, sessioni, password, token, allegati o credenziali DB. Non eseguire l’installer su un sito già installato: il blog crea il proprio archivio al primo accesso e usa gli account esistenti.

Nel pacchetto sono incluse le pagine pubbliche rigenerate con i contenuti locali attuali. Se i contenuti online sono stati modificati separatamente, fare prima backup e sincronizzarli: non sovrascrivere alla cieca le pagine HTML. Le successive pubblicazioni tramite l’editor esistente manterranno i collegamenti Blog grazie alla modifica del generatore.

Plesk con Apache: assicurare caricamento `blog/.htaccess` e passaggio delle richieste ad Apache. Con Nginx aggiungere la riscrittura `/blog/articolo/slug` a `/blog/index.php?slug=slug`, conservando la query; l’URL alternativo funziona senza tale regola. La cartella data rimane privata. Sono richiesti PHP 8.1+ (come il sito attuale), PDO SQLite, DOM, mbstring, iconv e pdo_mysql per MariaDB.

## Verifiche

29 controlli su copia isolata PHP 8.4 / SQLite: accesso con account amministratore esistente, rifiuto anonimi e clienti, scheda nel backend, layout comune, percorsi risorse, menu IT/EN, creazione/modifica, conflitti versione, CSRF, tre articoli, sidebar, pagine dedicate, HTML sanificato, bozze private, lingue, ricerca, token, webhook autenticato/idempotente, sitemap, errore MariaDB senza cambio connessione, eliminazione, trasferimento dati verificato a SQLite e rifiuto destinazione non vuota.

Verifica browser sul progetto locale: pagina /blog/ funzionante, header/footer reali, navigazione Blog, contatti e gestione cookie.

Non verificati: MariaDB reale, invio reale Soro e distribuzione sul server remoto. Nessun dato degli utenti o delle richieste originali è stato usato nei test. Nessun articolo dimostrativo viene inserito nel progetto reale.
