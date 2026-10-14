# Aggiornamento 2.1.13
Rimossa icona WhatsApp mobile: resta il link testuale nella toolbar. Label Privacy e cookie in basso a destra sopra la toolbar, anche dopo la chiusura del popup. Chiudere senza scegliere mantiene i tracciamenti facoltativi disabilitati.

Il form richiede sempre email e genera due messaggi: riepilogo al gestore e conferma IT/EN al cliente. In Privacy e integrazioni configurare mittente autorizzato dal server e email del gestore, abilitare gli invii e salvare. Il trasporto usa mail() di PHP e il servizio email del server: va configurato su Plesk. Il risultato Accettata dal server non prova la consegna nella casella: effettuare un invio reale e verificare entrambe le caselle.

Gli allegati restano nell’admin, non vengono spediti via email. La coda SQLite conserva invii falliti; dal dettaglio richiesta usare Riprova invio email. Non vengono reinviati i messaggi già accettati. Massimo 5 tentativi. In alternativa configurare una attività pianificata Plesk per eseguire php "PHP di sistema/mail-retry.php".

Caricare lo ZIP completo preservando data, uploads e content esistenti, poi salvare Privacy e integrazioni per rigenerare le pagine. La nuova tabella email viene creata automaticamente. Nuova installazione: vedere installazione-completa.md. Versione 2.1.13.

Documentazione trasporto: https://www.php.net/manual/en/function.mail.php
