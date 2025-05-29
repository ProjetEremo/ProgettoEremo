// File: dashboard.js

// Helper function to get element by ID
function gebi(id) {
    return document.getElementById(id);
}

// Global variables
let allUpcomingEventsData = [];
let allPastEventsData = [];
const defaultUserIconPath = 'uploads/icons/default_user.png'; // Assuming a default icon path
const adminAvatarPath = 'images/logo.png'; // Default admin avatar
const adminNameGlobalJS = "Admin Eremo"; // Default admin name

let currentUserData = null;
let groqApiKey = null;

// DOM Elements for the new event creation/edit modal (dashAdminEventPopup)
let adminAddEventBtnEl, dashAdminEventPopupOverlayEl, dashAdminEventPopupEl, closeDashAdminEventPopupBtnEl,
    dashAdminEventFormEl, dashAdminEventPopupTitleEl, dashAdminEventIdInputEl, dashAdminEventTitleEl,
    dashAdminEventDateEl, dashAdminEventStartTimeEl, dashAdminEventEndTimeEl, dashAdminEventShortDescEl,
    dashAdminEventLongDescEl, dashAdminEventPrefixEl, dashAdminEventSpeakerEl, dashAdminEventAssociationEl,
    dashAdminEventSeatsEl, dashAdminEventImageEl, dashAdminEventFlyerEl, dashAdminEventBookingCheckbox,
    dashAdminEventVoluntaryCheckbox, dashAdminEventPriceContainerEl, dashAdminEventPriceInputEl,
    dashAdminEventFormMessageEl, dashAdminCurrentImagePreviewEl, dashAdminCurrentFlyerPreviewEl,
    dashAdminPreviewImageEl, dashAdminPreviewNoImagePlaceholderEl, dashAdminPreviewTitleEl,
    dashAdminPreviewDateFullEl, dashAdminPreviewTimeFullEl, dashAdminPreviewTimeValueEl,
    dashAdminPreviewSpeakerFullEl, dashAdminPreviewSpeakerPrefixEl, dashAdminPreviewSpeakerNameEl,
    dashAdminPreviewShortDescEl, dashAdminPreviewSeatsFullEl, dashAdminPreviewPriceFullEl,
    generateShortDescBtnEl, aiShortDescStatusEl, dashAdminLongDescElForAI;

// DOM Elements for creation mode selection and flyer upload
let creationModeSelectionViewEl, createManuallyModeBtnEl, extractFromFlyerModeBtnEl;
let flyerUploadViewEl, flyerFileEl, flyerPreviewEl, processFlyerBtnEl, flyerProcessingStatusEl, flyerPreviewContainerEl;
let dashAdminFormAndPreviewContainerEl = null;

// DOM Elements for custom confirm modal
let customConfirmOverlayEl, customConfirmModalEl, customConfirmMessageEl,
    customConfirmYesBtn, customConfirmNoBtn;
let confirmResolve = null;

// DOM Elements for flyer display modal
let globalFlyerOverlayEff = null;
let currentFlyerModalEff = null;

// Form state for new event modal
let initialAdminEventFormData = {};
let isEventFormDirty = false;


// --- UTILITY FUNCTIONS (Sanitization, API calls, Date Formatting) ---
function sanitizeText(text) {
    if (text === null || typeof text === 'undefined') return '';
    const element = document.createElement('div');
    element.innerText = String(text);
    return element.innerHTML;
}

function sanitizeTextForHTML(str) {
    if (typeof str !== 'string') str = String(str || '');
    const temp = document.createElement('div');
    temp.textContent = str;
    return temp.innerHTML.replace(/\r\n|\r|\n/g, '<br>');
}

function sanitizeForAttribute(str) {
    if (typeof str !== 'string' || str === null) return '';
    return str.replace(/'/g, "&apos;").replace(/"/g, "&quot;");
}

async function callApi(url, method = 'GET', data = null, isFormData = false) {
    const headers = {};
    if (!isFormData) {
        headers['Content-Type'] = 'application/json';
        headers['Accept'] = 'application/json';
    } else {
        // When sending FormData, the browser sets the Content-Type automatically,
        // including the boundary. So, we don't set it manually here.
        headers['Accept'] = 'application/json'; // Still expect JSON response
    }

    const options = { method, headers, credentials: 'same-origin' };

    if (data) {
        if (isFormData) {
            options.body = data; // data is already a FormData object
        } else if (method === 'POST' || method === 'PUT' || method === 'DELETE') {
            options.body = JSON.stringify(data);
        }
    }

    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            let errorData = { message: `Errore HTTP ${response.status} (${response.statusText})` };
            let rawErrorText = '';
            try {
                rawErrorText = await response.text();
                errorData = JSON.parse(rawErrorText);
            } catch (e) {
                errorData.message = rawErrorText || errorData.message;
                errorData.rawError = rawErrorText;
            }
            console.error(`Fallimento API a ${url} (status ${response.status}):`, errorData.message, errorData.rawError);
            throw new Error(errorData.message || `Errore server: ${response.status}`);
        }
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return response.json();
        } else {
            // If not JSON, return the text, useful for debugging or simple text responses
            return response.text().then(text => text ? { success: true, message: text, data: text } : { success: true });
        }
    } catch (error) {
        console.error(`Eccezione chiamata API a ${url}:`, error);
        throw error;
    }
}


function simpleXorDecryptClient(base64String, key) {
    try {
        const encryptedText = atob(base64String);
        let outText = '';
        for (let i = 0; i < encryptedText.length; i++) {
            outText += String.fromCharCode(encryptedText.charCodeAt(i) ^ key.charCodeAt(i % key.length));
        }
        return outText;
    } catch (e) {
        console.error("Fallimento decifratura API key:", e);
        return null;
    }
}

async function fetchAndPrepareGroqApiKey() {
    if (groqApiKey) return true;
    try {
        const result = await callApi('api/get_groq_config.php');
        if (result.success && result.obfuscatedApiKey && result.decryptionKey) {
            const decryptedKey = simpleXorDecryptClient(result.obfuscatedApiKey, result.decryptionKey);
            if (decryptedKey) {
                groqApiKey = decryptedKey;
                console.log("Chiave API Groq pronta per l'uso (Dashboard).");
                return true;
            } else {
                console.error("Fallimento decifratura API Key Groq (Dashboard).");
                if (aiShortDescStatusEl) aiShortDescStatusEl.textContent = "Errore config. AI (dec.).";
                if (flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = "Errore config. AI (dec.).";
                return false;
            }
        } else {
            console.error('Fallimento recupero componenti API key Groq (Dashboard):', result.message || 'Dati mancanti.');
            if (aiShortDescStatusEl) aiShortDescStatusEl.textContent = "Errore config. AI (fetch).";
            if (flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = "Errore config. AI (fetch).";
            return false;
        }
    } catch (error) {
        console.error('Errore recupero API key Groq (Dashboard):', error);
        if (aiShortDescStatusEl) aiShortDescStatusEl.textContent = "Errore connessione AI.";
        if (flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = "Errore connessione AI.";
        return false;
    }
}

// --- GROQ API CALLS ---
async function callGroqForFlyerExtraction(base64ImageData) {
    if (!groqApiKey) {
        const keyReady = await fetchAndPrepareGroqApiKey();
        if (!keyReady) {
            return { success: false, error: "Chiave API Groq non disponibile per estrazione da volantino." };
        }
    }

    const groqApiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    const flyerPrompt = `Analizza l'immagine del volantino fornita e estrai le seguenti informazioni strutturate.
Formatta l'output ESCLUSIVAMENTE come un oggetto JSON valido. Non includere testo prima o dopo l'oggetto JSON.
Se un'informazione non è chiaramente presente o deducibile, usa 'null' per il valore del campo corrispondente.

Schema JSON richiesto:
{
  "titolo": "string | null",
  "data_inizio": "YYYY-MM-DD | null (es. '2024-07-20')",
  "ora_inizio": "HH:MM | null (es. '09:30')",
  "ora_fine": "HH:MM | null (es. '17:00', se non specificata, usa null)",
  "relatore_prefisso": "string | null (es. 'Don', 'Suor', 'Prof.', 'Dott.')",
  "relatore_nome": "string | null (nome completo del relatore/i)",
  "associazione": "string | null (nome dell'associazione organizzatrice, se presente)",
  "descrizione_breve_generata": "string (massimo 180 caratteri, concisa e informativa, basata sui contenuti principali)",
  "descrizione_estesa_generata": "string (più dettagliata, riassumendo i temi e le attività principali descritte nel volantino)",
  "posti_disponibili": "number | null (se indicato un numero esatto di posti)",
  "costo": "number | null (es. 25.00. Se 'offerta libera' o 'ingresso gratuito con offerta', imposta a 0.00. Se solo 'ingresso gratuito' senza menzione di offerta, usa null o 0.00 a seconda del contesto. Se non menzionato, usa null)",
  "flag_prenotabile": "boolean (true se il volantino indica la necessità o possibilità di prenotare/iscriversi, altrimenti false)",
  "flag_offerta_libera": "boolean (true se esplicitamente menzionato 'offerta libera', 'ingresso a offerta', etc.)"
}

Interpreta le date e gli orari nel modo più accurato possibile. Per 'costo', se è 'offerta libera', assicurati che 'flag_offerta_libera' sia true e 'costo' sia 0.00.
La 'descrizione_breve_generata' deve essere un riassunto molto conciso.
La 'descrizione_estesa_generata' può essere più lunga ma deve rimanere un riassunto del contenuto del volantino.
Non aggiungere commenti o spiegazioni al di fuori dell'oggetto JSON.`;

    const payload = {
        messages: [
            {
                role: "user",
                content: [
                    { type: "text", text: flyerPrompt },
                    {
                        type: "image_url",
                        image_url: { url: `data:image/jpeg;base64,${base64ImageData}` }
                    }
                ]
            }
        ],
        model: "meta-llama/Llama-4-scout-17b-16e-instruct", // MODELLO RIPRISTINATO COME RICHIESTO
        temperature: 0.2,
        max_tokens: 2500,
    };
    console.log("Payload inviato a Groq per estrazione da volantino:", JSON.stringify(payload, null, 2));


    try {
        const response = await fetch(groqApiUrl, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${groqApiKey}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorText = await response.text();
            let errorData;
            try { errorData = JSON.parse(errorText); }
            catch (e) { errorData = { error: { message: errorText || response.statusText }}; }
            console.error("Errore API Groq per estrazione da volantino:", response.status, errorData);
            return { success: false, error: `Errore AI (${response.status}): ${errorData.error?.message || 'Servizio non raggiungibile'}` };
        }

        const data = await response.json();
        if (data.choices && data.choices.length > 0 && data.choices[0].message && data.choices[0].message.content) {
            let content = data.choices[0].message.content;
            const jsonMatch = content.match(/\{[\s\S]*\}/);
            if (jsonMatch && jsonMatch[0]) {
                try {
                    const extractedJson = JSON.parse(jsonMatch[0]);
                    return { success: true, data: extractedJson };
                } catch (e) {
                    console.error("Errore parsing JSON estratto da Groq:", e, "\nContenuto ricevuto ripulito:", jsonMatch[0], "\nContenuto originale:", content);
                    return { success: false, error: "Risposta AI non è un JSON valido dopo pulizia." };
                }
            } else {
                try {
                    const extractedJson = JSON.parse(content);
                    return { success: true, data: extractedJson };
                } catch (e) {
                    console.warn("Nessun JSON valido trovato nella risposta Groq per volantino, né grezza né pulita:", content);
                    return { success: false, error: "Risposta AI non contiene un oggetto JSON interpretabile." };
                }
            }
        } else {
            console.warn("Struttura risposta Groq non valida per estrazione da volantino:", data);
            return { success: false, error: "Risposta AI non valida (struttura)." };
        }
    } catch (error) {
        console.error('Errore chiamata API Groq per estrazione da volantino:', error);
        return { success: false, error: "Errore comunicazione con servizio AI per estrazione." };
    }
}

async function generateShortDescriptionAI(longDescription) {
    if (!groqApiKey) {
        const keyReady = await fetchAndPrepareGroqApiKey();
        if (!keyReady || !groqApiKey) {
            console.error("API Key Groq non disponibile per la generazione della descrizione (Dashboard).");
            return { success: false, description: null, error: "Chiave API per AI non disponibile." };
        }
    }

    if (!longDescription || longDescription.trim().length < 20) {
        return { success: false, description: null, error: "La descrizione estesa è troppo corta per generare un riassunto." };
    }

    const groqApiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    const promptSystem = "Sei un assistente AI specializzato nella creazione di contenuti per eventi. Data una descrizione estesa, il tuo compito è generare una descrizione breve, concisa e accattivante, ideale per essere visualizzata su una card promozionale dell'evento. La descrizione breve non deve superare i 190 caratteri. Rispondi solo con la descrizione breve generata, senza alcuna introduzione o testo aggiuntivo.";

    const payload = {
        "messages": [
            { "role": "system", "content": promptSystem },
            { "role": "user", "content": `Descrizione estesa da riassumere:\n\n${longDescription}` }
        ],
        "model": "llama3-8b-8192",
        "temperature": 0.7,
        "max_tokens": 70,
        "top_p": 1,
        "stream": false
    };
    // console.log("Payload inviato a Groq per descrizione breve:", JSON.stringify(payload, null, 2));

    try {
        const response = await fetch(groqApiUrl, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${groqApiKey}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: { message: response.statusText } }));
            console.error("Errore API Groq per descrizione (Dashboard):", response.status, errorData);
            return { success: false, description: null, error: `Errore AI (${response.status}): ${errorData.error?.message || 'Servizio non raggiungibile'}` };
        }

        const data = await response.json();
        if (data.choices && data.choices.length > 0 && data.choices[0].message && data.choices[0].message.content) {
            let generatedText = data.choices[0].message.content.trim();
            generatedText = generatedText.replace(/^["']|["']$/g, "");
            return { success: true, description: generatedText, error: null };
        } else {
            console.warn("Struttura risposta Groq non valida per descrizione (Dashboard, non-streaming):", data);
            return { success: false, description: null, error: "Risposta AI non valida (struttura)." };
        }
    } catch (error) {
        console.error('Errore chiamata API Groq per descrizione (Dashboard):', error);
        return { success: false, description: null, error: "Errore comunicazione con servizio AI." };
    }
}


function formatDbDate(dateString) {
    if (!dateString) return 'N/D';
    try {
        const parts = dateString.substring(0, 10).split('-');
        if (parts.length === 3) {
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1;
            const day = parseInt(parts[2]);
            const date = new Date(Date.UTC(year, month, day));
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
        }
        return dateString;
    } catch (e) {
        console.warn("Errore formattazione data DB:", dateString, e);
        return dateString;
    }
}

function formatSimpleDateForPreview(dateString) {
    if (!dateString) return 'Seleziona data';
    try {
        const parts = dateString.split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    } catch (e) { return dateString; }
}

function formatMonthYear(dateString) {
    if (!dateString) return 'Data sconosciuta';
    try {
        const parts = dateString.substring(0, 10).split('-');
        if (parts.length === 3) {
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1;
            const date = new Date(Date.UTC(year, month, 1));
            if (isNaN(date.getTime())) return 'Data non valida';
            const options = { month: 'long', year: 'numeric', timeZone: 'UTC' };
            return new Intl.DateTimeFormat('it-IT', options).format(date);
        }
        return 'Data non valida';
    } catch (e) {
        console.warn("Errore formattazione Mese/Anno:", dateString, e);
        return 'Data non valida';
    }
}

function getMonthIndexFromString(monthName) {
    const months = ["gennaio", "febbraio", "marzo", "aprile", "maggio", "giugno", "luglio", "agosto", "settembre", "ottobre", "novembre", "dicembre"];
    return months.indexOf(monthName.toLowerCase());
}


// --- NAVBAR AND USER MENU ---
function setupNavbar() {
    const navbarContainer = gebi('navbarContainer');
    let lastScroll = 0;
    if (navbarContainer) {
        window.addEventListener('scroll', function () {
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            if (currentScroll <= 50) {
                navbarContainer.classList.remove('hidden');
                lastScroll = 0;
                return;
            }
            if (currentScroll > lastScroll && !navbarContainer.classList.contains('hidden')) {
                navbarContainer.classList.add('hidden');
            } else if (currentScroll < lastScroll && navbarContainer.classList.contains('hidden')) {
                navbarContainer.classList.remove('hidden');
            }
            lastScroll = currentScroll <= 0 ? 0 : currentScroll;
        });
    }

    const userBtn = document.querySelector('#userDropdownContainer .user-btn');
    if (userBtn) {
        userBtn.addEventListener('click', toggleUserMenu);
    }
}

function toggleUserMenu() {
    const dropdown = document.querySelector('#userDropdownContainer .dropdown-menu');
    const button = document.querySelector('#userDropdownContainer .user-btn');
    if (dropdown && button) {
        const isOpen = dropdown.style.display === 'block';
        dropdown.style.display = isOpen ? 'none' : 'block';
        button.setAttribute('aria-expanded', String(!isOpen));
        const arrow = button.querySelector('.dropdown-arrow');
        if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
    }
}

function logout() {
    localStorage.removeItem('adminUserEFF');
    alert('Logout effettuato.');
    window.location.href = 'index.html';
}

// --- GENERIC MODAL HANDLING ---
function openModal(modalId) {
    const modal = gebi(modalId);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
        document.body.style.overflow = 'hidden';
    } else {
        console.warn(`Modal con ID '${modalId}' non trovato.`);
    }
}

function closeModal(modalId) {
    const modal = gebi(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.addEventListener('transitionend', function handler() {
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
            }
            modal.removeEventListener('transitionend', handler);
        });
        setTimeout(() => {
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
            }
        }, 300);
    } else {
        console.warn(`Modal con ID '${modalId}' non trovato per la chiusura.`);
    }

    if (!isAnyPopupActive()) {
        document.body.style.overflow = '';
    }
}

