// File: assistenteAI.js

document.addEventListener('DOMContentLoaded', () => {
    const aiAssistantFabEl = document.getElementById('ai-assistant-fab');
    const aiChatPopupEl = document.getElementById('ai-chat-popup');
    const aiChatCloseBtnEl = document.getElementById('ai-chat-close-btn');
    const aiChatMessagesContainerEl = document.getElementById('ai-chat-messages');
    const aiChatInputEl = document.getElementById('ai-chat-input');
    const aiChatSendBtnEl = document.getElementById('ai-chat-send-btn');

    // Verifica iniziale degli elementi DOM
    if (!aiAssistantFabEl || !aiChatPopupEl || !aiChatCloseBtnEl || !aiChatMessagesContainerEl || !aiChatInputEl || !aiChatSendBtnEl) {
        console.warn("AssistenteAI: Elementi UI non trovati. L'assistente potrebbe non essere inizializzato correttamente.");
        if(aiAssistantFabEl) aiAssistantFabEl.style.display = 'none'; // Nascondi il FAB se mancano componenti
        return;
    }

    let GROQ_API_KEY_FOR_ASSISTANT = null; // La chiave API verrà caricata e decifrata qui
    const GROQ_MODEL_NAME_ASSISTANT = "meta-llama/llama-4-scout-17b-16e-instruct"; // Modello consigliato per chat generiche

    const SYSTEM_PROMPT_FOR_ASSISTANT = `
Sei un assistente virtuale amichevole e disponibile per il sito web "Eremo Frate Francesco".
Il tuo scopo è aiutare gli utenti a capire come utilizzare il sito e trovare informazioni.
Basati ESCLUSIVAMENTE sulle seguenti informazioni estratte dal manuale utente del sito. Non inventare funzionalità o pagine che non sono descritte qui.
Rispondi in modo conciso e chiaro. Se una domanda esula da queste informazioni, indica gentilmente che puoi fornire aiuto solo per le funzionalità descritte.

Manuale Utente Eremo Frate Francesco (Sintesi per AI):

1. Accesso e Registrazione:
   - Per accedere: cliccare sul pulsante "Accedi" situato in alto a destra nella pagina.
   - Nuovi visitatori: Cliccare su "Registrati" (accessibile dal popup di login) per creare un nuovo account.
   - Utenti già registrati (inclusi Admin): Usare nome utente (email) e password scelti.
   - Recupero password: Se dimenticata, cliccare su "Password dimenticata?" nel popup di login e seguire le istruzioni inviate via email.
   - Schermata iniziale (Homepage): Mostra informazioni generali sull'associazione e un carosello di immagini.

2. Consultare gli Eventi:
   - Per vedere gli eventi: Navigare alla sezione "Calendario attività" dal menu principale.
   - Ricerca e Filtri: È possibile usare una barra di ricerca per parole chiave o applicare filtri avanzati (data, categoria, ecc.).
   - Dettagli Evento: Cliccando su un evento si visualizzano descrizione, orari, posti disponibili. Gli utenti registrati possono vedere anche commenti e foto.

3. Prenotare un Evento:
   - Selezione: Dalla pagina "Calendario attività", cliccare sull'evento desiderato.
   - Pulsante Prenota: Se disponibile, cliccare su "Prenota ora".
   - Dati Prenotazione: Inserire il numero di posti (massimo 4 per utente per evento) e, se richiesto, i nomi degli altri partecipanti. È possibile indicare richieste speciali.
   - Conferma: Dopo aver verificato i dati, cliccare "Conferma". Si riceverà un'email di conferma.
   - Area Personale: Le prenotazioni effettuate sono visibili nella propria Area Personale.

4. Annullare una Prenotazione:
   - Dall'Area Personale: Selezionare la prenotazione da annullare.
   - Cliccare su "Annulla" e confermare.
   - Termini: L'annullamento deve avvenire entro i termini specificati (es. 24 ore prima dell'evento).

5. Gestione Profilo Utente (Area Personale):
   - Accesso: Cliccare sull'icona del proprio profilo in alto a destra, poi su "Il mio profilo".
   - Modifiche Info: È possibile modificare nome, cognome. L'email solitamente non è modificabile.
   - Icona Profilo: Si può cambiare l'icona del profilo scegliendone una dalla lista o generando una nuova con l'AI.
   - Password: È possibile modificare la password inserendo quella vecchia e poi quella nuova.
   - Le Mie Prenotazioni: In questa sezione si possono visualizzare gli eventi futuri a cui si è iscritti e quelli passati.

6. Feedback e Commenti (per Utenti Registrati):
   - Lasciare un Commento/Recensione: Dopo aver partecipato a un evento, si può accedere alla pagina di dettaglio dell'evento e lasciare un commento nella sezione apposita.
   - Moderazione: Tutti i commenti sono soggetti a moderazione.

7. Segnalare Problemi Tecnici:
   - Se si riscontrano errori o malfunzionamenti sul sito:
     - Cliccare sul link "Assistenza" o "Contatti" (solitamente nel footer o nel menu).
     - Descrivere il problema, allegando screenshot se possibile.
     - Inviare la segnalazione. Verrà recapitata agli amministratori del sito.

Se la domanda dell'utente è generica sull'Eremo (es. "Cos'è l'Eremo?"), puoi rispondere basandoti sulla descrizione generale che hai.
Se la domanda è su come fare qualcosa che non è nel manuale, rispondi che non hai informazioni specifiche su quella funzionalità e suggerisci di consultare la pagina "Contatti" per assistenza diretta.
Non rispondere a domande non pertinenti al sito o al suo utilizzo.
Mantieni un tono cortese e d'aiuto.
    `.trim();
    let chatHistoryForAssistant = [{ role: "assistant", content: "Ciao! Sono l'assistente virtuale dell'Eremo. Come posso aiutarti oggi riguardo il sito?" }];

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
        // Se la chiave è già stata caricata e non è un placeholder di errore, non fare nulla.
        if (GROQ_API_KEY_FOR_ASSISTANT && GROQ_API_KEY_FOR_ASSISTANT !== "CHIAVE_NON_CARICATA_O_ERRATA") return true;

        try {
            // Assicurati che il percorso '/api/get_groq_config.php' sia corretto rispetto alla root del tuo sito.
            const response = await fetch('/api/get_groq_config.php');
            if (!response.ok) {
                throw new Error(`Errore HTTP ${response.status} nel caricare la configurazione API.`);
            }
            const config = await response.json();

            // Controlla se il PHP restituisce i campi specifici per l'assistente,
            // o se dobbiamo usare i campi generici (obfuscatedApiKey, decryptionKey)
            // che potrebbero essere usati anche per la moderazione dei commenti.
            // Per questo esempio, assumo che il PHP fornisca chiavi specifiche per l'assistente.
            // Se la chiave è la stessa, il PHP potrebbe restituire solo "obfuscatedApiKey" e "decryptionKey".
            // In tal caso, dovrai cambiare i nomi dei campi qui sotto.
            const obfuscatedKeyField = config.obfuscatedAssistantApiKey || config.obfuscatedApiKey;
            const decryptionKeyField = config.decryptionKeyAssistant || config.decryptionKey;

            if (config.success && obfuscatedKeyField && decryptionKeyField) {
                const decryptedKey = simpleXorDecryptClientSide(obfuscatedKeyField, decryptionKeyField);
                if (decryptedKey) {
                    GROQ_API_KEY_FOR_ASSISTANT = decryptedKey;
                    console.log("AssistenteAI: Chiave API Groq per assistente pronta.");
                    if(aiAssistantFabEl) aiAssistantFabEl.style.display = 'flex'; // Mostra il FAB
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
            if(aiAssistantFabEl) aiAssistantFabEl.style.display = 'none';
            GROQ_API_KEY_FOR_ASSISTANT = "CHIAVE_NON_CARICATA_O_ERRATA"; // Placeholder per evitare tentativi ripetuti
            return false;
        }
    }

    function toggleAiChat() {
        const isActive = aiChatPopupEl.classList.toggle('active');
        aiAssistantFabEl.innerHTML = isActive ? '<i class="fas fa-times"></i>' : '<i class="fas fa-headset"></i>';

        const isAnyOtherMainPopupActive = document.querySelector(
            '.login-popup-overlay.active, #forgotPasswordOverlay.active, #bookingPopupOverlay.active, #waitlistPopupOverlay.active, #dashAdminEventPopupOverlay.active, #globalFlyerOverlayEff.active, #customConfirmOverlay.active, #editContentModal.active, #editQuandoModal.active, #lightbox.active, #conversationPopupOverlay.active, #likersPopupOverlay.active'
        );

        if (isActive) {
            document.body.style.overflow = 'hidden';
        } else if (!isAnyOtherMainPopupActive) {
            document.body.style.overflow = '';
        }
        if(isActive && aiChatInputEl) aiChatInputEl.focus();
    }

    function addMessageToChatUI(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'ai-message');
        messageDiv.appendChild(document.createTextNode(text));
        aiChatMessagesContainerEl.appendChild(messageDiv);
        aiChatMessagesContainerEl.scrollTop = aiChatMessagesContainerEl.scrollHeight;
        return messageDiv;
    }

    async function handleSendMessageToAI() {
        if (!GROQ_API_KEY_FOR_ASSISTANT || GROQ_API_KEY_FOR_ASSISTANT === "CHIAVE_NON_CARICATA_O_ERRATA") {
            const keyReady = await fetchAndPrepareAssistantApiKey(); // Tenta di caricare la chiave se non presente
            if (!keyReady) {
                addMessageToChatUI('ai', "L'assistente non è configurato. Impossibile inviare il messaggio.");
                console.error("AssistenteAI: Chiave API non disponibile o errata dopo tentativo di caricamento.");
                return;
            }
        }

        const userInput = aiChatInputEl.value.trim();
        if (!userInput) return;

        addMessageToChatUI('user', userInput);
        chatHistoryForAssistant.push({ role: "user", content: userInput });
        aiChatInputEl.value = '';
        aiChatInputEl.disabled = true;
        aiChatSendBtnEl.disabled = true;
        aiChatInputEl.style.height = 'auto';

        const thinkingMessageDiv = addMessageToChatUI('ai', "Sto pensando");
        if (thinkingMessageDiv) {
            thinkingMessageDiv.classList.add('thinking');
            const thinkingDotsSpan = document.createElement('span');
            thinkingDotsSpan.classList.add('dots');
            thinkingMessageDiv.appendChild(thinkingDotsSpan);
        }

        try {
            const messagesPayload = [
                { role: "system", content: SYSTEM_PROMPT_FOR_ASSISTANT },
                ...chatHistoryForAssistant.slice(-6)
            ];

            const response = await fetch("https://api.groq.com/openai/v1/chat/completions", {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${GROQ_API_KEY_FOR_ASSISTANT}`,
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    messages: messagesPayload, model: GROQ_MODEL_NAME_ASSISTANT,
                    temperature: 0.3, max_tokens: 350, top_p: 0.8
                })
            });

            if (thinkingMessageDiv && thinkingMessageDiv.parentNode) thinkingMessageDiv.remove();

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: { message: `Errore API Groq: ${response.status}` } }));
                throw new Error(errorData.error?.message || `Errore API Groq: ${response.status}`);
            }
            const data = await response.json();
            const aiResponse = data.choices[0]?.message?.content.trim();

            if (aiResponse) {
                addMessageToChatUI('ai', aiResponse);
                chatHistoryForAssistant.push({ role: "assistant", content: aiResponse });
            } else { addMessageToChatUI('ai', "Non ho ricevuto una risposta valida. Riprova."); }
        } catch (error) {
            console.error("Errore chiamata API Groq (Assistente):", error);
            if (thinkingMessageDiv && thinkingMessageDiv.parentNode) thinkingMessageDiv.remove();
            addMessageToChatUI('ai', `Spiacente, si è verificato un errore di comunicazione. Riprova.`);
        } finally {
            aiChatInputEl.disabled = false;
            aiChatSendBtnEl.disabled = false;
            aiChatInputEl.focus();
        }
    }

    // Inizializza l'assistente cercando di caricare la chiave API
    fetchAndPrepareAssistantApiKey().then(keyReady => {
        if (keyReady) {
            aiAssistantFabEl.addEventListener('click', toggleAiChat);
            aiChatCloseBtnEl.addEventListener('click', toggleAiChat);
            aiChatSendBtnEl.addEventListener('click', handleSendMessageToAI);
            aiChatInputEl.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleSendMessageToAI();
                }
            });
            aiChatInputEl.addEventListener('input', function () {
                this.style.height = 'auto';
                let newHeight = this.scrollHeight;
                if (newHeight > 80) newHeight = 80;
                this.style.height = newHeight + 'px';
            }, false);
        } else {
            // Se la chiave non può essere caricata, il FAB rimane nascosto (gestito in fetchAndPrepareAssistantApiKey)
            console.error("AssistenteAI: Inizializzazione fallita, chiave API non caricata.");
        }
    });

    // La gestione globale di ESC per la chat AI è meglio se gestita dalla pagina principale
    // per evitare conflitti se ci sono altri popup modali.
    // Se la pagina che include questo script ha una sua `handleGlobalKeyDown`,
    // quella funzione dovrebbe essere aggiornata per chiamare `toggleAiChat()`
    // quando `aiChatPopupEl.classList.contains('active')`.
});
