# Aggiornamento 2.1.59 — Dettaglio dell'email di prova (debug)

- Admin > Email > «Email di prova»: dopo l'invio compare il dettaglio del dialogo con il server di posta, passo per passo: risoluzione del nome del server, connessione, STARTTLS/SSL, autenticazione, mittente, destinatario e risposta finale. Le righe «C:» sono del sito, «S:» del server, «!» gli errori (anche quelli OpenSSL o di connessione). La password e il testo del messaggio non vengono mai mostrati.
- Con la funzione mail di PHP il dettaglio indica il sendmail_path e se mail() ha accettato il messaggio.
- Pulsante «Copia il dettaglio» per incollarlo dove serve.
- Admin > Privacy e integrazioni > scheda Email: pulsante «Vai all'email di prova».
- Il modo in cui le email vengono inviate non cambia.