function isAnyPopupActive() {
    const modals = document.querySelectorAll('.modal.active, .dash-admin-event-popup.active, .flyer-overlay-eff.active, .custom-confirm-overlay.active');
    return modals.length > 0;
}

function setupModalCloseButtons() {
    document.querySelectorAll('.close-modal-btn').forEach(button => {
        button.addEventListener('click', function () {
            const modalId = this.dataset.modalId;
            if (modalId) {
                closeModal(modalId);
            } else {
                const parentModal = this.closest('.modal');
                if (parentModal) {
                    closeModal(parentModal.id);
                } else {
                    console.warn("Impossibile determinare il modal da chiudere per il bottone:", this);
                }
            }
        });
    });
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal(this.id);
            }
        });
    });
}


// --- NEW EVENT MODAL (dashAdminEventPopup) ---
function initializeNewEventModalDOMReferences() {
    adminAddEventBtnEl = gebi('adminAddEventBtn');
    dashAdminEventPopupOverlayEl = gebi('dashAdminEventPopupOverlay');
    dashAdminEventPopupEl = gebi('dashAdminEventPopup');
    closeDashAdminEventPopupBtnEl = gebi('closeDashAdminEventPopupBtn');

    creationModeSelectionViewEl = gebi('creationModeSelectionView');
    createManuallyModeBtnEl = gebi('createManuallyModeBtn');
    extractFromFlyerModeBtnEl = gebi('extractFromFlyerModeBtn');

    flyerUploadViewEl = gebi('flyerUploadView');
    flyerFileEl = gebi('flyerFile');
    flyerPreviewContainerEl = gebi('flyerPreviewContainer');
    flyerPreviewEl = gebi('flyerPreview');
    processFlyerBtnEl = gebi('processFlyerBtn');
    flyerProcessingStatusEl = gebi('flyerProcessingStatus');

    dashAdminFormAndPreviewContainerEl = document.querySelector('.dash-admin-event-form-and-preview-container');
    dashAdminEventFormEl = gebi('dashAdminEventForm');
    dashAdminEventPopupTitleEl = gebi('dashAdminEventPopupTitle');
    dashAdminEventIdInputEl = gebi('dashAdmin-event-id');
    dashAdminEventTitleEl = gebi('dashAdmin-event-title');
    dashAdminEventDateEl = gebi('dashAdmin-event-date');
    dashAdminEventStartTimeEl = gebi('dashAdmin-event-start-time');
    dashAdminEventEndTimeEl = gebi('dashAdmin-event-end-time');
    dashAdminEventLongDescEl = gebi('dashAdmin-event-long-desc');
    dashAdminLongDescElForAI = dashAdminEventLongDescEl;
    dashAdminEventShortDescEl = gebi('dashAdmin-event-short-desc');
    generateShortDescBtnEl = gebi('generateShortDescBtn');
    aiShortDescStatusEl = gebi('aiShortDescStatus');
    dashAdminEventPrefixEl = gebi('dashAdmin-event-prefix');
    dashAdminEventSpeakerEl = gebi('dashAdmin-event-speaker');
    dashAdminEventAssociationEl = gebi('dashAdmin-event-association');
    dashAdminEventSeatsEl = gebi('dashAdmin-event-seats');
    dashAdminEventImageEl = gebi('dashAdmin-event-image');
    dashAdminCurrentImagePreviewEl = gebi('dashAdmin-current-event-image-preview');
    dashAdminEventFlyerEl = gebi('dashAdmin-event-flyer');
    dashAdminCurrentFlyerPreviewEl = gebi('dashAdmin-current-event-flyer-preview');
    dashAdminEventBookingCheckbox = gebi('dashAdmin-event-booking');
    dashAdminEventVoluntaryCheckbox = gebi('dashAdmin-event-voluntary');
    dashAdminEventPriceContainerEl = gebi('dashAdmin-event-price-container');
    dashAdminEventPriceInputEl = gebi('dashAdmin-event-price');
    dashAdminEventFormMessageEl = gebi('dashAdminEventFormMessage');

    dashAdminPreviewImageEl = gebi('dashAdmin_previewImage');
    dashAdminPreviewNoImagePlaceholderEl = document.querySelector('#dashAdminEventCardPreviewContainer .dash-admin-no-image-placeholder');
    dashAdminPreviewTitleEl = gebi('dashAdmin_previewTitle');
    dashAdminPreviewDateFullEl = gebi('dashAdmin_previewDateFull').querySelector('.value');
    dashAdminPreviewTimeFullEl = gebi('dashAdmin_previewTimeFull');
    dashAdminPreviewTimeValueEl = gebi('dashAdmin_previewTimeFull').querySelector('.value');
    dashAdminPreviewSpeakerFullEl = gebi('dashAdmin_previewSpeakerFull');
    dashAdminPreviewSpeakerPrefixEl = gebi('dashAdmin_previewSpeakerPrefix');
    dashAdminPreviewSpeakerNameEl = gebi('dashAdmin_previewSpeakerName').querySelector('.value');
    dashAdminPreviewShortDescEl = gebi('dashAdmin_previewShortDesc');
    dashAdminPreviewSeatsFullEl = gebi('dashAdmin_previewSeatsFull').querySelector('.value');
    dashAdminPreviewPriceFullEl = gebi('dashAdmin_previewPriceFull').querySelector('.value');
}

function setupNewEventModalHandlers() {
    if (adminAddEventBtnEl) adminAddEventBtnEl.addEventListener('click', () => openDashAdminEventPopup(null));
    if (closeDashAdminEventPopupBtnEl) closeDashAdminEventPopupBtnEl.addEventListener('click', () => tryCloseDashAdminEventPopup());
    if (dashAdminEventPopupOverlayEl) dashAdminEventPopupOverlayEl.addEventListener('click', (e) => { if (e.target === dashAdminEventPopupOverlayEl) tryCloseDashAdminEventPopup(); });

    if (createManuallyModeBtnEl) createManuallyModeBtnEl.addEventListener('click', () => {
        if(creationModeSelectionViewEl) creationModeSelectionViewEl.style.display = 'none';
        if(flyerUploadViewEl) flyerUploadViewEl.style.display = 'none';
        if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'flex';
        resetAndPrepareManualForm();
        if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Aggiungi Nuovo Evento Manualmente';
        if(dashAdminEventTitleEl) dashAdminEventTitleEl.focus();
        storeInitialFormData();
    });

    if (extractFromFlyerModeBtnEl) extractFromFlyerModeBtnEl.addEventListener('click', () => {
        if(creationModeSelectionViewEl) creationModeSelectionViewEl.style.display = 'none';
        if(flyerUploadViewEl) flyerUploadViewEl.style.display = 'block';
        if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'none';
        if(flyerFileEl) flyerFileEl.value = '';
        if(flyerPreviewContainerEl) flyerPreviewContainerEl.style.display = 'none';
        if(flyerPreviewEl) flyerPreviewEl.src = '#';
        if(flyerPreviewContainerEl) flyerPreviewContainerEl.classList.remove('pulsating-background');
        if(processFlyerBtnEl) processFlyerBtnEl.disabled = true;
        if(flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = '';
        if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Estrai da Volantino';
        resetAndPrepareManualForm();
    });

    if (flyerFileEl) flyerFileEl.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if(flyerPreviewEl) flyerPreviewEl.src = e.target.result;
                if(flyerPreviewContainerEl) {
                    flyerPreviewContainerEl.style.display = 'flex';
                    flyerPreviewContainerEl.classList.add('pulsating-background');
                }
            }
            reader.readAsDataURL(file);
            if(processFlyerBtnEl) processFlyerBtnEl.disabled = false;
            if(flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = '';
        } else {
            if(flyerPreviewContainerEl) {
                flyerPreviewContainerEl.style.display = 'none';
                flyerPreviewContainerEl.classList.remove('pulsating-background');
            }
            if(flyerPreviewEl) flyerPreviewEl.src = '#';
            if(processFlyerBtnEl) processFlyerBtnEl.disabled = true;
            if(flyerProcessingStatusEl && file) flyerProcessingStatusEl.textContent = 'Per favore, seleziona un file immagine (JPG, PNG, WEBP).';
        }
    });

    if (processFlyerBtnEl) processFlyerBtnEl.addEventListener('click', handleFlyerProcessing);

    if (dashAdminLongDescElForAI && generateShortDescBtnEl) {
        dashAdminLongDescElForAI.addEventListener('input', () => {
            const hasText = dashAdminLongDescElForAI.value.trim().length > 0;
            generateShortDescBtnEl.classList.toggle('visible', hasText);
            if (hasText && !generateShortDescBtnEl.classList.contains('loading')) {
                generateShortDescBtnEl.classList.add('expanded');
            } else {
                generateShortDescBtnEl.classList.remove('expanded');
            }
        });
    }

    if (generateShortDescBtnEl) {
        generateShortDescBtnEl.addEventListener('click', handleGenerateShortDescAI);
    }

    if (dashAdminEventFormEl) {
        dashAdminEventFormEl.addEventListener('submit', handleAdminEventFormSubmit);
        dashAdminEventFormEl.addEventListener('input', () => { isEventFormDirty = true; });
        dashAdminEventFormEl.addEventListener('change', () => { isEventFormDirty = true; });
    }

    if (dashAdminEventVoluntaryCheckbox) {
        dashAdminEventVoluntaryCheckbox.addEventListener('change', updateDashAdminPriceFieldVisibility);
    }
    attachDashAdminPreviewListeners();
}

