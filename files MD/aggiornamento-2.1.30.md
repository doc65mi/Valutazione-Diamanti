# Aggiornamento 2.1.30 · Collegamento Frase

Version: 2.1.30

Gli articoli scritti con Frase (next.frase.io) arrivano nel blog del sito tramite l'API ufficiale di Frase.

## Come collegarlo

1. In Frase: **Settings → API**, crea una chiave API e copiala.
2. Admin del sito → Blog → **Collegamento Frase · generazione articoli**: incolla la chiave, inserisci la password
   amministratore e premi **Collega Frase**. Il sito verifica la chiave e registra da solo il webhook su Frase
   (`https://www.valutazionediamanti.it/blog/frase.php`, eventi `content.approved` e `content.published`).
3. In Frase approva (o pubblica) un articolo: arriva nel blog. In alternativa premi **Importa da Frase**
   nell'admin e scegli l'articolo da importare o aggiornare.

## Cosa arriva nel blog

- Titolo, slug, testo HTML (ripulito: niente script, iframe o attributi pericolosi; il primo H1 viene tolto perché il titolo è già mostrato).
- Meta title e meta description di Frase, parole chiave target.
- Copertina (`featured_image`): scaricata e ricodificata in WebP (max 1200 px) in `uploads/site/`; se il download non riesce si usa l'indirizzo di Frase.
- Nuovi articoli: bozza, oppure online se è attivo «Pubblica subito i nuovi articoli ricevuti».
- Un articolo già importato viene aggiornato nel testo ma mantiene slug, stato, data, categoria e «in primo piano» decisi sul sito.

## Sicurezza

- La chiave API e il segreto del webhook restano in `data/blog-config.json` (non tracciato da git, permessi 0600) e non vengono mai mostrati dall'admin.
- Il webhook è accettato solo con firma HMAC-SHA256 valida (`X-Webhook-Signature`); il corpo serve solo a sapere quale articolo leggere: il testo viene sempre riletto dall'API di Frase con la chiave.
- **Ultimi eventi** mostra il registro `data/frase.log` (eventi, id, esito; mai chiavi, firme o testi).

## API Frase usate

Base `https://next.frase.io/api/v1`, intestazione `X-API-KEY`: `GET /content`, `GET /content/{id}`,
`GET /content/{id}/export?format=html`, `POST /webhooks`, `DELETE /webhooks/{id}`.
I percorsi vengono dal pacchetto ufficiale `@frase/mcp-server` (la documentazione pubblica dell'API non elenca i campi):
al primo invio reale controllare **Ultimi eventi**; se la firma o l'id dell'articolo hanno un formato diverso, il registro lo indica.

## Note

- Richiede l'estensione PHP cURL (presente di norma su Plesk).
- Il blog resta spento dal pannello Menu finché non lo riaccendi: gli articoli importati sono visibili solo all'amministratore collegato.
- Scollega rimuove il webhook da Frase e cancella la chiave; gli articoli già importati restano.
