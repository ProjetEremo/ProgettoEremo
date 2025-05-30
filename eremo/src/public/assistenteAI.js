// File: assistenteAI.js (Versione Rivoluzionata e Corretta v3)
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
    const GROQ_MODEL_NAME_ASSISTANT = "meta-llama/llama-4-scout-17b-16e-instruct";

    let IS_USER_LOGGED_IN = false;
    let CURRENT_USER_EMAIL = null;
    let CURRENT_USER_DATA = null;

    const MAX_SEATS_PER_SINGLE_BOOKING_REQUEST = 5; // Limite per una singola transazione di prenotazione
    const MAX_TOTAL_SEATS_PER_USER_PER_EVENT = 5;   // Limite totale che un utente può avere per un evento

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
            postiGiaPrenotatiUtente: undefined, // Aggiunto per il controllo proattivo
            postiDisponibiliEvento: undefined   // Aggiunto per info
        };
        console.log("AssistenteAI: Stato prenotazione resettato.");
    }

    const INTENT_ANALYSIS_SYSTEM_PROMPT = () => `
Analizza la richiesta dell'utente per il sito "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}). Stato prenotazione attuale (se esiste): ${JSON.stringify(currentBookingState)}.
Determina:
1.  "intent": L'azione principale. Valori: GET_EVENTS, GET_EVENT_DETAILS, START_BOOKING_FLOW, COLLECT_BOOKING_DETAILS, CONFIRM_BOOKING_DETAILS, GET_USER_PROFILE, GET_USER_BOOKINGS, GENERAL_QUERY, UNKNOWN.
    - Usa START_BOOKING_FLOW se l'utente esprime intenzione di prenotare E (mancano eventId/eventTitle in currentBookingState OPPURE l'utente sta chiaramente iniziando una nuova richiesta per un evento diverso, o non c'è un evento attivo in currentBookingState).
    - Usa COLLECT_BOOKING_DETAILS se currentBookingState è attivo con eventId e eventTitle, e l'utente fornisce dettagli (numero persone, nomi partecipanti) OPPURE se stai attivamente chiedendo questi dettagli.
        - Se stai chiedendo il numero di posti e l'utente fornisce un numero, estrai "numeroPosti".
        - Se stai chiedendo i nomi dei partecipanti (e currentBookingState.numeroPosti è noto), e l'utente fornisce testo che assomiglia a nomi completi: ESTRAI **solo le coppie Nome Cognome** in \`params.partecipanti_nomi_cognomi\` come un array di stringhe, dove ogni stringa è un singolo "Nome Cognome" completo. Ignora frasi introduttive come 'ecco i nomi:' o 'sono:'.
          Esempi di input utente -> estrazione per \`params.partecipanti_nomi_cognomi\`:
          - 'Mario Rossi' -> \`["Mario Rossi"]\`
          - 'Anna Bianchi, Luigi Verdi' -> \`["Anna Bianchi", "Luigi Verdi"]\`
          - 'Giovanna Costa e Marco Neri' -> \`["Giovanna Costa", "Marco Neri"]\`
          - 'Per il primo Paolo Rossi e per il secondo Maria Neri' -> \`["Paolo Rossi", "Maria Neri"]\`
          - 'Sono Paolo Frisoli e Francesco Virgolini' -> \`["Paolo Frisoli", "Francesco Virgolini"]\`
          - 'Luca Giambalvo, Michele Rinaldi' -> \`["Luca Giambalvo", "Michele Rinaldi"]\`
          - 'ecco i due : Paolo Frisoli e Francesco Virgolini' -> \`["Paolo Frisoli", "Francesco Virgolini"]\`
          Fai del tuo meglio per isolare e separare correttamente i nomi completi. L'intent DEVE rimanere COLLECT_BOOKING_DETAILS finché il codice JS non ha tutti i nomi necessari e li ha validati internamente (tramite validateBookingStateForConfirmation).
    - Usa CONFIRM_BOOKING_DETAILS ESCLUSIVAMENTE se il sistema JavaScript (tramite validateBookingStateForConfirmation) ha determinato che TUTTI i dati necessari (eventId, eventTitle, numeroPosti, e un array completo di nomi partecipanti VALIDI) sono stati raccolti e sono corretti in currentBookingState, E l'assistente ha presentato il riepilogo e l'utente ha risposto affermativamente (es. "sì", "conferma", "procedi"). Non anticipare questo intent.
2.  "params": Oggetto JSON con parametri ESTRATTI DALL'ULTIMO INPUT UTENTE.
3.  "php_script": Script PHP da chiamare (se applicabile). Valori: "get_events.php", "get_event_details.php", "prenota_evento.php", "api/api_get_user_profile.php", "api/api_get_user_bookings.php", "none".
4.  "requires_login": true/false.
5.  "missing_info_prompt": Se mancano info ESSENZIALI per l'intent (specialmente per COLLECT_BOOKING_DETAILS o se START_BOOKING_FLOW non ha hint evento E currentBookingState.eventId non è noto), una frase SPECIFICA per richiederle. Altrimenti null.
    - Per prenotazioni, i dati essenziali: eventId, eventTitle, numeroPosti (1-5, rispettando i limiti utente/evento), partecipanti (array "Nome Cognome").
6.  "is_clarification_needed": true/false.

Considera la cronologia. Se un evento (es. ID 84) è stato appena discusso e l'utente vuole prenotare (es. "per 1"), popola "params.event_id": 84, "params.event_name_hint": "titolo evento se noto", e "params.numeroPosti": 1.
Rispondi ESCLUSIVAMENTE in formato JSON.
    `.trim();

    const MAIN_ASSISTANT_SYSTEM_PROMPT = () => `
Sei un assistente virtuale avanzato per "Eremo Frate Francesco". Data e ora correnti: ${new Date().toISOString()}. Utente loggato: ${IS_USER_LOGGED_IN} (Email: ${CURRENT_USER_EMAIL || 'N/D'}). Stato prenotazione (se rilevante): ${JSON.stringify(currentBookingState)}.
Il tuo scopo è ESEGUIRE AZIONI e fornire informazioni. Il sistema JavaScript (JS) aggiorna currentBookingState. Tu guidi l'utente e fai domande basate su currentBookingState e sulle informazioni mancanti identificate dal JS.

INTERAZIONE CON DATI PHP (RUOLO "system"):
- Se ricevi una lista (es. eventi), presentala in Markdown (liste numerate o bullet). Se molti, riassumi e chiedi dettagli. Formato evento: \`1. Nome Evento (ID: xxx) - Data: YYYY-MM-DD\`.
- Se l'utente chiede di un evento per nome e il JS ti passa una lista di corrispondenze, presenta la lista e chiedi di specificare l'ID.

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
        Chiedi: "Perfetto. Adesso avrei bisogno del NOME e COGNOME completo per ${currentBookingState.numeroPosti > 1 ? ('i restanti ' + (currentBookingState.numeroPosti - currentBookingState.partecipanti.length) + ' partecipante' + ((currentBookingState.numeroPosti - currentBookingState.partecipanti.length > 1) ? 'i' : '')) : 'il partecipante'}. Assicurati di fornire NOME e COGNOME.${currentBookingState.partecipanti.length > 0 ? (' Finora ho registrato: ' + currentBookingState.partecipanti.join(', ') + '.') : ''}"
    - Se il JS (tramite messaggio "system" o perché validateBookingStateForConfirmation fallisce indicando un nome non completo) rileva un nome incompleto: "Per favore, fornisci sia il NOME che il COGNOME per [nome incompleto o posizione del partecipante, es. 'il primo partecipante']."
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
- Se una domanda esula, indica che non puoi assistere. Rispondi in italiano.
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

        if (type === 'html') messageDiv.innerHTML = text;
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
        if (!eventId || !userEmail) {
            console.warn("fetchEventDetailsAndUserBookingStatus: eventId o userEmail mancanti.");
            return null;
        }
        try {
            // Assumiamo che get_event_details.php sia modificato per restituire
            // anche posti_gia_prenotati_utente e posti_disponibili_evento se user_email è fornito.
            // Se non è possibile, get_events.php potrebbe essere usato con un event_id_specific filter.
            const eventDetailsResult = await callPhpScript("get_event_details.php", {
                id: eventId,
                user_email_for_script: userEmail // Invia l'email per permettere al PHP di calcolare i posti
            });

            if (eventDetailsResult.success && eventDetailsResult.data && eventDetailsResult.data.details) {
                currentBookingState.eventTitle = eventDetailsResult.data.details.Titolo;
                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventDetailsResult.data.details.posti_gia_prenotati_utente, 10) || 0;
                currentBookingState.postiDisponibiliEvento = parseInt(eventDetailsResult.data.details.PostiDisponibili, 10); // o PostiRimasti a seconda del PHP
                console.log("Dettagli evento e stato prenotazione utente recuperati:", currentBookingState);
                return true;
            } else {
                console.warn("Dettagli evento non trovati o formato risposta inatteso da get_event_details.php per eventId:", eventId, eventDetailsResult);
                // Prova con get_events.php come fallback se get_event_details non è stato modificato
                const eventsResult = await callPhpScript("get_events.php", {
                    event_id_specific: eventId, // get_events.php deve supportare questo filtro
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
                currentBookingState.postiGiaPrenotatiUtente = 0; // Fallback sicuro
                currentBookingState.postiDisponibiliEvento = 0; // Fallback sicuro
                return false; // Dettagli non trovati
            }
        } catch (e) {
            console.error("Errore in fetchEventDetailsAndUserBookingStatus:", e);
            currentBookingState.postiGiaPrenotatiUtente = 0; // Fallback sicuro in caso di errore
            currentBookingState.postiDisponibiliEvento = 0;
            return false;
        }
    }


    async function handleSendMessageToAI() {
        checkUserLoginStatus();
        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            const keyReady = await fetchAndPrepareAssistantApiKey();
            if (!keyReady) { addMessageToChatUI('ai', "L'assistente non è configurato."); return; }
        }

        const userInput = aiChatInputEl.value.trim();
        if (!userInput) return;

        addMessageToChatUI('user', userInput);
        chatHistoryForAssistant.push({ role: "user", content: userInput });
        aiChatInputEl.value = ''; aiChatInputEl.disabled = true; aiChatSendBtnEl.disabled = true; aiChatInputEl.style.height = 'auto';

        const thinkingMessageDiv = addMessageToChatUI('ai', "Sto elaborando la tua richiesta...");

        try {
            const intentHistory = chatHistoryForAssistant.slice(-6);
            let intentResponseJson = await getGroqCompletion(intentHistory, INTENT_ANALYSIS_SYSTEM_PROMPT(), 0.2, 700); // Aumentato token per sicurezza
            let parsedIntent;
            try {
                parsedIntent = JSON.parse(intentResponseJson);
                console.log("Intent Analysis:", parsedIntent);
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis result: ${JSON.stringify(parsedIntent)}` });
            } catch (e) {
                console.error("Errore parsing JSON dell'intento:", e, "\nRisposta LLM:\n", intentResponseJson);
                parsedIntent = { intent: "GENERAL_QUERY", php_script: "none", params: {}, requires_login: false, missing_info_prompt: "Non ho compreso bene. Puoi riformulare?", is_clarification_needed: true };
                chatHistoryForAssistant.push({ role: "system", content: `Intent analysis fallback (parsing error): ${JSON.stringify(parsedIntent)}` });
            }

            if (parsedIntent.requires_login && !IS_USER_LOGGED_IN) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                const loginMessage = "Per questa azione è necessario l'accesso. Puoi accedere/registrarti dal menu.";
                addMessageToChatUI('ai', loginMessage);
                chatHistoryForAssistant.push({ role: "assistant", content: loginMessage });
                finalizeUIAfterResponse(); resetBookingState(); return;
            }

            if (parsedIntent.intent === "START_BOOKING_FLOW") {
                if (!currentBookingState.isActive || (parsedIntent.params?.event_id && parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId) || parsedIntent.params?.event_name_hint) {
                    resetBookingState();
                }
                currentBookingState.isActive = true;
                if (parsedIntent.params?.event_id) currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10);
                if (parsedIntent.params?.event_name_hint) currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;
                if (parsedIntent.params?.numeroPosti) currentBookingState.numeroPosti = parseInt(parsedIntent.params.numeroPosti, 10);

                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                let initialBookingPrompt = parsedIntent.missing_info_prompt || "Certo, iniziamo la prenotazione!";

                // Tentativo di recuperare dettagli evento e stato prenotazione utente
                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined') {
                    const detailsFetched = await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    if (!detailsFetched && !currentBookingState.eventTitle) { // Se fetch fallisce e non abbiamo neanche un titolo dall'hint
                        initialBookingPrompt = `Non sono riuscito a trovare i dettagli per l'evento ID ${currentBookingState.eventId}. Potresti verificare?`;
                        resetBookingState();
                        addMessageToChatUI('ai', initialBookingPrompt);
                        chatHistoryForAssistant.push({ role: "assistant", content: initialBookingPrompt });
                        finalizeUIAfterResponse(); return;
                    }
                }

                // Controllo limite posti DOPO aver recuperato postiGiaPrenotatiUtente
                if (currentBookingState.eventId && IS_USER_LOGGED_IN && currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                    const limitReachedMsg = `Ho verificato e risulta che hai già prenotato ${currentBookingState.postiGiaPrenotatiUtente} posti per l'evento "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", raggiungendo il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}. Non è possibile aggiungere altri posti.`;
                    addMessageToChatUI('ai', limitReachedMsg);
                    chatHistoryForAssistant.push({ role: "assistant", content: limitReachedMsg });
                    resetBookingState(); finalizeUIAfterResponse(); return;
                }

                // Costruzione del prompt iniziale basato sui dati disponibili
                if (currentBookingState.eventId && currentBookingState.eventTitle) {
                    if (currentBookingState.numeroPosti) {
                        const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                        const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);
                        if (currentBookingState.numeroPosti > maxPerQuestaPrenotazione) {
                            initialBookingPrompt = `Per l'evento "${currentBookingState.eventTitle}", puoi prenotare al massimo ${maxPerQuestaPrenotazione} posti in questa richiesta. Vuoi procedere con ${maxPerQuestaPrenotazione} posti o un numero inferiore?`;
                            currentBookingState.numeroPosti = null; // Forza la richiesta del numero corretto
                        } else {
                            initialBookingPrompt = `Ok, per l'evento "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Hai richiesto per ${currentBookingState.numeroPosti} persona/e. Ora avrei bisogno del NOME e COGNOME completo per ${currentBookingState.numeroPosti > 1 ? 'ciascuno dei' : 'il'} ${currentBookingState.numeroPosti} partecipante/i.`;
                        }
                    } else {
                        const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                        const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);
                        initialBookingPrompt = `Ok, procediamo con la prenotazione per l'evento "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). ${ (currentBookingState.postiGiaPrenotatiUtente > 0) ? `Ne hai già prenotati ${currentBookingState.postiGiaPrenotatiUtente}. ` : '' }Per quante persone (da 1 a ${maxPerQuestaPrenotazione})?`;
                    }
                } else if (currentBookingState.eventNameHint && !currentBookingState.eventId) {
                    try {
                        const eventsResult = await callPhpScript("get_events.php", { search_term: currentBookingState.eventNameHint, period: "all_future", user_email_for_script: CURRENT_USER_EMAIL }); // Aggiunto user_email
                        chatHistoryForAssistant.push({ role: "system", content: `Ricerca eventi per "${currentBookingState.eventNameHint}": ${JSON.stringify(eventsResult)}` });
                        if (eventsResult.success && eventsResult.data && eventsResult.data.length > 0) {
                            if (eventsResult.data.length === 1) {
                                const eventInfo = eventsResult.data[0];
                                currentBookingState.eventId = eventInfo.idevento;
                                currentBookingState.eventTitle = eventInfo.titolo;
                                currentBookingState.postiGiaPrenotatiUtente = parseInt(eventInfo.posti_gia_prenotati_utente, 10) || 0;
                                currentBookingState.postiDisponibiliEvento = parseInt(eventInfo.posti_disponibili, 10);

                                if (currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                                    initialBookingPrompt = `Ho trovato l'evento: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Tuttavia, hai già ${currentBookingState.postiGiaPrenotatiUtente} posti prenotati, raggiungendo il limite di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}.`;
                                    resetBookingState();
                                } else {
                                    const postiAncoraPrenotabili = MAX_TOTAL_SEATS_PER_USER_PER_EVENT - (currentBookingState.postiGiaPrenotatiUtente || 0);
                                    const maxPerQuestaPrenotazione = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, postiAncoraPrenotabili);
                                    if (currentBookingState.numeroPosti) {
                                        initialBookingPrompt = `Ho trovato: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). Richiesti ${currentBookingState.numeroPosti} posti. Ora NOME e COGNOME per tutti.`;
                                    } else {
                                        initialBookingPrompt = `Ho trovato: "${currentBookingState.eventTitle}" (ID: ${currentBookingState.eventId}). ${ (currentBookingState.postiGiaPrenotatiUtente > 0) ? `Ne hai già ${currentBookingState.postiGiaPrenotatiUtente}. ` : '' }Per quante persone (1-${maxPerQuestaPrenotazione})?`;
                                    }
                                }
                            } else { /* Lista eventi... */ } // Logica per presentare lista omessa per brevità
                        } else { initialBookingPrompt = `Non ho trovato eventi per "${currentBookingState.eventNameHint}". Riprova con nome/ID.`; resetBookingState(); }
                    } catch (phpError) { initialBookingPrompt = `Errore ricerca evento. Fornisci ID.`; console.error(phpError); resetBookingState(); }
                } else if (!currentBookingState.eventId && !currentBookingState.eventNameHint) {
                    initialBookingPrompt = "A quale evento sei interessato/a? Fornisci nome o ID.";
                }

                addMessageToChatUI('ai', initialBookingPrompt);
                chatHistoryForAssistant.push({ role: "assistant", content: initialBookingPrompt });
                finalizeUIAfterResponse(); return;
            }

            if (parsedIntent.intent === "COLLECT_BOOKING_DETAILS" && currentBookingState.isActive) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();

                if (parsedIntent.params?.event_id && (!currentBookingState.eventId || parseInt(parsedIntent.params.event_id, 10) !== currentBookingState.eventId)) {
                    currentBookingState.eventId = parseInt(parsedIntent.params.event_id, 10); currentBookingState.eventTitle = null; currentBookingState.postiGiaPrenotatiUtente = undefined; // Resetta per ricaricare
                }
                if (parsedIntent.params?.event_name_hint && !currentBookingState.eventId) currentBookingState.eventNameHint = parsedIntent.params.event_name_hint;
                if (parsedIntent.params?.numeroPosti) currentBookingState.numeroPosti = parseInt(parsedIntent.params.numeroPosti, 10);

                if (parsedIntent.params?.partecipanti_nomi_cognomi) {
                    let newNamesInput = parsedIntent.params.partecipanti_nomi_cognomi;
                    let processedNewNames = [];
                    if (Array.isArray(newNamesInput)) {
                        newNamesInput.forEach(nameStr => {
                            if (typeof nameStr === 'string' && nameStr.trim() !== "") {
                                nameStr.split(/ e /i).forEach(namePart => {
                                    if (namePart.trim()) {
                                        const cleanedName = namePart.replace(/^(ecco i due|ecco i nomi|sono|per il partecipante)\s*:\s*/i, '').trim();
                                        if (cleanedName) processedNewNames.push(cleanedName);
                                    }
                                });
                            }
                        });
                    } else if (typeof newNamesInput === 'string' && newNamesInput.trim() !== "") {
                        let singleStringInput = newNamesInput.replace(/^(ecco i due|ecco i nomi|sono|per il partecipante)\s*:\s*/i, '').trim();
                        const potentialNames = singleStringInput.split(/,| e /i);
                        potentialNames.forEach(name => {
                            if (name.trim() !== "") {
                                processedNewNames.push(name.trim());
                            }
                        });
                    }
                    processedNewNames = processedNewNames.filter(name => name.length > 0 && name.trim().split(' ').length >= 1);
                    if (processedNewNames.length > 0) {
                        // Se l'utente sta fornendo i nomi per la prima volta O sta correggendo/ri-fornendo,
                        // è più sicuro sovrascrivere o gestire l'aggiunta in modo più intelligente.
                        // Per ora, semplice concatenazione e unicità, ma potrebbe essere migliorato.
                        if(currentBookingState.partecipanti.length < (currentBookingState.numeroPosti || 0) ) {
                            const combined = currentBookingState.partecipanti.concat(processedNewNames);
                            currentBookingState.partecipanti = [...new Set(combined)];
                        } else { // Se avevamo già il numero giusto di nomi, assumiamo che questi siano una correzione/sostituzione
                            currentBookingState.partecipanti = [...new Set(processedNewNames)];
                        }
                        // Assicura di non avere più partecipanti del numero di posti richiesto
                        if (currentBookingState.numeroPosti && currentBookingState.partecipanti.length > currentBookingState.numeroPosti) {
                            currentBookingState.partecipanti = currentBookingState.partecipanti.slice(0, currentBookingState.numeroPosti);
                        }
                        console.log("AssistenteAI: currentBookingState.partecipanti aggiornato a:", currentBookingState.partecipanti);
                    }
                }

                // Recupera dettagli evento e stato prenotazione se non ancora fatto (es. se ID evento è cambiato)
                if (currentBookingState.eventId && IS_USER_LOGGED_IN && (typeof currentBookingState.postiGiaPrenotatiUtente === 'undefined' || !currentBookingState.eventTitle)) {
                    await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                }

                // Controllo limite posti se rilevante
                if (currentBookingState.eventId && IS_USER_LOGGED_IN && typeof currentBookingState.postiGiaPrenotatiUtente !== 'undefined' && currentBookingState.postiGiaPrenotatiUtente >= MAX_TOTAL_SEATS_PER_USER_PER_EVENT) {
                    const limitReachedMsg = `Ho verificato e hai già ${currentBookingState.postiGiaPrenotatiUtente} posti per "${currentBookingState.eventTitle || 'ID ' + currentBookingState.eventId}", il limite massimo di ${MAX_TOTAL_SEATS_PER_USER_PER_EVENT}.`;
                    addMessageToChatUI('ai', limitReachedMsg); chatHistoryForAssistant.push({ role: "assistant", content: limitReachedMsg });
                    resetBookingState(); finalizeUIAfterResponse(); return;
                }

                let nextPrompt = "";
                const validation = validateBookingStateForConfirmation(currentBookingState);

                if (!currentBookingState.eventId || !currentBookingState.eventTitle) { // ID o titolo mancanti
                    nextPrompt = "Sembra ci sia stato un problema con la selezione dell'evento. A quale evento eri interessato/a?";
                } else if (!validation.isValid) {
                    nextPrompt = validation.missingInfo;
                } else { // Tutti i dati sono validi e raccolti! Proponi la conferma.
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
                    let errorMsg = "Attenzione, non posso procedere. " + validation.missingInfo;
                    addMessageToChatUI('ai', errorMsg);
                    chatHistoryForAssistant.push({ role: "system", content: `Validation failed before PHP call: ${validation.missingInfo}. Asking user.` });
                    chatHistoryForAssistant.push({ role: "assistant", content: errorMsg });
                    finalizeUIAfterResponse(); return;
                }
                parsedIntent.php_script = "prenota_evento.php";
                parsedIntent.params = {
                    eventId: currentBookingState.eventId,
                    numeroPosti: currentBookingState.numeroPosti,
                    contatto: CURRENT_USER_EMAIL,
                    partecipanti_nomi: currentBookingState.partecipanti.map(p => p.substring(0, p.lastIndexOf(' ') > 0 ? p.lastIndexOf(' ') : p.length).trim()),
                    partecipanti_cognomi: currentBookingState.partecipanti.map(p => { const lastSpace = p.lastIndexOf(' '); return (lastSpace === -1 || lastSpace === p.length - 1) ? "_" : p.substring(lastSpace + 1).trim(); }),
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

                try {
                    const phpResult = await callPhpScript(scriptPath, scriptParams, method);
                    chatHistoryForAssistant.push({ role: "system", content: `Risultato PHP (${scriptPath}): ${JSON.stringify(phpResult)}` });
                    console.log("PHP Result:", phpResult);

                    if (parsedIntent.intent === "CONFIRM_BOOKING_DETAILS") {
                        if (thinkingMessageDiv) thinkingMessageDiv.remove();
                        const finalMessage = phpResult.message || (phpResult.success ? "Prenotazione confermata con successo!" : "Si è verificato un errore durante la prenotazione.");
                        addMessageToChatUI('ai', finalMessage);
                        chatHistoryForAssistant.push({ role: "assistant", content: finalMessage });
                        if (phpResult.success) resetBookingState();
                        finalizeUIAfterResponse(); return;
                    }
                    // Se la chiamata PHP era per get_events o get_event_details (non per una prenotazione finale)
                    // la risposta dell'AI per presentare questi dati avverrà nella fase successiva
                } catch (phpError) {
                    console.error("Errore PHP:", phpError);
                    if (thinkingMessageDiv) thinkingMessageDiv.remove();
                    addMessageToChatUI('ai', `Spiacente, errore tecnico: ${phpError.message}`);
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
            const bookingFlowIntents = ["START_BOOKING_FLOW", "COLLECT_BOOKING_DETAILS"];
            // Solo se non è un'azione di booking che ha già prodotto un output specifico o se è una query generale.
            if (!bookingFlowIntents.includes(parsedIntent.intent) ||
                (bookingFlowIntents.includes(parsedIntent.intent) &&
                    (!parsedIntent.php_script || parsedIntent.php_script === "none") &&
                    !parsedIntent.missing_info_prompt &&
                    chatHistoryForAssistant[chatHistoryForAssistant.length -1]?.role !== 'assistant') // Evita doppie risposte
            ) {

                const finalResponseHistory = chatHistoryForAssistant.slice(-10);
                const aiFinalResponse = await getGroqCompletion(finalResponseHistory, MAIN_ASSISTANT_SYSTEM_PROMPT(), 0.5, 1500);

                if (aiFinalResponse) {
                    addMessageToChatUI('ai', aiFinalResponse);
                    chatHistoryForAssistant.push({ role: "assistant", content: aiFinalResponse });
                } else {
                    if (parsedIntent.intent === "GENERAL_QUERY" || parsedIntent.intent === "UNKNOWN") {
                        addMessageToChatUI('ai', "Non ho trovato una risposta. Riformula?");
                        chatHistoryForAssistant.push({ role: "assistant", content: "LLM response for general query was empty." });
                    }
                }
            }
        } catch (error) {
            console.error("Errore in handleSendMessageToAI:", error);
            if (thinkingMessageDiv) thinkingMessageDiv.remove();
            addMessageToChatUI('ai', `Spiacente, errore generale: ${error.message}. Riprova.`);
            chatHistoryForAssistant.push({ role: "assistant", content: `General Error: ${error.message}` });
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
            chatHistoryForAssistant = [chatHistoryForAssistant[0], ...chatHistoryForAssistant.slice(chatHistoryForAssistant.length - 29)];
        }
    }

    console.log("AssistenteAI: Script in esecuzione (v3).");
    checkUserLoginStatus();
    initializeChatHistory();

    fetchAndPrepareAssistantApiKey().then(keyReady => {
        if (keyReady) {
            if (aiAssistantFabEl) aiAssistantFabEl.addEventListener('click', toggleAiChat);
            if (aiChatCloseBtnEl) aiChatCloseBtnEl.addEventListener('click', toggleAiChat);
            if (aiChatSendBtnEl) aiChatSendBtnEl.addEventListener('click', handleSendMessageToAI);
            if (aiChatInputEl) {
                aiChatInputEl.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessageToAI(); }
                });
                aiChatInputEl.addEventListener('input', function () {
                    this.style.height = 'auto'; let newHeight = this.scrollHeight;
                    if (newHeight > 90) newHeight = 90;
                    this.style.height = newHeight + 'px';
                });
            }
            console.log("AssistenteAI: Event listeners attaccati.");
        } else {
            console.error("AssistenteAI: Inizializzazione fallita, API key non caricata.");
        }
    }).catch(error => {
        console.error("AssistenteAI: Errore critico durante fetchAndPrepareAssistantApiKey:", error);
    });
});