# Aggiornamento 2.1.80 – modulo di valutazione (certificati, forma, oreficeria, video)

## Cosa cambia nel modulo (IT ed EN)
1. **Certificato obbligatorio**: per «Diamante» e «Anello solitario con diamante» con certificato GIA, IGI o HRD serve
   il numero del certificato **oppure** la foto/PDF del certificato (nuovo campo al passo 4). Controllo nel browser e sul server.
2. **Nuovi laboratori**: IGI Italia, CISGEM e «Altro laboratorio» (chiede il nome del laboratorio). Avviso AIG/GRA/GWLAB/ALGT invariato.
   Numero e file del certificato si possono indicare con qualsiasi laboratorio.
3. **Diamanti**: nuovi campi facoltativi «Forma» (Rotondo, Ovale, Goccia, Cuore, Marquise, Smeraldo (taglio), Radiant,
   Cuscino, Princess, Altro) e «Carati esatti». I carati esatti scelgono da soli la fascia di peso.
4. **Orologi**: Jacob & Co tolto dal modulo e dai testi di Home, Acquistiamo e FAQ (i testi modificati dall'editor restano come sono).
5. **Oreficeria usata**: nuova voce prima di «Orologio»: titolo dell'oro (obbligatorio, anche «Non lo so») e peso in grammi
   (facoltativo); il passo dei certificati viene saltato. Link diretto: `valutazione.html?tipo=oro`.
6. Zaffiro, Rubino e Smeraldo: già dalla 2.1.76 non chiedono il tipo di pietra (nessuna modifica).
7. **Video**: un video MP4 o MOV fino a 100 MB al passo 5, con barra di avanzamento durante l'invio, più il pulsante
   «In alternativa invia il video su WhatsApp». I video caricati sul sito vengono **cancellati dopo 15 giorni**
   (la richiesta e le foto restano per il periodo di conservazione impostato).

## Admin ed email
- Dettaglio richiesta, ricerca ed export CSV con forma, carati esatti, laboratorio, titolo e peso dell'oro.
- Il video si guarda nell'anteprima (i MOV dell'iPhone a volte solo in Safari: altrimenti «Scarica originale»).
- In Richieste è indicato il limite di caricamento del server: se è sotto 100 MB vanno alzate le impostazioni PHP (sotto).
- Email al gestore: nuove righe e «Video: 1 (cancellato automaticamente dopo 15 giorni)».
- Informativa privacy e box «Come utilizziamo i tuoi dati»: aggiunta la cancellazione dei video dopo 15 giorni.

## Da fare in Plesk dopo il Pull
1. **Impostazioni PHP** del dominio: `upload_max_filesize` = 128M, `post_max_size` = 128M, `max_execution_time` = 300,
   `max_input_time` = 300. (Il file `api/.user.ini` prova a impostarli da solo, ma Plesk può ignorarlo.)
   Controllo: Admin › Richieste deve dire «limite caricamento video sul server: 100 MB».
2. **Impostazioni Apache e nginx** › «Direttive nginx aggiuntive»: aggiungi sotto la riga del robots
   `client_max_body_size 128m;`
3. **Operazioni pianificate** (cancellazione video anche senza nuove richieste): nuova operazione giornaliera
   «Esegui un comando», ore 03:00:
   `php "/var/www/vhosts/valutazionediamanti.it/httpdocs/PHP di sistema/cleanup.php"`
   (i video si cancellano comunque anche a ogni nuova richiesta e ogni volta che si apre Admin › Richieste).
4. I **backup** di Plesk possono contenere video più vecchi di 15 giorni: se serve, escludi `httpdocs/uploads/clienti` dai backup
   o tieni i backup al massimo 15 giorni.

## Nota WhatsApp
I video mandati su WhatsApp non passano dal sito: la cancellazione dopo 15 giorni va fatta a mano sul telefono/WhatsApp Business.
