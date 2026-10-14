# Recupero admin online — 2.1.14
Il messaggio Utente o password non corretti indica che le credenziali non corrispondono a un utente nel database attivo. Lo ZIP completo esclude il database locale: credenziali e dati online sono indipendenti.

Controllare con il file manager Plesk data/first-login.txt nella document root. Se presente, usare le credenziali indicate lì. Non scaricare il database locale per sovrascrivere quello online.

Se il file manca o la password è stata cambiata, caricare PHP di sistema/reset-admin.php dalla distribuzione completa. Dal terminale/SSH nella root del sito eseguire:

```sh
php "PHP di sistema/reset-admin.php" --reset-admin
```

Se si usa un’attività pianificata Plesk, scegliere Esegui uno script PHP, indicare il percorso completo al file e il parametro --reset-admin, con PHP 8.3 o successivo. Eseguire una volta, poi rimuovere l’attività: non deve restare periodica.

Il comando genera una nuova password, aggiorna/crea solo l’account admin, preserva richieste, allegati, impostazioni e contenuti. Leggere data/first-login.txt tramite file manager Plesk, effettuare login, cambiare password e rimuovere il file delle credenziali. Non aprire reset-admin.php nel browser: è un comando riservato alla CLI. I tentativi ripetuti di login possono richiedere un’attesa di 15 minuti.

Per un sito già installato basta caricare il comando di recupero; caricando l’intero ZIP conservare data, uploads e content del server. Nessun reset è stato eseguito sul server remoto dall’assistente.


## Recupero dal browser — 2.1.15
Aggiornare i file del programma preservando data, uploads e content. Aprire /admin/reset-password.php. La pagina crea data/admin-recovery.json: leggere il valore code dal File Manager Plesk e inserirlo nel form, con la nuova password e la conferma. Non pubblicare né condividere quel codice. Scade dopo 30 minuti.

Dopo il successo il codice viene eliminato e il recupero web è disabilitato. Per un recupero futuro rimuovere data/admin-recovery-disabled dal File Manager e riaprire la pagina. Il cambio password invalida le vecchie sessioni dell’account; con l’aggiornamento 2.1.15 gli utenti devono effettuare un nuovo login.

Sono conservati richieste, allegati, impostazioni e contenuti. La pagina è noindex e non mostra il codice al visitatore: possedere l’URL non permette di cambiare la password.
