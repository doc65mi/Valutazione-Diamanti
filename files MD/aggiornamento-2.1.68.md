# Aggiornamento 2.1.68

Version: 2.1.68

## Il blog non si spegne con gli aggiornamenti
- Il blog online non è mai stato spento: l'impostazione in Admin › Menu è salvata nel database del sito, che il Pull di Plesk non tocca. Nessuna correzione modifica la voce Blog.
- Cosa succedeva: dopo un Pull, Plesk rimette le pagine di riserva `it/*.html` ed `en/*.html` del repository. Il sito le riscrive con le tue impostazioni e i tuoi testi alla prima chiamata al server (apertura dell'admin, invio del modulo o caricamento delle recensioni). Nella 2.1.63 quelle pagine di riserva erano state create con il Blog spento, quindi per qualche minuto dopo il Pull il link Blog spariva.
- Ora le pagine di riserva sono create con il Blog acceso, come online, con lo script `tools/fallback_pages.php`.

## Dopo ogni Pull
- Apri l'admin (o una pagina con il modulo): le pagine si riscrivono subito con i testi modificati nell'editor e con le voci del menu scelte.