function openDashAdminEventPopup(eventData = null) {
    if (!dashAdminEventPopupEl || !dashAdminEventFormEl || !creationModeSelectionViewEl) {
        console.error("Elementi del modal di creazione evento non trovati.");
        return;
    }

    if(creationModeSelectionViewEl) creationModeSelectionViewEl.style.display = 'flex';
    if(flyerUploadViewEl) flyerUploadViewEl.style.display = 'none';
    if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'none';
    if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Come vuoi creare l\'evento?';

    if (eventData) {
        if(creationModeSelectionViewEl) creationModeSelectionViewEl.style.display = 'none';
        if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'flex';
        if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Modifica Evento';
        populateDashAdminForm(eventData);
        if(dashAdminEventTitleEl) dashAdminEventTitleEl.focus();
    } else {
        resetAndPrepareManualForm();
        if(flyerFileEl) flyerFileEl.value = '';
        if(flyerPreviewContainerEl) {
            flyerPreviewContainerEl.style.display = 'none';
            flyerPreviewContainerEl.classList.remove('pulsating-background');
        }
        if(flyerPreviewEl) flyerPreviewEl.src = '#';
        if(processFlyerBtnEl) processFlyerBtnEl.disabled = true;
        if(flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = '';
    }

    if (dashAdminLongDescElForAI && generateShortDescBtnEl) {
        const hasText = dashAdminLongDescElForAI.value.trim().length > 0;
        generateShortDescBtnEl.classList.toggle('visible', hasText);
        generateShortDescBtnEl.classList.toggle('expanded', hasText && !generateShortDescBtnEl.classList.contains('loading'));
    }

    if(dashAdminEventPopupOverlayEl) dashAdminEventPopupOverlayEl.classList.add('active');
    if(dashAdminEventPopupEl) dashAdminEventPopupEl.classList.add('active');
    document.body.style.overflow = 'hidden';
    storeInitialFormData();
}

function populateDashAdminForm(eventData) {
    if (!dashAdminEventFormEl) return;
    resetAndPrepareManualForm();

    if(dashAdminEventIdInputEl) dashAdminEventIdInputEl.value = eventData.IDEvento || eventData.idevento;
    dashAdminEventTitleEl.value = eventData.Titolo || eventData.titolo || '';

    const eventDate = eventData.Data || eventData.datainizio;
    dashAdminEventDateEl.value = eventDate ? eventDate.substring(0,10) : '';

    let startTime = '', endTime = '';
    const durata = eventData.Durata || eventData.durata || '';
    if (durata.includes('-')) {
        const parts = durata.split('-');
        startTime = parts[0].trim().match(/^\d{2}:\d{2}/) ? parts[0].trim().substring(0,5) : '';
        endTime = parts[1].trim().match(/^\d{2}:\d{2}/) ? parts[1].trim().substring(0,5) : '';
    } else if (durata.match(/^\d{2}:\d{2}/)) {
        startTime = durata.substring(0,5);
    }
    dashAdminEventStartTimeEl.value = startTime;
    dashAdminEventEndTimeEl.value = endTime;

    dashAdminEventShortDescEl.value = eventData.descrizione || '';
    dashAdminEventLongDescEl.value = eventData.descrizione_estesa || '';
    dashAdminEventPrefixEl.value = eventData.prefisso_relatore || 'Relatore';
    dashAdminEventSpeakerEl.value = eventData.relatore || '';
    dashAdminEventAssociationEl.value = eventData.associazione || '';

    const seats = eventData.posti_configurati_totali !== undefined ? eventData.posti_configurati_totali : eventData.posti_disponibili;
    dashAdminEventSeatsEl.value = seats !== null ? seats : '0';

    dashAdminEventBookingCheckbox.checked = parseInt(eventData.FlagPrenotabile || eventData.flagprenotabile) === 1;

    const costo = eventData.costo !== undefined ? eventData.costo : null;
    dashAdminEventVoluntaryCheckbox.checked = costo !== null && parseFloat(costo) === 0.00;
    updateDashAdminPriceFieldVisibility();

    if (!dashAdminEventVoluntaryCheckbox.checked && costo !== null && parseFloat(costo) > 0) {
        dashAdminEventPriceInputEl.value = parseFloat(costo).toFixed(2);
    } else {
        dashAdminEventPriceInputEl.value = '';
    }

    const imageUrl = eventData.immagine_url;
    if (dashAdminCurrentImagePreviewEl && imageUrl) {
        dashAdminCurrentImagePreviewEl.src = imageUrl;
        dashAdminCurrentImagePreviewEl.style.display = 'block';
    } else if (dashAdminCurrentImagePreviewEl) {
        dashAdminCurrentImagePreviewEl.style.display = 'none';
        dashAdminCurrentImagePreviewEl.src = '#';
    }

    if(dashAdminEventImageEl) dashAdminEventImageEl.value = '';

    const flyerUrl = eventData.volantino_url;
    if (dashAdminCurrentFlyerPreviewEl && flyerUrl) {
        dashAdminCurrentFlyerPreviewEl.innerHTML = `<a href="${sanitizeForAttribute(flyerUrl)}" target="_blank" title="Visualizza il volantino attuale">${sanitizeText(flyerUrl.split('/').pop())}</a>`;
    } else if (dashAdminCurrentFlyerPreviewEl) {
        dashAdminCurrentFlyerPreviewEl.innerHTML = `<span>Nessun volantino caricato.</span>`;
    }
    if(dashAdminEventFlyerEl) dashAdminEventFlyerEl.value = '';

    updateDashAdminEventPreview();
}


function resetAndPrepareManualForm() {
    if (!dashAdminEventFormEl) return;
    dashAdminEventFormEl.reset();
    if(dashAdminEventIdInputEl) dashAdminEventIdInputEl.value = '';
    updateDashAdminPriceFieldVisibility();
    if(dashAdminEventFormMessageEl) { dashAdminEventFormMessageEl.style.display = 'none'; dashAdminEventFormMessageEl.className = 'form-message';}
    if(dashAdminCurrentImagePreviewEl) { dashAdminCurrentImagePreviewEl.style.display = 'none'; dashAdminCurrentImagePreviewEl.src = '#';}
    if(dashAdminCurrentFlyerPreviewEl) { dashAdminCurrentFlyerPreviewEl.innerHTML = '<span>Nessun volantino caricato.</span>'; }
    if(dashAdminEventFlyerEl) dashAdminEventFlyerEl.value = '';
    if(dashAdminEventImageEl) dashAdminEventImageEl.value = '';
    if(dashAdminEventStartTimeEl) dashAdminEventStartTimeEl.value = '';
    if(dashAdminEventEndTimeEl) dashAdminEventEndTimeEl.value = '';
    if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = "";
    if(generateShortDescBtnEl) generateShortDescBtnEl.classList.remove('visible', 'loading', 'expanded');
    updateDashAdminEventPreview();
}

function storeInitialFormData() {
    if (dashAdminEventFormEl) {
        initialAdminEventFormData = getAdminEventFormData(dashAdminEventFormEl);
        isEventFormDirty = false;
    }
}

function getAdminEventFormData(form) {
    if (!form) return {};
    const formData = new FormData(form);
    const data = {};
    for (const [key, value] of formData.entries()) {
        const element = form.elements[key];
        if (element) {
            if (element.type === 'checkbox') data[key] = element.checked;
            else if (element.type === 'file') data[key] = element.files.length > 0 ? element.files[0].name : '';
            else data[key] = value;
        }
    }
    return data;
}

function compareAdminEventFormData(data1, data2) {
    const keys1 = Object.keys(data1);
    const keys2 = Object.keys(data2);
    if (keys1.length !== keys2.length) return false;
    for (let key of keys1) {
        if (data1[key] !== data2[key]) {
            return false;
        }
    }
    return true;
}


async function tryCloseDashAdminEventPopup() {
    if (dashAdminFormAndPreviewContainerEl && dashAdminFormAndPreviewContainerEl.style.display === 'flex') {
        const currentFormData = getAdminEventFormData(dashAdminEventFormEl);
        const formIsActuallyDirty = !compareAdminEventFormData(initialAdminEventFormData, currentFormData) ||
            (dashAdminEventImageEl && dashAdminEventImageEl.files.length > 0) ||
            (dashAdminEventFlyerEl && dashAdminEventFlyerEl.files.length > 0);


        if (isEventFormDirty || formIsActuallyDirty) {
            const confirmed = await showCustomConfirm("Hai modifiche non salvate. Sei sicuro di voler uscire?");
            if (confirmed) {
                closeDashAdminEventPopup_actual();
            }
            return;
        }
    }
    closeDashAdminEventPopup_actual();
}

function closeDashAdminEventPopup_actual() {
    if(dashAdminEventPopupOverlayEl) dashAdminEventPopupOverlayEl.classList.remove('active');
    if(dashAdminEventPopupEl) dashAdminEventPopupEl.classList.remove('active');

    if (!isAnyPopupActive()) {
        document.body.style.overflow = '';
    }
    isEventFormDirty = false;

    if(creationModeSelectionViewEl) creationModeSelectionViewEl.style.display = 'flex';
    if(flyerUploadViewEl) flyerUploadViewEl.style.display = 'none';
    if(flyerPreviewContainerEl) flyerPreviewContainerEl.style.display = 'none';
    if(flyerPreviewContainerEl) flyerPreviewContainerEl.classList.remove('pulsating-background');
    if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'none';
    if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Come vuoi creare l\'evento?';
}

function updateDashAdminPriceFieldVisibility() {
    if (dashAdminEventVoluntaryCheckbox && dashAdminEventPriceContainerEl && dashAdminEventPriceInputEl) {
        const isVoluntary = dashAdminEventVoluntaryCheckbox.checked;
        dashAdminEventPriceContainerEl.style.display = isVoluntary ? 'none' : 'block';
        dashAdminEventPriceInputEl.required = !isVoluntary;
        if (isVoluntary) dashAdminEventPriceInputEl.value = '';
        updateDashAdminEventPreview();
    }
}

function updateDashAdminEventPreview() {
    if (!dashAdminPreviewTitleEl) return;

    dashAdminPreviewTitleEl.textContent = dashAdminEventTitleEl.value || 'Titolo Evento';
    const dateValue = dashAdminEventDateEl.value;
    dashAdminPreviewDateFullEl.textContent = dateValue ? formatSimpleDateForPreview(dateValue) : 'Seleziona data';

    const startTimeValue = dashAdminEventStartTimeEl.value;
    const endTimeValue = dashAdminEventEndTimeEl.value;
    let timeString = '';
    if (startTimeValue && endTimeValue) timeString = `${startTimeValue} - ${endTimeValue}`;
    else if (startTimeValue) timeString = startTimeValue;

    if (timeString && dashAdminPreviewTimeFullEl && dashAdminPreviewTimeValueEl) {
        dashAdminPreviewTimeFullEl.style.display = 'block';
        dashAdminPreviewTimeValueEl.textContent = timeString;
    } else if (dashAdminPreviewTimeFullEl && dashAdminPreviewTimeValueEl) {
        dashAdminPreviewTimeFullEl.style.display = 'none';
        dashAdminPreviewTimeValueEl.textContent = '';
    }

    if(dashAdminPreviewSpeakerPrefixEl) dashAdminPreviewSpeakerPrefixEl.textContent = dashAdminEventPrefixEl.value || 'Relatore';
    if(dashAdminPreviewSpeakerNameEl) dashAdminPreviewSpeakerNameEl.textContent = dashAdminEventSpeakerEl.value || 'Nome Relatore';

    dashAdminPreviewShortDescEl.textContent = dashAdminEventShortDescEl.value || 'Descrizione breve dell\'evento qui...';
    dashAdminPreviewSeatsFullEl.textContent = dashAdminEventSeatsEl.value || '0';

    const isVoluntary = dashAdminEventVoluntaryCheckbox.checked;
    if (isVoluntary) {
        dashAdminPreviewPriceFullEl.textContent = 'Offerta libera';
    } else {
        const priceValue = parseFloat(dashAdminEventPriceInputEl.value);
        dashAdminPreviewPriceFullEl.textContent = !isNaN(priceValue) && priceValue >= 0 ? `€ ${priceValue.toFixed(2)}` : '€ 0.00';
    }

    const imageInput = dashAdminEventImageEl;
    if (imageInput && imageInput.files && imageInput.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.src = e.target.result;
            if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.style.display = 'block';
            if(dashAdminPreviewNoImagePlaceholderEl) dashAdminPreviewNoImagePlaceholderEl.style.display = 'none';
        }
        reader.readAsDataURL(imageInput.files[0]);
    } else if (dashAdminCurrentImagePreviewEl && dashAdminCurrentImagePreviewEl.src !== '#' && dashAdminCurrentImagePreviewEl.style.display !== 'none') {
        if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.src = dashAdminCurrentImagePreviewEl.src;
        if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.style.display = 'block';
        if(dashAdminPreviewNoImagePlaceholderEl) dashAdminPreviewNoImagePlaceholderEl.style.display = 'none';
    } else {
        if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.src = 'images/default-event.jpg';
        if(dashAdminPreviewImageEl) dashAdminPreviewImageEl.style.display = 'block';
        if(dashAdminPreviewNoImagePlaceholderEl) dashAdminPreviewNoImagePlaceholderEl.style.display = 'none';
    }
}

function attachDashAdminPreviewListeners() {
    const formElementsToWatch = [
        dashAdminEventTitleEl, dashAdminEventDateEl, dashAdminEventStartTimeEl, dashAdminEventEndTimeEl,
        dashAdminEventShortDescEl, dashAdminEventLongDescEl, dashAdminEventPrefixEl, dashAdminEventSpeakerEl,
        dashAdminEventSeatsEl, dashAdminEventPriceInputEl, dashAdminEventVoluntaryCheckbox,
        dashAdminEventImageEl, dashAdminEventAssociationEl
    ];
    formElementsToWatch.forEach(element => {
        if (element) {
            element.addEventListener('input', updateDashAdminEventPreview);
            if (element.type === 'checkbox' || element.type === 'file' || element.type === 'date' || element.type === 'time' || element.tagName === 'SELECT') {
                element.addEventListener('change', updateDashAdminEventPreview);
            }
        }
    });

    if (dashAdminEventFlyerEl && dashAdminCurrentFlyerPreviewEl) {
        dashAdminEventFlyerEl.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                dashAdminCurrentFlyerPreviewEl.innerHTML = `<a href="${URL.createObjectURL(this.files[0])}" target="_blank" title="Visualizza il volantino selezionato">${sanitizeText(this.files[0].name)}</a>`;
            } else if (!dashAdminEventIdInputEl.value) {
                dashAdminCurrentFlyerPreviewEl.innerHTML = '<span>Nessun volantino caricato.</span>';
            }
        });
    }
}

