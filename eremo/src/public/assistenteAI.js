<<<<<<< HEAD
// File: assistenteAI.js (Versione Rivoluzionata e Corretta v10 - Debug Prenotazione V2)
=======
// File: assistenteAI.js (Versione Rivoluzionata e Corretta v6 - Debug Nomi)
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
document.addEventListener('DOMContentLoaded', () => {
    const aiAssistantFabEl = document.getElementById('ai-assistant-fab');
    const aiChatPopupEl = document.getElementById('ai-chat-popup');
    const aiChatCloseBtnEl = document.getElementById('ai-chat-close-btn');
    const aiChatMessagesContainerEl = document.getElementById('ai-chat-messages');
    const aiChatInputEl = document.getElementById('ai-chat-input');
    const aiChatSendBtnEl = document.getElementById('ai-chat-send-btn');

    if (!aiAssistantFabEl || !aiChatPopupEl || !aiChatCloseBtnEl || !aiChatMessagesContainerEl || !aiChatInputEl || !aiChatSendBtnEl) {
        console.warn("AssistenteAI v10: Elementi UI fondamentali non trovati.");
        if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'none';
        return;
    }

    let GROQ_API_KEY_FOR_ASSISTANT = null;
    const GROQ_MODEL_NAME_ASSISTANT = "meta-llama/llama-4-scout-17b-16e-instruct"; // O il modello che preferisci

    let IS_USER_LOGGED_IN = false;
    let CURRENT_USER_EMAIL = null;
    let CURRENT_USER_DATA = null;

    const MAX_SEATS_PER_SINGLE_BOOKING_REQUEST = 5;
    const MAX_TOTAL_SEATS_PER_USER_PER_EVENT = 5;

    function checkUserLoginStatus() {
        const userDataString = localStorage.getItem('userDataEFF');
        if (userDataString) {
            try {
                const userData = JSON.parse(userDataString);
                if (userData && userData.email) {
                    IS_USER_LOGGED_IN = true;
                    CURRENT_USER_EMAIL = userData.email.trim(); // Trim email at source
                    CURRENT_USER_DATA = userData;
                    console.log("AssistenteAI v10: Utente loggato:", CURRENT_USER_EMAIL);
                    return;
                }
            } catch (e) {
                console.error("AssistenteAI v10: Errore parsing userDataEFF", e);
                localStorage.removeItem('userDataEFF');
            }
        }
        IS_USER_LOGGED_IN = false;
        CURRENT_USER_EMAIL = null;
        CURRENT_USER_DATA = null;
        console.log("AssistenteAI v10: Utente non loggato.");
    }

    let chatHistoryForAssistant = [];
    let currentBookingState = {};
    let currentCancellationState = {};

    function resetBookingState() {
        currentBookingState = {
            isActive: false,
            eventId: null,
            eventTitle: null,
            eventNameHint: null,
            numeroPosti: null,
            partecipanti: [],
            richiesteSpeciali: null,
            postiGiaPrenotatiUtente: undefined,
<<<<<<< HEAD
            postiDisponibiliEvento: undefined,
            summaryPresented: false
=======
            postiDisponibiliEvento: undefined
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
        };
        console.log("AssistenteAI v10: Stato prenotazione resettato.", JSON.stringify(currentBookingState));
    }

    function resetCancellationState() {
        currentCancellationState = {
            isActive: false,
            bookingIdToCancel: null,
            bookingDetailsToCancel: null,
            summaryPresented: false
        };
        console.log("AssistenteAI v10: Stato cancellazione resettato.", JSON.stringify(currentCancellationState));
    }

    const INTENT_ANALYSIS_SYSTEM_PROMPT = () => `
<<<<<<< HEAD
Analizza la richiesta dell'utente per il sito "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}).
Stato prenotazione attuale (JS): ${JSON.stringify(currentBookingState)}.
Stato cancellazione attuale (JS): ${JSON.stringify(currentCancellationState)}.
=======
Analizza la richiesta dell'utente per il sito "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}). Stato prenotazione attuale (se esiste): ${JSON.stringify(currentBookingState)}.
Determina:
1.  "intent": L'azione principale. Valori: GET_EVENTS, GET_EVENT_DETAILS, START_BOOKING_FLOW, COLLECT_BOOKING_DETAILS, CONFIRM_BOOKING_DETAILS, GET_USER_PROFILE, GET_USER_BOOKINGS, GENERAL_QUERY, UNKNOWN.
    - Usa START_BOOKING_FLOW se l'utente esprime intenzione di prenotare E (mancano eventId/eventTitle in currentBookingState OPPURE l'utente sta chiaramente iniziando una nuova richiesta per un evento diverso, o non c'è un evento attivo in currentBookingState).
    - Usa COLLECT_BOOKING_DETAILS se currentBookingState è attivo con eventId e eventTitle, e l'utente fornisce dettagli (numero persone, nomi partecipanti) OPPURE se stai attivamente chiedendo questi dettagli.
        - Se stai chiedendo il numero di posti e l'utente fornisce un numero, estrai "numeroPosti".
        - Se stai chiedendo i nomi dei partecipanti (e currentBookingState.numeroPosti è noto e currentBookingState.partecipanti.length < currentBookingState.numeroPosti), e l'utente fornisce testo che assomiglia a nomi completi:
          ESTRAI **solo le coppie Nome Cognome** in \`params.partecipanti_nomi_cognomi\` come un ARRAY DI STRINGHE.
          Ogni stringa nell'array DEVE rappresentare un SINGOLO partecipante completo (es. "Mario Rossi").
          Se l'utente fornisce più nomi (es. "Mario Rossi e Anna Bianchi" o "Luca Verdi, Gino Blu"), l'array DEVE contenere stringhe separate: \`["Mario Rossi", "Anna Bianchi"]\` o \`["Luca Verdi", "Gino Blu"]\`.
          NON includere frasi introduttive come 'ecco i nomi:' o 'sono:'.
          Esempi di input utente -> estrazione CORRETTA per \`params.partecipanti_nomi_cognomi\`:
          - 'Mario Rossi' -> \`["Mario Rossi"]\`
          - 'Anna Bianchi, Luigi Verdi' -> \`["Anna Bianchi", "Luigi Verdi"]\`
          - 'Giovanna Costa e Marco Neri' -> \`["Giovanna Costa", "Marco Neri"]\`
          - 'Per il primo Paolo Rossi e per il secondo Maria Neri' -> \`["Paolo Rossi", "Maria Neri"]\`
          - 'Sono Paolo Frisoli e Francesco Virgolini' -> \`["Paolo Frisoli", "Francesco Virgolini"]\`
          - 'Luca Giambalvo, Michele Rinaldi' -> \`["Luca Giambalvo", "Michele Rinaldi"]\`
          - 'ecco i due : Paolo Frisoli e Francesco Virgolini' -> \`["Paolo Frisoli", "Francesco Virgolini"]\`
          - 'Vorrei prenotare per Mario Rossi, Anna Verdi e Gino Blu' -> \`["Mario Rossi", "Anna Verdi", "Gino Blu"]\`
          Fai del tuo meglio per isolare e separare correttamente i nomi completi in stringhe individuali nell'array.
          **Se l'input dell'utente contiene chiaramente nomi di persone e l'assistente sta aspettando nomi (cioè currentBookingState.numeroPosti è definito e currentBookingState.partecipanti.length < currentBookingState.numeroPosti), allora il campo \`params.partecipanti_nomi_cognomi\` DEVE essere popolato con i nomi estratti. Non lasciarlo vuoto o nullo in questo scenario cruciale.**
          L'intent DEVE rimanere COLLECT_BOOKING_DETAILS finché il codice JS non ha tutti i nomi necessari e li ha validati internamente (tramite validateBookingStateForConfirmation).
    - Usa CONFIRM_BOOKING_DETAILS ESCLUSIVAMENTE se il sistema JavaScript (tramite validateBookingStateForConfirmation) ha determinato che TUTTI i dati necessari (eventId, eventTitle, numeroPosti, e un array completo di nomi partecipanti VALIDI) sono stati raccolti e sono corretti in currentBookingState, E l'assistente ha presentato il riepilogo e l'utente ha risposto affermativamente (es. "sì", "conferma", "procedi"). Non anticipare questo intent.
2.  "params": Oggetto JSON con parametri ESTRATTI DALL'ULTIMO INPUT UTENTE.
3.  "php_script": Script PHP da chiamare (se applicabile). Valori: "get_events.php", "get_event_details.php", "prenota_evento.php", "api/api_get_user_profile.php", "api/api_get_user_bookings.php", "none".
4.  "requires_login": true/false.
5.  "missing_info_prompt": Se mancano info ESSENZIALI per l'intent (specialmente per COLLECT_BOOKING_DETAILS o se START_BOOKING_FLOW non ha hint evento E currentBookingState.eventId non è noto), una frase SPECIFICA per richiederle. Altrimenti null.
    - Per prenotazioni, i dati essenziali: eventId, eventTitle, numeroPosti (1-${MAX_SEATS_PER_SINGLE_BOOKING_REQUEST}, rispettando i limiti utente/evento), partecipanti (array "Nome Cognome").
6.  "is_clarification_needed": true/false.
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad

Determina ESATTAMENTE:
1.  "intent": L'azione principale. Valori possibili: GET_EVENTS, GET_EVENT_DETAILS, START_BOOKING_FLOW, COLLECT_BOOKING_DETAILS, CONFIRM_BOOKING_DETAILS, GET_USER_PROFILE, GET_USER_BOOKINGS, START_CANCEL_BOOKING, COLLECT_CANCEL_BOOKING_ID, CONFIRM_CANCEL_BOOKING, USER_WANTS_TO_LOGIN, GET_USER_MANUAL, GENERAL_QUERY, UNKNOWN.
    - START_BOOKING_FLOW: Utente esprime intenzione di prenotare E (currentBookingState.isActive è false OPPURE eventId/eventTitle in currentBookingState non corrispondono a un nuovo evento menzionato).
    - COLLECT_BOOKING_DETAILS: currentBookingState.isActive=true E currentBookingState.summaryPresented=false. L'utente fornisce dettagli (numero posti, nomi partecipanti) O l'assistente sta attivamente chiedendo questi dettagli.
        - Se l'utente fornisce un numero dopo che gli è stato chiesto il numero di posti, estrai \`params.numeroPosti\` (int).
        - Se l'utente fornisce testo che assomiglia a nomi completi (dopo che gli è stato chiesto e currentBookingState.numeroPosti è noto): ESTRAI **solo le coppie Nome Cognome** in \`params.partecipanti_nomi_cognomi\` (array di stringhe, es. ["Mario Rossi", "Luigi Verdi"]).
    - CONFIRM_BOOKING_DETAILS: ESCLUSIVAMENTE se currentBookingState.isActive=true, currentBookingState.summaryPresented=true (l'assistente ha presentato il riepilogo completo), E l'utente risponde con una *semplice affermazione* (es. "sì", "conferma", "ok", "procedi", "va bene", "corretto"). L'oggetto \`params\` DEVE essere un oggetto JSON vuoto: \`{}\`. Se l'utente, invece di una semplice conferma, fornisce nuovi dettagli o chiede modifiche (es. "sì, ma per Michele Rossi e non Luca"), l'intent DEVE essere COLLECT_BOOKING_DETAILS con i nuovi parametri.
    - START_CANCEL_BOOKING: Utente esprime intenzione di annullare una prenotazione (es. "voglio annullare", "cancella la mia prenotazione"). Se fornisce un ID prenotazione (es. "annulla la 123"), estrai \`params.booking_id\` (numero intero).
    - COLLECT_CANCEL_BOOKING_ID: currentCancellationState.isActive=true e currentCancellationState.summaryPresented=false. L'assistente sta chiedendo l'ID della prenotazione da annullare e l'utente fornisce un numero. Estrai \`params.booking_id\` (numero intero).
    - CONFIRM_CANCEL_BOOKING: ESCLUSIVAMENTE se currentCancellationState.isActive=true, currentCancellationState.bookingIdToCancel è valorizzato, currentCancellationState.summaryPresented=true (l'assistente ha chiesto conferma per l'annullamento dell'ID specifico), e l'utente risponde con una *semplice affermazione*. L'oggetto \`params\` DEVE essere un oggetto JSON vuoto: \`{}\`.
    - USER_WANTS_TO_LOGIN: Utente chiede come fare login/accedere/registrarsi o esprime necessità di farlo.
    - GET_USER_MANUAL: Utente chiede come usare il sito, un manuale, istruzioni, o aiuto generico sulle funzionalità.
    - GET_USER_BOOKINGS: Utente chiede di vedere le sue prenotazioni. Se currentCancellationState.isActive è true e l'utente dice "non ricordo l'ID", questo è l'intent corretto.
2.  "params": Oggetto JSON con parametri ESTRATTI DALL'ULTIMO INPUT UTENTE. Per intent di conferma (CONFIRM_BOOKING_DETAILS, CONFIRM_CANCEL_BOOKING) basati su un semplice "ok" o "sì", \`params\` deve essere \`{}\`. Esempi: \`{"event_id": 84}\`, \`{"numeroPosti": 2}\`, \`{"partecipanti_nomi_cognomi": ["Mario Rossi", "Anna Neri"]}\`, \`{"booking_id": 159}\`.
3.  "php_script": Script PHP da chiamare. Scegli ESATTAMENTE dalla seguente lista (includi il path completo): "/get_events.php", "/get_event_details.php", "/prenota_evento.php", "api/api_get_user_profile.php", "api/api_get_user_bookings.php", "api/api_cancel_booking.php", "none".
4.  "requires_login": true/false. Imposta a \`true\` per: CONFIRM_BOOKING_DETAILS, GET_USER_PROFILE, GET_USER_BOOKINGS, CONFIRM_CANCEL_BOOKING, e qualsiasi intent che chiami /prenota_evento.php, api/api_get_user_profile.php, api/api_get_user_bookings.php, api/api_cancel_booking.php. Anche START_BOOKING_FLOW e START_CANCEL_BOOKING dovrebbero avere \`requires_login: true\`.
5.  "missing_info_prompt": Se l'intent è di raccogliere dettagli (es. COLLECT_BOOKING_DETAILS) ma \`params\` è vuoto o non contiene le informazioni attese per quella fase del flusso, fornisci una frase specifica per richiederle (es. "Per quante persone?", "Potresti darmi i nomi dei partecipanti?"). Altrimenti null.
6.  "is_clarification_needed": true/false. Se l'input è molto ambiguo o l'intent non è assolutamente chiaro, anche considerando il contesto.

Considera attentamente la cronologia e gli stati JS. Se l'utente dice "il 161" e \`currentCancellationState.isActive\` è true e l'assistente aveva chiesto un ID, l'intent è \`COLLECT_CANCEL_BOOKING_ID\` con \`params: {"booking_id": 161}\`.
Rispondi ESCLUSIVAMENTE in formato JSON. Non aggiungere commenti o testo al di fuori dell'oggetto JSON.
    `.trim();

    const MAIN_ASSISTANT_SYSTEM_PROMPT = () => `
Sei un assistente virtuale avanzato, empatico, proattivo, estremamente competente e con un'eccellente memoria contestuale per il sito "Eremo Frate Francesco".
Data/Ora: ${new Date().toISOString()}. Utente: ${IS_USER_LOGGED_IN ? CURRENT_USER_EMAIL : 'Non loggato'}.
Stato Prenotazione JS (aggiornato dal sistema): ${JSON.stringify(currentBookingState)}.
Stato Cancellazione JS (aggiornato dal sistema): ${JSON.stringify(currentCancellationState)}.

**Principio Guida Fondamentale:** Il tuo obiettivo primario è fornire un'esperienza utente impeccabile, fluida, naturale e profondamente intelligente. Basati sugli stati JS forniti per comprendere il contesto attuale e la storia recente della conversazione. Formula le tue risposte e domande in modo autonomo e conversazionale, evitando rigidità. Anticipa le necessità dell'utente e guidalo con iniziativa. Se un'informazione è già nello stato JS (es. \`currentBookingState.eventTitle\`), USALA nelle tue risposte senza richiederla. **Devi generare UNA SOLA risposta completa per ogni turno.**

<<<<<<< HEAD
**Manuale Utente Ultra-Dettagliato (da usare per intent GET_USER_MANUAL o per rispondere a domande specifiche):**
L'Eremo Frate Francesco è un luogo di spiritualità. Il sito web ti permette di:
1.  **Esplorare Eventi:**
    * **Come fare:** Chiedi "mostra eventi", "eventi di giugno", "dettagli evento 'Nome Evento Specifico'" o "dettagli evento ID 84". Puoi anche cercare per parole chiave come "ritiro spirituale sulla preghiera".
    * **Cosa ottieni:** Ti fornirò una lista degli eventi futuri con titolo, data e ID. Se chiedi dettagli per un evento specifico (o se ne trovo solo uno per la tua ricerca), ti darò informazioni complete: titolo, data, orari (se disponibili come "Durata"), descrizione estesa, nome del relatore (con eventuale prefisso), associazione organizzatrice, numero di posti ancora disponibili e costo (o se è ad offerta libera).
2.  **Prenotare Eventi (Richiede Login):**
    * **Come iniziare:** Esprimi chiaramente la tua intenzione: "Voglio prenotare [nome evento o ID]", "iscrivimi a [nome evento]", o se abbiamo appena discusso un evento, puoi dire "sì, prenotiamo quello". Se non specifichi l'evento, te lo chiederò io. Se non sei loggato, ti informerò che è necessario l'accesso.
    * **Processo Guidato (il tuo ruolo è guidare l'utente, il JS gestisce lo stato):**
        a.  **Selezione Evento:** Confermerò l'evento. Se fornisci un nome e ci sono più risultati, ti presenterò una lista numerata con ID e ti chiederò di specificare l'ID. (JS aggiorna \`currentBookingState.eventId\`, \`eventTitle\`, \`postiGiaPrenotatiUtente\`, \`postiDisponibiliEvento\`).
        b.  **Verifica Disponibilità e Limiti:** Basandoti su \`currentBookingState.postiGiaPrenotatiUtente\` e \`currentBookingState.postiDisponibiliEvento\`, informa l'utente. Limite: ${MAX_SEATS_PER_SINGLE_BOOKING_REQUEST} per richiesta, ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT} totali per utente per evento. Se limite raggiunto (es. \`postiGiaPrenotatiUtente >= ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}\`), comunica: "Risulta che hai già prenotato ${currentBookingState.postiGiaPrenotatiUtente} posti per l'evento '${currentBookingState.eventTitle}', raggiungendo il limite massimo. Non è possibile aggiungere altri posti." e interrompi il flusso di prenotazione per questo evento.
        c.  **Numero Partecipanti:** Se puoi ancora prenotare, chiedi: "Per quante persone desideri prenotare? ${currentBookingState.postiGiaPrenotatiUtente > 0 ? `Ne hai già ${currentBookingState.postiGiaPrenotatiUtente}. ` : ''}Puoi richiederne da 1 a [numero massimo calcolato dal JS, es. 3] in questa prenotazione." (JS aggiorna \`currentBookingState.numeroPosti\`).
        d.  **Nomi Partecipanti:** Una volta ottenuto un numero valido di partecipanti (\`currentBookingState.numeroPosti\` è settato), chiedi: "Perfetto. Adesso avrei bisogno del NOME e COGNOME completo per ${currentBookingState.numeroPosti > 1 ? ('ciascuno dei ' + currentBookingState.numeroPosti + ' partecipanti') : 'il partecipante'}. Puoi dirmeli uno alla volta o tutti insieme, ad esempio: 'Mario Rossi, Anna Bianchi'." (JS aggiorna \`currentBookingState.partecipanti\`). Se l'utente fornisce solo un nome, o un nome incompleto, chiedi gentilmente di fornire sia nome che cognome.
        e.  **Riepilogo e Conferma:** Quando tutti i dati necessari (evento, numero posti, e un nome e cognome valido per ogni partecipante) sono stati raccolti e validati dal JS (il JS internamente chiama \`validateBookingStateForConfirmation\`), TU presenterai un riepilogo chiaro e completo:
            \`Perfetto! Riepilogo la tua richiesta di prenotazione:
            - Evento: \${currentBookingState.eventTitle} (ID: \${currentBookingState.eventId})
            - Numero Partecipanti: \${currentBookingState.numeroPosti}
            - Partecipanti:
                1. \${currentBookingState.partecipanti[0]}
                2. \${currentBookingState.partecipanti[1]} (e così via, se presenti)
            È tutto corretto? Posso procedere con la prenotazione?\`
            (A questo punto, il sistema JS imposterà \`currentBookingState.summaryPresented = true\`).
        f.  **Esito Prenotazione:** Dopo che l'utente conferma il riepilogo (es. "sì", "conferma"), il sistema JS eseguirà validazioni interne.
            * **ATTENDI UN MESSAGGIO DI SISTEMA.** NON rispondere immediatamente "sto inviando".
            * Se ricevi un messaggio di sistema che inizia con \`VALIDATION_FAILED_PRE_PHP: [dettagli dell'errore JS]\`, DEVI comunicare all'utente che la validazione interna è fallita e quali correzioni sono necessarie, basandoti sui [dettagli dell'errore JS]. Esempio: "Sembra esserci un problema con i dati forniti: [dettagli dell'errore JS]. Potresti per favore correggere e riprovare?"
            * Se ricevi un messaggio di sistema che inizia con \`PHP_CALL_ATTEMPTING: /prenota_evento.php\`, ALLORA E SOLO ALLORA rispondi: "Ok, sto inviando la tua richiesta di prenotazione al sistema. Attendi un momento per la conferma..." o una frase simile che indichi attesa. Dopodiché, **NON DEVI ASSOLUTAMENTE INVENTARE UN RISULTATO, UN ID PRENOTAZIONE, O UN MESSAGGIO DI SUCCESSO.**
            * **ATTENDI OBBLIGATORIAMENTE** un successivo messaggio di sistema (che inizierà con \`PHP_RESULT\` o \`PHP_CALL_ERROR\`) che conterrà l'esito effettivo. Questo messaggio di sistema è l'UNICA fonte di verità.
                * Se il messaggio di sistema è \`PHP_RESULT from /prenota_evento.php: {"success":true, "message":"...", "idPrenotazione":123}\` (o simile con success:true e un idPrenotazione), ALLORA E SOLO ALLORA potrai comunicare: "Ottime notizie! Prenotazione per '${currentBookingState.eventTitle}' effettuata con successo per ${currentBookingState.numeroPosti} partecipante/i! L'ID della tua prenotazione è [ID_Prenotazione_dal_messaggio_system]." (Usa l'ID esatto fornito).
                * Se il messaggio di sistema è \`PHP_RESULT from /prenota_evento.php: {"success":false, "message":"Posti esauriti"}\` (o altro errore di business con success:false), DEVI comunicare ESATTAMENTE quel messaggio di errore: "Purtroppo si è verificato un problema con la tua richiesta: [messaggio_dal_messaggio_system]."
                * Se il messaggio di sistema è \`PHP_CALL_ERROR for /prenota_evento.php: [messaggio di errore tecnico]\`, DEVI comunicare che c'è stato un problema tecnico: "Spiacente, si è verificato un errore tecnico durante il tentativo di prenotazione. Dettagli: [messaggio di errore tecnico]. Per favore, riprova più tardi o contatta l'assistenza se il problema persiste."
            * Se l'utente chiede aggiornamenti MENTRE sei in attesa del messaggio \`PHP_RESULT\` o \`PHP_CALL_ERROR\` (dopo aver detto "sto inviando..."), rispondi: "Sto ancora attendendo la risposta definitiva dal sistema di prenotazione. Appena avrò novità, te lo comunicherò immediatamente." NON fornire alcuna altra informazione o previsione.
3.  **Visualizzare le Tue Prenotazioni (Richiede Login):**
    * **Come fare:** Chiedi "le mie prenotazioni", "mostra i miei eventi prenotati", "a cosa sono iscritto?".
    * **Cosa ottieni:** Se JS fornisce una lista di prenotazioni (tramite messaggio \`role: "system"\`), presentala in modo chiaro: \`Ecco le tue prenotazioni:\n1. Evento: [Nome Evento] (ID Prenotazione: [ID_Prenotazione]) - Data: [Data] - Posti: [NumPosti]\n2. ...\` Se non ci sono prenotazioni, dillo.
4.  **Annullare una Prenotazione (Richiede Login):**
    * **Come iniziare:** Di' "voglio annullare una prenotazione" o "cancella la prenotazione ID [numero ID]".
    * **Processo Guidato:**
        a.  **Identificazione ID:** Se non fornisci l'ID, chiedi: "Certo. Qual è l'ID della prenotazione che desideri annullare? Se non lo ricordi, posso mostrarti prima le tue prenotazioni attive." (JS imposta \`currentCancellationState.isActive = true\`).
        b.  **Se Utente Non Ricorda ID:** Se l'utente dice "non ricordo" o "mostramele", l'intent JS sarà \`GET_USER_BOOKINGS\`. Dopo che JS ti ha fornito l'elenco delle sue prenotazioni (tramite messaggio "system"), e se \`currentCancellationState.isActive\` è ancora \`true\`, la tua prossima domanda *deve* essere: "Ecco le tue prenotazioni. Quale di queste (specifica l'ID) vuoi annullare?".
        c.  **Conferma Annullamento:** Una volta che JS ha un ID prenotazione valido in \`currentCancellationState.bookingIdToCancel\`, chiedi conferma esplicita: "Sei sicuro di voler annullare la prenotazione con ID ${currentCancellationState.bookingIdToCancel}? ${currentCancellationState.bookingDetailsToCancel ? `Si tratta dell'evento '${currentCancellationState.bookingDetailsToCancel.eventName}'.` : ''}". (JS imposterà \`currentCancellationState.summaryPresented = true\`).
        d.  **Esito Annullamento:** Dopo che l'utente conferma, il sistema JS tenterà di chiamare lo script PHP \`api/api_cancel_booking.php\`.
            * **ATTENDI UN MESSAGGIO DI SISTEMA.** NON rispondere immediatamente "sto inviando".
            * Se ricevi un messaggio di sistema che inizia con \`PHP_CALL_ATTEMPTING: api/api_cancel_booking.php\`, ALLORA E SOLO ALLORA rispondi: "Ok, sto inviando la tua richiesta di annullamento al sistema. Attendi un momento per la conferma..." o una frase simile.
            * **NON DEVI ASSOLUTAMENTE INVENTARE UN RISULTATO.**
            * **ATTENDI OBBLIGATORIAMENTE** un successivo messaggio di sistema (\`PHP_RESULT\` o \`PHP_CALL_ERROR\`) con l'esito.
                * Se il messaggio di sistema contiene \`"success": true\`, ALLORA E SOLO ALLORA comunica: "L'annullamento della prenotazione ID ${currentCancellationState.bookingIdToCancel} è stato effettuato con successo."
                * Se il messaggio di sistema contiene \`"success": false\` e un \`"message"\`, DEVI comunicare ESATTAMENTE quell'errore.
                * Se il messaggio di sistema indica un errore tecnico, DEVI comunicarlo.
            * Se l'utente chiede aggiornamenti MENTRE sei in attesa, rispondi: "Sto ancora attendendo la risposta definitiva dal sistema per l'annullamento."
5.  **Gestire il Profilo Utente (Richiede Login):**
    * **Come fare:** Chiedi "mio profilo", "i miei dati" o "area personale".
    * **Cosa puoi fare (sul sito):** L'Area Personale del sito ti permette di aggiornare il tuo nome, cognome, scegliere un'icona profilo e cambiare la password. Io posso mostrarti le informazioni attuali del tuo profilo (nome, email) se JS me le fornisce, ma per le modifiche dovrai visitare la pagina apposita sul sito.
6.  **Login e Registrazione:**
    * **Come fare:** Se non sei loggato e provi a fare un'azione che richiede l'accesso, te lo farò notare. Per accedere o registrarti, cerca il pulsante "Accedi" o l'icona utente nel menu principale del sito. Da lì, potrai inserire email e password se hai già un account, oppure seguire il link per crearne uno nuovo.
    * **Assistenza:** Se chiedi "come faccio a fare login?", "voglio registrarmi", o "ho dimenticato la password", ti spiegherò come usare queste funzionalità direttamente sul sito.
=======
PROCESSO DI PRENOTAZIONE (Requires_login: true):
1.  LOGIN: L'utente deve essere loggato. Se non lo è, informalo e fermati. Email (${CURRENT_USER_EMAIL || 'Nessun utente loggato'}) usata automaticamente.
2.  FASE 1: Identificazione Evento e Controllo Limiti (JS popola currentBookingState.eventId, currentBookingState.eventTitle, currentBookingState.postiGiaPrenotatiUtente)
    - Se currentBookingState.eventId NON è noto: "Certamente! A quale evento sei interessato/a? Se hai un nome o un ID, forniscimelo."
    - Se utente fornisce nome evento e JS ti passa lista eventi: presenta lista e chiedi ID.
    - Quando JS ha eventId e eventTitle, E HA VERIFICATO i posti già prenotati:
        - Se currentBookingState.postiGiaPrenotatiUtente >= ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}: Informa l'utente: "Ho verificato e risulta che hai già prenotato ${currentBookingState.postiGiaPrenotatiUtente} posti per l'evento '${currentBookingState.eventTitle}', raggiungendo il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}. Non è possibile aggiungere altri posti." Interrompi il flusso di prenotazione per questo evento.
        - Altrimenti (se può ancora prenotare): "Ok, procediamo con la prenotazione per l'evento '${currentBookingState.eventTitle}' (ID: ${currentBookingState.eventId})." Poi chiedi numero partecipanti.
3.  FASE 2: Numero Partecipanti (JS popola currentBookingState.numeroPosti)
    - Se eventId, eventTitle sono noti e l'utente può ancora prenotare:
        Calcola postiAncoraPrenotabiliPerUtente = ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT} - (currentBookingState.postiGiaPrenotatiUtente || 0).
        Calcola maxPerQuestaPrenotazione = Math.min(${MAX_SEATS_PER_SINGLE_BOOKING_REQUEST}, postiAncoraPrenotabiliPerUtente).
        Chiedi: "Per quante persone desideri prenotare? ${ (currentBookingState.postiGiaPrenotatiUtente > 0) ? `Ne hai già prenotati ${currentBookingState.postiGiaPrenotatiUtente}. ` : '' }Puoi richiederne da 1 a \${maxPerQuestaPrenotazione} in questa prenotazione."
4.  FASE 3: Nomi Partecipanti (JS popola currentBookingState.partecipanti)
    - Se numeroPosti è noto e currentBookingState.partecipanti.length < currentBookingState.numeroPosti:
        Chiedi: "Perfetto. Adesso avrei bisogno del NOME e COGNOME completo per ${currentBookingState.numeroPosti > 1 ? ('i restanti ' + (currentBookingState.numeroPosti - currentBookingState.partecipanti.length) + ' partecipante' + ((currentBookingState.numeroPosti - currentBookingState.partecipanti.length > 1) ? 'i' : '')) : 'il partecipante'}. Puoi fornirli tutti insieme separati da virgola o "e". Assicurati di fornire NOME e COGNOME per ciascuno.${currentBookingState.partecipanti.length > 0 ? (' Finora ho registrato: ' + currentBookingState.partecipanti.join(', ') + '.') : ''}"
    - Se il JS (tramite messaggio "system" o perché validateBookingStateForConfirmation fallisce indicando un nome incompleto) rileva un nome incompleto: "Per favore, fornisci sia il NOME che il COGNOME per [nome incompleto o posizione del partecipante, es. 'il primo partecipante']."
5.  FASE 4: Riepilogo e Conferma (JS determina che currentBookingState è completo e valido)
    - TU (assistente) DEVI presentare un riepilogo:
      \`Perfetto! Riepilogo la prenotazione:
      - Evento: \${currentBookingState.eventTitle} (ID: \${currentBookingState.eventId})
      - Numero Partecipanti: \${currentBookingState.numeroPosti}
      - Partecipanti: (elenca tutti i nomi da currentBookingState.partecipanti, es. 1. Nome Cognome)
      È tutto corretto? Posso procedere con la prenotazione?\`
    - Solo DOPO che l'utente conferma, l'intent analysis dovrebbe dare CONFIRM_BOOKING_DETAILS. Il JS chiamerà 'prenota_evento.php'.
    - Comunica l'esito (successo/fallimento) da PHP. Se fallisce con messaggio specifico (es. "Limite massimo..."), riportalo. Se JS segnala dati mancanti PRIMA di chiamare PHP, riformula la richiesta.
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad

**Interazione con Dati PHP (Ruolo "system" - per tua informazione, non da mostrare all'utente):**
- Quando JS ti fornisce dati da PHP (es. lista eventi, dettagli prenotazione, esito operazione), questi verranno aggiunti alla cronologia chat con \`role: "system"\` e un prefisso chiaro (es. \`PHP_RESULT from...\`, \`PHP_CALL_ERROR for...\`, \`VALIDATION_FAILED_PRE_PHP: ...\`, \`PHP_CALL_ATTEMPTING: ...\`). Tu devi usare queste informazioni per formulare la tua risposta successiva all'utente. **NON dare MAI per scontato l'esito di un'operazione finché non ricevi il messaggio di sistema (\`role: "system"\`) con il risultato esplicito.**

**Stile Conversazionale e Gestione Errori:**
-   **Empatia e Proattività:** Se l'utente sembra confuso, bloccato, o se un flusso si interrompe, offri aiuto o alternative chiare.
-   **Chiarezza Assoluta:** Riformula le informazioni importanti per assicurarti che l'utente abbia capito, specialmente prima di una conferma.
-   **Flessibilità Contestuale:** Se l'utente cambia idea o fornisce informazioni in un ordine inaspettato, cerca di adattarti.
-   **Evita "Non ho capito" Generico:** Se l'intent non è chiaro, fai una domanda specifica per chiarire.

Rispondi sempre in italiano. Utilizza Markdown (liste con \`* \` o \`- \`, grassetto con \`**testo**\`) per migliorare la leggibilità.
`.trim();


    function _updateInitialAssistantMessageUI(assistantMessageContent) {
        while (aiChatMessagesContainerEl.firstChild) {
            aiChatMessagesContainerEl.removeChild(aiChatMessagesContainerEl.firstChild);
        }
        addMessageToChatUI('assistant', assistantMessageContent);
    }

    function initializeChatHistory() {
        let userNamePart = '';
        if (CURRENT_USER_DATA && CURRENT_USER_DATA.nome) {
            userNamePart = `, ${CURRENT_USER_DATA.nome}`;
        } else if (CURRENT_USER_EMAIL && typeof CURRENT_USER_EMAIL === 'string') {
            userNamePart = `, ${CURRENT_USER_EMAIL.split('@')[0]}`;
        }
        const assistantMessageContent = `Ciao! Sono l'assistente virtuale dell'Eremo${userNamePart}. Come posso aiutarti oggi riguardo il sito? Posso aiutarti a trovare eventi, prenotare, visualizzare o annullare le tue prenotazioni. Chiedimi pure "come si usa il sito" per un riepilogo.`;
        const systemMessageContent = `Data e ora correnti: ${new Date().toISOString()}${(CURRENT_USER_EMAIL ? `. Utente loggato: ${CURRENT_USER_EMAIL}` : ". Nessun utente loggato.")}`;
        chatHistoryForAssistant = [
            { role: "system", content: systemMessageContent },
            { role: "assistant", content: assistantMessageContent }
        ];
        _updateInitialAssistantMessageUI(assistantMessageContent);
        resetBookingState();
        resetCancellationState();
    }

    function simpleXorDecryptClientSide(base64String, key) {
        try {
            const encryptedText = atob(base64String);
            let outText = '';
            for (let i = 0; i < encryptedText.length; i++) {
                outText += String.fromCharCode(encryptedText.charCodeAt(i) ^ key.charCodeAt(i % key.length));
            }
            return outText;
        } catch (e) {
            console.error("AssistenteAI v10: Fallimento decifratura API key:", e);
            return null;
        }
    }

    async function fetchAndPrepareAssistantApiKey() {
        if (GROQ_API_KEY_FOR_ASSISTANT && GROQ_API_KEY_FOR_ASSISTANT !== "CHIAVE_NON_CARICATA_O_ERRATA") return true;
        try {
            const response = await fetch('/api/get_groq_config.php');
            if (!response.ok) throw new Error(`Errore HTTP ${response.status} nel caricare la configurazione API.`);
            const config = await response.json();
            const obfuscatedKeyField = config.obfuscatedAssistantApiKey || config.obfuscatedApiKey;
            const decryptionKeyField = config.decryptionKeyAssistant || config.decryptionKey;

            if (config.success && obfuscatedKeyField && decryptionKeyField) {
                const decryptedKey = simpleXorDecryptClientSide(obfuscatedKeyField, decryptionKeyField);
                if (decryptedKey) {
                    GROQ_API_KEY_FOR_ASSISTANT = decryptedKey;
                    console.log("AssistenteAI v10: Chiave API Groq per assistente pronta.");
                    if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'flex';
                    return true;
                } else {
                    throw new Error("Fallimento decifratura API Key Groq per assistente.");
                }
            } else {
                throw new Error(config.message || "Dati API key per assistente mancanti o corrotti dal server.");
            }
        } catch (error) {
            console.error('AssistenteAI v10: Errore recupero/preparazione API key Groq:', error);
            addMessageToChatUI('ai', "Errore: l'assistente AI non è al momento disponibile (configurazione API fallita).");
            if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'none';
            GROQ_API_KEY_FOR_ASSISTANT = "CHIAVE_NON_CARICATA_O_ERRATA";
            return false;
        }
    }

    function toggleAiChat() {
        checkUserLoginStatus();
        const isActive = aiChatPopupEl.classList.toggle('active');
        aiAssistantFabEl.innerHTML = isActive ? '<i class="fas fa-times"></i>' : '<i class="fas fa-headset"></i>';
        if (isActive) {
            if (chatHistoryForAssistant.length <= 2) initializeChatHistory();
            if (aiChatInputEl) aiChatInputEl.focus();
        } else {
<<<<<<< HEAD
            // Non resettare gli stati qui, potrebbero essere attivi flussi che l'utente vuole riprendere
=======
            resetBookingState(); // Resetta lo stato quando la chat viene chiusa
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
        }
        document.body.style.overflow = isActive ? 'hidden' : '';
    }

    function addMessageToChatUI(sender, text, type = 'text') {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'ai-message');
<<<<<<< HEAD
        if (sender === 'system') messageDiv.style.display = 'none'; // I messaggi di sistema non sono visibili all'utente
=======
        if (sender === 'system') messageDiv.style.display = 'none'; // I messaggi di sistema non sono visibili
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad

        if (type === 'html') {
            let processedText = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/^- (.*)/gm, '<li>$1</li>')
                .replace(/^(\d+)\. (.*)/gm, '<li>$2</li>');

            if (processedText.includes('<li>')) {
                const isUnordered = /<ul>|<\/ul>|<li>/.test(processedText) && !/<ol>|<\/ol>/.test(processedText);
                const listTag = isUnordered ? 'ul' : 'ol';
                if (!processedText.trim().startsWith(`<${listTag}>`)) {
                    processedText = `<${listTag}>` + processedText + `</${listTag}>`;
                }
                processedText = processedText.replace(/<br>\s*(<li>)/gi, '$1');
                processedText = processedText.replace(/(<\/li>)\s*<br>/gi, '$1');
            }
            processedText = processedText.replace(/\n(?!(<\/?(ul|ol|li)>))/g, '<br>');
            processedText = processedText.replace(/(<\/(ul|ol|li)>)\n/g, '$1');

            messageDiv.innerHTML = processedText;
        }
        else messageDiv.appendChild(document.createTextNode(text));

        aiChatMessagesContainerEl.appendChild(messageDiv);
        aiChatMessagesContainerEl.scrollTop = aiChatMessagesContainerEl.scrollHeight;
        return messageDiv;
    }

    async function callPhpScript(scriptPath, params = {}, method = 'GET', contentType = 'application/x-www-form-urlencoded') {
        let url = scriptPath;
        // Normalizzazione URL
        if (scriptPath.startsWith('api/')) { /* Path già corretto */ }
        else if (scriptPath.startsWith('/api/')) { url = scriptPath.substring(1); }
        else if (scriptPath.startsWith('/')) { /* Path corretto */ }
        else { url = '/' + scriptPath; }

        const options = { method };

        // Aggiunta automatica email utente per certi script se loggato
        if (CURRENT_USER_EMAIL && (url.includes('/get_events.php') || url.includes('/get_event_details.php') || url.includes('api/api_get_user_bookings.php'))) {
            if (!params.user_email_for_script && !params.email) {
                if (url.includes('api/api_get_user_bookings.php')) {
                    params.email = CURRENT_USER_EMAIL;
                } else {
                    params.user_email_for_script = CURRENT_USER_EMAIL;
                }
            }
        }

        if (method === 'GET') {
            if (Object.keys(params).length > 0) url += '?' + new URLSearchParams(params).toString();
        } else if (method === 'POST') {
            if (contentType === 'application/json') {
                options.headers = { 'Content-Type': 'application/json' };
                options.body = JSON.stringify(params);
            } else {
                const formData = new FormData();
                console.log("AssistenteAI v10 (callPhpScript): Populating FormData from params:", JSON.parse(JSON.stringify(params))); // Log dei parametri sorgente
                for (const key in params) {
                    if (params.hasOwnProperty(key)) {
                        if (Array.isArray(params[key])) {
                            params[key].forEach((value, index) => {
                                formData.append(key + '[]', value);
                                console.log(`  FormData append (array): ${key}[] = "${value}"`);
                            });
                        } else {
                            formData.append(key, params[key]);
                            console.log(`  FormData append (scalar): ${key} = "${params[key]}"`);
                        }
                    }
                }
                options.body = formData;
                console.log("AssistenteAI v10 (callPhpScript): FormData entries being sent:");
                for (let [fdKey, fdValue] of formData.entries()) {
                    console.log(`    ${fdKey}: ${fdValue}`);
                }
            }
        }

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorText = `Errore HTTP ${response.status} (${response.statusText}) dallo script ${url}`;
                try {
                    const errorData = await response.json();
                    errorText = errorData.message || errorData.error || `Errore ${response.status} - ${errorData.detail || response.statusText}`;
                } catch (e) { /* Il corpo dell'errore non era JSON */ }
                throw new Error(errorText);
            }
            return await response.json();
        } catch (error) {
            console.error(`AssistenteAI v10: Errore chiamata a ${url}:`, error);
            throw error;
        }
    }

    async function getGroqCompletion(messages, systemPromptContent, temperature = 0.3, max_tokens = 1500) {
        const payload = {
            messages: [
                { role: "system", content: systemPromptContent },
                ...messages
            ],
            model: GROQ_MODEL_NAME_ASSISTANT,
            temperature: temperature,
            max_tokens: max_tokens,
        };
        if (systemPromptContent === INTENT_ANALYSIS_SYSTEM_PROMPT()) {
            payload.response_format = { type: "json_object" };
        }

        const response = await fetch("https://api.groq.com/openai/v1/chat/completions", {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${GROQ_API_KEY_FOR_ASSISTANT}`,
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: { message: `Errore API Groq: ${response.status}` } }));
            throw new Error(errorData.error?.message || `Errore API Groq: ${response.status} - ${response.statusText}`);
        }
        const data = await response.json();
        return data.choices[0]?.message?.content.trim();
    }

    function validateBookingStateForConfirmation(state) {
        if (!state.isActive) return { isValid: false, missingInfo: "Il flusso di prenotazione non è attivo." };
        if (!state.eventId || !state.eventTitle) return { isValid: false, missingInfo: "L'evento non è stato specificato correttamente (manca ID o Titolo)." };
        if (isNaN(parseInt(state.eventId, 10))) return { isValid: false, missingInfo: "ID Evento non è un numero valido."};

        const postiGiaPrenotati = state.postiGiaPrenotatiUtente || 0;
        const postiAncoraPrenotabiliPerUtente = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati;
        const maxConsentitoPerQuestaRichiesta = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabiliPerUtente);

        const numeroPostiInt = parseInt(state.numeroPosti, 10);
        if (isNaN(numeroPostiInt) || numeroPostiInt < 1 || numeroPostiInt > maxConsentitoPerQuestaRichiesta) {
            return { isValid: false, missingInfo: `Il numero di posti richiesti (${state.numeroPosti || 'N/D'}) non è valido. Puoi richiedere da 1 a ${maxConsentitoPerQuestaRichiesta} posti per questa prenotazione.` };
        }
        if (!state.partecipanti || !Array.isArray(state.partecipanti)) return { isValid: false, missingInfo: "L'elenco dei partecipanti non è valido."};
        if (state.partecipanti.length !== numeroPostiInt) return { isValid: false, missingInfo: `Sono richiesti NOME e COGNOME per ${numeroPostiInt} partecipante/i, ma ne sono stati forniti ${state.partecipanti.length}.` };

        for (let i = 0; i < state.partecipanti.length; i++) {
            const partecipante = state.partecipanti[i];
            if (typeof partecipante !== 'string' || partecipante.trim().split(' ').filter(Boolean).length < 2) {
                return { isValid: false, missingInfo: `Il nome per il partecipante ${i + 1} ('${partecipante || 'N/D'}') non sembra completo. Assicurati di inserire sia NOME che COGNOME.` };
            }
        }
        return { isValid: true, missingInfo: null };
    }

    async function fetchEventDetailsAndUserBookingStatus(eventId, userEmail) {
        if (!eventId || !userEmail) {
            console.warn("AssistenteAI v10: fetchEventDetailsAndUserBookingStatus: eventId o userEmail mancanti.");
            return null;
        }
        try {
<<<<<<< HEAD
            const eventDetailsResult = await callPhpScript("/get_event_details.php", {
=======
            const eventDetailsResult = await callPhpScript("get_event_details.php", {
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                id: eventId,
                user_email_for_script: userEmail
            });

            if (eventDetailsResult.success && eventDetailsResult.data && eventDetailsResult.data.details) {
                currentBookingState.eventTitle = eventDetailsResult.data.details.Titolo;
                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventDetailsResult.data.details.posti_gia_prenotati_utente, 10) || 0;
                currentBookingState.postiDisponibiliEvento = parseInt(eventDetailsResult.data.details.PostiDisponibili, 10);
<<<<<<< HEAD
                console.log("AssistenteAI v10: Dettagli evento e stato prenotazione utente recuperati:", currentBookingState);
                chatHistoryForAssistant.push({ role: "system", content: `Stato prenotazione utente per evento ${currentBookingState.eventTitle} (ID: ${currentBookingState.eventId}): posti già prenotati = ${currentBookingState.postiGiaPrenotatiUtente}. Posti disponibili evento: ${currentBookingState.postiDisponibiliEvento}.`});
                return true;
            } else {
                console.warn("AssistenteAI v10: Dettagli evento non trovati o formato inatteso (get_event_details.php) per eventId:", eventId, eventDetailsResult);
                const eventsResult = await callPhpScript("/get_events.php", {
=======
                console.log("Dettagli evento e stato prenotazione utente recuperati:", currentBookingState);
                return true;
            } else {
                console.warn("Dettagli evento non trovati o formato risposta inatteso da get_event_details.php per eventId:", eventId, eventDetailsResult);
                const eventsResult = await callPhpScript("get_events.php", {
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                    event_id_specific: eventId,
                    user_email_for_script: userEmail
                });
                if (eventsResult.success && eventsResult.data && Array.isArray(eventsResult.data) && eventsResult.data.length > 0) {
                    const eventInfo = eventsResult.data.find(e => e.idevento == eventId);
                    if (eventInfo) {
                        currentBookingState.eventTitle = eventInfo.titolo;
                        currentBookingState.postiGiaPrenotatiUtente = parseInt(eventInfo.posti_gia_prenotati_utente, 10) || 0;
                        currentBookingState.postiDisponibiliEvento = parseInt(eventInfo.posti_disponibili, 10);
                        console.log("AssistenteAI v10: Dettagli evento e stato prenotazione utente recuperati (fallback get_events):", currentBookingState);
                        chatHistoryForAssistant.push({ role: "system", content: `Stato prenotazione utente per evento ${currentBookingState.eventTitle} (ID: ${currentBookingState.eventId}): posti già prenotati = ${currentBookingState.postiGiaPrenotatiUtente}. Posti disponibili evento: ${currentBookingState.postiDisponibiliEvento}.`});
                        return true;
                    }
                }
                currentBookingState.eventTitle = `Evento ID ${eventId} (Dettagli non trovati)`;
                currentBookingState.postiGiaPrenotatiUtente = 0;
                currentBookingState.postiDisponibiliEvento = 0;
                return false;
            }
        } catch (e) {
<<<<<<< HEAD
            console.error("AssistenteAI v10: Errore in fetchEventDetailsAndUserBookingStatus:", e);
=======
            console.error("Errore in fetchEventDetailsAndUserBookingStatus:", e);
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
            currentBookingState.eventTitle = currentBookingState.eventTitle || `Evento ID ${eventId} (Errore recupero dettagli)`;
            currentBookingState.postiGiaPrenotatiUtente = 0;
            currentBookingState.postiDisponibiliEvento = 0;
            return false;
        }
    }


    async function handleSendMessageToAI() {
        checkUserLoginStatus();
        console.log("AssistenteAI v10: handleSendMessageToAI - CURRENT_USER_EMAIL:", CURRENT_USER_EMAIL, "IS_USER_LOGGED_IN:", IS_USER_LOGGED_IN);
        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            const keyReady = await fetchAndPrepareAssistantApiKey();
            if (!keyReady) { addMessageToChatUI('ai', "L'assistente AI non è correttamente configurato. Impossibile procedere."); return; }
        }

        const userInput = aiChatInputEl.value.trim();
        if (!userInput) return;

        addMessageToChatUI('user', userInput);
        chatHistoryForAssistant.push({ role: "user", content: userInput });
        aiChatInputEl.value = ''; aiChatInputEl.disabled = true; aiChatSendBtnEl.disabled = true; aiChatInputEl.style.height = 'auto';

        let thinkingMessageDiv = null;

        try {
<<<<<<< HEAD
            const intentHistory = chatHistoryForAssistant.slice(-10);
            let intentResponseJson = await getGroqCompletion(intentHistory, INTENT_ANALYSIS_SYSTEM_PROMPT(), 0.1, 900);
            let parsedIntent;
            try {
                parsedIntent = JSON.parse(intentResponseJson);
                console.log("AssistenteAI v10: Intent Analysis:", parsedIntent);
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis result: ${JSON.stringify(parsedIntent)}` });
            } catch (e) {
                console.error("AssistenteAI v10: Errore parsing JSON dell'intento:", e, "\nRisposta LLM:\n", intentResponseJson);
                parsedIntent = { intent: "GENERAL_QUERY", php_script: "none", params: {}, requires_login: false, missing_info_prompt: null, is_clarification_needed: true };
=======
            const intentHistory = chatHistoryForAssistant.slice(-6);
            let intentResponseJson = await getGroqCompletion(intentHistory, INTENT_ANALYSIS_SYSTEM_PROMPT(), 0.2, 800);
            let parsedIntent;
            try {
                parsedIntent = JSON.parse(intentResponseJson);
                console.log("Intent Analysis:", parsedIntent);
                if (parsedIntent.params) {
                    console.log("Parametri nomi estratti dall'LLM (partecipanti_nomi_cognomi):", parsedIntent.params.partecipanti_nomi_cognomi);
                }
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis result: ${JSON.stringify(parsedIntent)}` });
            } catch (e) {
                console.error("Errore parsing JSON dell'intento:", e, "\nRisposta LLM:\n", intentResponseJson);
                parsedIntent = { intent: "GENERAL_QUERY", php_script: "none", params: {}, requires_login: false, missing_info_prompt: "Non ho compreso bene la tua richiesta. Puoi riformularla in modo più chiaro?", is_clarification_needed: true };
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis fallback (parsing error): ${JSON.stringify(parsedIntent)}` });
            }

            if (parsedIntent.requires_login && !IS_USER_LOGGED_IN) {
<<<<<<< HEAD
                const loginMessage = "Per questa azione è necessario l'accesso. Puoi accedere o registrarti dal menu principale del sito. Vuoi che ti spieghi come fare o preferisci provare un'altra azione che non richiede l'accesso?";
=======
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                const loginMessage = "Per questa azione è necessario effettuare l'accesso. Puoi accedere o registrarti tramite il menu del sito.";
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                addMessageToChatUI('ai', loginMessage);
                chatHistoryForAssistant.push({ role: "assistant", content: loginMessage });
                finalizeUIAfterResponse();
                return;
            }

            // --- GESTIONE STATI E LOGICA JS ---
            if (parsedIntent.intent === "START_BOOKING_FLOW") {
                if (!currentBookingState.isActive || (parsedIntent.params?.event_id && parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId) || (parsedIntent.params?.event_name_hint && parsedIntent.params.event_name_hint !== currentBookingState.eventNameHint)) {
                    resetBookingState();
                }
                currentBookingState.isActive = true;
                currentBookingState.summaryPresented = false;
                if (parsedIntent.params?.event_id) currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10);
                if (parsedIntent.params?.event_name_hint) currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;
