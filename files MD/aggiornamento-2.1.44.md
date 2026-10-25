# Aggiornamento 2.1.44 · Microsoft Clarity e altri codici di tracciamento

Version: 2.1.44

## Dove si configura

Admin → **Privacy e integrazioni**, sotto Google Tag Manager:

- **Microsoft Clarity**: casella «Abilita» e ID progetto (va bene anche lo snippet intero: il sito tiene solo l'ID).
  Categoria di consenso Analisi; il consenso viene passato a Clarity con `consentv2`.
- **Altri codici di tracciamento**: una riga per servizio (es. Hotjar, LinkedIn Insight, TikTok Pixel) con nome,
  categoria di consenso (Analisi, Marketing o Necessari), posizione (head o fondo del body), codice e casella «Attivo».

## Come funziona

- Nessun codice parte prima del consenso della sua categoria (Necessari parte sempre). Il nome del servizio compare nelle
  preferenze cookie e nell'elenco dei servizi della pagina Cookie.
- Aggiungere, togliere o rinominare uno strumento richiede di nuovo il consenso ai visitatori; modificare solo il codice no.
- Il codice viaggia verso il server in base64, così ModSecurity su Plesk non blocca il salvataggio per la presenza di `<script>`.
- Gli script in linea non sono permessi dalla Content-Security-Policy: `consent.js` li carica come file da
  `api/index.php?action=tracking-code`. Gli script esterni si caricano normalmente; i `<noscript>` vengono ignorati.
- CSP delle pagine pubbliche aperta agli script e alle connessioni HTTPS (prima erano bloccati anche Google Analytics, Ads,
  Meta e Tag Manager). Gli script in linea, `onclick` ed `eval` restano vietati. Amministrazione e API mantengono la CSP stretta
  (`admin/.htaccess`, `api/.htaccess`).

## File modificati

`.htaccess`, `admin/.htaccess`, `api/.htaccess` (nuovo), `api/index.php`, `PHP di sistema/core.php`,
`PHP di sistema/settings.php`, `assets/consent.js`, `assets/admin.js`, `assets/integrations.html`, `assets/site.css`.
