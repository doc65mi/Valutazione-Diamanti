# Aggiornamento 2.1.23 — Marchio "Valutazione Diamanti"

## Cosa cambia

Il vecchio marchio **ComproDiamanti.it** è stato sostituito con **Valutazione Diamanti** in tutto il sito pubblico, nel blog e nell'amministrazione:

- logo testuale nell'intestazione e nel piè di pagina di tutte le pagine IT/EN (il logo è il rombo dorato + la scritta, non un'immagine);
- titoli delle pagine (`… | Valutazione Diamanti`), copyright, testi "Chi siamo" e home;
- dati strutturati per Google (nome del sito), Open Graph del blog, `llms.txt`;
- intestazione e piè di pagina dell'amministrazione, segnaposto email nelle integrazioni.

Il vecchio dominio `comprodiamanti.piuper.site` è stato sostituito con `https://www.valutazionediamanti.it` in canonical, hreflang, sitemap, robots.txt e llms.txt, ed è il nuovo valore predefinito quando in Admin non è impostato l'URL pubblico del sito.

Nuovo file `assets/logo.svg` (rombo dorato su fondo scuro), usato come icona del sito nella scheda del browser.

Restano volutamente invariati: i redirect dei vecchi indirizzi `ComproDiamanti.html` (`.htaccess`, `dev-router.php`), gli script di sviluppo in `tools/` e `work/`, i file originali in `tools/originals/` e `old-page/`, i changelog precedenti.

## Dopo il caricamento su Plesk

1. Caricare i file mantenendo le cartelle. Le pagine in `it/` e `en/`, `sitemap.xml`, `robots.txt` e `llms.txt` devono avere permessi **644**.
2. In **Admin → Privacy e integrazioni** impostare l'URL pubblico del sito su `https://www.valutazionediamanti.it` (se c'è ancora il vecchio dominio) e salvare: il sito rigenera tutte le pagine.
3. Attenzione: i file in `content/` sostituiscono i testi presenti online. Se nel frattempo sono stati modificati testi dall'editor del sito, rifarli o segnalarlo prima di caricare quella cartella.

Nessuna modifica al database.
