# Distribuzione completa — 2.1.11

Lo ZIP contiene frontend IT/EN, admin, API, asset, template, sorgenti modificabili, contenuti JSON, backend PHP e protezioni delle cartelle. Esclude dati locali: database attuale, credenziali, sessioni, log, allegati clienti e old-page.

## Nuova installazione
Estrarre nella document root del dominio, conservando i file .htaccess. Abilitare PHP 8.3+ con PDO SQLite, DOM, mbstring, GD e cURL. Le cartelle data, data/sessions, uploads/clienti, content, it ed en devono essere scrivibili da PHP, come robots.txt, sitemap.xml e llms.txt nella root.

Dalla cartella del sito eseguire:

```sh
php "PHP di sistema/install.php"
```

L’installer crea il database SQLite e l’admin, scrive le credenziali generate in data/first-login.txt e pubblica le pagine. Leggere quel file da SSH o dal file manager Plesk; cambiare password al primo accesso e rimuovere il file delle credenziali. Non esporre data tramite il web.

Impostare l’URL pubblico e le integrazioni dall’admin. Per Apache/nginx su Plesk seguire installazione-plesk.md: le regole .htaccess devono essere applicate. L’installer CLI è necessario per inizializzare una nuova installazione.

## Aggiornamento sito esistente
Fare un backup. Caricare i file del programma mantenendo data, uploads/clienti e i JSON di content presenti sul server. Non sovrascrivere content con quelli dello ZIP se sul server sono stati modificati testi o immagini. Dopo l’aggiornamento salvare Privacy e integrazioni dall’admin per rigenerare le pagine con i contenuti server. Non viene azzerato il database e non vengono cambiate le credenziali.

## Distribuzioni successive
Generare sempre l’archivio completo con tools/package_full.py. Il nome include APP_VERSION. Gli archivi precedenti e old-page non vengono inclusi.
