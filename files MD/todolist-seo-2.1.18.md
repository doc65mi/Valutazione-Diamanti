# SEO — release 2.1.18

- [x] Sitemap XML con 16 URL pubblici: 14 pagine IT/EN e 2 archivi blog.
- [x] Articoli pubblicati aggiunti automaticamente alla sitemap dal backend e webhook.
- [x] Canonical assoluti e dati strutturati WebSite, Organization, WebPage, AboutPage e BlogPosting.
- [x] FAQPage JSON-LD sincronizzato con le 6 domande/risposte visibili per ciascuna lingua.
- [x] Verifica HTML: H1 singolo, canonical singolo e JSON valido su tutte le pagine generate.
- [x] Verifica HTTP locale: pagine pubbliche, blog, robots e sitemap rispondono 200 senza X-Robots-Tag noindex.
- [x] Verifica dominio pubblico: nessun noindex rilevato su home, FAQ e blog; robots non blocca queste URL. Sitemap online ancora vuota: caricare la release.
- [x] ZIP completo verificato.

Le pagine di conferma, amministrazione e ricerca interna rimangono escluse dall'indicizzazione. Nessun database o dato cliente incluso nello ZIP. Se site_url nel backend è vuoto, canonical e sitemap utilizzano https://comprodiamanti.piuper.site; impostarlo per installazioni su domini differenti.

Il JSON-LD descrive i contenuti: non garantisce risultati avanzati Google. Riferimenti: https://schema.org/FAQPage e https://schema.org/BlogPosting.
