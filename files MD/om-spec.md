# OM-SPEC: specifica del formato di pagina editabile

Version: 1.00
Data: 2026-10-08
Uso: knowledge base di progetto. Questo documento è la fonte di riferimento per generare, validare, pubblicare e far modificare pagine HTML senza dipendere da CMS esterni (WordPress, Elementor o altri).

---

## 0. Scopo e principi

Una pagina è un file HTML statico, completo e indicizzabile, i cui contenuti modificabili sono dichiarati con attributi `data-om-*`. Un'app di editing separata (PHP, senza database) legge quegli slot, salva i valori e rigenera la pagina pubblica.

Principi non negoziabili:

1. **Tre strati separati**: design (CSS), struttura (template annotato), contenuto (JSON). L'editing tocca solo il contenuto.
2. **La pagina pubblica è statica e pulita**: nessun attributo `data-om-*`, nessun codice di editing, nessun contenuto applicato via JavaScript.
3. **Il contenuto che vede il crawler è identico a quello che vede l'utente.** Niente testo nascosto per soli motori di ricerca.
4. **Nessun vincolo di piattaforma.** Eventuali adattatori (es. import in WordPress) sono opzionali e non fanno parte del formato.
5. **Gli id degli slot sono stabili.** Rigenerare un mockup non deve far perdere i contenuti già inseriti.

### Decisioni predefinite assunte in questa versione

Da confermare o modificare. Cambiano solo le sezioni indicate.

| Decisione | Valore assunto | Sezioni coinvolte |
|---|---|---|
| Il cliente modifica title e meta description della propria pagina | Sì, con contatore caratteri e anteprima | 1, 5.9, 11 |
| Il cliente aggiunge, rimuove e riordina le voci dei repeater | Sì, entro `min`/`max` dichiarati nel template | 1, 5.7, 11 |
| Backend | PHP, file flat, nessun database | 2, 12, 13 |
| Utenti | Più utenti: super admin e clienti | 1, 7.3, 12 |

---

## 1. Ruoli

| Capacità | Super admin | Cliente |
|---|---|---|
| Modificare i contenuti degli slot (testi, immagini, slider, hero, pulsanti) | Sì | Sì, solo pagine assegnate |
| Modificare title, meta description, immagine social | Sì | Sì, solo pagine assegnate |
| Aggiungere/rimuovere/riordinare voci di repeater | Sì | Sì, entro min/max |
| Caricare media | Sì | Sì |
| Revisioni e ripristino | Tutte | Solo pagine assegnate |
| Validatore, report markup, specifiche | Sì | No |
| Audit SEO/GEO/AEO, mappa semantica | Sì | No |
| Template sorgente, `site.css`, `design.json`, partials | Sì | No |
| `site.json` (dati attività, nav, URL preventivo) | Sì | No |
| Utenti e permessi | Sì | No |
| Rigenerazione globale del sito | Sì | No |

Il cliente non vede codice né strumenti tecnici. Se un salvataggio viene bloccato da un errore bloccante, riceve un messaggio in linguaggio semplice (es. "Manca il testo alternativo dell'immagine nella sezione Chi siamo"), mai il codice della regola.

Uno slot può essere riservato al super admin con `data-om-role="admin"`.

---

## 2. Struttura del sito

```
/site.css                  design del cliente: token, tipografia, componenti
/om-runtime.css            comportamento neutro: slider, hero, B/N (non è design)
/assets/om-slider.js       ~1 KB, incluso solo nelle pagine che hanno slider
/pages-src/{slug}.html     template annotato (frammento)
/content/{slug}.json       valori degli slot, meta SEO, versione
/public/{slug}.html        pagina statica generata ad ogni salvataggio
/partials/layout.html      involucro: {{head}} {{header}} {{content}} {{footer}}
/partials/header.html      intestazione (solo super admin)
/partials/footer.html      piè di pagina (solo super admin)
/data/site.json            dati attività, navigazione, impostazioni
/data/design.json          stili e palette ammessi nell'editor
/data/users.json           utenti, ruoli, pagine assegnate
/data/media.json           manifest dei media caricati
/data/revisions/{slug}/    versioni precedenti dei contenuti
/data/reviews.json         cache recensioni (se presente il modulo recensioni)
/media/                    file caricati e varianti generate
/admin/                    app di editing (HTML/JS)
/api/                      endpoint PHP
/sitemap.xml  /llms.txt    generati automaticamente
```

Cartelle `data/`, `content/`, `pages-src/`, `partials/`: **non raggiungibili dal web** (regola del server o posizionate fuori dalla root pubblica).

---

## 3. Flusso di generazione

Ad ogni salvataggio, ripristino o rigenerazione globale:

1. Carica `pages-src/{slug}.html` e `content/{slug}.json`.
2. Valida il template (sezione 9) e il contenuto rispetto al tipo di ciascuno slot.
3. Sanifica ogni valore con le allowlist (sezione 5).
4. Riempie gli slot, istanzia i repeater dal loro template di voce.
5. Genera `<head>`: title, meta description, canonical, Open Graph, JSON-LD (sezione 10), link a CSS, preload dell'immagine hero.
6. Inserisce header e footer da `partials`.
7. **Rimuove tutti gli attributi `data-om-*`** e i blocchi riservati (`<template data-om-item-template>`).
8. Scrive `public/{slug}.html` in modo atomico (file temporaneo, poi rinomina).
9. Salva la revisione precedente in `data/revisions/{slug}/`.
10. Rigenera `sitemap.xml` e `llms.txt`.

Il template sorgente non viene mai riscritto dall'editor dei contenuti. Per questo rigenerare un mockup non cancella i contenuti, che sono legati agli id degli slot.

### Modalità di modifica

Per l'editing inline l'app serve una variante della pagina costruita con gli attributi `data-om-*` mantenuti e il sovrapposto di editing, **solo a utenti autenticati**. La pagina pubblica non contiene mai questo codice.

---

## 4. Formato del template (`pages-src/{slug}.html`)

Il template è un **frammento**, non un documento completo:

- un elemento `<main data-om-page="{slug}" data-om-spec="1.00">` che contiene tutto il contenuto;
- facoltativo: `<style data-om-page-css>` con CSS specifico della pagina (solo ciò che non esiste già in `site.css`);
- facoltativo: `<script data-om-behavior>` con JavaScript vanilla racchiuso in una IIFE `(function(){ ... })();`.

Niente `<!DOCTYPE>`, `<html>`, `<head>`, `<body>`, librerie esterne, componenti di runtime di altri strumenti.

### 4.1 Sezioni

```html
<section data-om-section="home-hero" data-om-label="Hero" data-om-schema="none"> ... </section>
```

`data-om-schema` (facoltativo) indica quale dato strutturato generare: `faq`, `product`, `service`, `article`, `none`.

### 4.2 Slot

```html
<h2 data-om-slot="home-chisiamo-titolo" data-om-type="text" data-om-label="Titolo Chi siamo" data-om-max="70">...</h2>
```

Attributi:

| Attributo | Obbligatorio | Significato |
|---|---|---|
| `data-om-slot` | sì | id univoco dello slot |
| `data-om-type` | sì | `text`, `richtext`, `image`, `gallery`, `hero-media`, `button`, `video`, `repeater` |
| `data-om-label` | consigliato | etichetta mostrata nell'editor |
| `data-om-required` | no | `true` se il valore non può restare vuoto |
| `data-om-max` | no | numero massimo di caratteri (testi) o di elementi (gallery) |
| `data-om-allow` | no | tag aggiuntivi consentiti in un richtext (es. `h3,ul,ol,blockquote`) |
| `data-om-styles` | no | classi di stile ammesse in un richtext (devono esistere in `design.json`) |
| `data-om-role` | no | `admin` per riservare lo slot al super admin |
| `data-om-lock` | no | `true` per contenuto non editabile (dinamico o generato) |
| `data-om-alias` | no | id precedente, per migrare contenuti quando si rinomina uno slot |
| `data-om-decorative` | no | `true` su un'immagine decorativa, ammette `alt=""` |

### 4.3 Regola degli id

- kebab-case, solo `[a-z0-9-]`, massimo 64 caratteri.
- Schema: `[slug-pagina]-[sezione]-[elemento]`, es. `home-hero-titolo`, `vetrate-cat-battente-desc`.
- Univoci nella pagina.
- **Non si rinominano** dopo che esistono contenuti. Se serve, si usa `data-om-alias="vecchio-id"` così il builder migra il valore.

### 4.4 Regola del "ogni testo è uno slot"

Qualsiasi testo statico ragionevolmente isolabile deve essere uno slot: kicker (le etichette sopra i titoli), titoli e descrizioni di ogni cella di una griglia, anche di due sole parole, titoli dei passi di un configuratore, testi di aiuto, card, mini-box, CTA finale, risposte e domande FAQ. Il testo fuori da uno slot e non bloccato genera l'avviso W03.

Gli slot si applicano **direttamente sul tag esistente** (h1, h2, p, span, a), senza wrapper aggiuntivi.

### 4.5 Contenuto non editabile

Si marca con `data-om-lock="true"`. Casi tipici: testo generato da JavaScript a ogni interazione (es. il risultato di un configuratore), pulsanti generati da JavaScript, blocchi renderizzati da moduli (recensioni).

Non si usano contenuti nascosti con `hidden` solo per i crawler. Se un widget interattivo mostra varianti, tutte le varianti devono essere presenti nell'HTML come contenuto reale della pagina: pannelli di un `<details>`, schede di tab raggiungibili, o una sezione "tutte le varianti". Gli accordion sono in generale indicizzati, anche se il loro peso rispetto al testo sempre visibile può essere minore.

### 4.6 Elementi interattivi

Il testo dentro un elemento cliccabile con comportamento proprio (es. la domanda dentro il pulsante di un accordion) **non è modificabile con il clic inline** perché entrerebbe in conflitto con l'azione dell'elemento. Resta però modificabile dal **pannello laterale dei campi**, che elenca tutti gli slot della pagina. Il validatore segnala questo caso con l'avviso W11, non come errore.

