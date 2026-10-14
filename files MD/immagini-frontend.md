# Immagini frontend — 2.1.16
Dopo il login: Modifica il sito, Modifica pagina, clic su immagine, Carica una nuova immagine. I file vengono salvati in uploads/site. Compilare il testo alternativo e premere Applica e Salva.
Accettati JPG, PNG e WebP, massimo 5 MB e 16 megapixel. Ricodifica PNG e nomi casuali. Upload autenticato con permessi e CSRF. uploads/site contiene immagini pubbliche; uploads/clienti rimane riservata. File caricati non pubblicati restano sul server.
Aggiornare preservando data, content e uploads. uploads/site deve essere scrivibile da PHP.