<<<<<<< HEAD
                if (parsedIntent.params?.numeroPosti) {
                    currentBookingState.numeroPosti = parseInt(parsedIntent.params.numeroPosti, 10);
                    currentBookingState.partecipanti = [];
                }
            } else if (parsedIntent.intent === "COLLECT_BOOKING_DETAILS" && currentBookingState.isActive) {
                currentBookingState.summaryPresented = false;
                if (parsedIntent.params?.event_id && (!currentBookingState.eventId || parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId)) {
                    currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10); currentBookingState.eventTitle = null; currentBookingState.postiGiaPrenotatiUtente = undefined;
                }
                if (parsedIntent.params?.event_name_hint && !currentBookingState.eventId) currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;

                if (parsedIntent.params?.numeroPosti) {
                    const nuovoNumeroPosti = parseInt(parsedIntent.params.numeroPosti, 10);
                    if (currentBookingState.numeroPosti !== nuovoNumeroPosti) {
                        currentBookingState.numeroPosti = nuovoNumeroPosti;
                        currentBookingState.partecipanti = [];
                    }
                }
                if (parsedIntent.params?.partecipanti_nomi_cognomi && Array.isArray(parsedIntent.params.partecipanti_nomi_cognomi) && parsedIntent.params.partecipanti_nomi_cognomi.length > 0) {
                    const newNames = parsedIntent.params.partecipanti_nomi_cognomi.map(name => name.trim()).filter(name => name && name.split(' ').filter(Boolean).length >= 1);
                    if (newNames.length > 0 && currentBookingState.numeroPosti) {
                        const combinedNames = [...new Set([...currentBookingState.partecipanti, ...newNames])];
                        currentBookingState.partecipanti = combinedNames.slice(0, currentBookingState.numeroPosti);
                        console.log("AssistenteAI v10: Nomi partecipanti aggiornati:", currentBookingState.partecipanti);
                    }
                }
            } else if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                if (!currentBookingState.summaryPresented) {
                    parsedIntent.intent = "COLLECT_BOOKING_DETAILS";
                    parsedIntent.php_script = "none";
                    chatHistoryForAssistant.push({ role: "system", content: "AI_ERROR: CONFIRM_BOOKING_DETAILS ricevuto ma currentBookingState.summaryPresented era false. L'AI deve prima presentare il riepilogo completo e poi chiedere conferma." });
                }
            } else if (parsedIntent.intent === "START_CANCEL_BOOKING") {
                resetCancellationState();
                currentCancellationState.isActive = true;
                currentCancellationState.summaryPresented = false;
                if (parsedIntent.params?.booking_id) {
                    currentCancellationState.bookingIdToCancel = parseInt(parsedIntent.params.booking_id, 10);
                }
            } else if (parsedIntent.intent === "COLLECT_CANCEL_BOOKING_ID" && currentCancellationState.isActive) {
                currentCancellationState.summaryPresented = false;
                if (parsedIntent.params?.booking_id) {
                    currentCancellationState.bookingIdToCancel = parseInt(parsedIntent.params.booking_id, 10);
                }
            } else if (parsedIntent.intent === "CONFIRM_CANCEL_BOOKING") {
                if (!currentCancellationState.summaryPresented) {
                    parsedIntent.intent = "COLLECT_CANCEL_BOOKING_ID";
                    parsedIntent.php_script = "none";
                    chatHistoryForAssistant.push({ role: "system", content: "AI_ERROR: CONFIRM_CANCEL_BOOKING ricevuto ma currentCancellationState.summaryPresented era false. L'AI deve prima chiedere conferma per l'ID specifico." });
                }
            }

            // --- PREPARAZIONE PER CHIAMATA PHP ---
            let phpScriptToCall = parsedIntent.php_script;
            let scriptParams = { ...parsedIntent.params };

            if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS" && currentBookingState.summaryPresented) {
                const validation = validateBookingStateForConfirmation(currentBookingState);
                let canProceedToPhp = validation.isValid;
                let problemDetails = validation.missingInfo || "";

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!IS_USER_LOGGED_IN || !CURRENT_USER_EMAIL || !emailRegex.test(CURRENT_USER_EMAIL)) {
                    canProceedToPhp = false;
                    problemDetails += (problemDetails ? " " : "") + `L'email dell'utente (${CURRENT_USER_EMAIL || "N/D"}) non sembra valida o l'utente non è loggato.`;
                    console.error("AssistenteAI v10 (CONFIRM_BOOKING): Validazione email/login fallita. Email:", CURRENT_USER_EMAIL, "Logged in:", IS_USER_LOGGED_IN);
                }

                if (canProceedToPhp) {
                    phpScriptToCall = "/prenota_evento.php";
                    scriptParams = {
                        eventId: parseInt(currentBookingState.eventId, 10),
                        numeroPosti: parseInt(currentBookingState.numeroPosti, 10),
                        contatto: CURRENT_USER_EMAIL, // Già trimmata in checkUserLoginStatus
                        partecipanti_nomi: [],
                        partecipanti_cognomi: []
                    };

                    for (const fullName of currentBookingState.partecipanti) {
                        const parts = fullName.trim().split(/\s+/);
                        const cognome = parts.length > 1 ? parts.pop().trim() : ""; // Ultima parola come cognome
                        const nome = parts.join(" ").trim(); // Il resto come nome

                        if (nome && cognome) {
                            scriptParams.partecipanti_nomi.push(nome);
                            scriptParams.partecipanti_cognomi.push(cognome);
                        } else {
                            problemDetails += (problemDetails ? " " : "") + ` Partecipante "${fullName}" non ha prodotto un nome e cognome validi.`;
                            canProceedToPhp = false;
                            break;
                        }
                    }

                    if (canProceedToPhp && (scriptParams.partecipanti_nomi.length !== scriptParams.numeroPosti || scriptParams.partecipanti_cognomi.length !== scriptParams.numeroPosti)) {
                        canProceedToPhp = false;
                        problemDetails += (problemDetails ? " " : "") + " Il numero finale di nomi/cognomi processati non corrisponde al numero di posti.";
                        console.error("AssistenteAI v10 (CONFIRM_BOOKING): Mismatch conteggio nomi/cognomi finali.", scriptParams);
                    }
                }

                if (canProceedToPhp) {
                    console.log("AssistenteAI v10 (CONFIRM_BOOKING): scriptParams pronti per FormData:", JSON.parse(JSON.stringify(scriptParams)));
                    chatHistoryForAssistant.push({ role: "system", content: `PHP_CALL_ATTEMPTING: ${phpScriptToCall}. AI should now inform user it is sending request and to wait. PARAMS_LOG: ${JSON.stringify(scriptParams)}` });
                } else {
                    chatHistoryForAssistant.push({ role: "system", content: `VALIDATION_FAILED_PRE_PHP: Validazione fallita (JS) prima di chiamare prenota_evento.php: ${problemDetails.trim()}. L'AI deve informare l'utente e chiedere correzioni.` });
                    phpScriptToCall = "none";
                    currentBookingState.summaryPresented = false;
                }
            }
            else if (parsedIntent.intent === "CONFIRM_CANCEL_BOOKING" && currentCancellationState.summaryPresented && currentCancellationState.bookingIdToCancel) {
                scriptParams = { idPrenotazione: currentCancellationState.bookingIdToCancel };
                phpScriptToCall = "api/api_cancel_booking.php";
                chatHistoryForAssistant.push({ role: "system", content: `PHP_CALL_ATTEMPTING: ${phpScriptToCall}. AI should now inform user it is sending request and to wait. PARAMS_LOG: ${JSON.stringify(scriptParams)}` });
            }


            // Esegui la chiamata PHP se definita
            if (phpScriptToCall && phpScriptToCall !== "none") {
                let method = (phpScriptToCall.includes('/prenota_evento.php') || phpScriptToCall.includes('api/api_cancel_booking.php')) ? 'POST' : 'GET';
                let reqContentType = (phpScriptToCall.includes('api/api_cancel_booking.php')) ? 'application/json' : 'application/x-www-form-urlencoded';

                if (phpScriptToCall.includes('/get_event_details.php') && scriptParams.event_id && !scriptParams.id) {
                    scriptParams.id = scriptParams.event_id; delete scriptParams.event_id;
                }
                if (phpScriptToCall.includes('api/api_get_user_bookings.php') && !scriptParams.email && CURRENT_USER_EMAIL) {
                    scriptParams.email = CURRENT_USER_EMAIL;
                }
                if (phpScriptToCall.includes('/get_events.php') && !scriptParams.user_email_for_script && CURRENT_USER_EMAIL) {
                    scriptParams.user_email_for_script = CURRENT_USER_EMAIL;
                }

                if ((parsedIntent.intent === "START_BOOKING_FLOW" || parsedIntent.intent === "COLLECT_BOOKING_DETAILS" || (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS" && phpScriptToCall === "/prenota_evento.php" )) &&
                    currentBookingState.eventId &&
                    (!currentBookingState.eventTitle || currentBookingState.postiGiaPrenotatiUtente === undefined) &&
                    IS_USER_LOGGED_IN) {
                    await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                }

=======

                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                let initialBookingPrompt = parsedIntent.missing_info_prompt || "Certo, iniziamo la prenotazione!";

                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined') {
                    const detailsFetched = await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    if (!detailsFetched && !currentBookingState.eventTitle) {
                        initialBookingPrompt = `Non sono riuscito a trovare i dettagli per l'evento ID ${currentBookingState.eventId}. Potresti verificare l'ID o fornire il nome dell'evento?`;
                        resetBookingState();
                        addMessageToChatUI('ai', initialBookingPrompt);
                        chatHistoryForAssistant.push({ role: "assistant", content: initialBookingPrompt });
                        finalizeUIAfterResponse(); return;
                    }
                }

                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' && currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                    const limitReachedMsg = `Ho verificato e risulta che hai già prenotato ${currentBookingState.postiGiaPrenotatiUtente} posti per l'evento "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", raggiungendo il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT} per utente per questo evento. Non è possibile aggiungere altri posti.`;
                    addMessageToChatUI('ai', limitReachedMsg);
                    chatHistoryForAssistant.push({ role: "assistant", content: limitReachedMsg });
                    resetBookingState(); finalizeUIAfterResponse(); return;
                }

                if (currentBookingState.eventId && currentBookingState.eventTitle) {
                    if (parsedIntent.params?.numeroPosti) {
                        const numPostiRichiesti = parseInt(parsedIntent.params.numeroPosti, 10);
                        const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                        const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);

                        if (numPostiRichiesti > 0 && numPostiRichiesti <= maxPerQuestaPrenotazione) {
                            currentBookingState.numeroPosti = numPostiRichiesti;
                            initialBookingPrompt = `Ok, per l'evento "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Hai richiesto ${currentBookingState.numeroPosti} posto/i. Ora avrei bisogno del NOME e COGNOME completo per ${currentBookingState.numeroPosti > 1 ? 'ciascuno dei' : 'il'} ${currentBookingState.numeroPosti} partecipante/i. Puoi fornirli tutti insieme separati da virgola o "e".`;
                        } else {
                            initialBookingPrompt = `Per l'evento "${currentBookingState.eventTitle}", puoi prenotare da 1 a ${maxPerQuestaPrenotazione} posti in questa richiesta. Quanti ne desideri?`;
                        }
                    } else {
                        const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                        const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);
                        initialBookingPrompt = `Ok, procediamo con la prenotazione per l'evento "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). ${ (currentBookingState.postiGiaPrenotatiUtente > 0) ? `Ne hai già prenotati ${currentBookingState.postiGiaPrenotatiUtente}. ` : '' }Per quante persone (da 1 a ${maxPerQuestaPrenotazione}) desideri prenotare?`;
                    }
                } else if (currentBookingState.eventNameHint && !currentBookingState.eventId) {
                    try {
                        const eventsResult = await callPhpScript("get_events.php", { search_term: currentBookingState.eventNameHint, period: "all_future", user_email_for_script: CURRENT_USER_EMAIL });
                        chatHistoryForAssistant.push({ role: "system", content: `Ricerca eventi per "${currentBookingState.eventNameHint}": ${JSON.stringify(eventsResult)}` });
                        if (eventsResult.success && eventsResult.data && eventsResult.data.length > 0) {
                            if (eventsResult.data.length === 1) {
                                const eventInfo = eventsResult.data[0];
                                currentBookingState.eventId = eventInfo.idevento;
                                currentBookingState.eventTitle = eventInfo.titolo;
                                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventInfo.posti_gia_prenotati_utente, 10) || 0;
                                currentBookingState.postiDisponibiliEvento = parseInt(eventInfo.posti_disponibili, 10);

                                if (typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined') {
                                    await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                                }

                                if (currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                                    initialBookingPrompt = `Ho trovato l'evento: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Tuttavia, hai già ${currentBookingState.postiGiaPrenotatiUtente} posti prenotati, raggiungendo il limite di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}.`;
                                    resetBookingState();
                                } else {
                                    const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                                    const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);
                                    if (parsedIntent.params?.numeroPosti && parseInt(parsedIntent.params.numeroPosti, 10) > 0 && parseInt(parsedIntent.params.numeroPosti, 10) <= maxPerQuestaPrenotazione) {
                                        currentBookingState.numeroPosti = parseInt(parsedIntent.params.numeroPosti, 10);
                                        initialBookingPrompt = `Ho trovato: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Richiesti ${currentBookingState.numeroPosti} posti. Ora NOME e COGNOME per tutti. Puoi fornirli insieme separati da virgola o "e".`;
                                    } else {
                                        initialBookingPrompt = `Ho trovato: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). ${ (currentBookingState.postiGiaPrenotatiUtente > 0) ? `Ne hai già ${currentBookingState.postiGiaPrenotatiUtente}. ` : '' }Per quante persone (1-${maxPerQuestaPrenotazione})?`;
                                    }
                                }
                            } else {
                                let eventListString = "Ho trovato più eventi che corrispondono. Quale desideri?\n";
                                eventsResult.data.slice(0, 5).forEach(evt => {
                                    eventListString += `- ${evt.titolo} (ID: ${evt.idevento})\n`;
                                });
                                initialBookingPrompt = eventListString;
                            }
                        } else { initialBookingPrompt = `Non ho trovato eventi per "${currentBookingState.eventNameHint}". Riprova con un nome o ID più preciso.`; resetBookingState(); }
                    } catch (phpError) { initialBookingPrompt = `Si è verificato un errore durante la ricerca dell'evento. Per favore, fornisci l'ID esatto se lo conosci.`; console.error(phpError); resetBookingState(); }
                } else if (!currentBookingState.eventId && !currentBookingState.eventNameHint) {
                    initialBookingPrompt = "A quale evento sei interessato/a? Per favore, fornisci il nome o l'ID dell'evento.";
                }

                addMessageToChatUI('ai', initialBookingPrompt);
                chatHistoryForAssistant.push({ role: "assistant", content: initialBookingPrompt });
                finalizeUIAfterResponse(); return;
            }

            if (parsedIntent.intent === "COLLECT_BOOKING_DETAILS" && currentBookingState.isActive) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();

                if (parsedIntent.params?.event_id && (!currentBookingState.eventId || parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId)) {
                    currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10);
                    currentBookingState.eventTitle = null;
                    currentBookingState.postiGiaPrenotatiUtente = undefined;
                }
                if (parsedIntent.params?.event_name_hint && !currentBookingState.eventTitle && !currentBookingState.eventId) {
                    currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;
                }

                if (parsedIntent.params?.numeroPosti) {
                    const numPostiRichiesti = parseInt(parsedIntent.params.numeroPosti, 10);
                    const postiGiaPrenotati = typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' ? currentBookingState.postiGiaPrenotatiUtente : 0;
                    const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati;
                    const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);

                    if (numPostiRichiesti > 0 && numPostiRichiesti <= maxPerQuestaPrenotazione) {
                        currentBookingState.numeroPosti = numPostiRichiesti;
                    } else {
                        addMessageToChatUI('ai', `Puoi prenotare da 1 a ${maxPerQuestaPrenotazione} posti. Quanti ne desideri?`);
                        chatHistoryForAssistant.push({ role: "assistant", content: `Richiesta numero posti non valida: ${numPostiRichiesti}. Limite: ${maxPerQuestaPrenotazione}` });
                        finalizeUIAfterResponse(); return;
                    }
                }

                // Blocco di elaborazione nomi partecipanti con logging aggiuntivo
                if (parsedIntent.params?.partecipanti_nomi_cognomi) {
                    console.log("AssistenteAI (v6 debug): Ricevuto partecipanti_nomi_cognomi:", JSON.stringify(parsedIntent.params.partecipanti_nomi_cognomi));
                    let newNamesInput = parsedIntent.params.partecipanti_nomi_cognomi;
                    let rawExtractedNames = [];

                    if (Array.isArray(newNamesInput)) {
                        newNamesInput.forEach(item => {
                            if (typeof item === 'string' && item.trim() !== "") rawExtractedNames.push(item.trim());
                        });
                    } else if (typeof newNamesInput === 'string' && newNamesInput.trim() !== "") {
                        rawExtractedNames.push(newNamesInput.trim());
                    }
                    console.log("AssistenteAI (v6 debug): rawExtractedNames:", JSON.stringify(rawExtractedNames));

                    let fullyProcessedNewNames = [];
                    rawExtractedNames.forEach(nameEntry => {
                        let cleanedEntry = nameEntry.replace(/^(ecco i due|ecco i nomi|sono|per il partecipante|i nomi sono|i partecipanti sono)\s*[:]?\s*/i, '').trim();
                        const individualNames = cleanedEntry.split(/\s*,\s*|\s+e\s+|\s+ed\s+/i);
                        console.log(`AssistenteAI (v6 debug): nameEntry='${nameEntry}', cleanedEntry='${cleanedEntry}', individualNames='${JSON.stringify(individualNames)}'`);
                        individualNames.forEach(name => {
                            const trimmedName = name.trim();
                            if (trimmedName && trimmedName.split(' ').filter(Boolean).length >= 1) {
                                fullyProcessedNewNames.push(trimmedName);
                            }
                        });
                    });
                    fullyProcessedNewNames = [...new Set(fullyProcessedNewNames)];
                    console.log("AssistenteAI (v6 debug): fullyProcessedNewNames dopo elaborazione:", JSON.stringify(fullyProcessedNewNames), "Lunghezza:", fullyProcessedNewNames.length);

                    if (fullyProcessedNewNames.length > 0) {
                        console.log("AssistenteAI (v6 debug): Entrato in 'if (fullyProcessedNewNames.length > 0)'. Nomi processati:", JSON.stringify(fullyProcessedNewNames));
                        if (currentBookingState.numeroPosti) {
                            currentBookingState.partecipanti = fullyProcessedNewNames.slice(0, currentBookingState.numeroPosti);
                        } else {
                            currentBookingState.partecipanti = [...new Set(currentBookingState.partecipanti.concat(fullyProcessedNewNames))];
                        }
                        console.log("AssistenteAI (v6 debug): currentBookingState.partecipanti PRIMA della deduplica finale e slice:", JSON.stringify(currentBookingState.partecipanti));
                        currentBookingState.partecipanti = [...new Set(currentBookingState.partecipanti)];
                        if (currentBookingState.numeroPosti && currentBookingState.partecipanti.length > currentBookingState.numeroPosti) {
                            currentBookingState.partecipanti = currentBookingState.partecipanti.slice(0, currentBookingState.numeroPosti);
                        }
                        console.log("AssistenteAI (v6 debug): currentBookingState.partecipanti AGGIORNATO a:", JSON.stringify(currentBookingState.partecipanti), "Lunghezza:", currentBookingState.partecipanti.length);
                    } else {
                        console.log("AssistenteAI (v6 debug): fullyProcessedNewNames era vuoto. Nessun nome aggiunto a currentBookingState.partecipanti.");
                    }
                } else {
                    console.log("AssistenteAI (v6 debug): parsedIntent.params.partecipanti_nomi_cognomi non presente o vuoto.");
                }


                if (currentBookingState.eventId && IS_USER_LOGGED_IN && (typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined' || !currentBookingState.eventTitle)) {
                    const detailsSuccess = await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    if (!detailsSuccess && !currentBookingState.eventTitle) {
                        addMessageToChatUI('ai', `Non sono riuscito a recuperare i dettagli per l'evento ID ${currentBookingState.eventId}. Potresti ricontrollare l'ID o il nome?`);
                        chatHistoryForAssistant.push({ role: "assistant", content: `Fallimento recupero dettagli evento ID ${currentBookingState.eventId}` });
                        resetBookingState();
                        finalizeUIAfterResponse(); return;
                    }
                }

                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' && currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                    const limitReachedMsg = `Ho verificato e hai già ${currentBookingState.postiGiaPrenotatiUtente} posti per "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}. Non è possibile aggiungere altri posti.`;
                    addMessageToChatUI('ai', limitReachedMsg); chatHistoryForAssistant.push({ role: "assistant", content: limitReachedMsg });
                    resetBookingState(); finalizeUIAfterResponse(); return;
                }
                if (currentBookingState.numeroPosti) {
                    const postiGiaPrenotati = typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' ? currentBookingState.postiGiaPrenotatiUtente : 0;
                    const postiAncoraPrenotabiliPerUtente = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati;
                    const maxConsentitoPerQuestaRichiesta = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabiliPerUtente);
                    if (currentBookingState.numeroPosti > maxConsentitoPerQuestaRichiesta) {
                        const errorMsg = `Per l'evento "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", puoi prenotare al massimo altri ${maxConsentitoPerQuestaRichiesta} posti (ne hai già ${postiGiaPrenotati}). Vuoi procedere con ${maxConsentitoPerQuestaRichiesta} o un numero inferiore?`;
                        addMessageToChatUI('ai', errorMsg);
                        chatHistoryForAssistant.push({ role: "assistant", content: errorMsg });
                        currentBookingState.numeroPosti = null;
                        finalizeUIAfterResponse(); return;
                    }
                }

                console.log("AssistenteAI (v6 debug): Stato PRIMA di validateBookingStateForConfirmation:", JSON.stringify(currentBookingState));
                let nextPrompt = "";
                const validation = validateBookingStateForConfirmation(currentBookingState);

                if (!currentBookingState.eventId || !currentBookingState.eventTitle) {
                    nextPrompt = "Sembra ci sia stato un problema con la selezione dell'evento. A quale evento eri interessato/a? Per favore, fornisci nome o ID.";
                } else if (!validation.isValid) {
                    nextPrompt = validation.missingInfo;
                } else {
                    let summary = `Perfetto! Riepilogo la prenotazione:\n`;
                    summary += `- Evento: ${currentBookingState.eventTitle} (ID: ${currentBookingState.eventId})\n`;
                    summary += `- Numero Partecipanti: ${currentBookingState.numeroPosti}\n`;
                    summary += `- Partecipanti:\n`;
                    currentBookingState.partecipanti.forEach((p, idx) => { summary += `  ${idx + 1}. ${p}\n`; });
                    summary += `\nÈ tutto corretto? Posso procedere con la prenotazione?`;
                    nextPrompt = summary;
                }
                addMessageToChatUI('ai', nextPrompt);
                chatHistoryForAssistant.push({ role: "assistant", content: nextPrompt });
                finalizeUIAfterResponse(); return;
            }

            if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                const validation = validateBookingStateForConfirmation(currentBookingState);
                if (!validation.isValid) {
                    if (thinkingMessageDiv) thinkingMessageDiv.remove();
                    let errorMsg = "Attenzione, non posso procedere con la prenotazione. " + validation.missingInfo;
                    addMessageToChatUI('ai', errorMsg);
                    chatHistoryForAssistant.push({ role: "system", content: `Validation failed before PHP call: ${validation.missingInfo}. Asking user to correct.` });
                    chatHistoryForAssistant.push({ role: "assistant", content: errorMsg });
                    finalizeUIAfterResponse(); return;
                }
                parsedIntent.php_script = "prenota_evento.php";
                parsedIntent.params = {
                    eventId: currentBookingState.eventId,
                    numeroPosti: currentBookingState.numeroPosti,
                    contatto: CURRENT_USER_EMAIL,
                    partecipanti_nomi: currentBookingState.partecipanti.map(p => p.substring(0, p.lastIndexOf(' ') > 0 ? p.lastIndexOf(' ') : p.length).trim()),
                    partecipanti_cognomi: currentBookingState.partecipanti.map(p => {
                        const lastSpace = p.lastIndexOf(' ');
                        return (lastSpace === -1 || lastSpace === p.length - 1) ? "_" : p.substring(lastSpace + 1).trim();
                    }),
                };
            }

            if (parsedIntent.php_script && parsedIntent.php_script !== "none") {
                let scriptParams = { ...parsedIntent.params };
                let scriptPath = parsedIntent.php_script;
                let method = (scriptPath === 'prenota_evento.php') ? 'POST' : 'GET';

                if (scriptPath === 'prenota_evento.php' && !scriptParams.contatto && CURRENT_USER_EMAIL) {
                    scriptParams.contatto = CURRENT_USER_EMAIL;
                }
                if (scriptPath === 'get_event_details.php' && scriptParams.event_id && !scriptParams.id) {
                    scriptParams.id = scriptParams.event_id; delete scriptParams.event_id;
                }
                if (scriptPath === 'get_events.php' && CURRENT_USER_EMAIL && !scriptParams.user_email_for_script) {
                    scriptParams.user_email_for_script = CURRENT_USER_EMAIL;
                }

>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                try {
                    const phpResult = await callPhpScript(phpScriptToCall, scriptParams, method, reqContentType);
                    chatHistoryForAssistant.push({ role: "system", content: `PHP_RESULT from ${phpScriptToCall}: ${JSON.stringify(phpResult)}` });
                    console.log("AssistenteAI v10: PHP Result:", phpResult);

<<<<<<< HEAD
                    if (phpResult.success) {
                        if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                            console.log("AssistenteAI v10: Prenotazione PHP success. AI informerà.");
                        }
                        if (parsedIntent.intent === "CONFIRM_CANCEL_BOOKING") {
                            resetCancellationState();
                            console.log("AssistenteAI v10: Cancellazione PHP success. AI informerà.");
                        }
                    } else {
                        console.warn(`AssistenteAI v10: Chiamata a ${phpScriptToCall} fallita (PHP success=false):`, phpResult.message);
                        if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                            currentBookingState.summaryPresented = false;
                        }
                    }
                } catch (phpError) {
                    console.error("AssistenteAI v10: Errore grave durante chiamata PHP:", phpError);
                    const systemErrorMessage = `PHP_CALL_ERROR for ${phpScriptToCall}: ${phpError.message}`;
                    chatHistoryForAssistant.push({ role: "system", content: systemErrorMessage });

                    if (parsedIntent.intent.includes("BOOKING")) {
                        currentBookingState.summaryPresented = false;
                    }
                    if (parsedIntent.intent.includes("CANCEL")) {
                        currentCancellationState.summaryPresented = false;
=======
                    if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                        if (thinkingMessageDiv) thinkingMessageDiv.remove();
                        const finalMessage = phpResult.message || (phpResult.success ? "Prenotazione confermata con successo!" : "Si è verificato un errore durante la conferma della prenotazione. Riprova o contatta l'assistenza.");
                        addMessageToChatUI('ai', finalMessage);
                        chatHistoryForAssistant.push({ role: "assistant", content: finalMessage });
                        if (phpResult.success) resetBookingState();
                        finalizeUIAfterResponse(); return;
                    }
                } catch (phpError) {
                    console.error("Errore durante la chiamata allo script PHP:", phpError);
                    if (thinkingMessageDiv) thinkingMessageDiv.remove();
                    addMessageToChatUI('ai', `Spiacente, si è verificato un errore tecnico durante l'elaborazione della tua richiesta: ${phpError.message}. Per favore, riprova più tardi.`);
                    chatHistoryForAssistant.push({ role: "assistant", content: `Internal PHP Error: ${phpError.message}` });
                    if (parsedIntent.intent.includes("BOOKING")) resetBookingState();
                    finalizeUIAfterResponse(); return;
                }
            } else if (parsedIntent.is_clarification_needed && parsedIntent.missing_info_prompt && !currentBookingState.isActive) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                addMessageToChatUI('ai', parsedIntent.missing_info_prompt);
                chatHistoryForAssistant.push({ role: "assistant", content: parsedIntent.missing_info_prompt });
                finalizeUIAfterResponse(); return;
            }

            if (thinkingMessageDiv) thinkingMessageDiv.remove();

            const requiresAiResponseGeneration =
                !["START_BOOKING_FLOW", "COLLECT_BOOKING_DETAILS", "CONFIRM_BOOKING_DETAILS"].includes(parsedIntent.intent) ||
                (parsedIntent.intent === "GENERAL_QUERY" || parsedIntent.intent === "UNKNOWN") ||
                (parsedIntent.php_script && parsedIntent.php_script !== "none" && chatHistoryForAssistant[chatHistoryForAssistant.length-1]?.role === "system");

            if (requiresAiResponseGeneration && chatHistoryForAssistant[chatHistoryForAssistant.length -1]?.role !== 'assistant') {
                const finalResponseHistory = chatHistoryForAssistant.slice(-10);
                const aiFinalResponse = await getGroqCompletion(finalResponseHistory, MAIN_ASSISTANT_SYSTEM_PROMPT(), 0.5, 1500);

                if (aiFinalResponse) {
                    addMessageToChatUI('ai', aiFinalResponse);
                    chatHistoryForAssistant.push({ role: "assistant", content: aiFinalResponse });
                } else {
                    if (parsedIntent.intent === "GENERAL_QUERY" || parsedIntent.intent === "UNKNOWN") {
                        const fallbackMsg = "Non sono sicuro di come rispondere. Puoi provare a chiedere in un altro modo?";
                        addMessageToChatUI('ai', fallbackMsg);
                        chatHistoryForAssistant.push({ role: "assistant", content: "LLM response for general query was empty. Used fallback." });
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
                    }
                }
            }

