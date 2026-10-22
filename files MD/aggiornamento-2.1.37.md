# Aggiornamento 2.1.37 · Collegamento Frase senza password di conferma

Version: 2.1.37

- Il riquadro **Collegamento Frase** non chiede più la «password amministratore attuale»: basta incollare la chiave API di Frase e premere **Collega Frase**. La richiesta resta protetta dalla sessione amministratore e dal token CSRF; la chiave di Frase non è una credenziale del sito.
- La chiave API ora si incolla in un campo di testo normale: il gestore password del browser non la sostituisce più con la password salvata del sito.
- Negli altri riquadri del blog (preferenza di pubblicazione, token ContentAI, database) il campo si chiama ora «Password con cui accedi a questo pannello» e l'errore spiega che non è la password di Frase.