---

## 5. Tipi di slot

Per ogni tipo: markup nel template, valore in `content/{slug}.json`, regole di rendering.

### 5.1 `text`

Testo semplice, una riga o poche righe, senza HTML.

- Valore: stringa.
- Sanificazione: HTML rimosso, spazi normalizzati, lunghezza limitata da `data-om-max`.

### 5.2 `richtext`

Editor HTML avanzato (basato su TipTap/ProseMirror, schema a whitelist).

- Valore: stringa HTML sanificata.
- Tag base ammessi: `p`, `br`, `strong`, `em`, `a`, `ul`, `ol`, `li`.
- Tag aggiuntivi solo se dichiarati in `data-om-allow`: `h3`, `h4`, `blockquote`, `table` e simili. Gli `h1` e `h2` non sono mai ammessi in un richtext (la gerarchia dei titoli è decisa dal template).
- Attributi ammessi: `href`, `target`, `rel` su `a`; `class` solo con valori presenti in `data-om-styles`.
- **Nessuno stile inline, nessun font-size libero, nessun colore libero.** Gli stili passano solo dai token di `design.json` (es. `om-lead`, `om-small`, `om-accent`). Così l'editing non può rompere il design.
- I link esterni ricevono `rel="noopener"`; `target="_blank"` solo se scelto esplicitamente.
- Nell'interfaccia: formattazione base, elenchi, link (con suggerimento di link interni dalla mappa semantica), stili da token, pulizia al momento dell'incolla.

### 5.3 `image`

```html
<div data-om-slot="home-chisiamo-foto" data-om-type="image" data-om-label="Foto Chi siamo">
  <img src="/media/placeholder-4x5.svg" alt="Descrizione" width="800" height="1000">
</div>
```