async function handleFlyerProcessing() {
    if (!flyerFileEl || !flyerFileEl.files || flyerFileEl.files.length === 0) {
        if(flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = 'Nessun volantino selezionato.';
        return;
    }
    const file = flyerFileEl.files[0];
    if (!file.type.startsWith('image/')) {
        if(flyerProcessingStatusEl) flyerProcessingStatusEl.textContent = 'Per favore, carica un file immagine.';
        return;
    }

    if(processFlyerBtnEl) {
        processFlyerBtnEl.disabled = true;
        processFlyerBtnEl.innerHTML = '<div class="spinner-mini"></div> Processo AI...';
    }
    if(flyerProcessingStatusEl) {
        flyerProcessingStatusEl.textContent = 'Estrazione informazioni dal volantino in corso...';
        flyerProcessingStatusEl.className = '';
    }
    if(flyerPreviewContainerEl) flyerPreviewContainerEl.classList.remove('pulsating-background');

    try {
        const base64ImageData = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result.split(',')[1]);
            reader.onerror = error => reject(error);
            reader.readAsDataURL(file);
        });

        const extractionResult = await callGroqForFlyerExtraction(base64ImageData);

        if (extractionResult.success && extractionResult.data) {
            populateFormWithExtractedData(extractionResult.data);
            if(flyerProcessingStatusEl) {
                flyerProcessingStatusEl.textContent = 'Informazioni estratte! Controlla e completa il modulo.';
                flyerProcessingStatusEl.classList.add('success');
            }
            if(flyerUploadViewEl) flyerUploadViewEl.style.display = 'none';
            if(dashAdminFormAndPreviewContainerEl) dashAdminFormAndPreviewContainerEl.style.display = 'flex';
            if(dashAdminEventPopupTitleEl) dashAdminEventPopupTitleEl.textContent = 'Verifica Evento da Volantino';

            if (dashAdminEventFlyerEl && flyerFileEl.files.length > 0) {
                const uploadedFlyerFile = flyerFileEl.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(uploadedFlyerFile);
                dashAdminEventFlyerEl.files = dataTransfer.files;
                if (dashAdminCurrentFlyerPreviewEl) {
                    dashAdminCurrentFlyerPreviewEl.innerHTML = `<a href="${URL.createObjectURL(uploadedFlyerFile)}" target="_blank" title="Visualizza il volantino caricato">${sanitizeText(uploadedFlyerFile.name)}</a> (Appena caricato)`;
                }
                dashAdminEventFlyerEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            storeInitialFormData();
        } else {
            throw new Error(extractionResult.error || "Estrazione fallita.");
        }
    } catch (error) {
        console.error("Errore processamento volantino:", error);
        if(flyerProcessingStatusEl) {
            flyerProcessingStatusEl.textContent = `Errore: ${error.message}`;
            flyerProcessingStatusEl.classList.add('error');
        }
    } finally {
        if(processFlyerBtnEl) {
            processFlyerBtnEl.disabled = false;
            processFlyerBtnEl.innerHTML = '<i class="fas fa-cogs"></i> Processa Volantino con AI';
        }
    }
}

function populateFormWithExtractedData(data) {
    if (!data) return;

    if(dashAdminEventTitleEl) dashAdminEventTitleEl.value = data.titolo || '';
    if(dashAdminEventDateEl && data.data_inizio) dashAdminEventDateEl.value = data.data_inizio;
    if(dashAdminEventStartTimeEl && data.ora_inizio) dashAdminEventStartTimeEl.value = data.ora_inizio;
    if(dashAdminEventEndTimeEl && data.ora_fine) dashAdminEventEndTimeEl.value = data.ora_fine;

    if(dashAdminEventPrefixEl) dashAdminEventPrefixEl.value = data.relatore_prefisso || 'Relatore';
    if(dashAdminEventSpeakerEl) dashAdminEventSpeakerEl.value = data.relatore_nome || '';
    if(dashAdminEventAssociationEl) dashAdminEventAssociationEl.value = data.associazione || '';

    if(dashAdminEventShortDescEl) dashAdminEventShortDescEl.value = data.descrizione_breve_generata || '';
    if(dashAdminEventLongDescEl) dashAdminEventLongDescEl.value = data.descrizione_estesa_generata || '';

    if(dashAdminEventSeatsEl && data.posti_disponibili !== null) dashAdminEventSeatsEl.value = data.posti_disponibili;
    else if(dashAdminEventSeatsEl) dashAdminEventSeatsEl.value = '50';

    if(dashAdminEventBookingCheckbox && data.flag_prenotabile !== null) dashAdminEventBookingCheckbox.checked = data.flag_prenotabile;
    else if(dashAdminEventBookingCheckbox) dashAdminEventBookingCheckbox.checked = true;

    if(dashAdminEventVoluntaryCheckbox && data.flag_offerta_libera !== null) dashAdminEventVoluntaryCheckbox.checked = data.flag_offerta_libera;
    updateDashAdminPriceFieldVisibility();

    if(dashAdminEventPriceInputEl && data.costo !== null && !dashAdminEventVoluntaryCheckbox.checked) {
        dashAdminEventPriceInputEl.value = parseFloat(data.costo).toFixed(2);
    } else if (dashAdminEventPriceInputEl && !dashAdminEventVoluntaryCheckbox.checked) {
        dashAdminEventPriceInputEl.value = '';
    }

    if(dashAdminEventImageEl) dashAdminEventImageEl.value = '';
    if(dashAdminCurrentImagePreviewEl) {
        dashAdminCurrentImagePreviewEl.src = '#';
        dashAdminCurrentImagePreviewEl.style.display = 'none';
    }

    updateDashAdminEventPreview();
}

async function handleGenerateShortDescAI() {
    const longDesc = dashAdminLongDescElForAI.value;
    if (!longDesc.trim()) {
        if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = "Inserisci prima una descrizione estesa.";
        setTimeout(() => { if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = ""; }, 3000);
        return;
    }
    generateShortDescBtnEl.classList.add('loading');
    generateShortDescBtnEl.classList.remove('expanded');
    generateShortDescBtnEl.disabled = true;
    if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = "";

    const result = await generateShortDescriptionAI(longDesc);

    generateShortDescBtnEl.classList.remove('loading');
    generateShortDescBtnEl.disabled = false;
    if (dashAdminLongDescElForAI.value.trim().length > 0) {
        generateShortDescBtnEl.classList.add('expanded');
    }

    if (result.success && result.description) {
        dashAdminEventShortDescEl.value = result.description;
        dashAdminEventShortDescEl.dispatchEvent(new Event('input', { bubbles: true }));
        if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = "Descrizione breve generata!";
    } else {
        console.error("Errore generazione descrizione AI (Dashboard):", result.error);
        if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = `Errore AI: ${result.error || 'Sconosciuto'}`;
    }
    setTimeout(() => { if(aiShortDescStatusEl) aiShortDescStatusEl.textContent = ""; }, 4000);
}

async function handleAdminEventFormSubmit(e) {
    e.preventDefault();
    if (!dashAdminEventFormEl) return;

    const submitBtn = dashAdminEventFormEl.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true; submitBtn.innerHTML = '<div class="spinner-mini"></div>Salvataggio...';
    if(dashAdminEventFormMessageEl) { dashAdminEventFormMessageEl.style.display = 'none'; dashAdminEventFormMessageEl.className = 'form-message';}

    try {
        const formData = new FormData(dashAdminEventFormEl);
        // ACTION: Aggiungi esplicitamente l'azione
        formData.append('action', formData.get('event-id') ? 'update_event' : 'add_event');

        const startTime = formData.get('event-start-time');
        const endTime = formData.get('event-end-time');
        let durataString = '';
        if (startTime && endTime) durataString = `${startTime}-${endTime}`;
        else if (startTime) durataString = startTime;
        formData.append('event-time-combined', durataString);

        if (dashAdminEventVoluntaryCheckbox.checked) {
            formData.set('event-price', '0.00');
        } else if (!formData.get('event-price')) {
            formData.set('event-price', '0.00');
        }
        formData.set('event-booking', dashAdminEventBookingCheckbox.checked ? '1' : '0');


        const response = await callApi('gestione_eventi.php', 'POST', formData, true);

        if (response.success) {
            if(dashAdminEventFormMessageEl) {
                dashAdminEventFormMessageEl.textContent = response.message || 'Operazione completata!';
                dashAdminEventFormMessageEl.classList.add('success');
                dashAdminEventFormMessageEl.style.display = 'block';
            }
            await fetchDashboardData();
            isEventFormDirty = false;
            setTimeout(closeDashAdminEventPopup_actual, 1500);
        } else {
            throw new Error(response.message || 'Errore durante il salvataggio dell\'evento.');
        }
    } catch (error) {
        console.error('Errore invio form evento admin (Dashboard):', error);
        if(dashAdminEventFormMessageEl) {
            dashAdminEventFormMessageEl.textContent = `Errore: ${error.message}`;
            dashAdminEventFormMessageEl.classList.add('error');
            dashAdminEventFormMessageEl.style.display = 'block';
        }
    } finally {
        submitBtn.disabled = false; submitBtn.innerHTML = originalBtnText;
    }
}


// --- FLYER DISPLAY MODAL (globalFlyerOverlayEff) ---
function initGlobalFlyerOverlay() {
    if (!gebi('globalFlyerOverlayEff')) {
        const overlay = document.createElement('div');
        overlay.id = 'globalFlyerOverlayEff';
        overlay.className = 'flyer-overlay-eff';
        document.body.appendChild(overlay);
        globalFlyerOverlayEff = overlay;
        globalFlyerOverlayEff.addEventListener('click', (e) => {
            if (e.target === globalFlyerOverlayEff) hideFlyerPopupEff();
        });
    } else {
        globalFlyerOverlayEff = gebi('globalFlyerOverlayEff');
    }
}

function showFlyerPopupEff(volantinoUrl, eventTitle = "Volantino Evento") {
    if (!globalFlyerOverlayEff) {
        console.error("Flyer overlay non inizializzato.");
        return;
    }
    if (currentFlyerModalEff) currentFlyerModalEff.remove();

    document.body.classList.add("flyer-popup-active-eff");
    globalFlyerOverlayEff.classList.add("active");

    currentFlyerModalEff = document.createElement("article");
    currentFlyerModalEff.className = "flyer-modal-eff";

    let flyerContentHTML = '';
    const sanitizedUrl = typeof sanitizeForAttribute === 'function' ? sanitizeForAttribute(volantinoUrl) : volantinoUrl;

    if (volantinoUrl && volantinoUrl.trim() !== "") {
        if (volantinoUrl.match(/\.(jpeg|jpg|gif|png|webp|svg)$/i)) {
            flyerContentHTML = `<img src="${sanitizedUrl}" alt="${sanitizeForAttribute(eventTitle)}">`;
        } else if (volantinoUrl.endsWith('.pdf')) {
            flyerContentHTML = `<iframe src="${sanitizedUrl}" type="application/pdf" title="Volantino PDF: ${sanitizeForAttribute(eventTitle)}"></iframe>`;
        } else {
            flyerContentHTML = `<div class="flyer-message-display-eff"><p>Il volantino è disponibile come file esterno.</p><a href="${sanitizedUrl}" target="_blank" rel="noopener noreferrer">Visualizza Volantino: ${sanitizeText(eventTitle)}</a></div>`;
        }
    } else {
        flyerContentHTML = `<div class="flyer-message-display-eff"><p>Volantino non disponibile per: ${sanitizeText(eventTitle)}</p></div>`;
    }

    currentFlyerModalEff.innerHTML = `
        <button class="flyer-close-btn-eff" aria-label="Chiudi">&times;</button>
        <div class="flyer-content-area-eff">
            <div class="flyer-media-container-eff">${flyerContentHTML}</div>
        </div>`;

    globalFlyerOverlayEff.appendChild(currentFlyerModalEff);
    const closeBtn = currentFlyerModalEff.querySelector('.flyer-close-btn-eff');
    if (closeBtn) {
        closeBtn.onclick = hideFlyerPopupEff;
        closeBtn.focus();
    }
}

function hideFlyerPopupEff() {
    if (globalFlyerOverlayEff) globalFlyerOverlayEff.classList.remove("active");

    if (currentFlyerModalEff) {
        currentFlyerModalEff.addEventListener('transitionend', function handleTransitionEnd(event) {
            if (event.propertyName === 'opacity' && currentFlyerModalEff && !globalFlyerOverlayEff.classList.contains('active')) {
                currentFlyerModalEff.remove();
                currentFlyerModalEff = null;
                event.target.removeEventListener('transitionend', handleTransitionEnd);
            }
        });
        if (currentFlyerModalEff && !globalFlyerOverlayEff.classList.contains('active')) {
            setTimeout(() => {
                if (currentFlyerModalEff && !globalFlyerOverlayEff.classList.contains('active')) {
                    currentFlyerModalEff.remove();
                    currentFlyerModalEff = null;
                }
            }, 450);
        }
    }
    if (!isAnyPopupActive()) {
        document.body.classList.remove("flyer-popup-active-eff");
        document.body.style.overflow = '';
    }
}


