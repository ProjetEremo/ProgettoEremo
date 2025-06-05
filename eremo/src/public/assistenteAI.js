// File: assistenteAI.js (Versione Rivoluzionata e Corretta v12 - Correzione Invio Dati Prenotazione)
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

    const TTS_OPTIMIZER_SYSTEM_PROMPT = () => `
Sei un MODELLO DI TRASFORMAZIONE TESTUALE, non un assistente conversazionale. Il tuo unico scopo è ottimizzare il testo fornito per la sintesi vocale (TTS) in italiano. Non devi rispondere al contenuto del testo, ma solo riformularlo.
Il tuo compito è trasformare il testo fornito (che è una risposta generata da un'altra AI per una chat testuale) in una versione che suoni il più naturale, fluida e piacevole possibile quando letta ad alta voce da un sistema TTS.

REGOLE FONDAMENTALI:
1.  **Mantieni il Significato Originale**: L'essenza e tutte le informazioni cruciali del messaggio originale DEVONO essere preservate. NON devi aggiungere opinioni, risposte o informazioni non presenti nel testo originale.
2.  **Naturalezza e Fluidità**: Riscrivi le frasi per un eloquio colloquiale. Evita strutture complesse o un linguaggio robotico. Preferisci frasi brevi e dirette se possibile, ma mantieni un tono conversazionale.
3.  **Pulizia per il Parlato**:
    * **Markdown e HTML**: Rimuovi OGNI traccia di Markdown (es. \`**\`, \`*\`, \`-\` per liste, \`\`\` \`\`\`) o tag HTML. L'output deve essere puro testo.
    * **ID, Codici, URL**: Evita di leggere ID alfanumerici (es. "ID: xyz123"), codici tecnici o URL completi. Se l'informazione è essenziale, parafrasala (es. "l'evento con codice identificativo alfa-beta-uno" o "puoi trovare i dettagli sul nostro sito"). Spesso, se il nome dell'oggetto è chiaro, l'ID può essere omesso nel parlato.
    * **Liste**: Trasforma liste puntate o numerate in frasi discorsive. Ad esempio, invece di "Eventi: 1. Yoga - 08:00 2. Meditazione - 09:00", potresti dire: "Ci sono due eventi: lo Yoga alle otto e la Meditazione alle nove."
    * **Parentesi e Simboli**: Riformula o integra le informazioni in parentesi nel testo principale. Evita simboli come '#', '&', '%' a meno che non facciano parte integrante di un'espressione comune o nome proprio che si pronuncia bene.
    * **Abbreviazioni**: Sciogli le abbreviazioni comuni se possono suonare male (es. "per es." diventa "per esempio").
4.  **Numeri e Date**: Esprimili in modo naturale (es. "il quindici luglio duemilaventicinque" invece di "15-07-2025", "per cinque persone").
5.  **Output Diretto**: Fornisci ESCLUSIVAMENTE il testo ottimizzato per il parlato. Non includere commenti, saluti, spiegazioni del tuo processo di trasformazione, o qualsiasi testo che implichi che tu sia un assistente che sta 'rispondendo'. Il tuo output è SOLO il testo da far leggere al TTS.
6.  **Non Interagire**: NON devi interpretare il testo in input come una domanda, un'istruzione per te, o un'affermazione a cui rispondere. Il tuo compito è puramente di editing e riformulazione stilistica per la voce. Ignora il significato conversazionale del testo e concentrati solo sulla sua leggibilità ad alta voce.

Esempio Input (testo dalla chat AI):
"Ecco gli eventi per te:
- **Meditazione Sonora** (ID: EVT001) - Data: 2025-09-10, Ore: 18:00. Posti: 5. Dettagli: [link](https://example.com/evt001)
- **Pellegrinaggio Silenzioso** (ID: EVT002) - Data: 2025-09-15, Ore: 06:00. Posti: 10. Dettagli: [link](https://example.com/evt002)"

Esempio Output Ottimizzato (solo il testo da leggere):
"Ho trovato alcuni eventi per te. C'è la Meditazione Sonora il dieci settembre alle diciotto, con cinque posti disponibili. Poi c'è il Pellegrinaggio Silenzioso il quindici settembre alle sei del mattino, per cui ci sono dieci posti. Puoi trovare maggiori dettagli sul sito."

Fornisci solo il testo finale, pronto per essere letto.
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
        const assistantMessageContent = `Ciao! Sono l'assistente virtuale dell'Eremo${userNamePart}. Come posso aiutarti oggi riguardo il sito?`;
        const systemMessageContent = `Data e ora correnti: ${new Date().toISOString()}${(CURRENT_USER_EMAIL ? `. Utente loggato: ${CURRENT_USER_EMAIL}` : ". Nessun utente loggato.")}`;

        chatHistoryForAssistant = [
            { role: "system", content: systemMessageContent },
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
            if (chatHistoryForAssistant.length <= 1) {
                initializeChatHistory();
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

        if (isTTSEnabled && (sender === 'ai' || sender === 'assistant') && aiChatPopupEl.classList.contains('active')) {
            if (isInitialAssistantMessage) {
                speakText(text);
            }
        }
        return messageDiv;
    }

    async function processAndDisplayAiResponse(chatResponseText, isThinkingMessage = false) {
        const messageDiv = addMessageToChatUI('ai', chatResponseText, 'html');

        if (!isThinkingMessage) {
            chatHistoryForAssistant.push({ role: "assistant", content: chatResponseText });
        }

        if (isTTSEnabled && aiChatPopupEl.classList.contains('active') && !isThinkingMessage) {
            let textToSpeak = chatResponseText;

            try {
                const ttsOptimizerMessages = [{ role: "user", content: chatResponseText }];
                console.log("AssistenteAI: Richiesta ottimizzazione TTS per:", chatResponseText.substring(0,100)+"...");
                const spokenResponseCandidate = await getGroqCompletion(
                    ttsOptimizerMessages,
                    TTS_OPTIMIZER_SYSTEM_PROMPT(),
                    GROQ_MODEL_NAME_TTS_OPTIMIZER,
                    0.2,
                    1000
                );

                if (spokenResponseCandidate && spokenResponseCandidate.trim() !== "") {
                    textToSpeak = spokenResponseCandidate.trim();
                    console.log("AssistenteAI: Testo ottimizzato per TTS:", textToSpeak.substring(0,100)+"...");
                } else {
                    console.warn("AssistenteAI: Ottimizzazione TTS non ha prodotto output valido, uso testo originale ripulito.");
                }
            } catch (error) {
                console.error("AssistenteAI: Errore durante l'ottimizzazione TTS:", error);
            }
            speakText(textToSpeak);
        }
        return messageDiv;
    }


    async function callPhpScript(scriptPath, params = {}, method = 'GET') {
        let url = scriptPath;
        const options = { method };

        // Aggiungi user_email_for_script se disponibile e lo script lo richiede (per GET e POST)
        if (CURRENT_USER_EMAIL && (scriptPath.includes('get_events.php') || scriptPath.includes('get_event_details.php'))) {
            if (!params.user_email_for_script) { // Evita di sovrascrivere se già presente
                if (method === 'GET') {
                    // Per GET, aggiungilo ai parametri URL se non esiste già
                    const tempParams = new URLSearchParams(params);
                    if (!tempParams.has('user_email_for_script')) {
                        tempParams.set('user_email_for_script', CURRENT_USER_EMAIL);
                    }
                    // Ricostruisci i params per GET o l'URL
                    // Questa parte è complessa se params è già un oggetto.
                    // È più semplice se lo script PHP si aspetta user_email come parte dei parametri normali.
                    // Per ora, assumiamo che se params è un oggetto, user_email_for_script venga aggiunto lì.
                    params.user_email_for_script = CURRENT_USER_EMAIL;

                } else if (method === 'POST') {
                    // Per POST, assicurati che sia nei parametri che verranno messi nel FormData
                    // Se params è l'oggetto che va in FormData, basta aggiungerlo.
                    params.user_email_for_script = CURRENT_USER_EMAIL;
                }
            }
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
            chatHistoryForAssistant.push({ role: "system", content: `Errore da ${scriptPath}: ${error.message}` });
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
                ...messages
            ],
            model: modelName,
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
        if (!userEmail && currentBookingState.isActive) {
            console.warn("fetchEventDetailsAndUserBookingStatus: userEmail mancante per un flusso di prenotazione attivo.");
            return false;
        }
        try {
            const scriptParams = { id: eventId };
            if (userEmail) {
                scriptParams.user_email_for_script = userEmail;
            }
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
                if (userEmail) {
                    fallbackParams.user_email_for_script = userEmail;
                }
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
            return false;
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
                chatHistoryForAssistant.slice(-5),
                INTENT_ANALYSIS_SYSTEM_PROMPT(),
                GROQ_MODEL_NAME_ASSISTANT,
                0.1,
                500
            );

            let intentAnalysisParsed;
            try {
                intentAnalysisParsed = JSON.parse(intentAnalysisRaw);
            } catch (e) {
                console.error("AssistenteAI: Errore parsing intent analysis JSON:", e, intentAnalysisRaw);
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                await processAndDisplayAiResponse("C'è stato un problema nell'interpretare la tua richiesta. Potresti riformulare?");
                return;
            }
            console.log("AssistenteAI: Intent Analysis:", intentAnalysisParsed);

            if (intentAnalysisParsed.requires_login && !IS_USER_LOGGED_IN) {
                if (thinkingMessageDiv) thinkingMessageDiv.remove();
                await processAndDisplayAiResponse("Per questa operazione è necessario effettuare il login. Accedi al sito e riprova.");
                if (currentBookingState.isActive) resetBookingState();
                return;
            }

            let mainAiResponseContent;
            let systemMessageForMainAI = null;

            switch (intentAnalysisParsed.intent) {
                case "GET_EVENTS":
                    try {
                        const phpParams = intentAnalysisParsed.params || {};
                        // Non è necessario aggiungere user_email_for_script qui, callPhpScript lo gestirà
                        const eventsData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                        systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(eventsData)}`;
                    } catch (error) {
                        systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                    }
                    break;

                case "GET_EVENT_DETAILS":
                    try {
                        const phpParams = intentAnalysisParsed.params || {};
                        // Non è necessario aggiungere user_email_for_script qui, callPhpScript lo gestirà
                        const eventDetailsData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                        systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(eventDetailsData)}`;
                    } catch (error) {
                        systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                    }
                    break;

                case "START_BOOKING_FLOW":
                    resetBookingState();
                    currentBookingState.isActive = true;
                    if (intentAnalysisParsed.params.event_id) {
                        currentBookingState.eventId = intentAnalysisParsed.params.event_id;
                        await fetchEventDetailsAndUserBookingStatus(currentBookingState.eventId, CURRENT_USER_EMAIL);
                    } else if (intentAnalysisParsed.params.event_name_hint) {
                        currentBookingState.eventNameHint = intentAnalysisParsed.params.event_name_hint;
                    }
                    break;

                case "COLLECT_BOOKING_DETAILS":
                    if (!currentBookingState.isActive || !currentBookingState.eventId) {
                        systemMessageForMainAI = "L'utente sta cercando di fornire dettagli di prenotazione, ma il flusso non è attivo o manca l'ID evento. Chiedi di iniziare specificando un evento.";
                        resetBookingState();
                    } else {
                        if (intentAnalysisParsed.params.numeroPosti) {
                            const numPostiRichiesti = parseInt(intentAnalysisParsed.params.numeroPosti, 10);
                            const postiGiaPrenotati = currentBookingState.postiGiaPrenotatiUtente || 0;
                            const maxPrenotabiliOra = Math.min(MAX_SEATS_PER_SINGLE_BOOKING_REQUEST, MAX_TOTAL_SEATS_PER_USER_PER_EVENT - postiGiaPrenotati);

                            if (numPostiRichiesti > 0 && numPostiRichiesti <= maxPrenotabiliOra) {
                                currentBookingState.numeroPosti = numPostiRichiesti;
                            } else {
                                systemMessageForMainAI = `L'utente ha richiesto ${numPostiRichiesti} posti, ma il massimo consentito è ${maxPrenotabiliOra}. Informalo.`;
                                currentBookingState.numeroPosti = null;
                            }
                        }
                        if (intentAnalysisParsed.params.partecipanti_nomi_cognomi && Array.isArray(intentAnalysisParsed.params.partecipanti_nomi_cognomi) && currentBookingState.numeroPosti) {
                            const nomiDaAggiungere = intentAnalysisParsed.params.partecipanti_nomi_cognomi;
                            for (const nome of nomiDaAggiungere) {
                                if (currentBookingState.partecipanti.length < currentBookingState.numeroPosti) {
                                    if (typeof nome === 'string' && nome.trim().split(' ').filter(Boolean).length >= 2) {
                                        currentBookingState.partecipanti.push(nome.trim());
                                    } else {
                                        systemMessageForMainAI = (systemMessageForMainAI || "") + ` Il nome '${nome}' non sembra completo (Nome Cognome). Richiedilo corretto.`;
                                    }
                                } else {
                                    break;
                                }
                            }
                        }
                    }
                    break;

                case "CONFIRM_BOOKING_DETAILS":
                    if (!currentBookingState.isActive) {
                        systemMessageForMainAI = "L'utente vuole confermare, ma non c'è una prenotazione attiva.";
                        break;
                    }
                    const validation = validateBookingStateForConfirmation(currentBookingState);
                    if (validation.isValid) {
                        try {
                            // Prepara i dati per prenota_evento.php
                            const nomiPartecipanti = [];
                            const cognomiPartecipanti = [];
                            currentBookingState.partecipanti.forEach(partecipante => {
                                const parts = partecipante.trim().split(' ');
                                nomiPartecipanti.push(parts.shift()); // Primo elemento come nome
                                cognomiPartecipanti.push(parts.join(' ')); // Il resto come cognome
                            });

                            const bookingParams = {
                                eventId: currentBookingState.eventId, // Nome corretto per PHP
                                numeroPosti: currentBookingState.numeroPosti, // Nome corretto per PHP
                                contatto: CURRENT_USER_EMAIL, // Nome corretto per PHP (email dell'utente)
                                partecipanti_nomi: nomiPartecipanti, // Array di nomi
                                partecipanti_cognomi: cognomiPartecipanti, // Array di cognomi
                                // richieste_speciali: currentBookingState.richiesteSpeciali || '' // Non sembra essere usato da prenota_evento.php
                            };
                            // Aggiungi richieste speciali se presente e gestita da PHP (attualmente non lo è)
                            // if (currentBookingState.richiesteSpeciali) {
                            //     bookingParams.richieste_speciali = currentBookingState.richiesteSpeciali;
                            // }

                            console.log("AssistenteAI: Invio parametri a prenota_evento.php:", bookingParams);
                            const bookingResult = await callPhpScript("prenota_evento.php", bookingParams, 'POST');
                            systemMessageForMainAI = `Risultato prenotazione: ${JSON.stringify(bookingResult)}`;
                            if (bookingResult.success) {
                                resetBookingState();
                            }
                        } catch (error) {
                            // L'errore da callPhpScript è già stato loggato e aggiunto alla cronologia system
                            // systemMessageForMainAI non è necessario qui perché l'errore è già nel system message
                            // systemMessageForMainAI = `Errore durante la prenotazione: ${error.message}`;
                            // Il messaggio di errore specifico da PHP verrà mostrato all'utente dall'AI principale
                        }
                    } else {
                        systemMessageForMainAI = `Tentativo di conferma fallito. Dati mancanti o non validi: ${validation.missingInfo}. Chiedi all'utente di correggere o fornire i dati. Stato attuale: ${JSON.stringify(currentBookingState)}`;
                    }
                    break;

                case "GET_USER_PROFILE":
                case "GET_USER_BOOKINGS":
                    try {
                        const phpParams = intentAnalysisParsed.params || {};
                        if (CURRENT_USER_EMAIL) phpParams.user_email_for_script = CURRENT_USER_EMAIL;
                        const userData = await callPhpScript(intentAnalysisParsed.php_script, phpParams);
                        systemMessageForMainAI = `Risultato da ${intentAnalysisParsed.php_script}: ${JSON.stringify(userData)}`;
                    } catch (error) {
                        systemMessageForMainAI = `Errore da ${intentAnalysisParsed.php_script}: ${error.message}`;
                    }
                    break;

                case "GENERAL_QUERY":
                case "UNKNOWN":
                default:
                    if (intentAnalysisParsed.missing_info_prompt) {
                        systemMessageForMainAI = `L'analisi dell'intento suggerisce di chiedere: ${intentAnalysisParsed.missing_info_prompt}`;
                    }
                    break;
            }

            const messagesForMainAI = [...chatHistoryForAssistant];
            if (systemMessageForMainAI) {
                messagesForMainAI.push({ role: "system", content: systemMessageForMainAI });
            }

            mainAiResponseContent = await getGroqCompletion(
                messagesForMainAI.slice(-10),
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
            // Il messaggio di errore specifico (se da PHP) è già stato aggiunto come system message
            // L'AI principale dovrebbe riceverlo e comunicarlo.
            // Qui possiamo mettere un messaggio generico se l'errore non è già stato gestito per la visualizzazione.
            // Se l'errore è già stato comunicato dall'AI, questo potrebbe essere ridondante.
            // await processAndDisplayAiResponse(`Si è verificato un errore: ${error.message}. Riprova più tardi.`);
            // Lasciamo che l'AI principale gestisca la visualizzazione dell'errore basandosi sul system message.
            // Se non c'è un system message specifico (es. errore API Groq), allora mostriamo questo.
            if (!chatHistoryForAssistant.find(msg => msg.role === "system" && msg.content.startsWith("Errore da"))) {
                await processAndDisplayAiResponse(`Si è verificato un errore: ${error.message}. Riprova più tardi.`);
            }
        } finally {
            aiChatInputEl.disabled = false;
            aiChatSendBtnEl.disabled = false;
            aiChatInputEl.focus();
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
            console.log("AssistenteAI: Pronto. La chat verrà inizializzata all'apertura.");
        } else {
            console.warn("AssistenteAI: Chiave API non caricata. L'assistente potrebbe non funzionare.");
        }
    });

});