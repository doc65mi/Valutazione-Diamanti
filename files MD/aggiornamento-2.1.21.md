# Aggiornamento 2.1.21 — Collegamento ContentAI Pro e blog ottimizzato

Il blog riceve ora gli articoli prodotti da **ContentAI Pro** (Content Studio) e li mostra con markup SEO/GEO/AEO più completo. Nessun dato esistente viene modificato: la migrazione del database è additiva.

## Cosa cambia

- **Nuovo endpoint** `https://comprodiamanti.piuper.site/blog/contentai.php`. Il vecchio `blog/webhook.php` resta invariato.
- **Campi nuovi** nella tabella `blog_articles` (aggiunti in automatico al primo accesso al blog): `meta_title`, `meta_description`, `keywords`, `faq_json`, `role` (Pillar/Cluster), `pillar_slug`. Gli articoli esistenti restano identici; salvare un articolo dal backend non cancella questi campi.
- **Pagine articolo**:
  - titolo e descrizione SEO propri dell'articolo (con ripiego sui valori attuali);
  - Open Graph e Twitter Card (prima venivano rimossi e non rigenerati);
  - sezione **Domande frequenti** visibile e dati strutturati **FAQPage**; `BlogPosting` con parole chiave, numero di parole e immagine strutturata;
  - blocco risposta diretta (`class="answer"` è l'unica classe consentita, solo su `<p>`);
  - **Continua a leggere**: collegamenti Pillar ↔ Cluster;
  - copertina con dimensioni dichiarate e priorità di caricamento (meno spostamenti di layout).
- **Sitemap** con `lastmod` e immagine di copertina per articolo; **llms.txt** elenca gli articoli pubblicati (massimo 100).
- Home, pagine istituzionali, form e area clienti non sono toccati.

## Collegare ContentAI Pro

1. **Admin → Blog → Collegamento**: genera il token (si vede una sola volta; rigenerarlo revoca il precedente). È lo stesso token del webhook storico.
2. In ContentAI Pro, **Siti & Tenant**: dominio `comprodiamanti.piuper.site` (senza `https://`).
3. **Content Studio → Destinazione di pubblicazione**:
   - URL: `https://comprodiamanti.piuper.site/blog/contentai.php`
   - Metodo: `POST`
   - Header: `Authorization: Bearer <token>`
   - Segreto firma HMAC: lasciare vuoto (l'autenticazione è il Bearer su HTTPS)
   - Schema URL: `/blog/articolo/{slug}` (così link interni e canonical coincidono con il sito)
4. **Invia test (ping)**, poi **Invia bozza**. L'articolo compare in Admin → Blog come **bozza**: rivedi e pubblica dal backend.

## Regole di funzionamento

- Ogni articolo nuovo arriva **sempre in bozza**, salvo l'impostazione già esistente di pubblicazione automatica nel backend (`auto_publish`, spenta di default). Il pulsante "Pubblica" di ContentAI Pro non pubblica da solo.
- Un secondo invio dello stesso articolo **aggiorna** testo, descrizioni, FAQ e copertina; **mantiene** slug, stato, data e primo piano scelti nel backend (200 invece di 201).
- Lo slug deve essere già nel formato `lettere-minuscole-e-trattini`; altrimenti l'invio è rifiutato (422), così i link interni restano validi.
- Se la bozza ha una copertina, viene ricodificata e salvata in `uploads/site/blog-<slug>-<codice>.webp` (JPEG se il server non supporta WebP). Una nuova copertina sostituisce e cancella la precedente; un invio senza immagine non la toglie.
- Categoria: Pillar → "Guide", Cluster → "Approfondimenti" (in inglese "Guides" / "Insights").
- Gli articoli sono nella lingua del sito indicata da ContentAI Pro; non vengono tradotti.

## Sicurezza

- HTTPS obbligatorio; autenticazione Bearer verificata con confronto a tempo costante sull'hash già salvato (nessun token in chiaro nel sito). Senza token generato l'endpoint rifiuta tutto.
- Dopo 10 tentativi falliti l'IP è bloccato per 15 minuti; limite generale di 600 richieste/ora per IP.
- Corpo massimo 15 MB; JSON limitato in profondità; ogni campo ha lunghezza massima. Le query usano sempre parametri preparati.
- HTML sanificato con la stessa lista consentita del blog; FAQ, meta, Open Graph e JSON-LD sono sempre escapati. Script, `onerror`, `javascript:` e iframe vengono rimossi.
- Immagine: solo PNG valido (firma, dimensioni, massimo 9 megapixel, 8 MB), decodificata e ricodificata (si perdono metadati e dati nascosti), nome file generato dal server, cartella `uploads/site` senza esecuzione di script.
- Gli errori sono generici; i dettagli restano nel log del server senza token né contenuti. Ogni ricezione è registrata nell'audit.

## Aggiornamento online

1. **Backup** della cartella del sito e dei database (`data/`).
2. Caricare il pacchetto mantenendo la struttura delle cartelle. Non contiene `data`, sessioni, token o allegati clienti.
3. Aprire `/blog/` una volta: la migrazione aggiunge le colonne. Nessuna azione sul database.
4. Apache/Plesk: `blog/.htaccess` ora inoltra l'header `Authorization` a PHP (necessario con PHP-FPM/CGI). Con Nginx aggiungere `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` e indicare HTTPS a PHP.
5. Provare con "Invia test" da ContentAI Pro.

**Rollback**: ripristinare i file dal backup. Le colonne aggiunte restano nel database ma non sono usate dalla versione precedente, che continua a funzionare.

## Verifiche

68 controlli automatici su copia isolata (PHP 8.3, SQLite, HTTPS simulato), compresi: autenticazione e blocco tentativi, metodo/Content-Type/limiti, slug e id non validi, immagini non valide o con codice nascosto, creazione/aggiornamento/replay senza duplicati né file orfani, stato bozza non aggirabile, conflitto di slug, iniezione SQL/HTML in titolo, meta e FAQ, resa delle pagine (JSON-LD valido, FAQPage, Open Graph, correlati), sitemap, llms.txt, articolo precedente alla migrazione, compatibilità del webhook storico e conservazione dei campi dopo un salvataggio dal backend.

Non verificati: server Apache/Plesk reale (inoltro dell'header Authorization), MariaDB reale (trasferimento con le colonne nuove), primo invio reale da ContentAI Pro verso il sito online.