- Valore: `{ "media": "m_ab12", "alt": "Descrizione" }`.
- Il builder risolve `media` dal manifest e produce `<img>` con `src`, `srcset`, `sizes`, `width`, `height`, `loading="lazy"` e `decoding="async"` (`fetchpriority="high"` e niente lazy solo per l'immagine LCP).
- Il testo alternativo è obbligatorio (salvo `data-om-decorative`), massimo 125 caratteri consigliati.
- **Le immagini sono a colori**, salvo indicazione contraria nella richiesta.
- Il placeholder del template è un SVG grigio (`#e7e9e7`) con proporzioni coerenti con la sezione (4:5, 3:2, 16:11…).
- Il clic sulla foto pubblica (zoom) è un comportamento della pagina, non dell'editing, e non va sovrapposto a effetti hover di sollevamento.

### 5.4 `gallery` (una o più immagini, anche slider)

```html
<div data-om-slot="home-lavori-gallery" data-om-type="gallery" data-om-max="12" data-om-label="Galleria lavori"></div>
```

- Valore:

```json
{
  "mode": "slider",
  "items": [ { "media": "m_01", "alt": "...", "caption": "..." } ],
  "show_captions": false,
  "autoplay": false
}
```

- `mode`: `single`, `slider`, `grid`. Con un solo elemento il builder produce una semplice `<figure>` senza controlli, a prescindere dal `mode`.
- **Didascalia**: il campo `caption` è sempre modificabile nell'editor, ma `show_captions` è `false` per impostazione predefinita e la didascalia **non viene mostrata** finché il super admin non lo abilita per quella galleria.
- Slider: contenitore con scroll-snap orizzontale, pulsanti precedente/successivo, `role="group"`, `aria-roledescription="carousel"`, navigazione da tastiera, nessun autoplay di default; se l'autoplay è attivo si ferma con `prefers-reduced-motion`, con hover e con focus.
- Solo la prima immagine può essere eager; le altre sono lazy.
- JS di supporto: `/assets/om-slider.js`, incluso solo se la pagina contiene uno slider.

### 5.5 `hero-media` (una o più immagini nella hero)

```html
<div data-om-slot="home-hero-media" data-om-type="hero-media" data-om-label="Immagine hero"></div>
```

- Valore:

```json
{
  "mode": "slideshow",
  "items": [ { "media": "m_h1", "alt": "..." }, { "media": "m_h2", "alt": "..." } ],
  "interval": 5000,
  "tone": "bw-hover"
}
```

- `mode`: `static` (una sola immagine) o `slideshow` (dissolvenza). Massimo 6 immagini.
- `tone` (scelta del colore): `color` (a colori, predefinito), `bw` (bianco e nero fisso), `bw-hover` (bianco e nero che diventa a colori al passaggio del mouse). Sui dispositivi senza hover (`@media (hover:none)`) `bw-hover` mostra l'immagine a colori.
- Prestazioni: la prima immagine ha `fetchpriority="high"`, nessun `loading="lazy"` e un `<link rel="preload" as="image" imagesrcset=...>` nel `<head>`; le altre sono lazy. Il contenitore ha `aspect-ratio` esplicito per evitare spostamenti di layout.
- Lo slideshow rispetta `prefers-reduced-motion` (mostra solo la prima immagine) e si ferma con hover e focus.

### 5.6 `button`

```html
<a data-om-slot="home-hero-cta" data-om-type="button" class="btn btn-cta" href="#preventivo">
  <span class="om-label">Richiedi il preventivo</span>
</a>
```

- Valore: `{ "label": "...", "href": "...", "target": "_self", "rel": "" }`.
- Il builder sostituisce **solo** lo `<span class="om-label">`, lasciando intatte eventuali icone. Se il pulsante non ha icone, può essere omesso lo span e il testo è il contenuto del tag.
- `href` ammessi: `http`, `https`, `mailto`, `tel`, ancore `#...`, percorsi relativi. Qualsiasi altro schema è rifiutato.
- **Ogni card di una griglia tipo catalogo ha il proprio pulsante** "Scopri di più" (o equivalente) verso la pagina o sezione di quella card.
- L'URL del modulo di preventivo o appuntamento non è scritto fisso nel template: viene letto da `site.json` (`preventivo_url`). Nei template e negli script si usa il segnaposto `{{site.preventivo_url}}`, risolto dal builder; negli script di comportamento si legge da `window.OM_SITE.preventivoUrl`, che il builder inietta solo se la pagina lo richiede.

### 5.7 `repeater` (card, celle, FAQ, liste di elementi)

```html
<div data-om-slot="home-faq-lista" data-om-type="repeater" data-om-min="3" data-om-max="10" data-om-schema="faq" data-om-label="Domande frequenti">
  <template data-om-item-template>
    <details>
      <summary><span data-om-slot="domanda" data-om-type="text"></span></summary>
      <div data-om-slot="risposta" data-om-type="richtext"></div>
    </details>
  </template>
</div>
```

- Il `<template data-om-item-template>` descrive la struttura di **una** voce. Gli slot interni hanno id **locali** (`domanda`, `risposta`), senza prefisso.
- Valore:

```json
{
  "items": [
    { "uid": "a1b2", "slots": { "domanda": "...", "risposta": "<p>...</p>" } }
  ]
}
```

- `uid` è stabile e generato dall'app: serve per riordino, revisioni e confronto.
- Il cliente può aggiungere, rimuovere e riordinare le voci entro `data-om-min` e `data-om-max`. Fuori da questi limiti il salvataggio è rifiutato.
- Un repeater può contenere anche slot di tipo `image`, `button`, `richtext`.
- Se il repeater ha `data-om-schema="faq"`, il builder genera il JSON-LD `FAQPage` dalle sue voci.

### 5.8 `video`

- Valore: `{ "url": "https://www.youtube.com/watch?v=...", "poster": "m_xx", "mode": "poster" }`.
- Sono ammessi solo URL YouTube o Vimeo riconosciuti dal server (nessun embed HTML libero). Il builder genera l'URL di embed.
- `mode`: `poster` (immagine cliccabile che carica il player al clic, consigliato per le prestazioni) oppure `player`.
- Formato dichiarato nel template con `data-om-ratio="16:9"` o `9:16`.

### 5.9 Meta SEO della pagina (non sono slot)

Nel file di contenuto, blocco `meta`:

```json
"meta": {
  "title": "...",
  "description": "...",
  "og_image": "m_og1",
  "noindex": false,
  "canonical": ""
}
```

- `title` consigliato fino a 60 caratteri, `description` fino a 160. L'editor mostra un contatore e un'anteprima del risultato di ricerca.
- Il cliente può modificare `title`, `description`, `og_image`. `noindex` e `canonical` sono riservati al super admin.

---

## 6. File di contenuto (`content/{slug}.json`)

```json
{
  "spec": "1.00",
  "slug": "home",
  "version": 14,
  "updated_at": "2026-10-08T11:23:00Z",
  "updated_by": "u_02",
  "meta": { "title": "...", "description": "...", "og_image": "m_og1", "noindex": false, "canonical": "" },
  "slots": {
    "home-hero-titolo": "Titolo",
    "home-hero-media": { "mode": "static", "items": [ { "media": "m_h1", "alt": "..." } ], "tone": "color" },
    "home-hero-cta": { "label": "Richiedi il preventivo", "href": "#preventivo", "target": "_self", "rel": "" },
    "home-faq-lista": { "items": [ { "uid": "a1b2", "slots": { "domanda": "...", "risposta": "<p>...</p>" } } ] }
  }
}
```

Regole:

- `version` è un intero che si incrementa a ogni salvataggio. L'API richiede `base_version` e risponde `409` se non coincide (modifica concorrente).
- Slot presenti nel template ma assenti nel JSON usano il valore iniziale scritto nel template.
- Slot presenti nel JSON ma non più nel template vengono conservati in `orphans` e segnalati al super admin; non vengono persi.

---

## 7. File dei dati del sito

### 7.1 `data/site.json`

```json
{
  "name": "Nome commerciale",
  "legal_name": "Ragione sociale",
  "base_url": "https://www.esempio.it",
  "lang": "it",
  "address": { "street": "", "postal_code": "", "city": "", "region": "", "country": "IT" },
  "phone": "",
  "email": "",
  "vat": "",
  "hours": [ { "days": "Mo-Fr", "open": "09:00", "close": "18:00" } ],
  "geo": { "lat": 0, "lng": 0 },
  "social": [ "https://..." ],
  "brands": [ { "name": "", "url": "" } ],
  "products": [ { "name": "", "url": "", "material": "" } ],
  "preventivo_url": "https://www.esempio.it/fissa-un-appuntamento/",
  "nav": [ { "label": "Home", "href": "/" } ],
  "default_og_image": "m_og0"
}
```

È l'**unica fonte** per nome, indirizzo, telefono, prodotti e marchi. I testi non copiano questi dati a mano e il builder li riusa per il JSON-LD. Questo evita errori di riferimento.

### 7.2 `data/design.json`

```json
{
  "styles": [
    { "class": "om-lead", "label": "Testo introduttivo", "applies": "block" },
    { "class": "om-small", "label": "Nota", "applies": "block" },
    { "class": "om-accent", "label": "Evidenzia", "applies": "inline" }
  ],
  "palette": [ { "token": "brand", "label": "Verde" } ]
}
```

Definisce che cosa l'editor mette a disposizione. Le classi devono esistere in `site.css`.

### 7.3 `data/users.json`

```json
[
  {
    "id": "u_01",
    "name": "Nome",
    "email": "nome@esempio.it",
    "role": "superadmin",
    "password_hash": "$argon2id$...",
    "pages": ["*"],
    "disabled": false,
    "last_login": "2026-10-08T09:00:00Z"
  },
  {
    "id": "u_02",
    "name": "Cliente",
    "email": "cliente@esempio.it",
    "role": "client",
    "password_hash": "$argon2id$...",
    "pages": ["home", "chi-siamo"],
    "disabled": false
  }
]
```

### 7.4 `data/media.json`

```json
{
  "m_ab12": {
    "file": "2026/10/ab12.webp",
    "original": "2026/10/ab12-orig.jpg",
    "width": 1600, "height": 1067,
    "variants": [480, 768, 1200, 1600],
    "formats": ["webp", "avif"],
    "uploaded_by": "u_02",
    "uploaded_at": "2026-10-08T10:00:00Z"
  }
}
```

---

## 8. Contratto del design

- `site.css` contiene token (colori, font, spaziature) dichiarati su `:root`, tipografia e componenti. Nessuna pagina ridichiara i token.
- `om-runtime.css` contiene solo il comportamento neutro di slider, hero, effetto B/N. Non ha scelte estetiche.
- Il CSS di pagina (`<style data-om-page-css>`) contiene **solo** ciò che non è già in `site.css`. Se supera circa 80 righe, il validatore avvisa (W09).
- Griglie fluide con `auto-fit`/`minmax`, senza breakpoint inutili.
- Font: self-hosted (preferibile, anche per il GDPR) invece di caricarli da servizi esterni (avviso W10).
- Animazioni hover sui box: un'unica transizione condivisa, definita in `site.css`, non ridefinita per pagina.
- Il design è proprietà del super admin. L'editor dei clienti non può cambiare font, colori o dimensioni oltre ai token di `design.json`.

---

## 9. Validatore (solo super admin)

Eseguito al salvataggio del template e su richiesta. Gli **errori** bloccano la generazione. Gli **avvisi** vengono mostrati ma non bloccano.

### Errori

| Codice | Regola |
|---|---|
| E01 | id di slot duplicato nella pagina |
| E02 | `data-om-type` mancante o non valido |
| E03 | id non in kebab-case o che non inizia col prefisso dello slug |
| E04 | zero H1 oppure più di un H1 |
| E05 | immagine senza `width` e `height` |
| E06 | immagine senza `alt` (salvo `data-om-decorative`) |
| E07 | attributo `style` inline (salvo uso interno del builder) |
| E08 | tag non ammessi nel template: `iframe` (salvo embed generato dal builder), `object`, `embed`, `form` con action esterna |
| E09 | script esterni o librerie; `<script>` non marcato `data-om-behavior`, o non in IIFE |
| E10 | contenuto nascosto per soli crawler (`hidden` o `display:none` su testo non raggiungibile dall'interfaccia) |
| E11 | repeater senza `<template data-om-item-template>` o con min/max incoerenti |
| E12 | `data-om-allow` o `data-om-styles` con valori non ammessi o non presenti in `design.json` |
| E13 | `href` con schema non consentito |
| E14 | più `<main>` o assenza di `data-om-page` |

### Avvisi

| Codice | Regola |
|---|---|
| W01 | salto di livello nei titoli (es. H2 → H4) |
| W02 | un H2 non è seguito da un paragrafo di sintesi (AEO) |
| W03 | testo orfano: testo statico di oltre 15 caratteri fuori da uno slot e non bloccato |
| W04 | testo alternativo oltre 125 caratteri |
| W05 | title oltre 60 o description oltre 160 caratteri |
| W06 | sezione senza alcun link interno dove sarebbe naturale averne uno |
| W07 | mancano landmark (`main`, `nav`, `footer`) nel layout |
| W08 | immagine hero con `loading="lazy"` |
| W09 | CSS di pagina oltre 80 righe |
| W10 | font caricati da servizi esterni |
| W11 | slot dentro un elemento interattivo: non editabile con clic inline, solo da pannello |
| W12 | slot con `data-om-max` superato dal valore iniziale |
| W13 | FAQ con meno di 3 voci o risposte non coerenti con il contenuto della pagina (controllo manuale suggerito) |

---

## 10. SEO, GEO, AEO

### 10.1 Generazione automatica a ogni build

- `<title>`, meta description, `canonical`, Open Graph, Twitter card.
- JSON-LD:
  - `LocalBusiness` (o `Organization`) da `site.json`;
  - `BreadcrumbList` da navigazione e percorso;
  - `FAQPage` dalle sezioni con `data-om-schema="faq"`;
  - `Product` o `Service` dalle sezioni con `data-om-schema="product"` / `"service"`;
  - `Article` per articoli e guide.
- `sitemap.xml` con data di ultima modifica reale.
- `llms.txt` con elenco e descrizione delle pagine principali.

Nota onesta: da alcuni anni Google mostra i risultati avanzati delle FAQ solo per siti istituzionali di certi settori. Il markup `FAQPage` resta comunque utile per altri motori e per i sistemi di risposta basati su AI, ma non va venduto al cliente come garanzia di rich result.

### 10.2 Regole di scrittura (AEO)

- Sotto ogni H2, un paragrafo di sintesi di circa 40-60 parole che risponde alla domanda implicita del titolo, prima dei dettagli.
- Frasi chiare e autonome, definizioni esplicite dei termini chiave, liste e tabelle per i confronti.
- FAQ con domande reali che il cliente si pone **per quel prodotto o servizio specifico**, e risposte coerenti con i contenuti della pagina. Non FAQ generiche o riadattate da un'altra pagina.

### 10.3 Regole di entità (GEO)

- Nome, indirizzo, telefono, marchi e prodotti presi da `site.json`, mai riscritti a mano.
- Descrizione coerente dell'attività in tutte le pagine (stessi nomi di prodotto, stessi materiali, stesso posizionamento).
- Fonti e dati verificabili quando si citano numeri o norme.

### 10.4 Mappa semantica e collegamenti interni

Il super admin ha una vista che incrocia sitemap e contenuti per proporre link interni. L'editor di testo suggerisce, mentre il cliente scrive, le pagine del sito semanticamente pertinenti. I link interni sono inseriti solo dove il contenuto li giustifica in modo naturale, mai forzati.

### 10.5 Audit per pagina (solo super admin)

Punteggio indicativo su 100:

| Area | Peso | Cosa controlla |
|---|---|---|
| Struttura | 25 | heading, landmark, validatore senza errori |
| Contenuto | 25 | lunghezza, leggibilità, testi orfani, coerenza testo/immagine |
| SEO tecnico | 20 | meta, canonical, immagini, prestazioni di base |
| AEO | 15 | paragrafi di sintesi, FAQ, definizioni |
| GEO | 15 | entità coerenti, dati attività, link interni, fonti |

---

## 11. App di editing

### 11.1 Schermate

- **Accesso**: login, sessione, uscita.
- **Pagine**: elenco filtrato per ruolo (il cliente vede solo le sue), con stato e data ultima modifica. Il super admin vede anche il punteggio di audit.
- **Editor di pagina**:
  - anteprima della pagina in modalità di modifica (clic su uno slot per modificarlo);
  - pannello laterale con l'albero di sezioni e **tutti** gli slot come campi, compresi quelli non cliccabili in anteprima;
  - editor avanzato per i richtext;
  - gestione di slider e hero (aggiunta, rimozione, riordino per trascinamento, alt per ogni immagine, scelta `tone`);
  - repeater con aggiunta, rimozione e riordino;
  - scheda **SEO** (title, description, immagine social) con contatori e anteprima;
  - pulsante **Salva** esplicito (nessun salvataggio automatico) e **Anteprima**;
  - revisioni con confronto e ripristino.
- **Media**: caricamento, libreria, ricerca, eliminazione dei file non usati.
- **Solo super admin**: Template sorgente (con validatore), Specifiche, Audit, Mappa semantica, Design, Sito (`site.json`), Utenti, Rigenerazione globale.

### 11.2 Comportamento di salvataggio

1. Il client invia i valori con `base_version` e token CSRF.
2. Il server valida tipo, lunghezza, ruolo, allowlist.
3. Se `base_version` non coincide: risposta `409` con il contenuto attuale; l'app propone un confronto.
4. Se è valido: scrive il JSON, crea la revisione, rigenera la pagina, risponde con la nuova versione.
5. Errori bloccanti: messaggio in linguaggio semplice per il cliente, dettaglio con codice per il super admin.

---

## 12. API (JSON, PHP)

Tutte le scritture richiedono sessione valida e header `X-CSRF-Token`. I permessi si verificano **sul server**, mai solo nell'interfaccia.

| Metodo e percorso | Ruolo | Funzione |
|---|---|---|
| `POST /api/auth/login` | pubblico (con rate limit) | accesso |
| `POST /api/auth/logout` | autenticato | uscita |
| `GET /api/me` | autenticato | utente corrente e permessi |
| `GET /api/pages` | autenticato | elenco (filtrato per ruolo) |
| `GET /api/pages/{slug}` | autenticato, pagina assegnata | slot, valori, meta, versione |
| `PUT /api/pages/{slug}/content` | autenticato, pagina assegnata | salva contenuti e meta |
| `POST /api/pages/{slug}/preview` | autenticato, pagina assegnata | genera senza scrivere |
| `GET /api/pages/{slug}/revisions` | autenticato, pagina assegnata | elenco revisioni |
| `POST /api/pages/{slug}/restore` | autenticato, pagina assegnata | ripristina una revisione |
| `POST /api/media` | autenticato | caricamento |
| `GET /api/media` | autenticato | libreria |
| `DELETE /api/media/{id}` | autenticato (solo non usati) | eliminazione |
| `GET/PUT /api/pages-src/{slug}` | super admin | template sorgente |
| `POST /api/pages/{slug}/validate` | super admin | validatore |
| `GET /api/audit/{slug}` | super admin | audit SEO/GEO/AEO |
| `GET /api/semantic-map` | super admin | mappa semantica e link suggeriti |
| `GET/PUT /api/site` | super admin | `site.json` |
| `GET/PUT /api/design` | super admin | `design.json` |
| `GET/POST/PUT/DELETE /api/users` | super admin | utenti |
| `POST /api/build/all` | super admin | rigenerazione globale |

---

## 13. Sicurezza

- Password con `password_hash` (argon2id), sessioni con cookie `HttpOnly`, `Secure`, `SameSite=Lax` o `Strict`, rigenerazione dell'id di sessione al login.
- Rate limit e blocco temporaneo sui tentativi di accesso falliti.
- Protezione CSRF su ogni scrittura.
- Sanificazione lato server di ogni valore con allowlist (mai fidarsi del client).
- Validazione degli `href`, degli URL video (solo YouTube/Vimeo) e dei tipi di file.
- Upload: tipo verificato con `finfo`, nome casuale, dimensione massima, ricodifica con GD/Imagick (rimuove metadati e payload), cartella media senza esecuzione di script. SVG ammessi solo dal super admin, sanificati.
- Scrittura atomica dei file, permessi minimi sulle cartelle, cartelle dati non servite dal web.
- Log delle azioni di scrittura (chi, cosa, quando).
- Header di sicurezza (CSP, `X-Content-Type-Options`, `Referrer-Policy`) sulla pagina pubblica e sull'area admin.

---

## 14. Pipeline media

- Formati accettati: JPG, PNG, WebP, AVIF. Dimensione massima configurabile (consigliato 10 MB).
- Generazione automatica di varianti a larghezze 480, 768, 1200, 1600, 2000 px in WebP (e AVIF se disponibile), conservando l'originale.
- Rimozione dei metadati EXIF.
- Il manifest `data/media.json` conserva dimensioni e varianti; il builder usa queste informazioni per `width`, `height`, `srcset`, `sizes`.
- Il testo alternativo non si salva nel file: appartiene all'uso, cioè allo slot.

---

## 15. Integrazioni dinamiche

Funzioni che richiedono logica lato server stanno in moduli separati, ognuno con un endpoint dedicato, e non fanno parte del formato di pagina:

- **Modulo form e preventivi**: riceve i dati, li valida, invia l'email; l'URL o l'endpoint è in `site.json`.
- **Modulo recensioni Google**: interroga la Places API tramite un'esecuzione pianificata, salva `data/reviews.json` e il builder inserisce il markup nello slot bloccato (`data-om-lock`). La pagina pubblica contiene le recensioni già scritte nell'HTML. Va rispettata l'attribuzione richiesta da Google.
- **Configuratori**: JavaScript vanilla in `<script data-om-behavior>`. Il risultato è testo generato, quindi bloccato; ogni variante possibile è comunque presente nell'HTML come contenuto reale (vedi 4.5).

---

## 16. Regole per generare un nuovo mockup conforme

Checklist per chi (o quale strumento) produce il template di una pagina.

1. Produrre un frammento `<main data-om-page="{slug}" data-om-spec="1.00">`, senza `<!DOCTYPE>`, `<html>`, `<head>`, `<body>`.
2. Usare solo token e classi di `site.css`. Non inventare colori, font, bottoni o pattern nuovi. Creare nuove classi solo per pattern che non esistono ancora.
3. Se esiste una pagina di riferimento, **copiarne solo l'aspetto** (colori, font, pattern, layout), mai i contenuti. I testi si scrivono da zero sull'argomento reale indicato nella richiesta.
4. Verificare i riferimenti (indirizzo, telefono, nome del brand, nomi di prodotto, materiali) sul sito del cliente prima di scriverli, e prenderli da `site.json` quando disponibili. Non inventare prodotti per somiglianza.
5. Coerenza testo-immagine: non descrivere nel testo un materiale o una finitura diversi da quelli dell'immagine richiesta.
6. Immagini a colori salvo indicazione diversa. Per la hero chiedere esplicitamente la modalità `tone` (`color`, `bw`, `bw-hover`).
7. Dichiarare come slot ogni testo ragionevolmente isolabile (sezione 4.4), con id nello schema `[slug]-[sezione]-[elemento]`.
8. Per ogni immagine: slot `image`, `gallery` o `hero-media`, placeholder SVG grigio `#e7e9e7` con proporzioni corrette, `width`, `height` e `alt`.
9. Ogni card di una griglia catalogo ha il proprio pulsante "Scopri di più" con slot `button`.
10. Inserire link interni verso altre sezioni o pagine del sito solo quando il contenuto li giustifica.
11. FAQ scritte sul contenuto reale della pagina, come `repeater` con `data-om-schema="faq"`.
12. Gerarchia dei titoli corretta: un solo H1, poi H2 e H3 in ordine, ogni H2 seguito da un paragrafo di sintesi.
13. Nessuno stile inline, nessuna libreria esterna, nessun contenuto nascosto per i crawler.
14. Elementi interattivi con JavaScript vanilla in una IIFE dentro `<script data-om-behavior>`. Il testo generato dopo un'azione è `data-om-lock`.
15. Strutture a sezioni alternate coerenti con il design (sfondi chiaro, grigio, scuro) come da `site.css`.
16. Larghezza massima del contenuto: quella definita in `site.css` per quel cliente.
17. Eseguire il validatore e correggere tutti gli errori prima della consegna.

---

## 17. Compatibilità con le pagine 1.05 (Oltre Editable Media)

Le pagine già realizzate con il plugin `.editable-*` possono migrare senza perdere i contenuti. Lo script di migrazione converte:

| Vecchio | Nuovo |
|---|---|
| `.editable-photo` + id | `data-om-type="image"`, stesso id |
| `.editable-video` + id | `data-om-type="video"`, stesso id |
| `.editable-text` + id | `data-om-type="text"` o `richtext` (se contiene formattazione) |
| `.editable-button` + id | `data-om-type="button"`, stesso id |
| Blocco `-seo-content` con `hidden` | eliminato; le varianti diventano contenuto visibile (4.5) |
| Doppio blocco HTML per widget nativi | pagina unica |

Gli id sono conservati, quindi i valori già salvati restano associati.

---

## 18. Adattatori opzionali

Non sono richiesti dal formato. Se in futuro servono:

- **Import in WordPress**: il template e i contenuti possono essere convertiti in pattern Gutenberg. Per le schede prodotto WooCommerce si usano i **blocchi**, non gli shortcode.
- **Altri sistemi**: qualsiasi destinazione può consumare `pages-src` + `content` + `design.json`.

---

## 19. Piano di sviluppo suggerito

| Fase | Contenuto |
|---|---|
| M1 | Parser del template, validatore, builder (merge, sanificazione, head, JSON-LD) |
| M2 | Autenticazione, ruoli, API contenuti con versioni e revisioni |
| M3 | Interfaccia di editing (inline + pannello campi) con editor richtext avanzato |
| M4 | Media (upload, varianti), gallery/slider, hero multi-immagine con `tone` |
| M5 | Repeater, scheda SEO per il cliente, anteprima |
| M6 | Audit SEO/GEO/AEO, mappa semantica, sitemap e `llms.txt` |
| M7 | Moduli form/preventivi e recensioni |
| M8 | Script di migrazione dalle pagine 1.05 |

---

## 20. Esempio minimo completo

Template (`pages-src/home.html`):

```html
<main data-om-page="home" data-om-spec="1.00">
  <section data-om-section="home-hero" data-om-label="Hero">
    <span class="kicker" data-om-slot="home-hero-kicker" data-om-type="text">Serramenti a Milano</span>
    <h1 data-om-slot="home-hero-titolo" data-om-type="text" data-om-max="70">Titolo principale</h1>
    <p class="om-lead" data-om-slot="home-hero-lead" data-om-type="richtext">Sintesi che risponde alla domanda chiave.</p>
    <div data-om-slot="home-hero-media" data-om-type="hero-media" data-om-label="Immagine hero"></div>
    <a class="btn btn-cta" data-om-slot="home-hero-cta" data-om-type="button" href="{{site.preventivo_url}}">
      <span class="om-label">Richiedi il preventivo</span>
    </a>
  </section>

  <section data-om-section="home-faq" data-om-label="FAQ">
    <h2 data-om-slot="home-faq-titolo" data-om-type="text">Domande frequenti</h2>
    <div data-om-slot="home-faq-lista" data-om-type="repeater" data-om-min="3" data-om-max="10" data-om-schema="faq">
      <template data-om-item-template>
        <details>
          <summary><span data-om-slot="domanda" data-om-type="text"></span></summary>
          <div data-om-slot="risposta" data-om-type="richtext"></div>
        </details>
      </template>
    </div>
  </section>
</main>
```

---

## 21. Registro delle versioni

| Version | Data | Note |
|---|---|---|
| 1.00 | 2026-10-08 | Prima versione: formato slot, ruoli, builder PHP, validatore, SEO/GEO/AEO, migrazione dalla 1.05 |
