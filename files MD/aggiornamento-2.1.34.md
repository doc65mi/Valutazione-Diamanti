# Aggiornamento 2.1.34 · Account e richieste nel backend

Version: 2.1.34

- Admin > Account: nuovo riquadro "Account attivi" (solo amministratori) con l'elenco di tutti gli account: utente, ruolo, pagine assegnate e ultimo accesso.
- Per ogni account c'è "Cambia password": si inserisce la nuova password (minimo 12 caratteri) senza conoscere quella vecchia. Le sessioni aperte con la vecchia password di quell'account si chiudono subito; se cambi la tua resti collegato.
- Admin > Richieste: nel dettaglio di ogni richiesta c'è "Elimina richiesta", con conferma. Cancella definitivamente la richiesta, i suoi allegati (anche i file in uploads/clienti) e le email in coda.
- Entrambe le operazioni finiscono nel registro audit (user-password, request-delete).

Dopo il Pull in Plesk non serve altro: nessuna modifica al database.
