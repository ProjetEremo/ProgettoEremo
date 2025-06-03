// File: assistenteAI.js (Versione Rivoluzionata e Corretta v10 - Formattazione Eventi Migliorata)
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
            partecipanti: [],
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
Il tuo scopo è ESEGUIRE AZIONI e fornire informazioni. Il sistema JavaScript (JS) aggiorna currentBookingState. Tu guidi l'utente e fai domande basate su currentBookingState e sulle informazioni mancanti identificate dal JS.

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
1.  LOGIN: L'utente deve essere loggato. Se non lo è, informalo e fermati. Email (${CURRENT_USER_EMAIL || 'Nessun utente loggato'}) usata automaticamente.
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
- SALUTI E CONVERSAZIONE GENERICA: Se l'intent è GENERAL_QUERY (es. l'utente dice "ciao"), rispondi in modo amichevole e breve, ad esempio "Ciao! Come posso aiutarti oggi con le informazioni sull'Eremo Frate Francesco o con le prenotazioni?". Non limitarti a dire che non puoi assistere per semplici saluti.
- Se una domanda specifica esula dalle tue capacità (informazioni non pertinenti al sito Eremo Frate Francesco o alle sue funzionalità), allora indica gentilmente che non puoi assistere su quell'argomento specifico. Rispondi in italiano.
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
        const assistantMessageContent = `Ciao! Sono l'assistente virtuale dell'Eremo${userNamePart}. Come posso aiutarti oggi riguardo il sito?`;
        const systemMessageContent = `Data e ora correnti: ${new Date().toISOString()}${(CURRENT_USER_EMAIL ? `. Utente loggato: ${CURRENT_USER_EMAIL}` : ". Nessun utente loggato.")}`;
        chatHistoryForAssistant = [
            { role: "system", content: systemMessageContent },
            { role: "assistant", content: assistantMessageContent }
        ];
        _updateInitialAssistantMessageUI(assistantMessageContent);
        resetBookingState();
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
            resetBookingState();
        }
        document.body.style.overflow = isActive ? 'hidden' : '';
    }

    function addMessageToChatUI(sender, text, type = 'text') {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'ai-message');
        if (sender === 'system') messageDiv.style.display = 'none';

        if (type === 'html') messageDiv.innerHTML = text; // Use innerHTML for Markdown formatted text
        else messageDiv.appendChild(document.createTextNode(text));

        aiChatMessagesContainerEl.appendChild(messageDiv);
        aiChatMessagesContainerEl.scrollTop = aiChatMessagesContainerEl.scrollHeight;
        return messageDiv;
    }

    async function callPhpScript(scriptPath, params = {}, method = 'GET') {
        let url = scriptPath;
        const options = { method };

        if (CURRENT_USER_EMAIL && (scriptPath.includes('get_events.php') || scriptPath.includes('get_event_details.php'))) {
            if (!params.user_email_for_script) params.user_email_for_script = CURRENT_USER_EMAIL;
        }

        if (method === 'GET') {
            if (Object.keys(params).length > 0) url += '?' + new URLSearchParams(params).toString();
        } else if (method === 'POST') {
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
        console.log(`Calling PHP: ${method} ${url}`, method === 'POST' ? Object.fromEntries(options.body instanceof FormData ? options.body.entries() : []) : '');
        const thinkingPhpMessage = addMessageToChatUI('ai', `Sto contattando i nostri sistemi per ${scriptPath.split('/').pop()}...`);
        try {
            const response = await fetch(url, options);
            if (thinkingPhpMessage) thinkingPhpMessage.remove();
            if (!response.ok) {
                let errorText = `Errore HTTP ${response.status} dallo script ${scriptPath}`;
                try { const errorData = await response.json(); errorText = errorData.message || errorData.error || errorText; } catch (e) { /*ignore*/ }
                throw new Error(errorText);
            }
            return await response.json();
        } catch (error) {
            if (thinkingPhpMessage) thinkingPhpMessage.remove();
            console.error(`Errore chiamata a ${scriptPath}:`, error);
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
        if (!userEmail) {
            console.warn("fetchEventDetailsAndUserBookingStatus: userEmail mancante (utente non loggato?).");
            currentBookingState.eventTitle = `Accesso richiesto per dettagli evento ID ${eventId}`;
            currentBookingState.postiGiaPrenotatiUtente = 0;
            currentBookingState.postiDisponibiliEvento = 0;
            return false;
        }
        try {
            const eventDetailsResult = await callPhpScript("get_event_details.php", {
                id: eventId,
                user_email_for_script: userEmail
            });

            if (eventDetailsResult.success && eventDetailsResult.data && eventDetailsResult.data.details) {
                currentBookingState.eventTitle = eventDetailsResult.data.details.Titolo;
                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventDetailsResult.data.details.posti_gia_prenotati_utente, 10) || 0;
                currentBookingState.postiDisponibiliEvento = parseInt(eventDetailsResult.data.details.PostiDisponibili, 10);
                console.log("Dettagli evento e stato prenotazione utente recuperati:", currentBookingState);
                return true;
            } else {
                console.warn("Dettagli evento non trovati o formato risposta inatteso da get_event_details.php per eventId:", eventId, eventDetailsResult);
                const eventsResult = await callPhpScript("get_events.php", {
                    event_id_specific: eventId,
                    user_email_for_script: userEmail
                });
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
                currentBookingState.eventTitle = `Evento ID ${eventId} (Dettagli non trovati)`;
                currentBookingState.postiGiaPrenotatiUtente = 0;
                currentBookingState.postiDisponibiliEvento = 0;
                return false;
            }
        } catch (e) {
            console.error("Errore in fetchEventDetailsAndUserBookingStatus:", e);
            currentBookingState.eventTitle = currentBookingState.eventTitle || `Evento ID ${eventId} (Errore recupero dettagli)`;
            currentBookingState.postiGiaPrenotatiUtente = 0;
            currentBookingState.postiDisponibiliEvento = 0;
            return false;
        }
    }


    async function handleSendMessageToAI() {
        checkUserLoginStatus();
        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            const keyReady = await fetchAndPrepareAssistantApiKey();
            if (!keyReady) { addMessageToChatUI('ai', "L'assistente AI non è correttamente configurato. Impossibile procedere."); return; }
        }

        const userInput = aiChatInputEl.value.trim();
        if (!userInput) return;

        addMessageToChatUI('user', userInput);
        chatHistoryForAssistant.push({ role: "user", content: userInput });
        aiChatInputEl.value = ''; aiChatInputEl.disabled = true; aiChatSendBtnEl.disabled = true; aiChatInputEl.style.height = 'auto';

        const thinkingMessageDiv = addMessageToChatUI('ai', "Sto elaborando la tua richiesta...");

        try {
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
                parsedIntent = {
                    intent: "GENERAL_QUERY", params: {}, php_script: "none", requires_login: false,
                    missing_info_prompt: "Non ho compreso bene la tua richiesta. Puoi riformularla in modo più chiaro?",
                    is_clarification_needed: true
                };
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis fallback (parsing error): ${JSON.stringify(parsedIntent)}` });
            }

            const bookingIntents = ["START_BOOKING_FLOW", "COLLECT_BOOKING_DETAILS", "CONFIRM_BOOKING_DETAILS"];
            if (bookingIntents.includes(parsedIntent.intent) && !IS_USER_LOGGED_IN) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                const loginMessage = "Per procedere con una prenotazione, è necessario effettuare l'accesso. Puoi accedere o registrarti tramite il menu del sito.";
                addMessageToChatUI('ai', loginMessage);
                chatHistoryForAssistant.push({ role: "assistant", content: loginMessage });
                resetBookingState();
                finalizeUIAfterResponse(); return;
            }

            if (parsedIntent.requires_login && !IS_USER_LOGGED_IN) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                const loginMessage = "Per questa azione è necessario effettuare l'accesso. Puoi accedere o registrarti tramite il menu del sito.";
                addMessageToChatUI('ai', loginMessage);
                chatHistoryForAssistant.push({ role: "assistant", content: loginMessage });
                resetBookingState();
                finalizeUIAfterResponse(); return;
            }


            if (parsedIntent.intent === "START_BOOKING_FLOW") {
                if (!currentBookingState.isActive || (parsedIntent.params?.event_id && parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId) || parsedIntent.params?.event_name_hint) {
                    resetBookingState();
                }
                currentBookingState.isActive = true;
                if (parsedIntent.params?.event_id) currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10);
                if (parsedIntent.params?.event_name_hint) currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;

                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                let initialBookingPrompt = parsedIntent.missing_info_prompt || "Certo, iniziamo la prenotazione!";

                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined') {
                    const detailsFetched = await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    if (!detailsFetched && (!currentBookingState.eventTitle || currentBookingState.eventTitle.includes("Errore") || currentBookingState.eventTitle.includes("Dettagli non trovati") || currentBookingState.eventTitle.includes("Accesso richiesto"))) {
                        initialBookingPrompt = `Non sono riuscito a trovare i dettagli per l'evento ID ${currentBookingState.eventId}. Potresti verificare l'ID o fornire il nome dell'evento?`;
                        if (currentBookingState.eventTitle.includes("Accesso richiesto")) {
                            initialBookingPrompt = "Per visualizzare i dettagli dell'evento e prenotare, devi prima accedere.";
                        }
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

                if (currentBookingState.eventId && currentBookingState.eventTitle && !currentBookingState.eventTitle.includes("Errore") && !currentBookingState.eventTitle.includes("Dettagli non trovati")  && !currentBookingState.eventTitle.includes("Accesso richiesto")) {
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

                                if (!IS_USER_LOGGED_IN){
                                    initialBookingPrompt = `Ho trovato l'evento: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Per prenotare, è necessario effettuare l'accesso.`;
                                    resetBookingState();
                                } else if (currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
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
                                let eventListString = "Ho trovato più eventi che corrispondono. Quale desideri? Fornisci l'ID:\n";
                                eventsResult.data.slice(0, 5).forEach(evt => {
                                    eventListString += `- **${evt.titolo}** (ID: ${evt.idevento}) - Data: ${evt.data_inizio_evento || evt.DataInizio || 'N/D'}\n`;
                                });
                                initialBookingPrompt = eventListString;
                            }
                        } else { initialBookingPrompt = `Non ho trovato eventi per "${currentBookingState.eventNameHint}". Riprova con un nome o ID più preciso.`; resetBookingState(); }
                    } catch (phpError) { initialBookingPrompt = `Si è verificato un errore durante la ricerca dell'evento. Per favore, fornisci l'ID esatto se lo conosci.`; console.error(phpError); resetBookingState(); }
                } else if (!currentBookingState.eventId && !currentBookingState.eventNameHint) {
                    initialBookingPrompt = "A quale evento sei interessato/a? Per favore, fornisci il nome o l'ID dell'evento.";
                } else if (currentBookingState.eventTitle && (currentBookingState.eventTitle.includes("Errore") || currentBookingState.eventTitle.includes("Dettagli non trovati") || currentBookingState.eventTitle.includes("Accesso richiesto"))) {
                    initialBookingPrompt = `C'è stato un problema con l'evento selezionato (${currentBookingState.eventTitle}). A quale evento sei interessato/a? Per favore, fornisci nome o ID.`;
                    if (currentBookingState.eventTitle.includes("Accesso richiesto")) {
                        initialBookingPrompt = "Per procedere, è necessario effettuare l'accesso.";
                    }
                    resetBookingState();
                }
                addMessageToChatUI('ai', initialBookingPrompt, 'html'); // Use html for Markdown
                chatHistoryForAssistant.push({ role: "assistant", content: initialBookingPrompt });
                finalizeUIAfterResponse(); return;
            }

            if (parsedIntent.intent === "COLLECT_BOOKING_DETAILS") {
                if (!currentBookingState.isActive) {
                    if (thinkingMessageDiv) thinkingMessageDiv.remove();
                    const msg = "Per raccogliere i dettagli della prenotazione, dobbiamo prima iniziare un flusso di prenotazione. Vuoi che ti aiuti a trovare un evento e iniziare?";
                    addMessageToChatUI('ai', msg);
                    chatHistoryForAssistant.push({ role: "assistant", content: msg });
                    finalizeUIAfterResponse(); return;
                }
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
                    if (IS_USER_LOGGED_IN && currentBookingState.eventId && typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined') {
                        await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    }
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

                if (parsedIntent.params?.partecipanti_nomi_cognomi) {
                    console.log("AssistenteAI (v10 debug): Ricevuto partecipanti_nomi_cognomi:", JSON.stringify(parsedIntent.params.partecipanti_nomi_cognomi));
                    let newNamesInput = parsedIntent.params.partecipanti_nomi_cognomi;
                    let rawExtractedNames = [];

                    if (Array.isArray(newNamesInput)) {
                        newNamesInput.forEach(item => {
                            if (typeof item === 'string' && item.trim() !== "") rawExtractedNames.push(item.trim());
                        });
                    } else if (typeof newNamesInput === 'string' && newNamesInput.trim() !== "") {
                        rawExtractedNames.push(newNamesInput.trim());
                    }

                    let fullyProcessedNewNames = [];
                    rawExtractedNames.forEach(nameEntry => {
                        let cleanedEntry = nameEntry.replace(/^(ecco i due|ecco i nomi|sono|per il partecipante|i nomi sono|i partecipanti sono)\s*[:]?\s*/i, '').trim();
                        const individualNames = cleanedEntry.split(/\s*,\s*|\s+e\s+|\s+ed\s+/i);
                        individualNames.forEach(name => {
                            const trimmedName = name.trim();
                            if (trimmedName && trimmedName.split(' ').filter(Boolean).length >= 1) {
                                fullyProcessedNewNames.push(trimmedName);
                            }
                        });
                    });
                    fullyProcessedNewNames = [...new Set(fullyProcessedNewNames)];

                    if (fullyProcessedNewNames.length > 0) {
                        let combinedNames = [...currentBookingState.partecipanti];
                        fullyProcessedNewNames.forEach(newName => {
                            if (!combinedNames.includes(newName)) {
                                combinedNames.push(newName);
                            }
                        });
                        currentBookingState.partecipanti = [...new Set(combinedNames)];

                        if (currentBookingState.numeroPosti && currentBookingState.partecipanti.length > currentBookingState.numeroPosti) {
                            currentBookingState.partecipanti = currentBookingState.partecipanti.slice(0, currentBookingState.numeroPosti);
                        }
                        console.log("AssistenteAI (v10 debug): currentBookingState.partecipanti AGGIORNATO a:", JSON.stringify(currentBookingState.partecipanti));
                    }
                }


                if (currentBookingState.eventId && IS_USER_LOGGED_IN && (typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined' || !currentBookingState.eventTitle || currentBookingState.eventTitle.includes("Errore") || currentBookingState.eventTitle.includes("Dettagli non trovati") || currentBookingState.eventTitle.includes("Accesso richiesto"))) {
                    const detailsSuccess = await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    if (!detailsSuccess) {
                        addMessageToChatUI('ai', `Non sono riuscito a recuperare i dettagli aggiornati per l'evento ID ${currentBookingState.eventId}. Potresti ricontrollare l'ID o il nome?`);
                        chatHistoryForAssistant.push({ role: "assistant", content: `Fallimento recupero dettagli evento ID ${currentBookingState.eventId} in COLLECT_BOOKING_DETAILS` });
                        resetBookingState();
                        finalizeUIAfterResponse(); return;
                    }
                }

                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' && currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                    const limitReachedMsg = `Ho verificato e hai già ${currentBookingState.postiGiaPrenotatiUtente} posti per "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}. Non è possibile aggiungere altri posti.`;
                    addMessageToChatUI('ai', limitReachedMsg); chatHistoryForAssistant.push({ role: "assistant", content: limitReachedMsg });
                    resetBookingState(); finalizeUIAfterResponse(); return;
                }
                if (currentBookingState.numeroPosti && IS_USER_LOGGED_IN) {
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

                let nextPrompt = "";
                const validation = validateBookingStateForConfirmation(currentBookingState);

                if (!currentBookingState.eventId || !currentBookingState.eventTitle || currentBookingState.eventTitle.includes("Errore") || currentBookingState.eventTitle.includes("Dettagli non trovati") || currentBookingState.eventTitle.includes("Accesso richiesto")) {
                    nextPrompt = "Sembra ci sia stato un problema con la selezione dell'evento. A quale evento eri interessato/a? Per favore, fornisci nome o ID.";
                    if (currentBookingState.eventTitle && currentBookingState.eventTitle.includes("Accesso richiesto")) {
                        nextPrompt = "Per procedere con la prenotazione, è necessario effettuare l'accesso.";
                    }
                    resetBookingState();
                } else if (!validation.isValid) {
                    nextPrompt = validation.missingInfo;
                } else {
                    let summary = `Perfetto! Riepilogo la prenotazione:\n`;
                    summary += `- Evento: **${currentBookingState.eventTitle}** (ID: ${currentBookingState.eventId})\n`;
                    summary += `- Numero Partecipanti: ${currentBookingState.numeroPosti}\n`;
                    summary += `- Partecipanti:\n`;
                    currentBookingState.partecipanti.forEach((p, idx) => { summary += `  ${idx + 1}. ${p}\n`; });
                    summary += `\nÈ tutto corretto? Posso procedere con la prenotazione?`;
                    nextPrompt = summary;
                }
                addMessageToChatUI('ai', nextPrompt, 'html'); // Use html for Markdown
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

                if (scriptPath === 'get_events.php' && (!scriptParams.period && !scriptParams.search_term && !scriptParams.event_id_specific)) {
                    scriptParams.period = "all_future";
                    console.log("AssistenteAI: Defaulting to 'all_future' for get_events.php");
                }
                if (scriptPath === 'prenota_evento.php' && !scriptParams.contatto && CURRENT_USER_EMAIL) {
                    scriptParams.contatto = CURRENT_USER_EMAIL;
                }
                if (scriptPath === 'get_event_details.php' && scriptParams.event_id && !scriptParams.id) {
                    scriptParams.id = scriptParams.event_id; delete scriptParams.event_id;
                }
                if (scriptPath === 'get_events.php' && CURRENT_USER_EMAIL && !scriptParams.user_email_for_script) {
                    scriptParams.user_email_for_script = CURRENT_USER_EMAIL;
                }
                if ((scriptPath.includes("prenota_evento.php") || scriptPath.includes("api/api_get_user_profile.php") || scriptPath.includes("api/api_get_user_bookings.php")) && !IS_USER_LOGGED_IN) {
                    if (thinkingMessageDiv) thinkingMessageDiv.remove();
                    const loginRequiredMsg = "Per questa operazione è necessario essere autenticati. Per favore, effettua l'accesso.";
                    addMessageToChatUI('ai', loginRequiredMsg);
                    chatHistoryForAssistant.push({ role: "assistant", content: loginRequiredMsg });
                    finalizeUIAfterResponse(); return;
                }


                try {
                    const phpResult = await callPhpScript(scriptPath, scriptParams, method);
                    chatHistoryForAssistant.push({ role: "system", content: `Risultato PHP (${scriptPath}): ${JSON.stringify(phpResult)}` });
                    console.log("PHP Result for " + scriptPath + ":", phpResult);

                    if (parsedIntent.intent === "GET_EVENTS") {
                        if (thinkingMessageDiv) thinkingMessageDiv.remove();
                        if (phpResult.success && phpResult.data) {
                            chatHistoryForAssistant.push({ role: "system", content: `Dati degli eventi ricevuti. Per favore, presentali all'utente in modo chiaro, usando Markdown per la lista.`});
                            const aiFormattedEvents = await getGroqCompletion(chatHistoryForAssistant.slice(-10), MAIN_ASSISTANT_SYSTEM_PROMPT(), 0.5, 1500);

                            if (aiFormattedEvents) {
                                addMessageToChatUI('ai', aiFormattedEvents, 'html'); // Use html for Markdown
                                chatHistoryForAssistant.push({ role: "assistant", content: aiFormattedEvents });
                            } else {
                                let fallbackDisplay = "Non sono riuscito a formattare la risposta come avrei voluto, ma ecco gli eventi trovati:\n\n";
                                if (phpResult.data.length > 0) {
                                    phpResult.data.forEach((event, index) => {
                                        fallbackDisplay += `- **${event.titolo}** (ID: ${event.idevento}) - Data: ${event.data_inizio_evento || event.DataInizio || 'N/D'}\n`;
                                    });
                                } else {
                                    fallbackDisplay = "Non ci sono eventi disponibili al momento o che corrispondano alla tua ricerca.";
                                }
                                addMessageToChatUI('ai', fallbackDisplay, 'html'); // Use html for Markdown
                                chatHistoryForAssistant.push({ role: "system", content: "Main LLM failed to format GET_EVENTS, JS fallback used."});
                                chatHistoryForAssistant.push({ role: "assistant", content: fallbackDisplay }); // Store the raw fallback string
                            }
                        } else {
                            addMessageToChatUI('ai', phpResult.message || "Non sono riuscito a recuperare gli eventi in questo momento.");
                            chatHistoryForAssistant.push({ role: "assistant", content: phpResult.message || "Failed to retrieve events from PHP for GET_EVENTS." });
                        }
                        finalizeUIAfterResponse(); return;
                    }

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
                (parsedIntent.intent === "GENERAL_QUERY" || parsedIntent.intent === "UNKNOWN") ||
                (parsedIntent.php_script && parsedIntent.php_script !== "none" &&
                    chatHistoryForAssistant[chatHistoryForAssistant.length - 1]?.role === "system") ||
                (!["START_BOOKING_FLOW", "COLLECT_BOOKING_DETAILS", "CONFIRM_BOOKING_DETAILS", "GET_EVENTS"].includes(parsedIntent.intent) &&
                    !(parsedIntent.is_clarification_needed && parsedIntent.missing_info_prompt));


            if (requiresAiResponseGeneration && chatHistoryForAssistant[chatHistoryForAssistant.length - 1]?.role !== 'assistant') {
                const finalResponseHistory = chatHistoryForAssistant.slice(-10);
                const aiFinalResponse = await getGroqCompletion(finalResponseHistory, MAIN_ASSISTANT_SYSTEM_PROMPT(), 0.5, 1500);

                if (aiFinalResponse) {
                    addMessageToChatUI('ai', aiFinalResponse, 'html'); // Use html for Markdown
                    chatHistoryForAssistant.push({ role: "assistant", content: aiFinalResponse });
                } else {
                    let fallbackMsg = "Non sono sicuro di come rispondere. Puoi provare a chiedere in un altro modo?";
                    if (parsedIntent.intent !== "GENERAL_QUERY" && parsedIntent.intent !== "UNKNOWN") {
                        fallbackMsg = "Si è verificato un problema nella generazione della risposta. Riprova.";
                    }
                    addMessageToChatUI('ai', fallbackMsg);
                    chatHistoryForAssistant.push({ role: "system", content: `Main LLM response was empty for intent '${parsedIntent.intent}'. Used fallback message.` });
                    chatHistoryForAssistant.push({ role: "assistant", content: fallbackMsg });
                }
            }

        } catch (error) {
            console.error("Errore in handleSendMessageToAI:", error);
            if (thinkingMessageDiv) thinkingMessageDiv.remove();
            addMessageToChatUI('ai', `Spiacente, si è verificato un errore imprevisto: ${error.message}. Per favore, riprova.`);
            chatHistoryForAssistant.push({ role: "assistant", content: `General Error in handleSendMessageToAI: ${error.message}` });
            resetBookingState();
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
        if (chatHistoryForAssistant.length > 30) {
            const systemMessagesToKeep = chatHistoryForAssistant.filter(msg => msg.role === 'system' && msg.content.startsWith('Data e ora correnti:'));
            const lastSystemDateTime = systemMessagesToKeep.length > 0 ? systemMessagesToKeep[systemMessagesToKeep.length-1] : chatHistoryForAssistant[0];
            const recentMessages = chatHistoryForAssistant.slice(Math.max(1, chatHistoryForAssistant.length - 29));
            chatHistoryForAssistant = [lastSystemDateTime, ...recentMessages.filter(msg => msg !== lastSystemDateTime)];
        }
    }

    console.log("AssistenteAI: Script in esecuzione (v10 - Formattazione Eventi Migliorata).");
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
    });
});