// --- CUSTOM CONFIRM MODAL ---
function initializeCustomConfirmModalDOMReferences() {
    customConfirmOverlayEl = gebi('customConfirmOverlay');
    customConfirmModalEl = gebi('customConfirmModal');
    customConfirmMessageEl = gebi('customConfirmMessage');
    customConfirmYesBtn = gebi('customConfirmYes');
    customConfirmNoBtn = gebi('customConfirmNo');
}

function setupCustomConfirmModalHandlers() {
    if (customConfirmYesBtn) customConfirmYesBtn.addEventListener('click', () => handleConfirm(true));
    if (customConfirmNoBtn) customConfirmNoBtn.addEventListener('click', () => handleConfirm(false));
    if (customConfirmOverlayEl) customConfirmOverlayEl.addEventListener('click', (e) => {
        if (e.target === customConfirmOverlayEl) handleConfirm(false);
    });
}

function showCustomConfirm(message, title = "Conferma Azione") {
    return new Promise((resolve) => {
        if(customConfirmMessageEl) customConfirmMessageEl.textContent = message || "Sei sicuro di voler procedere?";
        const titleEl = customConfirmModalEl ? customConfirmModalEl.querySelector('h3') : null;
        if (titleEl) titleEl.textContent = title;

        if(customConfirmOverlayEl) customConfirmOverlayEl.classList.add('active');
        confirmResolve = resolve;
    });
}

function handleConfirm(confirmed) {
    if(customConfirmOverlayEl) customConfirmOverlayEl.classList.remove('active');
    if (confirmResolve) {
        confirmResolve(confirmed);
        confirmResolve = null;
    }
    if (!isAnyPopupActive()) {
        document.body.style.overflow = '';
    }
}


// --- DASHBOARD DATA FETCHING AND RENDERING ---
async function fetchDashboardData() {
    const upcomingGrid = gebi('upcomingEventsGrid');
    const pastGrid = gebi('pastEventsGrid');
    if (!upcomingGrid || !pastGrid) {
        console.error("Elementi griglia eventi mancanti per fetchDashboardData.");
        return;
    }
    if (upcomingGrid.children.length <= (upcomingGrid.querySelector('.add-event-btn-container-wrapper') ? 1 : 0) ) {
        upcomingGrid.innerHTML = (upcomingGrid.querySelector('.add-event-btn-container-wrapper')?.outerHTML || '') +
            '<p class="loading-message-dashboard"><i class="fas fa-spinner fa-spin"></i> Caricamento eventi futuri...</p>';
    }
    if (pastGrid.children.length === 0) {
        pastGrid.innerHTML = '<p class="loading-message-dashboard"><i class="fas fa-spinner fa-spin"></i> Caricamento archivio eventi...</p>';
    }

    try {
        const response = await callApi('get_dashboard_event_data.php');
        if (response.success) {
            allUpcomingEventsData = response.upcoming_events || [];
            allPastEventsData = response.past_events || [];
            applyFiltersAndRender();
        } else {
            const errorMsg = sanitizeText(response.message || 'Formato dati non corretto.');
            if (upcomingGrid.children.length <= (upcomingGrid.querySelector('.add-event-btn-container-wrapper') ? 1 : 0)) {
                upcomingGrid.innerHTML = (upcomingGrid.querySelector('.add-event-btn-container-wrapper')?.outerHTML || '') +
                    `<div class="error-message-dashboard"><p>Errore nel caricamento: ${errorMsg}</p></div>`;
            }
            if (pastGrid.children.length === 0) {
                pastGrid.innerHTML = `<div class="error-message-dashboard"><p>Errore nel caricamento: ${errorMsg}</p></div>`;
            }
        }
    } catch (error) {
        console.error('Errore fetchDashboardData:', error);
        const errorMsg = sanitizeText(error.message);
        if (upcomingGrid.children.length <= (upcomingGrid.querySelector('.add-event-btn-container-wrapper') ? 1 : 0)) {
            upcomingGrid.innerHTML = (upcomingGrid.querySelector('.add-event-btn-container-wrapper')?.outerHTML || '') +
                `<div class="error-message-dashboard"><p>Impossibile caricare i dati: ${errorMsg}</p> <button onclick="fetchDashboardData()" class="btn-admin">Riprova</button></div>`;
        }
        if (pastGrid.children.length === 0) {
            pastGrid.innerHTML = `<div class="error-message-dashboard"><p>Impossibile caricare i dati: ${errorMsg}</p> <button onclick="fetchDashboardData()" class="btn-admin">Riprova</button></div>`;
        }
    }
}

function applyFiltersAndRender() {
    const titleFilter = gebi('searchTitleDashboard').value.toLowerCase().trim();
    const dateFilter = gebi('searchDateDashboard').value;
    const speakerFilter = gebi('searchSpeakerDashboard').value.toLowerCase().trim();
    const sortBy = gebi('sortCriteria').value;
    const sortDirection = gebi('sortDirection').value;
    const groupBy = gebi('groupCriteria').value;

    const filterEvent = (event) => {
        const eventTitle = (event.Titolo || event.titolo || '').toLowerCase();
        const eventDateStr = event.Data || event.datainizio || '';
        const eventSpeaker = (event.relatore || '').toLowerCase();

        let titleMatch = !titleFilter || eventTitle.includes(titleFilter);
        let dateMatch = !dateFilter || (eventDateStr && eventDateStr.substring(0, 7) === dateFilter);
        let speakerMatch = !speakerFilter || eventSpeaker.includes(speakerFilter);
        return titleMatch && dateMatch && speakerMatch;
    };

    let filteredUpcoming = allUpcomingEventsData.filter(filterEvent);
    let filteredPast = allPastEventsData.filter(filterEvent);

    const compareFunction = (a, b) => {
        let valA, valB;
        let comparison = 0;
        switch (sortBy) {
            case 'mese':
                valA = new Date(a.Data || a.datainizio);
                valB = new Date(b.Data || b.datainizio);
                if (valA < valB) comparison = -1;
                if (valA > valB) comparison = 1;
                break;
            case 'titolo':
                valA = (a.Titolo || a.titolo || '').toLowerCase();
                valB = (b.Titolo || b.titolo || '').toLowerCase();
                comparison = valA.localeCompare(valB, 'it', { sensitivity: 'base' });
                break;
            case 'relatore':
                valA = (a.relatore || '').toLowerCase();
                valB = (b.relatore || '').toLowerCase();
                comparison = valA.localeCompare(valB, 'it', { sensitivity: 'base' });
                break;
            case 'prenotazioni':
                valA = parseInt(a.elenco_prenotazioni?.length) || 0;
                valB = parseInt(b.elenco_prenotazioni?.length) || 0;
                if (valA < valB) comparison = -1; if (valA > valB) comparison = 1;
                break;
            case 'post_occupati':
                valA = parseInt(a.posti_occupati) || 0;
                valB = parseInt(b.posti_occupati) || 0;
                if (valA < valB) comparison = -1; if (valA > valB) comparison = 1;
                break;
            case 'post_tot':
                valA = parseInt(a.posti_configurati_totali || a.posti_disponibili) || 0;
                valB = parseInt(b.posti_configurati_totali || b.posti_disponibili) || 0;
                if (valA < valB) comparison = -1; if (valA > valB) comparison = 1;
                break;
            default:
                valA = new Date(a.Data || a.datainizio);
                valB = new Date(b.Data || b.datainizio);
                if (valA < valB) comparison = -1; if (valA > valB) comparison = 1;
        }
        if (comparison === 0 && sortBy !== 'mese') {
            let dateA = new Date(a.Data || a.datainizio);
            let dateB = new Date(b.Data || b.datainizio);
            if (dateA < dateB) comparison = -1;
            if (dateA > valB) comparison = 1;
        }
        if (comparison === 0) {
            comparison = (parseInt(a.IDEvento || a.idevento) || 0) < (parseInt(b.IDEvento || b.idevento) || 0) ? -1 : 1;
        }
        return sortDirection === 'asc' ? comparison : -comparison;
    };

    filteredUpcoming.sort(compareFunction);
    filteredPast.sort(compareFunction);

    renderEventsSection(filteredUpcoming, 'upcomingEventsGrid', true, groupBy, sortDirection);
    renderEventsSection(filteredPast, 'pastEventsGrid', false, groupBy, sortDirection);
    updateActiveFiltersDisplay();
}

function getWeekStartDate(dInput) {
    const parts = dInput.split('-');
    const d = new Date(Date.UTC(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2])));
    const day = d.getUTCDay();
    const diff = d.getUTCDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setUTCDate(diff));
}

function getWeekRangeString(dateStr) {
    if (!dateStr || !dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) return 'Data non valida';
    try {
        const weekStart = getWeekStartDate(dateStr);
        const weekEnd = new Date(weekStart);
        weekEnd.setUTCDate(weekStart.getUTCDate() + 6);
        const options = { day: '2-digit', month: 'short', timeZone: 'UTC' };
        const year = weekStart.getUTCFullYear();
        return `Sett. ${weekStart.toLocaleDateString('it-IT', options)} - ${weekEnd.toLocaleDateString('it-IT', options)} ${year}`;
    } catch (e) {
        console.warn("Errore getWeekRangeString:", dateStr, e);
        return 'Data non valida';
    }
}

function renderEventsSection(events, gridContainerId, isUpcoming, groupBy, sortDirection) {
    const gridContainer = gebi(gridContainerId);
    if (!gridContainer) return;

    const addEventButtonHTML = gridContainer.querySelector('.add-event-btn-container-wrapper')?.outerHTML || '';
    gridContainer.innerHTML = addEventButtonHTML;

    const newAdminAddEventBtnEl = gridContainer.querySelector('#adminAddEventBtn');
    if (newAdminAddEventBtnEl) {
        newAdminAddEventBtnEl.addEventListener('click', () => openDashAdminEventPopup(null));
    }


    if (events.length === 0) {
        if (!addEventButtonHTML || gridContainer.children.length === (addEventButtonHTML ? 1:0) ) {
            gridContainer.innerHTML += `<p class="no-events-dashboard">Nessun evento ${isUpcoming ? 'futuro' : 'passato'} trovato con i filtri applicati.</p>`;
        }
        return;
    }

    if (groupBy === "nessuno") {
        events.forEach(event => gridContainer.appendChild(createAdminEventCard(event)));
        return;
    }

    const groupedEvents = events.reduce((acc, event) => {
        let groupKey = 'Non specificato';
        const eventDate = event.Data || event.datainizio;
        try {
            switch (groupBy) {
                case 'giorno': groupKey = eventDate ? formatDbDate(eventDate) : 'Data sconosciuta'; break;
                case 'settimana': groupKey = eventDate ? getWeekRangeString(eventDate) : 'Data sconosciuta'; break;
                case 'mese': groupKey = eventDate ? formatMonthYear(eventDate) : 'Data sconosciuta'; break;
                case 'anno': groupKey = eventDate ? new Date(eventDate.replace(/-/g, '/')).getUTCFullYear().toString() : 'Anno sconosciuto'; break;
                case 'relatore': groupKey = sanitizeText(event.relatore || 'Senza Relatore'); break;
                default: groupKey = eventDate ? formatMonthYear(eventDate) : 'Data sconosciuta';
            }
        } catch (e) { console.warn("Errore calcolo groupKey:", event, e); }
        if (!acc[groupKey]) acc[groupKey] = [];
        acc[groupKey].push(event);
        return acc;
    }, {});

    let sortedGroupKeys = Object.keys(groupedEvents);
    if (groupBy === 'giorno' || groupBy === 'settimana' || groupBy === 'mese' || groupBy === 'anno') {
        sortedGroupKeys.sort((a, b) => {
            let dateA, dateB;
            try {
                if (groupBy === 'giorno') {
                    const pA = a.split('/'); dateA = new Date(Date.UTC(pA[2], pA[1]-1, pA[0]));
                    const pB = b.split('/'); dateB = new Date(Date.UTC(pB[2], pB[1]-1, pB[0]));
                } else if (groupBy === 'settimana') {
                    const matchA = a.match(/Sett\. (\d{2}) (\w+)/); const yearA = parseInt(a.substring(a.lastIndexOf(' ') + 1));
                    if (matchA) dateA = new Date(Date.UTC(yearA, getMonthIndexFromString(matchA[2]), parseInt(matchA[1])));
                    const matchB = b.match(/Sett\. (\d{2}) (\w+)/); const yearB = parseInt(b.substring(b.lastIndexOf(' ') + 1));
                    if (matchB) dateB = new Date(Date.UTC(yearB, getMonthIndexFromString(matchB[2]), parseInt(matchB[1])));
                } else if (groupBy === 'mese') {
                    const pA = a.split(' '); dateA = new Date(Date.UTC(pA[1], getMonthIndexFromString(pA[0]), 1));
                    const pB = b.split(' '); dateB = new Date(Date.UTC(pB[1], getMonthIndexFromString(pB[0]), 1));
                } else if (groupBy === 'anno') {
                    dateA = new Date(Date.UTC(a, 0, 1)); dateB = new Date(Date.UTC(b, 0, 1));
                }
            } catch (e) {  }
            if (dateA && dateB && !isNaN(dateA) && !isNaN(dateB)) {
                return sortDirection === 'asc' ? dateA - dateB : dateB - dateA;
            }
            return sortDirection === 'asc' ? String(a).localeCompare(String(b)) : String(b).localeCompare(String(a));
        });
    } else if (groupBy === 'relatore') {
        sortedGroupKeys.sort((a,b) => sortDirection === 'asc' ? a.localeCompare(b,'it',{sensitivity:'base'}) : b.localeCompare(a,'it',{sensitivity:'base'}));
    }

    for (const groupKey of sortedGroupKeys) {
        const headerEl = document.createElement('h3');
        headerEl.className = 'month-year-header';
        headerEl.textContent = groupKey;
        gridContainer.appendChild(headerEl);
        groupedEvents[groupKey].forEach(event => gridContainer.appendChild(createAdminEventCard(event)));}
}

