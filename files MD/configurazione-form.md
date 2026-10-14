# Configurazione form — 2.1.6

## Email
La verifica del formato email è già attiva nel browser e sul server. L’indirizzo è obbligatorio quando si sceglie Email come contatto preferito. Non viene inviata una mail per verificare il possesso della casella; non è configurato un servizio SMTP.

## reCAPTCHA v3
Registrare una chiave v3 per comprodiamanti.piuper.site su https://www.google.com/recaptcha/admin/create . Nel backend, Privacy e integrazioni, inserire Site key, Secret key e dominio atteso comprodiamanti.piuper.site, soglia iniziale 0.5; attivare la verifica e salvare. Se la console Google Cloud mostra solo l’ID chiave, recuperare la Legacy secret key. La chiave segreta resta sul server. Il server richiede estensione PHP cURL e accesso HTTPS a Google. Eseguire un invio reale dopo aver configurato le chiavi.

## URL pubblici
Le pagine generate sono ora nelle cartelle it ed en. Impostare URL pubblico del sito a https://comprodiamanti.piuper.site e salvare per aggiornare canonical e sitemap. Caricare la patch nella root effettiva del sito, mantenendo admin, api, assets e PHP di sistema nella stessa root. I vecchi URL public vengono reindirizzati con 301 da Apache. Su Plesk nginx deve inoltrare le richieste ad Apache per applicare le regole .htaccess (vedere installazione-plesk.md).

La patch non include database, impostazioni, allegati o contenuti JSON. Include le pagine frontend generate localmente: se sono stati modificati contenuti sul server, rigenerarli dal backend dopo l’aggiornamento salvando le impostazioni.
