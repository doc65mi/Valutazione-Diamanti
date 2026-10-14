# Aggiornamento 2.1.8
Il preventivatore riprende il layout e le scelte a schede di Quotazione.dc.html. Rimangono validazione, upload, invio SQLite/admin, reCAPTCHA e IT/EN. I tre testi di fiducia su mobile restano in linea senza pulsanti, come richiesto.

Estrarre comprodiamanti-2.1.8-fix.zip nella root del sito. Poi salvare Privacy e integrazioni nell’admin per rigenerare le pagine con i contenuti del server. Non sono inclusi database, segreti, allegati o JSON contenuti. I nuovi campi della colonna laterale sono aggiunti con valori predefiniti senza sovrascrivere gli altri contenuti.

In locale HTML legacy della root, cartella public e archivio Quotazione.dc.zip sono stati spostati in old-page. Sul server effettuare gli stessi spostamenti se presenti; conservare index.html e index.php nella root. Non caricare old-page: è solo un archivio locale, protetto da .htaccess. Non occorre includerlo nella distribuzione.