<<<<<<< HEAD
            // --- GENERAZIONE RISPOSTA FINALE AI ---
            if (thinkingMessageDiv && thinkingMessageDiv.parentNode) {
                const lastSystemMessage = chatHistoryForAssistant.findLast(msg => msg.role === 'system');
                if (!lastSystemMessage || !lastSystemMessage.content.startsWith('PHP_CALL_ATTEMPTING')) {
                    // thinkingMessageDiv.remove();
                }
            }


            const finalResponseHistory = chatHistoryForAssistant.slice(-12);
            const aiFinalResponse = await getGroqCompletion(finalResponseHistory, MAIN_ASSISTANT_SYSTEM_PROMPT(), 0.35, 2000);

            if (aiFinalResponse) {
                if ( (parsedIntent.intent === "COLLECT_BOOKING_DETAILS" && validateBookingStateForConfirmation(currentBookingState).isValid) ||
                    ( (parsedIntent.intent === "START_CANCEL_BOOKING" || parsedIntent.intent === "COLLECT_CANCEL_BOOKING_ID") && currentCancellationState.bookingIdToCancel)
                ) {
                    if (aiFinalResponse.toLowerCase().includes("confermi?") || aiFinalResponse.toLowerCase().includes("posso procedere?") || aiFinalResponse.toLowerCase().includes("sei sicuro")) {
                        if (currentBookingState.isActive && (parsedIntent.intent.includes("BOOKING") || parsedIntent.intent.includes("COLLECT_BOOKING"))) {
                            currentBookingState.summaryPresented = true;
                            console.log("AssistenteAI v10: JS ha impostato currentBookingState.summaryPresented = true dopo risposta AI che chiede conferma.")
                        }
                        if (currentCancellationState.isActive && (parsedIntent.intent.includes("CANCEL") || parsedIntent.intent.includes("COLLECT_CANCEL"))) {
                            currentCancellationState.summaryPresented = true;
                            console.log("AssistenteAI v10: JS ha impostato currentCancellationState.summaryPresented = true dopo risposta AI che chiede conferma.")
                        }
                    }
                }
                if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS" &&
                    chatHistoryForAssistant.some(msg =>
                        msg.role === "system" &&
                        msg.content.includes('PHP_RESULT from /prenota_evento.php') &&
                        (() => { try { const result = JSON.parse(msg.content.substring(msg.content.indexOf('{'))); return result.success === true && result.idPrenotazione; } catch { return false; } })()
                    ) &&
                    (aiFinalResponse.toLowerCase().includes("prenotazione effettuata con successo") || aiFinalResponse.toLowerCase().includes("id della tua prenotazione è"))
                ) {
                    resetBookingState();
                    console.log("AssistenteAI v10: Stato prenotazione resettato dopo conferma di successo comunicata dall'AI, basata su PHP_RESULT.");
                }


                addMessageToChatUI('ai', aiFinalResponse, aiFinalResponse.includes('\n- ') || aiFinalResponse.includes('\n* ') || aiFinalResponse.includes('</') ? 'html' : 'text');
                chatHistoryForAssistant.push({ role: "assistant", content: aiFinalResponse });
            } else {
                addMessageToChatUI('ai', "Non sono sicuro di come rispondere in questo momento. Potresti provare a riformulare la tua richiesta?");
                chatHistoryForAssistant.push({ role: "assistant", content: "LLM_NO_RESPONSE" });
            }

        } catch (error) {
            console.error("AssistenteAI v10: Errore grave in handleSendMessageToAI:", error);
            if (thinkingMessageDiv && thinkingMessageDiv.parentNode) thinkingMessageDiv.remove();
            addMessageToChatUI('ai', `Spiacente, si è verificato un errore generale imprevisto: ${error.message}. Per favore, riprova tra poco.`);
=======
        } catch (error) {
            console.error("Errore in handleSendMessageToAI:", error);
            if (thinkingMessageDiv) thinkingMessageDiv.remove();
            addMessageToChatUI('ai', `Spiacente, si è verificato un errore imprevisto: ${error.message}. Per favore, riprova.`);
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
            chatHistoryForAssistant.push({ role: "assistant", content: `General Error in handleSendMessageToAI: ${error.message}` });
            resetBookingState();
            resetCancellationState();
        } finally {
            finalizeUIAfterResponse();
        }
    }

    function finalizeUIAfterResponse() {
        aiChatInputEl.disabled = false;
        aiChatSendBtnEl.disabled = false;
        if (aiChatPopupEl.classList.contains('active')) {
            aiChatInputEl.focus();
        }
        if (chatHistoryForAssistant.length > 20) {
            chatHistoryForAssistant = [
                chatHistoryForAssistant[0],
                ...chatHistoryForAssistant.slice(chatHistoryForAssistant.length - 19)
            ];
        }
    }

