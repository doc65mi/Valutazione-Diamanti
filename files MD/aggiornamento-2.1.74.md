# Aggiornamento 2.1.74 – Correzioni audit SEO e ricezione articoli da Frase

Version: 2.1.74

## Sito
- /index.html: redirect 301 alla home italiana (prima c'era solo per «/»). La vecchia pagina di rimando ha ora il canonical verso /it/index.html e noindex, nel caso nginx la serva senza passare da Apache.
- Title entro 60 caratteri: il suffisso « | Valutazione Diamanti» si toglie quando il titolo lo supera (Cosa acquistiamo, Chi siamo, FAQ, What we buy). Il marchio resta in og:site_name e nei dati strutturati.
- Meta description: se le correzioni automatiche dei testi («bonifico istantaneo e tracciabile») la portano oltre 160 caratteri resta il testo salvato. Nuovo testo di partenza per la home italiana (153 caratteri, già con «bonifico istantaneo e tracciabile»); se online la description non era stata cambiata dall'editor viene aggiornata da sola.
- Home IT/EN: le 4 domande frequenti diventano anche dati strutturati FAQPage.

## Blog e Frase
- Articoli: il suffisso del titolo si aggiunge solo se il titolo resta entro 60 caratteri.
- Schema FAQPage anche per gli articoli di Frase: se il campo FAQ è vuoto, le domande si leggono dalla sezione «Domande frequenti» / «FAQ» del testo (domande come titoli o in grassetto). Vale subito anche per gli articoli già pubblicati.
- Articoli in inglese: Frase non invia la lingua, quindi finivano tutti nel blog italiano. Ora la lingua di un articolo nuovo si riconosce dal testo (connettore WordPress e import via API). Gli articoli già ricevuti mantengono la lingua salvata: si cambia da Admin › Blog › articolo › Lingua.
- Import via API: la versione in un'altra lingua dello stesso documento Frase diventa un articolo separato invece di sovrascrivere quello esistente.
- Import via API: titolo SEO e meta description scritti nel sito non vengono più cancellati se Frase li manda vuoti.
- Connettore WordPress: l'eventuale H1 all'inizio del testo viene tolto (il titolo è già in pagina).

## Da fare a mano (contenuti)
- «Vendere diamanti a Milano: come funziona la valutazione da privati» ha un titolo di 66 caratteri: impostare un titolo SEO più corto (in Frase o in Admin › Blog).
- Stesso articolo: aggiungere un blocco «In breve / Sintesi».
- Articolo smeraldo: in Frase trasformare le domande in grassetto in titoli H3 (lo schema FAQ ora funziona già, ma l'audit chiede titoli a domanda).
- Home e FAQ inglese: l'audit suggerisce una riga di riepilogo finale (voce «Info», facoltativa).