function createAdminEventCard(event) {
    const eventCard = document.createElement('article'); eventCard.className = 'admin-event-card';
    const eventId = event.IDEvento || event.idevento;
    eventCard.id = `admin-event-${eventId}`;

    const numPrenotazioni = event.elenco_prenotazioni?.length || 0;
    const partecipanti = event.posti_occupati || 0;
    const postiConfigurati = event.posti_configurati_totali || event.posti_disponibili || 0;
    const commentCount = event.comment_count || 0;
    const mediaCount = event.media_count || 0;

    let imageSrc = (event.immagine_url && event.immagine_url.trim() !== '') ? event.immagine_url : 'images/default-event-admin.jpg';
    const relatoreDisplay = event.relatore ? `<span class="admin-event-date" style="display:block; margin-top:2px; font-style:italic;">${sanitizeText(event.prefisso_relatore) || 'Relatore'}: ${sanitizeText(event.relatore)}</span>` : '';
    const eventTitle = event.Titolo || event.titolo || "Titolo sconosciuto";
    const eventDate = event.Data || event.datainizio;
    const eventDurata = event.Durata || event.durata || '';

    eventCard.innerHTML = `
        <div class="admin-event-card-image-container">
            <img src="${imageSrc}" alt="Copertina: ${sanitizeText(eventTitle)}" class="admin-event-card-image" loading="lazy" onerror="this.onerror=null;this.src='images/default-event-error.jpg';">
        </div>
        <div class="admin-event-card-content">
            <div class="admin-event-card-header">
                <h3 class="admin-event-title">${sanitizeText(eventTitle)} <small>(ID: ${eventId})</small></h3>
                <span class="admin-event-date">Data: ${formatDbDate(eventDate)} ${sanitizeText(eventDurata)}</span>
                ${relatoreDisplay}
            </div>
            <div class="admin-event-card-quick-stats">
                <div class="stat-item" title="Prenotazioni"><i class="fas fa-ticket-alt"></i> ${numPrenotazioni} Pren.</div>
                <div class="stat-item" title="Partecipanti / Posti Config."><i class="fas fa-users"></i> ${partecipanti}/${postiConfigurati}</div>
                <div class="stat-item" title="Media"><i class="fas fa-photo-video"></i> ${mediaCount} Media</div>
                <div class="stat-item" title="Commenti"><i class="fas fa-comments"></i> ${commentCount} Comm.</div>
            </div>
            <div class="admin-event-card-actions top-actions">
                <button class="btn-admin btn-admin-warning btn-manage-media" data-event-id="${eventId}" data-event-title="${sanitizeForAttribute(eventTitle)}" title="Media"><i class="fas fa-photo-video"></i> Media</button>
                <button class="btn-admin btn-admin-info btn-manage-comments" data-event-id="${eventId}" data-event-title="${sanitizeForAttribute(eventTitle)}" title="Commenti"><i class="fas fa-comments"></i> Commenti</button>
                <button class="btn-admin btn-bookings" data-event-id="${eventId}" title="Prenotazioni"><i class="fas fa-list-alt"></i> Prenotazioni</button>
            </div>
            <div class="admin-event-card-actions bottom-actions">
                <button class="btn-admin btn-admin-secondary btn-generate-pdf" data-event-id="${eventId}" title="PDF"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-admin btn-edit" data-event-id="${eventId}" title="Modifica"><i class="fas fa-edit"></i> Modifica</button>
                <button class="btn-admin btn-admin-danger btn-delete" data-event-id="${eventId}" title="Elimina"><i class="fas fa-trash-alt"></i> Elimina</button>
            </div>
        </div>`;

    const editButton = eventCard.querySelector('.btn-edit');
    if (editButton) editButton.addEventListener('click', function () {
        const eventToEdit = [...allUpcomingEventsData, ...allPastEventsData].find(e => String(e.IDEvento || e.idevento) === this.dataset.eventId);
        if (eventToEdit) openDashAdminEventPopup(eventToEdit);
        else alert("Dati evento non trovati per la modifica.");
    });

    const deleteButton = eventCard.querySelector('.btn-delete');
    if (deleteButton) deleteButton.addEventListener('click', function () {
        const eventToDelete = [...allUpcomingEventsData, ...allPastEventsData].find(e => String(e.IDEvento || e.idevento) === this.dataset.eventId);
        confirmDeleteEvent(this.dataset.eventId, eventToDelete ? (eventToDelete.Titolo || eventToDelete.titolo) : "evento sconosciuto");
    });

    const bookingsButton = eventCard.querySelector('.btn-bookings');
    if (bookingsButton) bookingsButton.addEventListener('click', function () {
        const eventForBookings = [...allUpcomingEventsData, ...allPastEventsData].find(e => String(e.IDEvento || e.idevento) === this.dataset.eventId);
        if (eventForBookings) openManageBookingsModal(eventForBookings);
        else alert("Dati evento non trovati per le prenotazioni.");
    });

    const mediaButton = eventCard.querySelector('.btn-manage-media');
    if(mediaButton) mediaButton.addEventListener('click', function() {
        openManageMediaModal(this.dataset.eventId, this.dataset.eventTitle);
    });

    const commentsButton = eventCard.querySelector('.btn-manage-comments');
    if(commentsButton) commentsButton.addEventListener('click', function() {
        openManageCommentsModal(this.dataset.eventId, this.dataset.eventTitle);
    });

    const pdfButton = eventCard.querySelector('.btn-generate-pdf');
    if(pdfButton) pdfButton.addEventListener('click', function() {
        window.open(`generate_event_pdf.php?event_id=${this.dataset.eventId}`, '_blank');
    });


    return eventCard;
}

function updateActiveFiltersDisplay() {
    const displayContainer = gebi('activeFiltersDisplayContainer');
    const listElement = gebi('activeFiltersList');
    if (!displayContainer || !listElement) return;

    listElement.innerHTML = '';
    let hasActiveFilters = false;

    const titleFilter = gebi('searchTitleDashboard').value.trim();
    const dateFilter = gebi('searchDateDashboard').value;
    const speakerFilter = gebi('searchSpeakerDashboard').value.trim();

    if (titleFilter) {
        const li = document.createElement('li');
        li.innerHTML = `<span class="filter-label">Titolo:</span> ${sanitizeText(titleFilter)}`;
        listElement.appendChild(li);
        hasActiveFilters = true;
    }
    if (dateFilter) {
        const li = document.createElement('li');
        const [year, month] = dateFilter.split('-');
        const dateObj = new Date(Date.UTC(parseInt(year), parseInt(month) - 1, 1));
        const formattedDate = dateObj.toLocaleDateString('it-IT', { month: 'long', year: 'numeric', timeZone: 'UTC' });
        li.innerHTML = `<span class="filter-label">Mese/Anno:</span> ${sanitizeText(formattedDate)}`;
        listElement.appendChild(li);
        hasActiveFilters = true;
    }
    if (speakerFilter) {
        const li = document.createElement('li');
        li.innerHTML = `<span class="filter-label">Relatore:</span> ${sanitizeText(speakerFilter)}`;
        listElement.appendChild(li);
        hasActiveFilters = true;
    }

    displayContainer.style.display = hasActiveFilters ? 'block' : 'none';
}


// --- SPECIFIC MODAL FUNCTIONALITIES ---

