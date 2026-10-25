# Aggiornamento 2.1.41 · Grafica del blog

Version: 2.1.41

- Il blog poteva apparire senza grafica (testi e moduli senza stile). La pagina caricava CSS e script con l'indirizzo completo dell'«URL pubblico del sito»: se il blog veniva aperto da un indirizzo diverso (con/senza www, http/https), il browser li bloccava per la regola di sicurezza Content-Security-Policy 'self'.
- Ora fogli di stile, script, immagini, link interni e il modulo di ricerca del blog usano percorsi relativi alla radice (`/assets/...`): funzionano con qualunque indirizzo del sito. Canonical, Open Graph e dati strutturati restano con l'indirizzo completo.
