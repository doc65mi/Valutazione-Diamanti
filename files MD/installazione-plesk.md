# Correzione Plesk e avvio locale — 2.1.5

La versione precedente dipendeva dalle riscritture per la pagina iniziale. Ora la root contiene `index.php` e `index.html`: entrambi aprono `public/it/index.html`, dove i percorsi relativi di navigazione e risorse sono corretti.

## Aggiornamento

Estrarre `comprodiamanti-2.1.5-fix.zip` nella stessa document root che contiene `public`, `assets`, `admin` e `api` (normalmente `httpdocs` in Plesk). L'archivio contiene solo la correzione; non sovrascrive database, allegati, contenuti JSON o impostazioni.

Aprire il dominio e verificare anche `/index.php`, `/public/it/index.html` e `/assets/site.css`. Se le ultime due risorse non esistono, l'applicazione non è stata copiata integralmente o la document root punta a un'altra cartella. Se appare un errore 500, leggere il log errori del dominio in Plesk: il messaggio identifica la direttiva o il problema PHP.

## Configurazione Plesk Linux con Apache

In Domini → dominio → Impostazioni Apache e nginx:

- Usare `index.php index.html` come file indice, prima dell'eventuale pagina predefinita Plesk.
- Mantenere la modalità proxy verso Apache.
- Disabilitare **Serve static files directly by nginx** per applicare le protezioni e le regole `.htaccess` a template e file statici. Verificare che i file `.htaccess` siano stati caricati, inclusi quelli delle sottocartelle.

La distribuzione diretta delle risorse statiche da nginx può ignorare le regole Apache: [documentazione Plesk](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/websites-and-domains/hosting-settings/web-server-settings/apache-and-nginx-settings.72320/).

Se si usa esclusivamente nginx, tradurre le protezioni nella configurazione del server: negare `data`, `content`, `pages-src`, `partials`, `tools`, `files MD`, `PHP di sistema` e `uploads/clienti`. Non cambiare la document root in `public`: con questa struttura gli URL di asset, admin e API richiedono la cartella principale.

## Locale

Con Laragon usare il dominio locale, ad esempio `http://compro-diamanti.test/`, oppure il percorso del sito su localhost. In alternativa, dalla cartella del progetto:

```powershell
php -S 127.0.0.1:8092 "PHP di sistema/dev-router.php"
```

Aprire `http://127.0.0.1:8092/`. Il doppio clic su `index.html` mostra la pagina, ma login, form, SQLite e configurazioni richiedono PHP tramite HTTP/HTTPS.

Verifiche della release: ingresso PHP e nomi legacy, homepage, admin e API su Apache locale; sintassi Apache. Test del nuovo ingresso nel browser non completato. La configurazione del server remoto non è stata verificata senza URL e log dell'installazione.

