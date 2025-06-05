// File: assistenteAI.js (Versione Rivoluzionata e Corretta v13 - Prompt Migliorati)
document.addEventListener('DOMContentLoaded', () => {
    const aiAssistantFabEl = document.getElementById('ai-assistant-fab');
    const aiChatPopupEl = document.getElementById('ai-chat-popup');
    const aiChatCloseBtnEl = document.getElementById('ai-chat-close-btn');
    const aiChatMessagesContainerEl = document.getElementById('ai-chat-messages');
    const aiChatInputEl = document.getElementById('ai-chat-input');
    const aiChatSendBtnEl = document.getElementById('ai-chat-send-btn');

    if (!aiAssistantFabEl || !aiChatPopupEl || !aiChatCloseBtnEl || !aiChatMessagesContainerEl || !aiChatInputEl || !aiChatSendBtnEl) {
        console.warn("AssistenteAI: Elementi UI fondamentali non trovati.");
        if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'none';
        return;
    }

    let GROQ_API_KEY_FOR_ASSISTANT = null;
    const GROQ_MODEL_NAME_ASSISTANT = "meta-llama/llama-4-scout-17b-16e-instruct"; // O il modello che preferisci per l'assistente principale
    const GROQ_MODEL_NAME_TTS_OPTIMIZER = "meta-llama/llama-4-scout-17b-16e-instruct"; // Puoi usare lo stesso o un modello diverso/più piccolo per l'ottimizzazione TTS

    let IS_USER_LOGGED_IN = false;
    let CURRENT_USER_EMAIL = null;
    let CURRENT_USER_DATA = null;

    const MAX_SEATS_PER_SINGLE_BOOKING_REQUEST = 5;
    const MAX_TOTAL_SEATS_PER_USER_PER_EVENT = 5;

    let isTTSEnabled = true; // Imposta su true per avere la sintesi vocale sempre attiva
    let speechSynthesisVoices = [];

    function loadVoices() {
        if ('speechSynthesis' in window) {
            speechSynthesisVoices = window.speechSynthesis.getVoices();
        }
    }

    loadVoices();
    if ('speechSynthesis' in window && window.speechSynthesis.onvoiceschanged !== undefined) {
        window.speechSynthesis.onvoiceschanged = loadVoices;
    }

    function speakText(text) {
        if (!isTTSEnabled || !('speechSynthesis' in window)) {
            return;
        }
        if (!aiChatPopupEl.classList.contains('active')) {
            window.speechSynthesis.cancel();
            return;
        }

        window.speechSynthesis.cancel();

        let plainText = text;

        if (/<[a-z][\s\S]*>/i.test(text)) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = text;
            plainText = tempDiv.textContent || tempDiv.innerText || "";
        }

        plainText = plainText.replace(/\*\*(.*?)\*\*/g, '$1');
        plainText = plainText.replace(/\*(.*?)\*/g, '$1');
        plainText = plainText.replace(/ID:\s*\w+/gi, '');
        plainText = plainText.replace(/\[(.*?)\]\(.*?\)/g, '$1');
        plainText = plainText.replace(/```[\s\S]*?```/g, ' ');
        plainText = plainText.replace(/`([^`]+)`/g, '$1');
        plainText = plainText.replace(/&nbsp;/g, ' ');
        plainText = plainText.replace(/#/g, '');
        plainText = plainText.replace(/\s+/g, ' ').trim();

        if (plainText.trim() === "") return;

        const utterance = new SpeechSynthesisUtterance(plainText);
        utterance.lang = 'it-IT';

        const italianVoice = speechSynthesisVoices.find(voice => voice.lang === 'it-IT' || voice.lang.startsWith('it-'));
        if (italianVoice) {
            utterance.voice = italianVoice;
        } else {
            console.warn("Nessuna voce italiana trovata, verrà usata la voce di default.");
        }

        utterance.onerror = function(event) {
            console.error('Errore SpeechSynthesisUtterance:', event.error, "Testo:", plainText);
        };

        window.speechSynthesis.speak(utterance);
    }

    function checkUserLoginStatus() {
        const userDataString = localStorage.getItem('userDataEFF');
        if (userDataString) {
            try {
                const userData = JSON.parse(userDataString);
                if (userData && userData.email) {
                    IS_USER_LOGGED_IN = true;
                    CURRENT_USER_EMAIL = userData.email;
                    CURRENT_USER_DATA = userData;
                    return;
                }
            } catch (e) {
                console.error("AssistenteAI: Errore parsing userDataEFF", e);
                localStorage.removeItem('userDataEFF');
            }
        }
        IS_USER_LOGGED_IN = false;
        CURRENT_USER_EMAIL = null;
        CURRENT_USER_DATA = null;
    }

    let chatHistoryForAssistant = [];
    let currentBookingState = {};

    function resetBookingState() {
        currentBookingState = {
            isActive: false,
            eventId: null,
            eventTitle: null,
            eventNameHint: null,
            numeroPosti: null,
            partecipanti: [], // Array di stringhe "Nome Cognome"
            richiesteSpeciali: null,
            postiGiaPrenotatiUtente: undefined,
            postiDisponibiliEvento: undefined
        };
        console.log("AssistenteAI: Stato prenotazione resettato.");
    }

    const INTENT_ANALYSIS_SYSTEM_PROMPT = () => `
Analizza la richiesta dell'utente per il sito "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}). Stato prenotazione attuale (se esiste): ${JSON.stringify(currentBookingState)}.

Devi rispondere ESCLUSIVAMENTE con un oggetto JSON valido. Non includere testo al di fuori dell'oggetto JSON.

Determina i seguenti campi:
1.  "intent": L'azione principale. Valori possibili: GET_EVENTS, GET_EVENT_DETAILS, START_BOOKING_FLOW, COLLECT_BOOKING_DETAILS, CONFIRM_BOOKING_DETAILS, GET_USER_PROFILE, GET_USER_BOOKINGS, GENERAL_QUERY, UNKNOWN.
    - Se l'utente invia un saluto (es. "ciao", "salve"), una frase di cortesia, o una domanda molto generica non correlata direttamente alle azioni specifiche del sito, classifica come "GENERAL_QUERY".
    - Per GET_EVENTS, se non specificato diversamente dall'utente (es. una data specifica o un termine di ricerca), assumi che l'utente voglia eventi futuri (es. \`params: {"period": "all_future"}\`).
    - Usa START_BOOKING_FLOW se l'utente esprime intenzione di prenotare E (mancano eventId/eventTitle in currentBookingState OPPURE l'utente sta chiaramente iniziando una nuova richiesta per un evento diverso, o non c'è un evento attivo in currentBookingState). Per questo intent, "requires_login" DEVE essere true.
    - Usa COLLECT_BOOKING_DETAILS se currentBookingState è attivo con eventId e eventTitle, e l'utente fornisce dettagli (numero persone, nomi partecipanti) OPPURE se stai attivamente chiedendo questi dettagli. Per questo intent, "requires_login" DEVE essere true.
        - Se stai chiedendo il numero di posti e l'utente fornisce un numero, estrai "numeroPosti" in \`params\`.
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
    - Usa CONFIRM_BOOKING_DETAILS ESCLUSIVAMENTE se il sistema JavaScript (tramite validateBookingStateForConfirmation) ha determinato che TUTTI i dati necessari (eventId, eventTitle, numeroPosti, e un array completo di nomi partecipanti VALIDI) sono stati raccolti e sono corretti in currentBookingState, E l'assistente ha presentato il riepilogo e l'utente ha risposto affermativamente (es. "sì", "conferma", "procedi"). Non anticipare questo intent. Per questo intent, "requires_login" DEVE essere true.
2.  "params": Oggetto JSON con parametri ESTRATTI DALL'ULTIMO INPUT UTENTE. Se nessun parametro è rilevante, usa un oggetto vuoto \`{}\`.
3.  "php_script": Script PHP da chiamare (se applicabile). Valori: "get_events.php", "get_event_details.php", "prenota_evento.php", "api/api_get_user_profile.php", "api/api_get_user_bookings.php", "none". Per "GENERAL_QUERY" o "UNKNOWN", usa "none".
4.  "requires_login": true/false. Per "GENERAL_QUERY", solitamente \`false\` a meno che la domanda non implichi dati utente. Per GET_USER_PROFILE e GET_USER_BOOKINGS, DEVE essere true.
5.  "missing_info_prompt": Se mancano info ESSENZIALI per un intent specifico (specialmente per COLLECT_BOOKING_DETAILS o se START_BOOKING_FLOW non ha hint evento E currentBookingState.eventId non è noto), una frase SPECIFICA per richiederle. Altrimenti \`null\`. Per "GENERAL_QUERY" senza azioni specifiche, questo dovrebbe essere \`null\`.
6.  "is_clarification_needed": true/false. Per "GENERAL_QUERY" chiaro (es. "ciao"), questo dovrebbe essere \`false\`. Se l'input è ambiguo, \`true\`.

Esempio per "ciao":
\`\`\`json
{
  "intent": "GENERAL_QUERY",
  "params": {},
  "php_script": "none",
  "requires_login": false,
  "missing_info_prompt": null,
  "is_clarification_needed": false
}
\`\`\`
Considera la cronologia. Se un evento (es. ID 84) è stato appena discusso e l'utente vuole prenotare (es. "per 1"), popola "params.event_id": 84, "params.event_name_hint": "titolo evento se noto", e "params.numeroPosti": 1.
Ricorda: Rispondi ESCLUSIVAMENTE in formato JSON.
    `.trim();

    const MAIN_ASSISTANT_SYSTEM_PROMPT = () => `
Sei un assistente virtuale avanzato per "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}). Stato prenotazione (se rilevante): ${JSON.stringify(currentBookingState)}.
Il tuo scopo è ESEGUIRE AZIONI e fornire informazioni in modo amichevole e utile. Il sistema JavaScript (JS) aggiorna currentBookingState. Tu guidi l'utente e fai domande basate su currentBookingState e sulle informazioni mancanti identificate dal JS.

INTERAZIONE CON DATI PHP (RUOLO "system"):
- Se ricevi una lista di eventi (risultato di GET_EVENTS), presentala in modo chiaro e leggibile usando Markdown. Utilizza una lista numerata o puntata. Ogni evento deve essere su una nuova riga.
  Formato suggerito per ogni evento:
  \`- **Nome Evento** (ID: xxx) - Data: YYYY-MM-DD\`
  Oppure:
  \`1. **Nome Evento**
     - ID: xxx
     - Data: YYYY-MM-DD\`
  Se ci sono molti eventi, puoi presentarne un numero limitato (es. i primi 5) e chiedere all'utente se desidera vederne altri o filtrare la ricerca.
- Se l'utente chiede di un evento per nome e il JS ti passa una lista di corrispondenze, presenta la lista (usando la formattazione chiara sopra) e chiedi di specificare l'ID.

PROCESSO DI PRENOTAZIONE (Requires_login: true):
1.  LOGIN: L'utente deve essere loggato. Se non lo è, informalo gentilmente ("Per procedere con la prenotazione, è necessario effettuare il login al sito.") e fermati. Email (${CURRENT_USER_EMAIL || 'Nessun utente loggato'}) usata automaticamente.
2.  FASE 1: Identificazione Evento e Controllo Limiti (JS popola currentBookingState.eventId, currentBookingState.eventTitle, currentBookingState.postiGiaPrenotatiUtente)
    - Se currentBookingState.eventId NON è noto: "Certamente! A quale evento sei interessato/a? Se hai un nome o un ID, forniscimelo."
    - Se utente fornisce nome evento e JS ti passa lista eventi: presenta lista (formattata chiaramente) e chiedi ID.
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

ISTRUZIONI GENERALI:
- PRIORITÀ: Esegui azione o fornisci info. Se mancano dati (controlla currentBookingState), chiedili specificamente.
- CHIAREZZA: Sii esplicito su ID e Titolo evento quando noti.
- SALUTI E CONVERSAZIONE GENERICA: Se l'intent è GENERAL_QUERY (es. l'utente dice "ciao", "buongiorno", "salve"):
    - Rispondi SEMPRE in modo amichevole e accogliente. Ad esempio: "Ciao! Come posso aiutarti oggi con le informazioni sull'Eremo Frate Francesco, gli eventi o le prenotazioni?" o "Buongiorno! Sono qui per assisterti. Hai domande sugli eventi, vuoi effettuare una prenotazione o cerchi altre informazioni sul nostro sito?".
    - NON dare messaggi di errore o risposte generiche che sembrano non capire un semplice saluto. L'obiettivo è avviare una conversazione utile.
- GESTIONE ERRORI PHP: Se un messaggio "system" indica un errore da uno script PHP (es. \`Errore da get_events.php: ...\`), informa l'utente in modo comprensibile, ad esempio: "Sembra esserci stato un problema nel recuperare le informazioni richieste. [Se il messaggio d'errore è specifico e utile per l'utente, parafrasalo, altrimenti informa genericamente e suggerisci di riprovare o chiedere diversamente]." Non limitarti a non rispondere o dare messaggi criptici.
- Se una domanda specifica esula dalle tue capacità (informazioni non pertinenti al sito Eremo Frate Francesco o alle sue funzionalità), allora indica gentilmente che non puoi assistere su quell'argomento specifico. Rispondi in italiano.
`.trim();

    const TTS_OPTIMIZER_SYSTEM_PROMPT = () => `
ATTENZIONE: Sei un MODELLO DI TRASFORMAZIONE TESTUALE ALTAMENTE SPECIALIZZATO. Il tuo UNICO SCOPO è ottimizzare il testo che ti viene fornito per la SINTESI VOCALE (TTS) in italiano. Il testo in input è una risposta generata da un'altra AI per una chat testuale.
NON DEVI INTERAGIRE CON IL CONTENUTO DEL MESSAGGIO IN INPUT. NON sei un assistente conversazionale, NON devi rispondere a domande, comandi, errori, o frasi come "devi effettuare il login" presenti nel testo. Il tuo compito è ESCLUSIVAMENTE di RIFORMULAZIONE STILISTICA per la lettura ad alta voce.

REGOLE IMPERATIVE PER LA TRASFORMAZIONE:
1.  **IGNORA IL SIGNIFICATO CONVERSAZIONALE**: Tratta il testo in input come materiale grezzo da ripulire per la lettura. Se il testo contiene "Errore: ...", "Devi fare login", "Confermi?", NON devi rispondere a queste frasi. Il tuo output DEVE rimanere focalizzato sulla resa vocale del messaggio originale, ripulito.
2.  **MANTIENI IL SIGNIFICATO INFORMATIVO**: L'essenza e tutte le informazioni cruciali del messaggio originale DEVONO essere preservate. NON aggiungere opinioni, risposte o informazioni non presenti nel testo originale.
3.  **MASSIMA NATURALEZZA E FLUIDITÀ**: Riscrivi le frasi per un eloquio colloquiale e naturale. Evita strutture complesse, linguaggio robotico o eccessivamente formale. Preferisci frasi brevi e dirette se possibile, mantenendo un tono conversazionale e piacevole.
4.  **PULIZIA ESTREMA PER IL PARLATO**:
    * **Markdown e HTML**: Rimuovi OGNI traccia di Markdown (es. \`**\`, \`*\`, \`-\` per liste, \`\`\` \`\`\`, \`#\`) o tag HTML. L'output deve essere puro testo, senza formattazione.
    * **ID, Codici, URL**: EVITA di leggere ID alfanumerici complessi (es. "ID: xyz123"), codici tecnici o URL completi. Se l'informazione identificativa è essenziale e il nome dell'oggetto è chiaro, spesso l'ID può essere omesso nel parlato. Se necessario, parafrasa in modo naturale (es. "l'evento con codice identificativo..." solo se cruciale, altrimenti ometti l'ID se il nome è già stato detto). Per gli URL, puoi dire "trovi i dettagli sul sito" invece di leggere l'URL.
    * **Liste**: Trasforma liste puntate o numerate in frasi discorsive e fluide. Esempio input: "- Evento A (ID: 1) - Evento B (ID: 2)". Esempio output: "Ci sono l'Evento A e l'Evento B." oppure "Puoi scegliere tra l'Evento A oppure l'Evento B."
    * **Parentesi e Simboli**: Integra le informazioni contenute in parentesi nel flusso principale del discorso o omettile se non aggiungono valore significativo al parlato. Evita simboli come '#', '&', '%' a meno che non facciano parte di un nome proprio o espressione comune (es. "50% di sconto" -> "cinquanta per cento di sconto").
    * **Abbreviazioni**: Sciogli le abbreviazioni comuni (es. "per es." diventa "per esempio", "ecc." diventa "eccetera").
5.  **NUMERI E DATE**: Esprimili in modo naturale per il parlato italiano (es. "il quindici luglio duemilaventicinque" invece di "15-07-2025"; "per cinque persone" invece di "x 5 persone").
6.  **OUTPUT ESCLUSIVO**: Fornisci ESCLUSIVAMENTE il testo ottimizzato per il parlato. NON includere commenti, saluti, spiegazioni del tuo processo, o qualsiasi testo che non sia la frase finale da far leggere al TTS. Il tuo output è SOLO e UNICAMENTE il testo trasformato.
7.  **GESTIONE FRASI PROBLEMATICHE**: Se il testo originale contiene frasi come "devi effettuare il login" o messaggi di errore, il tuo compito NON è rispondere o reagire, ma riformularle (se necessario per la fluidità) mantenendo l'informazione, come se stessi leggendo un avviso. Esempio input: "Errore: operazione non permessa. Devi effettuare il login." Esempio output: "Si è verificato un errore, l'operazione non è permessa. È necessario effettuare il login."

ESEMPIO DI PROCESSO MENTALE (Input: "Okay. Per l'evento 'Concerto Serale' (ID: E887), per quante persone? *Max 5*")
1.  "Okay." -> Mantenere.
2.  "Per l'evento 'Concerto Serale'" -> Mantenere. Nome chiaro.
3.  "(ID: E887)" -> Omettere. Il nome dell'evento è sufficiente per il parlato.
4.  ", per quante persone?" -> Mantenere.
5.  "*Max 5*" -> Rimuovere Markdown. Trasformare in frase: "Ricorda che puoi prenotare per un massimo di cinque persone." oppure integrare: "Per quante persone? Puoi richiederne fino a un massimo di cinque."
Output finale potrebbe essere: "Okay. Per l'evento 'Concerto Serale', per quante persone? Puoi richiederne fino a un massimo di cinque."

Il tuo output deve essere PRONTO PER LA LETTURA IMMEDIATA. Non aggiungere alcuna introduzione tipo "Ecco il testo ottimizzato:". Solo il testo.
`.trim();


    function _updateInitialAssistantMessageUI(assistantMessageContent) {
        while (aiChatMessagesContainerEl.firstChild) {
            aiChatMessagesContainerEl.removeChild(aiChatMessagesContainerEl.firstChild);
        }
        addMessageToChatUI('assistant', assistantMessageContent, 'html', true); // isInitialMsg = true
    }

    function initializeChatHistory() {
        let userNamePart = '';
        if (CURRENT_USER_DATA && CURRENT_USER_DATA.nome) {
            userNamePart = `, ${CURRENT_USER_DATA.nome}`;
        } else if (CURRENT_USER_EMAIL && typeof CURRENT_USER_EMAIL === 'string') {
            userNamePart = `, ${CURRENT_USER_EMAIL.split('@')[0]}`;
        }
        // La risposta iniziale ora è gestita dal prompt principale dell'AI per coerenza.
        // Qui prepariamo solo la cronologia base.
        const systemMessageContent = `Data e ora correnti: ${new Date().toISOString()}${(CURRENT_USER_EMAIL ? `. Utente loggato: ${CURRENT_USER_EMAIL}` : ". Nessun utente loggato.")}`;

        chatHistoryForAssistant = [
            { role: "system", content: systemMessageContent },
            // Invia un "messaggio utente" fittizio per simulare l'apertura della chat
            // e far generare all'AI il suo saluto iniziale basato sul nuovo prompt.
            { role: "user", content: "[L'utente ha appena aperto la chat]" }
        ];
        // Non impostiamo più un messaggio UI iniziale statico qui.
        // Lo faremo dopo la prima chiamata all'AI.
        resetBookingState();
        // Simula un invio per ottenere il saluto iniziale dall'AI
        // Questo verrà gestito quando la chat si apre effettivamente.
    }


    async function displayInitialAssistantGreeting() {
        // Assicurati che la cronologia sia inizializzata
        if (chatHistoryForAssistant.length === 0 || chatHistoryForAssistant.filter(m => m.role === 'assistant').length === 0) {
            initializeChatHistory(); // Prepara la cronologia con il system message e il trigger "[L'utente ha appena aperto la chat]"

            aiChatInputEl.disabled = true;
            aiChatSendBtnEl.disabled = true;
            const thinkingMessageDiv = await processAndDisplayAiResponse("Ciao! Sto per collegarmi...", true);

            try {
                const initialGreeting = await getGroqCompletion(
                    chatHistoryForAssistant, // Invierà system + "[L'utente ha appena aperto la chat]"
                    MAIN_ASSISTANT_SYSTEM_PROMPT(),
                    GROQ_MODEL_NAME_ASSISTANT
                );
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                if (initialGreeting) {
                    // _updateInitialAssistantMessageUI(initialGreeting); // Usa la funzione per pulire e aggiungere
                    // Rimuoviamo il messaggio "Sto pensando" e la finta richiesta utente
                    while (aiChatMessagesContainerEl.firstChild) {
                        aiChatMessagesContainerEl.removeChild(aiChatMessagesContainerEl.firstChild);
                    }
                    // Rimuovi "[L'utente ha appena aperto la chat]" dalla history se vuoi
                    chatHistoryForAssistant = chatHistoryForAssistant.filter(msg => msg.content !== "[L'utente ha appena aperto la chat]");
                    await processAndDisplayAiResponse(initialGreeting); // Aggiunge alla UI e alla history
                } else {
                    _updateInitialAssistantMessageUI("Ciao! Benvenuto all'Eremo. Come posso aiutarti?");
                }
            } catch (error) {
                console.error("AssistenteAI: Errore nel generare il saluto iniziale:", error);
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                _updateInitialAssistantMessageUI("Ciao! Si è verificato un problema nel contattarmi. Riprova ad aprirmi tra poco.");
            } finally {
                aiChatInputEl.disabled = false;
                aiChatSendBtnEl.disabled = false;
                if (aiChatPopupEl.classList.contains('active')) aiChatInputEl.focus();
            }
        }
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
            console.error("AssistenteAI: Fallimento decifratura API key:", e);
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
                    console.log("AssistenteAI: Chiave API Groq per assistente pronta.");
                    if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'flex';
                    return true;
                } else {
                    throw new Error("Fallimento decifratura API Key Groq per assistente.");
                }
            } else {
                throw new Error(config.message || "Dati API key per assistente mancanti o corrotti dal server.");
            }
        } catch (error) {
            console.error('AssistenteAI: Errore recupero/preparazione API key Groq:', error);
            if (aiAssistantFabEl) aiAssistantFabEl.style.display = 'none';
            GROQ_API_KEY_FOR_ASSISTANT = "CHIAVE_NON_CARICATA_O_ERRATA";
            return false;
        }
    }

    async function toggleAiChat() { // Resa async per gestire il saluto
        checkUserLoginStatus();
        const isActive = aiChatPopupEl.classList.toggle('active');
        aiAssistantFabEl.innerHTML = isActive ? '<i class="fas fa-times"></i>' : '<i class="fas fa-headset"></i>';

        if (isActive) {
            // Se la cronologia è vuota o non ci sono messaggi dell'assistente, genera il saluto
            if (chatHistoryForAssistant.length === 0 || !chatHistoryForAssistant.some(m => m.role === 'assistant')) {
                await displayInitialAssistantGreeting();
            } else if (aiChatMessagesContainerEl.children.length === 0 && chatHistoryForAssistant.some(m => m.role === 'assistant')) {
                // Se la UI è vuota ma la history esiste, ricostruisci la UI dalla history
                chatHistoryForAssistant.forEach(msg => {
                    if (msg.role === 'user') {
                        addMessageToChatUI('user', msg.content, 'text');
                    } else if (msg.role === 'assistant') {
                        addMessageToChatUI('assistant', msg.content, 'html'); // Non ripulire, è già il messaggio AI
                    }
                });
            }
            if (aiChatInputEl) aiChatInputEl.focus();

        } else {
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        }
        document.body.style.overflow = isActive ? 'hidden' : '';
    }


    function addMessageToChatUI(sender, text, type = 'html', isInitialAssistantMessage = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'ai-message');
        if (sender === 'system') messageDiv.style.display = 'none';

        if (type === 'html') messageDiv.innerHTML = text;
        else messageDiv.textContent = text;

        aiChatMessagesContainerEl.appendChild(messageDiv);
        aiChatMessagesContainerEl.scrollTop = aiChatMessagesContainerEl.scrollHeight;

        // La logica TTS per il messaggio iniziale è ora gestita da processAndDisplayAiResponse
        // quando il saluto viene generato.
        // Qui gestiamo solo TTS per i messaggi successivi.
        if (isTTSEnabled && (sender === 'ai' || sender === 'assistant') && aiChatPopupEl.classList.contains('active') && !isInitialAssistantMessage) {
            // Non chiamare speakText qui se processAndDisplayAiResponse lo farà già.
            // Questa funzione è ora più generica per aggiungere alla UI.
            // Il TTS viene attivato da processAndDisplayAiResponse.
        }
        return messageDiv;
    }


    async function processAndDisplayAiResponse(chatResponseText, isThinkingMessage = false) {
        const messageDiv = addMessageToChatUI(isThinkingMessage ? 'ai' : 'assistant', chatResponseText, 'html'); // Usa 'assistant' per messaggi reali

        if (!isThinkingMessage) {
            // Aggiungi alla cronologia solo se non è un messaggio "sto pensando"
            // e se non è già presente (per evitare duplicati dal saluto iniziale)
            if (!chatHistoryForAssistant.find(m => m.role === "assistant" && m.content === chatResponseText)) {
                chatHistoryForAssistant.push({ role: "assistant", content: chatResponseText });
            }
        }

        if (isTTSEnabled && aiChatPopupEl.classList.contains('active') && !isThinkingMessage) {
            let textToSpeak = chatResponseText; // Inizializza con il testo originale

            try {
                const ttsOptimizerMessages = [{ role: "user", content: `MESSAGGIO DA ANALIZZARE: ${chatResponseText}` }];
                console.log("AssistenteAI: Richiesta ottimizzazione TTS per:", chatResponseText.substring(0,100)+"...");

                const spokenResponseCandidate = await getGroqCompletion(
                    ttsOptimizerMessages, // Solo il messaggio utente con il testo da ottimizzare
                    TTS_OPTIMIZER_SYSTEM_PROMPT(), // Il prompt di sistema per l'ottimizzatore
                    GROQ_MODEL_NAME_TTS_OPTIMIZER, // Modello per TTS
                    0.2, // Temperatura bassa per output più deterministico
                    Math.max(300, Math.floor(chatResponseText.length * 1.5)) // Max tokens basati sulla lunghezza, minimo 300
                );

                if (spokenResponseCandidate && spokenResponseCandidate.trim() !== "") {
                    textToSpeak = spokenResponseCandidate.trim();
                    console.log("AssistenteAI: Testo ottimizzato per TTS:", textToSpeak.substring(0,100)+"...");
                } else {
                    console.warn("AssistenteAI: Ottimizzazione TTS non ha prodotto output valido, uso testo originale ripulito (dopo speakText).");
                    // speakText farà la pulizia base se l'ottimizzazione fallisce
                }
            } catch (error) {
                console.error("AssistenteAI: Errore durante l'ottimizzazione TTS:", error);
                // In caso di errore, speakText userà il chatResponseText originale (con la sua pulizia interna)
            }
            speakText(textToSpeak); // Chiama speakText con il testo ottimizzato o originale
        }
        return messageDiv;
    }


    async function callPhpScript(scriptPath, params = {}, method = 'GET') {
        let url = scriptPath;
        const options = { method };

        if (CURRENT_USER_EMAIL) {
            if (method === 'GET') {
                const tempParams = new URLSearchParams(params); // Crea una copia o usa i parametri esistenti
                if (!tempParams.has('user_email_for_script')) {
                    tempParams.set('user_email_for_script', CURRENT_USER_EMAIL);
                }
                // Aggiorna params se era un oggetto, o url se i params erano già stringa.
                // Per semplicità, ricostruiamo i params come oggetto se non lo erano.
                let newParams = {};
                for(let pair of tempParams.entries()) { newParams[pair[0]] = pair[1]; }
                params = newParams;

            } else if (method === 'POST') {
                // Assicurati che sia nei parametri che verranno messi nel FormData
                if (!(params instanceof FormData) && !params.user_email_for_script) {
                    params.user_email_for_script = CURRENT_USER_EMAIL;
                } else if (params instanceof FormData && !params.has('user_email_for_script')) {
                    params.append('user_email_for_script', CURRENT_USER_EMAIL);
                }
            }
        }


        if (method === 'GET') {
            if (Object.keys(params).length > 0) url += '?' + new URLSearchParams(params).toString();
        } else if (method === 'POST') {
            if (params instanceof FormData) {
                options.body = params;
            } else {
                const formData = new FormData();
                for (const key in params) {
                    if (Array.isArray(params[key])) {
                        params[key].forEach(value => formData.append(key + '[]', value));
                    } else {
                        formData.append(key, params[key]);
                    }
                }
                options.body = formData;
            }
        }
        console.log(`Calling PHP: ${method} ${url}`, method === 'POST' ? Object.fromEntries(options.body instanceof FormData ? options.body.entries() : []) : params);


        const thinkingPhpMessage = await processAndDisplayAiResponse(`Sto contattando i nostri sistemi per ${scriptPath.split('/').pop()}...`, true);

        try {
            const response = await fetch(url, options);
            if (thinkingPhpMessage) thinkingPhpMessage.remove();
            if (!response.ok) {
                let errorText = `Errore HTTP ${response.status} dallo script ${scriptPath}`;
                try {
                    const errorData = await response.json();
                    errorText = errorData.message || errorData.error || errorText;
                } catch (e) { /*ignore*/ }
                throw new Error(errorText);
            }
            return await response.json();
        } catch (error) {
            if (thinkingPhpMessage) thinkingPhpMessage.remove();
            console.error(`Errore chiamata a ${scriptPath}:`, error);
            // Aggiungiamo il messaggio di errore specifico alla cronologia AI per debug
            // chatHistoryForAssistant.push({ role: "system", content: `Errore da ${scriptPath}: ${error.message}` });
            // Non lo aggiungo più qui, lo aggiungerà handleSendMessageToAI come systemMessageForMainAI
            throw error;
        }
    }

    async function getGroqCompletion(messages, systemPromptContent, modelName = GROQ_MODEL_NAME_ASSISTANT, temperature = 0.3, max_tokens = 1500) {
        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            throw new Error("Chiave API Groq non disponibile o non valida.");
        }
        const payload = {
            messages: [
                { role: "system", content: systemPromptContent },
                ...messages // messages ora è l'array completo della cronologia o il messaggio specifico per TTS
            ],
            model: modelName,
            temperature: temperature,
            max_tokens: max_tokens,
        };
        // Se stiamo chiamando l'intent analysis, richiedi JSON object.
        // Per TTS optimizer, non vogliamo JSON object.
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
        if (data.choices && data.choices.length > 0 && data.choices[0].message) {
            return data.choices[0].message.content.trim();
        }
        throw new Error("Risposta API Groq non valida o vuota.");
    }

    function validateBookingStateForConfirmation(state) {
        if (!state.isActive) return { isValid: false, missingInfo: "Il flusso di prenotazione non è attivo." };
        if (!state.eventId || !state.eventTitle) return { isValid: false, missingInfo: "L'evento non è stato specificato correttamente (manca ID o Titolo)." };

        const postiGiaPrenotati = state.postiGiaPrenotatiUtente || 0;
        const postiAncoraPrenotabiliPerUtente = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati;
        const maxConsentitoPerQuestaRichiesta = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabiliPerUtente);

        if (!state.numeroPosti || state.numeroPosti < 1 || state.numeroPosti > maxConsentitoPerQuestaRichiesta) {
            return { isValid: false, missingInfo: `Il numero di posti richiesti (${state.numeroPosti || 'N/D'}) non è valido. Puoi richiedere da 1 a ${maxConsentitoPerQuestaRichiesta} posti per questa prenotazione (limite ${MAX_SEATS_PER_SINGLE_BOOKING_REQUEST} per richiesta, ${postiAncoraPrenotabiliPerUtente} ancora disponibili per te per questo evento).` };
        }
        if (!state.partecipanti || !Array.isArray(state.partecipanti)) return { isValid: false, missingInfo: "L'elenco dei partecipanti non è valido."};
        if (state.partecipanti.length !== state.numeroPosti) return { isValid: false, missingInfo: `Sono richiesti NOME e COGNOME per ${state.numeroPosti} partecipante/i, ma ne sono stati forniti ${state.partecipanti.length}.` };

        for (let i = 0; i < state.partecipanti.length; i++) {
            const partecipante = state.partecipanti[i];
            if (typeof partecipante !== 'string' || partecipante.trim().split(' ').filter(Boolean).length < 2) {
                return { isValid: false, missingInfo: `Il nome per il partecipante ${i + 1} ('${partecipante || 'N/D'}') non sembra completo. Assicurati di inserire sia NOME che COGNOME.` };
            }
        }
        return { isValid: true, missingInfo: null };
    }

    async function fetchEventDetailsAndUserBookingStatus(eventId, userEmail) {
        if (!eventId) {
            console.warn("fetchEventDetailsAndUserBookingStatus: eventId mancante.");
            currentBookingState.eventTitle = `ID Evento non specificato (o errore interno).`;
            currentBookingState.postiGiaPrenotatiUtente = 0;
            currentBookingState.postiDisponibiliEvento = 0;
            return false;
        }
        if (!userEmail && currentBookingState.isActive) { // Se siamo in un flusso di prenotazione attivo, l'email è necessaria
            console.warn("fetchEventDetailsAndUserBookingStatus: userEmail mancante per un flusso di prenotazione attivo.");
            // Non impostare messaggi di errore qui, lascia che l'AI principale lo gestisca se necessario
            return false;
        }
        try {
            const scriptParams = { id: eventId };
            // userEmail verrà aggiunto da callPhpScript se presente
            const eventDetailsResult = await callPhpScript("get_event_details.php", scriptParams);

            if (eventDetailsResult.success && eventDetailsResult.data && eventDetailsResult.data.details) {
                currentBookingState.eventTitle = eventDetailsResult.data.details.Titolo;
                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventDetailsResult.data.details.posti_gia_prenotati_utente, 10) || 0;
                currentBookingState.postiDisponibiliEvento = parseInt(eventDetailsResult.data.details.PostiDisponibili, 10);
                console.log("Dettagli evento e stato prenotazione utente recuperati:", currentBookingState);
                return true;
            } else {
                console.warn("Dettagli evento non trovati o formato risposta inatteso da get_event_details.php per eventId:", eventId, eventDetailsResult);
                const fallbackParams = { event_id_specific: eventId };
                // userEmail verrà aggiunto da callPhpScript se presente
                const eventsResult = await callPhpScript("get_events.php", fallbackParams);
                if (eventsResult.success && eventsResult.data && eventsResult.data.length > 0) {
                    const eventInfo = eventsResult.data.find(e => e.idevento == eventId);
                    if (eventInfo) {
                        currentBookingState.eventTitle = eventInfo.titolo;
                        currentBookingState.postiGiaPrenotatiUtente = parseInt(eventInfo.posti_gia_prenotati_utente, 10) || 0;
                        currentBookingState.postiDisponibiliEvento = parseInt(eventInfo.posti_disponibili, 10);
                        console.log("Dettagli evento e stato prenotazione utente recuperati (fallback get_events):", currentBookingState);
                        return true;
                    }
                }
                currentBookingState.eventTitle = currentBookingState.eventTitle || `Evento ID ${eventId} (Dettagli non trovati)`;
                currentBookingState.postiGiaPrenotatiUtente = currentBookingState.postiGiaPrenotatiUtente === undefined ? 0 : currentBookingState.postiGiaPrenotatiUtente;
                currentBookingState.postiDisponibiliEvento = currentBookingState.postiDisponibiliEvento === undefined ? 0 : currentBookingState.postiDisponibiliEvento;
                return false;
            }
        } catch (e) {
            console.error("Errore in fetchEventDetailsAndUserBookingStatus:", e);
            currentBookingState.eventTitle = currentBookingState.eventTitle || `Evento ID ${eventId} (Errore recupero dettagli)`;
            currentBookingState.postiGiaPrenotatiUtente = currentBookingState.postiGiaPrenotatiUtente === undefined ? 0 : currentBookingState.postiGiaPrenotatiUtente;
            currentBookingState.postiDisponibiliEvento = currentBookingState.postiDisponibiliEvento === undefined ? 0 : currentBookingState.postiDisponibiliEvento;
            return false; // L'errore è già stato loggato, l'AI principale lo comunicherà.
        }
    }


    async function handleSendMessageToAI() {
        checkUserLoginStatus();

        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            const keyReady = await fetchAndPrepareAssistantApiKey();
            if (!keyReady) {
                await processAndDisplayAiResponse("L'assistente AI non è correttamente configurato. Impossibile procedere.");
                return;
            }
        }

        const userInput = aiChatInputEl.value.trim();
        if (!userInput) return;

        addMessageToChatUI('user', userInput, 'text');
        chatHistoryForAssistant.push({ role: "user", content: userInput });
        aiChatInputEl.value = '';
        aiChatInputEl.disabled = true;
        aiChatSendBtnEl.disabled = true;
        aiChatInputEl.style.height = 'auto';


        const thinkingMessageDiv = await processAndDisplayAiResponse("Sto pensando...", true);

        try {
            const intentAnalysisRaw = await getGroqCompletion(
                chatHistoryForAssistant.slice(-5), // Invia solo gli ultimi 5 messaggi per l'analisi dell'intento
                INTENT_ANALYSIS_SYSTEM_PROMPT(),
                GROQ_MODEL_NAME_ASSISTANT, // Usa il modello principale anche per l'intent analysis
                0.1, // Temperatura bassa per output più deterministico (JSON)
                500 // Max tokens per l'analisi dell'intento
            );

            let intentAnalysisParsed;
            try {
                intentAnalysisParsed = JSON.parse(intentAnalysisRaw);
            } catch (e) {
                console.error("AssistenteAI: Errore parsing intent analysis JSON:", e, intentAnalysisRaw);
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                await processAndDisplayAiResponse("C'è stato un problema nell'interpretare la tua richiesta. Potresti riformulare?");
                // Non uscire del tutto, potremmo voler che l'AI principale gestisca questo
                // Aggiungiamo un system message per l'AI principale.
                chatHistoryForAssistant.push({role: "system", content: "L'analisi dell'intento ha prodotto un JSON non valido. Chiedi all'utente di riformulare o prova a rispondere genericamente."});
                // E poi procediamo a chiamare l'AI principale
            }
            // Se intentAnalysisParsed è undefined a causa di un errore di parsing gestito sopra,
            // l'AI principale riceverà un system message che lo informa.
            if (intentAnalysisParsed) console.log("AssistenteAI: Intent Analysis:", intentAnalysisParsed);


            // Verifica il login DOPO l'analisi dell'intento, ma PRIMA di eseguire azioni che lo richiedono
            if (intentAnalysisParsed && intentAnalysisParsed.requires_login && !IS_USER_LOGGED_IN) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                // La risposta "devi fare login" verrà generata dall'AI principale basandosi sul suo prompt
                // e sullo stato IS_USER_LOGGED_IN.
                // Qui impostiamo un system message per guidare l'AI.
                chatHistoryForAssistant.push({ role: "system", content: "Azione richiesta necessita di login, ma l'utente non è loggato. Informalo." });
                if (currentBookingState.isActive) resetBookingState();
                // Non fare return qui, lascia che l'AI principale formuli la risposta.
            }


            let mainAiResponseContent;
            let systemMessageForMainAI = null; // Questo verrà aggiunto alla fine della cronologia PRIMA di chiamare l'AI principale.

            if (intentAnalysisParsed) { // Solo se il parsing è andato a buon fine
                switch (intentAnalysisParsed.intent) {
                    case "GET_EVENTS":
                        try {
                            const phpParams = intentAnalysisParsed.params || {};
                            const eventsData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                            systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(eventsData)}`;
                        } catch (error) {
                            systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                        }
                        break;

                    case "GET_EVENT_DETAILS":
                        try {
                            const phpParams = intentAnalysisParsed.params || {};
                            if (intentAnalysisParsed.params && intentAnalysisParsed.params.id) { // Assicurati che 'id' sia il parametro corretto
                                phpParams.id = intentAnalysisParsed.params.id;
                            } else if (currentBookingState.eventId) { // Fallback se ID non nei params ma in booking state (meno probabile qui)
                                phpParams.id = currentBookingState.eventId;
                            }

                            if (!phpParams.id && intentAnalysisParsed.params && intentAnalysisParsed.params.event_id) { // Altro possibile nome per l'ID
                                phpParams.id = intentAnalysisParsed.params.event_id;
                            }

                            if (!phpParams.id) {
                                systemMessageForMainAI = `Tentativo di ottenere dettagli evento, ma ID evento non specificato dall'analisi intento. Chiedi ID.`;
                                break;
                            }
                            const eventDetailsData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                            systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(eventDetailsData)}`;
                        } catch (error) {
                            systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                        }
                        break;

                    case "START_BOOKING_FLOW":
                        if (IS_USER_LOGGED_IN) { // Procedi solo se loggato, altrimenti l'AI informerà
                            resetBookingState();
                            currentBookingState.isActive = true;
                            if (intentAnalysisParsed.params.event_id) {
                                currentBookingState.eventId = intentAnalysisParsed.params.event_id;
                                // Recupera dettagli e stato prenotazione UTENTE per l'evento specificato
                                await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                                // Il systemMessageForMainAI verrà costruito dal prompt dell'AI basandosi su currentBookingState
                            } else if (intentAnalysisParsed.params.event_name_hint) {
                                currentBookingState.eventNameHint = intentAnalysisParsed.params.event_name_hint;
                                // L'AI principale chiederà di specificare l'evento se solo l'hint è presente.
                                systemMessageForMainAI = `L'utente vuole iniziare una prenotazione e ha fornito un indizio sul nome: '${currentBookingState.eventNameHint}'. Se necessario, cerca eventi corrispondenti o chiedi ID.`;
                            } else {
                                // L'AI principale chiederà quale evento, come da suo prompt.
                            }
                        } else {
                            systemMessageForMainAI = "L'utente vuole iniziare una prenotazione ma non è loggato. Informalo.";
                        }
                        break;

                    case "COLLECT_BOOKING_DETAILS":
                        if (!currentBookingState.isActive || !currentBookingState.eventId) {
                            systemMessageForMainAI = "L'utente sta cercando di fornire dettagli di prenotazione, ma il flusso non è attivo o manca l'ID evento. Chiedi di iniziare specificando un evento.";
                            resetBookingState();
                        } else if (!IS_USER_LOGGED_IN) {
                            systemMessageForMainAI = "L'utente sta fornendo dettagli di prenotazione ma non è loggato. Informalo che il login è necessario.";
                            resetBookingState(); // Resetta perché non può procedere
                        } else {
                            // Qui fetchEventDetailsAndUserBookingStatus dovrebbe essere già stato chiamato in START_BOOKING_FLOW se eventId era noto.
                            // Se l'utente specifica un evento ORA, e non era in START_BOOKING_FLOW, dovremmo aggiornare.
                            // Ma START_BOOKING_FLOW dovrebbe aver gestito la selezione dell'evento.
                            // Assicuriamoci che currentBookingState.postiGiaPrenotatiUtente sia definito.
                            if (currentBookingState.postiGiaPrenotatiUtente === undefined) {
                                await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                            }

                            if (intentAnalysisParsed.params.numeroPosti) {
                                const numPostiRichiesti = parseInt(intentAnalysisParsed.params.numeroPosti, 10);
                                const postiGiaPrenotati = currentBookingState.postiGiaPrenotatiUtente || 0;
                                const maxPrenotabiliOra = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati);

                                if (numPostiRichiesti > 0 && numPostiRichiesti <= maxPrenotabiliOra) {
                                    currentBookingState.numeroPosti = numPostiRichiesti;
                                    systemMessageForMainAI = `Numero posti (${numPostiRichiesti}) accettato. Ora chiedi nomi se necessario.`;
                                } else {
                                    systemMessageForMainAI = `L'utente ha richiesto ${numPostiRichiesti} posti. Il massimo consentito in questa prenotazione è ${maxPrenotabiliOra} (considerando ${postiGiaPrenotati} già prenotati e il limite di ${MAX_SEATS_PER_SINGLE_BOOKING_REQUEST} per richiesta). Informalo chiaramente.`;
                                    currentBookingState.numeroPosti = null; // Resetta se non valido
                                }
                            }
                            if (intentAnalysisParsed.params.partecipanti_nomi_cognomi && Array.isArray(intentAnalysisParsed.params.partecipanti_nomi_cognomi) && currentBookingState.numeroPosti) {
                                const nomiDaAggiungere = intentAnalysisParsed.params.partecipanti_nomi_cognomi;
                                let nomiAggiuntiCorrettamente = 0;
                                let nomiIncompletiTrovati = [];

                                for (const nome of nomiDaAggiungere) {
                                    if (currentBookingState.partecipanti.length < currentBookingState.numeroPosti) {
                                        if (typeof nome === 'string' && nome.trim().split(' ').filter(Boolean).length >= 2) {
                                            currentBookingState.partecipanti.push(nome.trim());
                                            nomiAggiuntiCorrettamente++;
                                        } else {
                                            nomiIncompletiTrovati.push(nome);
                                        }
                                    } else {
                                        break; // Raggiunto il numero di partecipanti necessari
                                    }
                                }
                                let tempSystemMessage = systemMessageForMainAI || "";
                                if (nomiAggiuntiCorrettamente > 0) {
                                    tempSystemMessage += ` ${nomiAggiuntiCorrettamente} nomi partecipanti aggiunti.`;
                                }
                                if (nomiIncompletiTrovati.length > 0) {
                                    tempSystemMessage += ` I seguenti nomi sembrano incompleti (serve Nome Cognome): ${nomiIncompletiTrovati.join(', ')}. Richiedili corretti.`;
                                }
                                systemMessageForMainAI = tempSystemMessage.trim();
                            }
                            // Alla fine di COLLECT_BOOKING_DETAILS, controlla se siamo pronti per la conferma
                            const validationForConfirm = validateBookingStateForConfirmation(currentBookingState);
                            if (validationForConfirm.isValid) {
                                systemMessageForMainAI = (systemMessageForMainAI || "") + " Tutti i dati per la prenotazione sembrano raccolti e validi. Presenta il riepilogo e chiedi conferma all'utente.";
                            } else if (currentBookingState.numeroPosti && currentBookingState.partecipanti.length < currentBookingState.numeroPosti) {
                                // Non sovrascrivere un messaggio di errore sul numero di posti
                                if (!systemMessageForMainAI || !systemMessageForMainAI.includes("posti")) {
                                    systemMessageForMainAI = (systemMessageForMainAI || "") + ` Mancano ancora ${currentBookingState.numeroPosti - currentBookingState.partecipanti.length} nomi.`;
                                }
                            }
                        }
                        break;

                    case "CONFIRM_BOOKING_DETAILS":
                        if (!IS_USER_LOGGED_IN) {
                            systemMessageForMainAI = "L'utente vuole confermare ma non è loggato. Informalo.";
                            resetBookingState();
                            break;
                        }
                        if (!currentBookingState.isActive) {
                            systemMessageForMainAI = "L'utente vuole confermare, ma non c'è una prenotazione attiva.";
                            break;
                        }
                        const validation = validateBookingStateForConfirmation(currentBookingState);
                        if (validation.isValid) {
                            try {
                                const nomiPartecipanti = [];
                                const cognomiPartecipanti = [];
                                currentBookingState.partecipanti.forEach(partecipante => {
                                    const parts = partecipante.trim().split(' ');
                                    nomiPartecipanti.push(parts.shift());
                                    cognomiPartecipanti.push(parts.join(' '));
                                });

                                const bookingParams = {
                                    eventId: currentBookingState.eventId,
                                    numeroPosti: currentBookingState.numeroPosti,
                                    contatto: CURRENT_USER_EMAIL,
                                    partecipanti_nomi: nomiPartecipanti,
                                    partecipanti_cognomi: cognomiPartecipanti,
                                    // richieste_speciali: currentBookingState.richiesteSpeciali || '' // Attualmente non usato da PHP
                                };
                                console.log("AssistenteAI: Invio parametri a prenota_evento.php:", bookingParams);
                                const bookingResult = await callPhpScript("prenota_evento.php", bookingParams, 'POST');
                                systemMessageForMainAI = `Risultato prenotazione: ${JSON.stringify(bookingResult)}`;
                                if (bookingResult.success) {
                                    resetBookingState();
                                }
                            } catch (error) {
                                systemMessageForMainAI = `Errore durante la chiamata a prenota_evento.php: ${error.message}. Informa l'utente.`;
                            }
                        } else {
                            systemMessageForMainAI = `Tentativo di conferma prenotazione fallito. Dati mancanti o non validi: ${validation.missingInfo}. Chiedi all'utente di correggere o fornire i dati. Stato attuale: ${JSON.stringify(currentBookingState)}`;
                        }
                        break;

                    case "GET_USER_PROFILE":
                    case "GET_USER_BOOKINGS":
                        if (!IS_USER_LOGGED_IN) {
                            systemMessageForMainAI = `L'utente chiede informazioni personali (${intentAnalysisParsed.intent}) ma non è loggato. Informalo.`;
                            break;
                        }
                        try {
                            const phpParams = intentAnalysisParsed.params || {};
                            // user_email sarà aggiunto da callPhpScript
                            const userData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                            systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(userData)}`;
                        } catch (error) {
                            systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                        }
                        break;

                    case "GENERAL_QUERY":
                    case "UNKNOWN":
                    default:
                        // Per GENERAL_QUERY (es. "ciao"), l'AI principale dovrebbe rispondere amichevolmente come da suo prompt.
                        // Non è necessario un systemMessageForMainAI specifico a meno che non ci sia un missing_info_prompt.
                        if (intentAnalysisParsed.missing_info_prompt) {
                            systemMessageForMainAI = `L'analisi dell'intento suggerisce di chiedere: ${intentAnalysisParsed.missing_info_prompt}`;
                        } else if (intentAnalysisParsed.intent === "UNKNOWN") {
                            systemMessageForMainAI = "Non ho compreso la richiesta. Prova a formulare diversamente o chiedi aiuto sulle mie capacità.";
                        }
                        // Se è un semplice saluto, MAIN_ASSISTANT_SYSTEM_PROMPT lo gestirà senza systemMessageForMainAI aggiuntivo.
                        break;
                }
            } else { // Se intentAnalysisParsed è null/undefined (errore parsing JSON)
                systemMessageForMainAI = chatHistoryForAssistant.find(m => m.role === 'system' && m.content.includes("L'analisi dell'intento ha prodotto un JSON non valido"))?.content || "Ho avuto difficoltà a capire la tua ultima richiesta. Potresti riprovare?";
            }


            const messagesForMainAI = [...chatHistoryForAssistant];
            if (systemMessageForMainAI) {
                messagesForMainAI.push({ role: "system", content: systemMessageForMainAI });
            }

            mainAiResponseContent = await getGroqCompletion(
                messagesForMainAI.slice(-10), // Invia gli ultimi 10 messaggi (inclusi user, assistant, system)
                MAIN_ASSISTANT_SYSTEM_PROMPT(),
                GROQ_MODEL_NAME_ASSISTANT
            );

            if (thinkingMessageDiv) thinkingMessageDiv.remove();

            if (mainAiResponseContent) {
                await processAndDisplayAiResponse(mainAiResponseContent);
            } else {
                await processAndDisplayAiResponse("Mi dispiace, non sono riuscito a elaborare una risposta. Riprova.");
            }

        } catch (error) {
            console.error("AssistenteAI: Errore in handleSendMessageToAI:", error);
            if (thinkingMessageDiv) thinkingMessageDiv.remove();

            let displayErrorMessage = `Si è verificato un errore: ${error.message}. Riprova più tardi.`;
            // Se l'errore è già stato messo come system message, l'AI principale dovrebbe gestirlo.
            // Altrimenti, mostriamo un messaggio generico.
            const lastSystemMessage = chatHistoryForAssistant.filter(m => m.role === 'system').pop();
            if (!lastSystemMessage || !lastSystemMessage.content.includes(error.message)) {
                await processAndDisplayAiResponse(displayErrorMessage);
            } else {
                // Se l'errore è già stato comunicato al sistema, l'AI principale dovrebbe averlo usato per rispondere.
                // Se non l'ha fatto, potrebbe essere necessario un fallback, ma per ora assumiamo che lo faccia.
                console.log("AssistenteAI: Errore già comunicato al sistema, l'AI principale dovrebbe rispondere di conseguenza.")
            }
        } finally {
            aiChatInputEl.disabled = false;
            aiChatSendBtnEl.disabled = false;
            if (aiChatPopupEl.classList.contains('active')) aiChatInputEl.focus();
        }
    }

    // Event Listeners
    aiAssistantFabEl.addEventListener('click', toggleAiChat);
    aiChatCloseBtnEl.addEventListener('click', toggleAiChat);
    aiChatSendBtnEl.addEventListener('click', handleSendMessageToAI);
    aiChatInputEl.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessageToAI();
        }
    });
    aiChatInputEl.addEventListener('input', () => {
        aiChatInputEl.style.height = 'auto';
        aiChatInputEl.style.height = (aiChatInputEl.scrollHeight) + 'px';
    });


    // Inizializzazione
    fetchAndPrepareAssistantApiKey().then(keyReady => {
        if (keyReady) {
            console.log("AssistenteAI: Pronto. Il saluto iniziale verrà generato all'apertura della chat.");
            // Non inizializzare la chat o mostrare il saluto qui,
            // toggleAiChat lo farà quando l'utente apre effettivamente la chat.
        } else {
            console.warn("AssistenteAI: Chiave API non caricata. L'assistente potrebbe non funzionare o mostrare un messaggio di errore all'apertura.");
            // Potresti voler mostrare un messaggio di errore direttamente nel FAB se la chiave non carica,
            // o gestire questo all'interno di toggleAiChat se la chiave non è pronta.
            if (aiAssistantFabEl) {
                // Non nascondere il FAB, ma toggleAiChat mostrerà l'errore se la chiave non è pronta
            }
        }
    });

<<<<<<< HEAD
});
=======
}); // Chiusura del listener per DOMContentLoaded
; // Assicura che l'istruzione addEventListener termini con un punto e virgola.
>>>>>>> 2dea0ba9f868f1554fa910d85fc4628e63bd657c