<<<<<<< HEAD
    console.log("AssistenteAI: Script in esecuzione (v10 - Debug Prenotazione V2).");
=======
    console.log("AssistenteAI: Script in esecuzione (v6 - Debug Nomi).");
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
    checkUserLoginStatus();
    initializeChatHistory();

    fetchAndPrepareAssistantApiKey().then(keyReady => {
        if (keyReady) {
            if (aiAssistantFabEl) aiAssistantFabEl.addEventListener('click', toggleAiChat);
            if (aiChatCloseBtnEl) aiChatCloseBtnEl.addEventListener('click', toggleAiChat);
            if (aiChatSendBtnEl) aiChatSendBtnEl.addEventListener('click', handleSendMessageToAI);
            if (aiChatInputEl) {
                aiChatInputEl.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        handleSendMessageToAI();
                    }
                });
                aiChatInputEl.addEventListener('input', function () {
                    this.style.height = 'auto';
                    let newHeight = this.scrollHeight;
                    if (newHeight > 90) newHeight = 90;
                    this.style.height = newHeight + 'px';
                });
            }
<<<<<<< HEAD
            console.log("AssistenteAI v10: Event listeners attaccati.");
        } else {
            console.error("AssistenteAI v10: Inizializzazione fallita, API key non caricata.");
        }
    }).catch(error => {
        console.error("AssistenteAI v10: Errore critico durante fetchAndPrepareAssistantApiKey:", error);
=======
            console.log("AssistenteAI: Event listeners attaccati correttamente.");
        } else {
            console.error("AssistenteAI: Inizializzazione fallita, API key non caricata o non valida.");
            if(aiAssistantFabEl) {
                aiAssistantFabEl.title = "Assistente AI non disponibile";
                aiAssistantFabEl.style.cursor = "not-allowed";
            }
        }
    }).catch(error => {
        console.error("AssistenteAI: Errore critico durante la preparazione della API key:", error);
        if(aiAssistantFabEl) {
            aiAssistantFabEl.title = "Assistente AI non disponibile (errore)";
            aiAssistantFabEl.style.cursor = "not-allowed";
        }
>>>>>>> 859ae99fd6c55362ac78ef0431ca2eb8eecea1ad
    });
});