// Advanced Options Modal (Moderation)
function setupAdvancedOptionsModal() {
    const openBtn = gebi('openAdvancedOptionsModalBtn');
    const modal = gebi('advancedOptionsModal');
    const slider = gebi('moderationLevelSliderInput');
    const levelLabel = gebi('moderationLevelLabel');
    const levelDesc = gebi('moderationLevelDescription');
    const chatPreview = gebi('moderationPreviewChat');
    const saveBtn = gebi('saveAdvancedSettingsModalBtn');
    const messageArea = gebi('advancedSettingsModalMessage');

    const moderationLevels = [
        { label: "Nessuna Moderazione", threshold: 10, colorClass: "level-0", description: "Commenti sempre pubblicati (salvo filtri server per casi estremi)." },
        { label: "Moderazione Leggera", threshold: 6, colorClass: "level-1", description: "Blocca commenti con punteggio offensività IA ≥ 6." },
        { label: "Moderazione Standard", threshold: 4, colorClass: "level-2", description: "Blocca commenti con punteggio offensività IA ≥ 4. (Raccomandato)" },
        { label: "Moderazione Rigorosa", threshold: 2, colorClass: "level-3", description: "Blocca commenti con punteggio offensività IA ≥ 2." }
    ];
    const exampleComments = [ // Definizione completa dei commenti esempio
        { text: "Ciao, sono felice di essere parte di questa fraternità! Un saluto a tutti.", offensiveness: 1, id: "ex_comm_1" },
        { text: "Bello il nuovo logo del gruppo, ci sta un sacco!", offensiveness: 2, id: "ex_comm_2" },
        { text: "Attendo con ansia la festa di sabato, speriamo sia divertente.", offensiveness: 3, id: "ex_comm_3" },
        { text: "L'organizzazione dell'ultimo evento lasciava un po' a desiderare, spero si possa fare meglio la prossima volta.", offensiveness: 4, id: "ex_comm_4" },
        { text: "Francamente, alcuni membri dovrebbero impegnarsi di più invece di fare solo presenza e chiacchiere.", offensiveness: 5, id: "ex_comm_5" },
        { text: "Ma come diavolo ha fatto certa gente ad entrare qui? Non capiscono nulla dello spirito del gruppo. Pazzesco.", offensiveness: 6, id: "ex_comm_6" },
        { text: "Questo posto è pieno di superficiali ipocriti che pensano solo a sé stessi. La vera fratellanza è un'altra cosa, sveglia!", offensiveness: 7, id: "ex_comm_7" },
        { text: "Una massa di idioti presuntuosi, ecco cos'è diventata questa fraternità. Solo apparenza e zero sostanza. Che schifo!", offensiveness: 8, id: "ex_comm_8" },
        { text: "Quel cretino di Tizio e quell'incapace di Caio stanno mandando tutto a rotoli con le loro decisioni stupide. Via subito, incompetenti!", offensiveness: 9, id: "ex_comm_9" },
        { text: "Questo posto fa vomitare, è un covo di gente spregevole e malintenzionata. Andatevene affanc**o tutti quanti!", offensiveness: 10, id: "ex_comm_10" }
    ];

    function updateSliderUI(value) {
        const level = moderationLevels[parseInt(value)];
        if (!level) return;
        if (levelLabel) { levelLabel.textContent = level.label; levelLabel.className = 'slider-level-label ' + level.colorClass; }
        if (slider) slider.className = 'moderation-slider ' + level.colorClass;
        if (levelDesc) levelDesc.textContent = level.description;
        renderChatSimulation(level.threshold, chatPreview, exampleComments);
    }

    function renderChatSimulation(currentThreshold, chatContainer, comments) {
        if (!chatContainer) return;
        chatContainer.innerHTML = '';
        comments.forEach(comment => {
            const isEffectivelyBlocked = currentThreshold < 10 && comment.offensiveness >= currentThreshold;
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message-example';
            if (isEffectivelyBlocked) messageDiv.classList.add('blocked');
            messageDiv.innerHTML = `
                <span class="chat-user">Utente Esempio:</span>
                <span class="chat-text">${sanitizeText(comment.text)}</span>
                <div class="blocked-overlay">
                    <span class="blocked-icon"><i class="fas fa-ban"></i></span>
                    <span class="blocked-text">Messaggio filtrato dall'IA</span>
                </div>`;
            chatContainer.appendChild(messageDiv);
        });
    }


    if (openBtn) openBtn.addEventListener('click', async () => {
        if(messageArea) { messageArea.style.display = 'none'; messageArea.textContent = ''; }
        try {
            const result = await callApi('manage_global_settings.php?action=get_setting&key=comment_moderation_level');
            let sliderValueToSet = '2';
            if (result.success && result.value !== null && typeof moderationLevels[parseInt(result.value)] !== 'undefined') {
                sliderValueToSet = result.value;
            } else if (result.value === null) {
                console.log("Livello moderazione non trovato, si usa default 'Standard'.");
            } else if (!result.success && messageArea) {
                messageArea.textContent = `Errore caricamento: ${result.message}`;
                messageArea.className = 'form-message error';
                messageArea.style.display = 'block';
            }
            if (slider) slider.value = sliderValueToSet;
            updateSliderUI(sliderValueToSet);
        } catch (error) {
            console.error('Fallimento fetch impostazioni avanzate:', error);
            if (messageArea) {
                messageArea.textContent = `Errore caricamento: ${error.message}`;
                messageArea.className = 'form-message error';
                messageArea.style.display = 'block';
            }
            if (slider) slider.value = '2'; updateSliderUI('2');
        }
        openModal('advancedOptionsModal');
    });

    if (slider) slider.addEventListener('input', () => updateSliderUI(slider.value));

    if (saveBtn) saveBtn.addEventListener('click', async () => {
        const selectedLevelValue = slider.value;
        saveBtn.disabled = true; saveBtn.innerHTML = '<div class="spinner-mini"></div>Salvataggio...';
        if(messageArea) { messageArea.style.display = 'none'; messageArea.className = 'form-message';}
        try {
            const formData = new FormData(); // Usa FormData
            formData.append('action', 'update_setting');
            formData.append('key', 'comment_moderation_level');
            formData.append('value', selectedLevelValue);
            const result = await callApi('manage_global_settings.php', 'POST', formData, true); // isFormData = true

            if (result.success) {
                if(messageArea) { messageArea.textContent = result.message || 'Impostazioni salvate!'; messageArea.classList.add('success'); messageArea.style.display = 'block';}
                setTimeout(() => { closeModal('advancedOptionsModal'); }, 1500);
            } else { throw new Error(result.message || 'Errore durante il salvataggio.'); }
        } catch (error) {
            console.error('Errore salvataggio impostazioni avanzate:', error);
            if(messageArea) { messageArea.textContent = `Errore: ${error.message}`; messageArea.classList.add('error'); messageArea.style.display = 'block';}
        } finally { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Salva Impostazioni'; }
    });
}

// Manage Media Modal
async function openManageMediaModal(eventId, eventTitle) {
    const eventIdInput = gebi('uploadMediaEventId');
    const eventTitleEl = gebi('uploadMediaEventTitle');
    const existingMediaContainer = gebi('existingMediaContainer');
    const feedbackDiv = gebi('uploadMediaFeedback');
    const uploadForm = gebi('uploadMediaForm');

    if(eventIdInput) eventIdInput.value = eventId;
    if(eventTitleEl) eventTitleEl.textContent = sanitizeText(eventTitle);
    if(uploadForm) uploadForm.reset();
    if(feedbackDiv) feedbackDiv.innerHTML = '';
    if(existingMediaContainer) existingMediaContainer.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Caricamento media...</p>';

    openModal('uploadMediaModal');

    try {
        const result = await callApi(`manage_event_media.php?action=get_event_media&event_id=${eventId}`);
        if (result.success && result.media) {
            renderExistingMedia(result.media, eventId, existingMediaContainer);
        } else if (existingMediaContainer) {
            existingMediaContainer.innerHTML = `<p>${sanitizeText(result.message || 'Nessun media o errore nel caricamento.')}</p>`;
        }
    } catch(error) {
        console.error('Errore recupero media:', error);
        if(existingMediaContainer) existingMediaContainer.innerHTML = `<p class="error-message">Errore caricamento media: ${sanitizeText(error.message)}</p>`;
    }
}

function renderExistingMedia(mediaItems, eventId, container) {
    if (!container) return;
    if (mediaItems.length === 0) {
        container.innerHTML = '<p>Nessun media caricato per questo evento.</p>';
        return;
    }
    let html = '<div class="existing-media-grid">';
    mediaItems.forEach(item => {
        const filename = item.url_media ? item.url_media.split('/').pop() : 'File Sconosciuto';
        const itemDesc = sanitizeText(item.Descrizione || '');
        let mediaPreviewHTML = '';
        if (item.tipo_media === 'image') {
            mediaPreviewHTML = `<a href="${sanitizeForAttribute(item.url_media)}" target="_blank" title="Visualizza: ${sanitizeForAttribute(filename)}${itemDesc ? ' - ' + itemDesc : ''}"><img src="${sanitizeForAttribute(item.url_media)}" alt="${itemDesc || filename}" loading="lazy" onerror="this.style.display='none'; this.parentElement.nextElementSibling.style.display='flex';"></a><div class="media-icon-placeholder" style="display:none;" title="Immagine: ${sanitizeForAttribute(filename)}"><i class="fas fa-image"></i></div>`;
        } else if (item.tipo_media === 'video') {
            mediaPreviewHTML = `<a href="${sanitizeForAttribute(item.url_media)}" target="_blank" title="Guarda: ${sanitizeForAttribute(filename)}${itemDesc ? ' - ' + itemDesc : ''}"><div class="media-icon-placeholder"><i class="fas fa-video"></i></div></a>`;
        } else {
            mediaPreviewHTML = `<a href="${sanitizeForAttribute(item.url_media)}" target="_blank" title="Apri: ${sanitizeForAttribute(filename)}${itemDesc ? ' - ' + itemDesc : ''}"><div class="media-icon-placeholder"><i class="fas fa-file-alt"></i></div></a>`;
        }
        html += `
            <div class="existing-media-item">
                ${mediaPreviewHTML}
                <p class="media-filename" title="${sanitizeForAttribute(item.url_media)}">${sanitizeText(filename)}</p>
                ${itemDesc ? `<p class="media-description"><em>${itemDesc}</em></p>` : ''}
                <button class="btn-admin btn-admin-danger btn-sm btn-delete-media" data-media-id="${item.id_media}" data-event-id="${eventId}" title="Elimina Media"><i class="fas fa-trash-alt"></i> Elimina</button>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
    container.querySelectorAll('.btn-delete-media').forEach(button => {
        button.addEventListener('click', async function() {
            const confirmed = await showCustomConfirm(`Sei sicuro di voler eliminare questo media?`, "Conferma Eliminazione Media");
            if (confirmed) {
                deleteMediaItem(this.dataset.mediaId, this.dataset.eventId);
            }
        });
    });
}

async function deleteMediaItem(mediaId, eventId) {
    const feedbackDiv = gebi('uploadMediaFeedback');
    if(feedbackDiv) feedbackDiv.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Eliminazione media in corso...</p>';
    try {
        const formData = new FormData(); // Usa FormData
        formData.append('action', 'delete_event_media');
        formData.append('media_id', mediaId);
        const result = await callApi('manage_event_media.php', 'POST', formData, true); // isFormData = true
        if (result.success) {
            if(feedbackDiv) feedbackDiv.innerHTML = `<p class="success-message">${sanitizeText(result.message)}</p>`;
            openManageMediaModal(eventId, gebi('uploadMediaEventTitle')?.textContent || "Evento");
            await fetchDashboardData();
        } else {
            if(feedbackDiv) feedbackDiv.innerHTML = `<p class="error-message">Errore: ${sanitizeText(result.message)}</p>`;
        }
    } catch(error) {
        console.error('Errore eliminazione media:', error);
        if(feedbackDiv) feedbackDiv.innerHTML = `<p class="error-message">Errore eliminazione: ${sanitizeText(error.message)}</p>`;
    }
    setTimeout(() => { if(feedbackDiv && !feedbackDiv.querySelector('.error-message')) feedbackDiv.innerHTML = ''; }, 4000);
}

// Manage Comments Modal
let currentEventIdForCommentsGl = null;

async function openManageCommentsModal(eventId, eventTitle) {
    currentEventIdForCommentsGl = eventId;
    const titleEl = gebi('commentsEventTitle');
    let listContainerEl = gebi('commentsListContainer');
    const feedbackEl = gebi('commentsManagementFeedback');

    if(titleEl) titleEl.textContent = sanitizeText(eventTitle);
    if(feedbackEl) feedbackEl.innerHTML = '';

    if (listContainerEl && listContainerEl.parentNode) {
        const newClonedListContainer = listContainerEl.cloneNode(false);
        listContainerEl.parentNode.replaceChild(newClonedListContainer, listContainerEl);
        listContainerEl = newClonedListContainer;
        attachAdminCommentListeners(listContainerEl);
    } else {
        console.error("commentsListContainer non trovato o senza parent.");
        if(feedbackEl) feedbackEl.innerHTML = '<p class="error-message">Errore UI caricamento commenti.</p>';
        openModal('manageCommentsModal');
        return;
    }

    listContainerEl.innerHTML = '<li><p><i class="fas fa-spinner fa-spin"></i> Caricamento commenti...</p></li>';
    openModal('manageCommentsModal');

    try {
        const result = await callApi(`manage_comments_admin.php?action=get_comments&event_id=${eventId}`);
        if (result.success && result.comments) {
            renderCommentsForAdminThreaded(result.comments, eventId, listContainerEl);
        } else {
            listContainerEl.innerHTML = `<li><p>${sanitizeText(result.message || 'Nessun commento o errore nel caricamento.')}</p></li>`;
        }
    } catch(error) {
        console.error('Errore caricamento commenti:', error);
        listContainerEl.innerHTML = `<li><p class="error-message">Errore caricamento commenti: ${sanitizeText(error.message)}</p></li>`;
    }
}

function renderCommentsForAdminThreaded(comments, eventId, parentUlElement, depth = 0) {
    if (depth === 0) {
        parentUlElement.innerHTML = '';
        if (!comments || comments.length === 0) {
            parentUlElement.innerHTML = '<li style="list-style-type:none;text-align:center;margin:1rem 0;"><p>Nessun commento per questo evento.</p></li>';
            return;
        }
    }
    comments.forEach(comment => {
        const commentItemLi = document.createElement('li');
        commentItemLi.className = 'comment-item-admin-styled';
        commentItemLi.id = `admin-comment-${comment.Progressivo}`;
        if (comment.is_admin_reply) commentItemLi.classList.add('commento-admin-generale');

        const commenterAvatar = comment.commenter_icon_path || defaultUserIconPath;
        const commenterName = sanitizeText(comment.utente_display_name || 'Anonimo');
        const commentDate = sanitizeText(comment.data_creazione_formattata || 'N/D');
        const commentText = sanitizeTextForHTML(comment.Descrizione);
        let adminBadgeHTML = comment.is_admin_reply ? '<span class="badge-admin-dashboard">(Admin)</span>' : '';

        commentItemLi.innerHTML = `
            <div class="comment-header">
                <img src="${commenterAvatar}" alt="Avatar di ${commenterName}" class="user-avatar" onerror="this.onerror=null;this.src='${defaultUserIconPath}';">
                <div class="comment-meta">
                    <strong>${commenterName}</strong> ${adminBadgeHTML}
                    <small>${commentDate}</small>
                    <small class="comment-id-details">ID: ${comment.Progressivo}${comment.CodRisposta ? ` | Risposta a ID: ${comment.CodRisposta}` : ''}</small>
                </div>
            </div>
            <div class="comment-text-admin">${commentText}</div>
            <div class="comment-actions-admin">
                <button class="btn-admin btn-admin-reply btn-sm" data-comment-id="${comment.Progressivo}" title="Rispondi al commento"><i class="fas fa-reply"></i> Rispondi</button>
                <button class="btn-admin btn-admin-danger btn-admin-delete-comment btn-sm" data-comment-id="${comment.Progressivo}" title="Elimina commento"><i class="fas fa-trash-alt"></i> Elimina</button>
            </div>
            <div class="reply-form-admin" id="reply-form-admin-${comment.Progressivo}" style="display:none;">
                <form data-parent-comment-id="${comment.Progressivo}" data-event-id="${eventId}">
                    <textarea name="Descrizione" placeholder="Rispondi come ${adminNameGlobalJS}..." rows="3" required class="form-control"></textarea>
                    <div class="form-actions-reply" style="text-align:right; margin-top:0.5rem;">
                        <button type="submit" class="btn-admin btn-admin-primary btn-sm"><i class="fas fa-paper-plane"></i> Invia Risposta</button>
                    </div>
                    <div class="form-message-local" style="margin-top:0.5em; font-size:0.85rem;"></div>
                </form>
            </div>`;
        parentUlElement.appendChild(commentItemLi);

        if (comment.replies && comment.replies.length > 0) {
            const repliesUl = document.createElement('ul');
            repliesUl.className = 'comment-replies-list-styled';
            commentItemLi.appendChild(repliesUl);
            renderCommentsForAdminThreaded(comment.replies, eventId, repliesUl, depth + 1);
        }
    });
}

function attachAdminCommentListeners(commentsListContainerUL) {
    if (!commentsListContainerUL) return;
    commentsListContainerUL.addEventListener('click', handleCommentActionClick);
    commentsListContainerUL.addEventListener('submit', handleCommentFormSubmit);
}

async function handleCommentActionClick(event) {
    const target = event.target;
    const replyButton = target.closest('.btn-admin-reply');
    if (replyButton) {
        event.preventDefault();
        const commentId = replyButton.dataset.commentId;
        const replyFormDiv = gebi(`reply-form-admin-${commentId}`);
        if (replyFormDiv) {
            const isActive = replyFormDiv.style.display === 'block';
            replyFormDiv.style.display = isActive ? 'none' : 'block';
            if (!isActive) replyFormDiv.querySelector('textarea')?.focus();
            const localMsg = replyFormDiv.querySelector('.form-message-local');
            if (localMsg) { localMsg.textContent = ''; localMsg.style.display = 'none'; }
        }
        return;
    }
    const deleteButton = target.closest('.btn-admin-delete-comment');
    if (deleteButton) {
        event.preventDefault();
        const commentId = deleteButton.dataset.commentId;
        if (currentEventIdForCommentsGl) {
            const confirmed = await showCustomConfirm(`Sei sicuro di voler eliminare questo commento (ID: ${commentId}) e tutte le sue risposte?`, "Conferma Eliminazione Commento");
            if(confirmed) deleteAdminComment(commentId, currentEventIdForCommentsGl);
        } else {
            alert("Errore: ID evento non specificato per l'eliminazione del commento.");
        }
        return;
    }
}

async function handleCommentFormSubmit(event) {
    const targetForm = event.target.closest('.reply-form-admin form');
    if (targetForm) {
        event.preventDefault();
        const eventId = targetForm.dataset.eventId;
        await handleAdminReplySubmit(targetForm, eventId);
    }
}

async function handleAdminReplySubmit(formElement, eventId) {
    const parentCommentId = formElement.dataset.parentCommentId;
    const replyText = formElement.querySelector('textarea[name="Descrizione"]').value.trim();
    const feedbackEl = formElement.querySelector('.form-message-local');
    const submitBtn = formElement.querySelector('button[type="submit"]');

    if (!replyText) {
        if (feedbackEl) { feedbackEl.textContent = 'Il testo della risposta non può essere vuoto.'; feedbackEl.className = 'form-message-local error'; feedbackEl.style.display = 'block';}
        return;
    }
    const originalBtnHTML = submitBtn.innerHTML;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-mini" style="border-top-color:white;"></span> Invio...'; }
    if (feedbackEl) { feedbackEl.textContent = ''; feedbackEl.style.display = 'none'; }

    try {
        const formData = new FormData(); // Usa FormData
        formData.append('action', 'submit_admin_reply');
        formData.append('event_id', eventId);
        formData.append('comment_id', parentCommentId);
        formData.append('reply_text', replyText);
        const result = await callApi('manage_comments_admin.php', 'POST', formData, true); // isFormData = true

        if (result.success) {
            if (feedbackEl) { feedbackEl.textContent = 'Risposta inviata con successo!'; feedbackEl.className = 'form-message-local success'; feedbackEl.style.display = 'block'; setTimeout(() => { if (feedbackEl) { feedbackEl.style.display = 'none'; feedbackEl.textContent = ''; }}, 3000);}
            formElement.reset();
            const replyFormDiv = formElement.closest('.reply-form-admin');
            if (replyFormDiv) replyFormDiv.style.display = 'none';
            await openManageCommentsModal(eventId, gebi('commentsEventTitle')?.textContent || "Evento");
            await fetchDashboardData();
        } else {
            throw new Error(result.message || 'Errore durante l\'invio della risposta.');
        }
    } catch(error) {
        console.error("Errore invio risposta admin:", error);
        if (feedbackEl) { feedbackEl.textContent = `Errore: ${sanitizeText(error.message)}`; feedbackEl.className = 'form-message-local error'; feedbackEl.style.display = 'block';}
    } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalBtnHTML;}
    }
}

async function deleteAdminComment(commentId, eventIdForReload) {
    const feedbackDiv = gebi('commentsManagementFeedback');
    if (feedbackDiv) feedbackDiv.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Eliminazione commento in corso...</p>';
    try {
        const formData = new FormData(); // Usa FormData
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);
        const result = await callApi('manage_comments_admin.php', 'POST', formData, true); // isFormData = true
        if (result.success) {
            if (feedbackDiv) feedbackDiv.innerHTML = `<p class="success-message">${sanitizeText(result.message)}</p>`;
            await openManageCommentsModal(eventIdForReload, gebi('commentsEventTitle')?.textContent || "Evento");
            await fetchDashboardData();
        } else {
            throw new Error(result.message || "Errore durante l'eliminazione del commento.");
        }
    } catch(error) {
        console.error('Errore deleteAdminComment:', error);
        if (feedbackDiv) feedbackDiv.innerHTML = `<p class="error-message">Errore eliminazione: ${sanitizeText(error.message)}</p>`;
    }
    setTimeout(() => { if (feedbackDiv && !feedbackDiv.querySelector('.error-message')) feedbackDiv.innerHTML = ''; }, 3000);
}


// Manage Bookings Modal
function openManageBookingsModal(eventData) {
    const modalTitleEl = gebi('bookingsEventTitle');
    const bookingsContainerEl = gebi('bookingsListContainerModal');
    const feedbackEl = gebi('bookingsManagementFeedbackModal');

    if (modalTitleEl) modalTitleEl.textContent = sanitizeText(eventData.Titolo || eventData.titolo);
    if (feedbackEl) feedbackEl.innerHTML = '';
    if (bookingsContainerEl) bookingsContainerEl.innerHTML = renderGroupedBookings(eventData.elenco_prenotazioni || [], eventData.IDEvento || eventData.idevento);

    openModal('manageBookingsModal');
}

function renderGroupedBookings(bookings, eventId) {
    if (!bookings || bookings.length === 0) return '<p style="text-align:center; margin:1rem 0;">Nessuna prenotazione per questo evento.</p>';
    let html = '';
    bookings.forEach(booking => {
        const nomeCompletoUtente = `${sanitizeText(booking.NomeUtenteRegistrato || '')} ${sanitizeText(booking.CognomeUtenteRegistrato || '')}`.trim();
        const displayNomeUtente = nomeCompletoUtente || sanitizeText(booking.Contatto);
        const partecipantiHTML = booking.partecipanti_della_prenotazione && booking.partecipanti_della_prenotazione.length > 0
            ? `<ul class="participant-list-inline">${booking.partecipanti_della_prenotazione.map(p => `<li>${sanitizeText(p.Nome)} ${sanitizeText(p.Cognome)}</li>`).join('')}</ul>`
            : '<p><small>Nessun dettaglio partecipante specificato.</small></p>';
        html += `
            <div class="user-booking-group" id="booking-group-${booking.idPrenotazione}">
                <h5>Prenotazione di: ${displayNomeUtente} <small>(${sanitizeText(booking.Contatto)})</small></h5>
                <div class="single-booking-details">
                    <p><strong>ID Prenotazione: ${booking.idPrenotazione}</strong> - Posti Prenotati: ${booking.NumeroPosti}</p>
                    <strong>Partecipanti:</strong>
                    ${partecipantiHTML}
                    <button class="btn-admin btn-admin-danger btn-sm" onclick="confirmCancelBookingInModal(${booking.idPrenotazione}, ${eventId}, ${booking.NumeroPosti}, '${sanitizeForAttribute(displayNomeUtente)}')" title="Annulla Prenotazione"><i class="fas fa-times-circle"></i> Annulla Prenotazione</button>
                </div>
            </div>`;
    });
    return html;
}

async function confirmCancelBookingInModal(idPrenotazione, eventId, numPosti, nomeUtente) {
    const confirmed = await showCustomConfirm(`Sei sicuro di voler annullare la prenotazione ID ${idPrenotazione} (${numPosti} posti) di ${nomeUtente}?`, "Conferma Annullamento Prenotazione");
    if (confirmed) {
        cancelBookingInModal(idPrenotazione, eventId);
    }
}

async function cancelBookingInModal(idPrenotazione, eventIdForModalReload) {
    const feedbackEl = gebi('bookingsManagementFeedbackModal');
    if(feedbackEl) feedbackEl.innerHTML = `<p><i class="fas fa-spinner fa-spin"></i> Annullamento prenotazione in corso...</p>`;
    try {
        const formData = new FormData(); // Usa FormData
        formData.append('action', 'cancel_booking'); // Aggiungi action
        formData.append('id_prenotazione', idPrenotazione);
        const result = await callApi('annulla_prenotazione_admin.php', 'POST', formData, true); // isFormData = true
        if (feedbackEl) {
            if (result.success) {
                feedbackEl.innerHTML = `<p class="success-message"><i class="fas fa-check-circle"></i> ${sanitizeText(result.message)}</p>`;
                await fetchDashboardData();
                const updatedEventData = [...allUpcomingEventsData, ...allPastEventsData].find(e => String(e.IDEvento || e.idevento) === String(eventIdForModalReload));
                if(updatedEventData) {
                    const bookingsContainerEl = gebi('bookingsListContainerModal');
                    if(bookingsContainerEl) bookingsContainerEl.innerHTML = renderGroupedBookings(updatedEventData.elenco_prenotazioni || [], updatedEventData.IDEvento || updatedEventData.idevento);
                } else {
                    closeModal('manageBookingsModal');
                }
            } else {
                feedbackEl.innerHTML = `<p class="error-message"><i class="fas fa-exclamation-circle"></i> Errore: ${sanitizeText(result.message || 'Impossibile annullare la prenotazione.')}</p>`;
            }
        }
    } catch (error) {
        console.error("Errore AJAX annullamento prenotazione:", error);
        if (feedbackEl) feedbackEl.innerHTML = `<p class="error-message"><i class="fas fa-exclamation-circle"></i> Errore di comunicazione: ${sanitizeText(error.message)}</p>`;
    }
}


// --- EVENT DELETION ---
async function confirmDeleteEvent(eventId, eventTitle) {
    const confirmed = await showCustomConfirm(`ATTENZIONE: Sei sicuro di voler eliminare l'evento "${sanitizeText(eventTitle)}" (ID: ${eventId}) e tutti i dati associati (prenotazioni, commenti, media)? L'azione è IRREVERSIBILE.`, "Conferma Eliminazione Evento");
    if (confirmed) {
        const cardEl = gebi(`admin-event-${eventId}`);
        if (cardEl) cardEl.style.opacity = '0.5';
        try {
            const formData = new FormData(); // Usa FormData
            formData.append('action', 'delete_event');
            formData.append('event_id', eventId);
            const result = await callApi('gestione_eventi.php', 'POST', formData, true); // isFormData = true
            alert(sanitizeText(result.message));
            if (result.success) {
                await fetchDashboardData();
            } else {
                if (cardEl) cardEl.style.opacity = '1';
            }
        } catch(error) {
            console.error('Errore deleteEvent:', error);
            alert(`Errore durante l'eliminazione: ${sanitizeText(error.message)}`);
            if (cardEl) cardEl.style.opacity = '1';
        }
    }
}

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', async () => {
    gebi('currentYear').textContent = new Date().getFullYear();

    setupNavbar();
    setupModalCloseButtons();

    initializeNewEventModalDOMReferences();
    setupNewEventModalHandlers();

    initializeCustomConfirmModalDOMReferences();
    setupCustomConfirmModalHandlers();

    initGlobalFlyerOverlay();

    const tabButtons = document.querySelectorAll('.event-tabs-container-main .event-tab-button');
    const tabContents = document.querySelectorAll('#eventManagementContent .event-tab-content');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (!button.id || !button.id.startsWith('filterToggle')) {
                tabButtons.forEach(btn => { if (!btn.id || !btn.id.startsWith('filterToggle')) btn.classList.remove('active'); });
                button.classList.add('active');
                const targetTab = button.dataset.tab;
                tabContents.forEach(content => {
                    const isActiveTab = content.id === `${targetTab}EventsContainer`;
                    content.style.display = isActiveTab ? 'block' : 'none';
                    if(isActiveTab) content.classList.add('active'); else content.classList.remove('active');
                });
            }
        });
    });

    const filterToggleButton = gebi('filterToggleButton');
    const filterPanelDropdown = gebi('filterPanelDropdown');
    const applyFiltersFromPanelBtn = gebi('applyFiltersFromPanelBtn');
    const resetFiltersBtnPanel = gebi('resetFiltersBtnPanel');

    if (filterToggleButton && filterPanelDropdown) {
        filterToggleButton.addEventListener('click', function(event) {
            event.stopPropagation();
            const isActive = filterPanelDropdown.classList.toggle('active');
            filterToggleButton.classList.toggle('active', isActive);
            filterToggleButton.setAttribute('aria-expanded', isActive.toString());
        });
    }
    if (applyFiltersFromPanelBtn) applyFiltersFromPanelBtn.addEventListener('click', () => {
        applyFiltersAndRender();
        if (filterPanelDropdown) filterPanelDropdown.classList.remove('active');
        if (filterToggleButton) { filterToggleButton.classList.remove('active'); filterToggleButton.setAttribute('aria-expanded', 'false');}
    });
    if (resetFiltersBtnPanel) resetFiltersBtnPanel.addEventListener('click', () => {
        const searchTitleInput = gebi('searchTitleDashboard'); if(searchTitleInput) searchTitleInput.value = '';
        const searchDateInput = gebi('searchDateDashboard'); if(searchDateInput) searchDateInput.value = '';
        const searchSpeakerInput = gebi('searchSpeakerDashboard'); if(searchSpeakerInput) searchSpeakerInput.value = '';
        applyFiltersAndRender();
        if (filterPanelDropdown) filterPanelDropdown.classList.remove('active');
        if (filterToggleButton) { filterToggleButton.classList.remove('active'); filterToggleButton.setAttribute('aria-expanded', 'false');}
    });

    const sortCriteriaSelect = gebi('sortCriteria');
    const sortDirectionSelect = gebi('sortDirection');
    const groupCriteriaSelect = gebi('groupCriteria');
    if(sortCriteriaSelect) sortCriteriaSelect.addEventListener('change', applyFiltersAndRender);
    if(sortDirectionSelect) sortDirectionSelect.addEventListener('change', applyFiltersAndRender);
    if(groupCriteriaSelect) groupCriteriaSelect.addEventListener('change', applyFiltersAndRender);

    const eventUploadMediaForm = gebi('uploadMediaForm');
    if(eventUploadMediaForm) eventUploadMediaForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const eventId = gebi('uploadMediaEventId').value;
        const feedbackDiv = gebi('uploadMediaFeedback');
        const mediaFilesInput = gebi('mediaFiles');

        if (mediaFilesInput.files.length === 0) {
            if(feedbackDiv) feedbackDiv.innerHTML = '<p class="error-message">Nessun file selezionato per il caricamento.</p>';
            return;
        }
        if(feedbackDiv) feedbackDiv.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Caricamento media in corso...</p>';
        const formData = new FormData(this);
        formData.set('action', 'upload_media');
        formData.set('event_id', eventId);

        try {
            const result = await callApi('manage_event_media.php', 'POST', formData, true);
            if (result.success) {
                if(feedbackDiv) feedbackDiv.innerHTML = `<p class="success-message">${sanitizeText(result.message)}</p>`;
                this.reset();
                openManageMediaModal(eventId, gebi('uploadMediaEventTitle')?.textContent || "Evento");
                await fetchDashboardData();
            } else {
                if(feedbackDiv) feedbackDiv.innerHTML = `<p class="error-message">Errore: ${sanitizeText(result.message)}</p>`;
            }
        } catch(error) {
            console.error('Errore uploadMedia:', error);
            if(feedbackDiv) feedbackDiv.innerHTML = `<p class="error-message">Errore di comunicazione durante l'upload: ${sanitizeText(error.message)}</p>`;
        }
        setTimeout(() => { if(feedbackDiv && !feedbackDiv.querySelector('.error-message')) feedbackDiv.innerHTML = ''; }, 5000);
    });

    setupAdvancedOptionsModal();

    await fetchAndPrepareGroqApiKey();
    await fetchDashboardData();
    updateActiveFiltersDisplay();

    document.addEventListener("keydown", async (e) => {
        if (e.key === "Escape") {
            if (gebi('dashAdminEventPopup')?.classList.contains("active")) await tryCloseDashAdminEventPopup();
            else if (gebi('globalFlyerOverlayEff')?.classList.contains("active")) hideFlyerPopupEff();
            else if (gebi('customConfirmOverlay')?.classList.contains('active')) handleConfirm(false);
            else {
                const activeGenericModal = document.querySelector('.modal.active');
                if (activeGenericModal) closeModal(activeGenericModal.id);
            }
        }
    });

    console.log("dashboard.js caricato e inizializzato.");
});
