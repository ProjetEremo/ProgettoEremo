// js/app_global.js

// --- Variabili Globali Essenziali ---
let currentUserData = null; // Conterrà i dati dell'utente loggato { id, nome, cognome, email, iconPath, isAdmin }
const defaultUserIconPath = '/uploads/icons/default_user.png'; // Assicurati che il path sia corretto dalla root
const defaultNavbarLogoPath = '/images/Logo_eremo.png'; // Path di fallback per il logo navbar

// --- Funzioni Helper Generali ---

/**
 * Esegue una chiamata API fetch.
 * @param {string} url - L'URL dell'API.
 * @param {string} method - Metodo HTTP (GET, POST, PUT, DELETE).
 * @param {object|FormData|null} data - Dati da inviare (oggetto JSON o FormData).
 * @param {boolean} isFormData - True se 'data' è FormData.
 * @returns {Promise<object|null>} - La risposta JSON parsata o null per 204.
 * @throws {Error} - In caso di errore di rete o risposta non OK.
 */
async function callApi(url, method = 'GET', data = null, isFormData = false) {
    const headers = {};
    if (!isFormData) {
        headers['Content-Type'] = 'application/json';
    }
    headers['Accept'] = 'application/json';

    const options = { method, headers, credentials: 'same-origin' };

    if (data) {
        options.body = isFormData ? data : JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            let errorData = { message: `Errore HTTP ${response.status} (${response.statusText || 'Errore Sconosciuto'}) per ${url}` };
            try {
                const errorJson = await response.json();
                errorData = { ...errorData, ...errorJson };
                if (response.status === 401 && errorJson.action_required === 'relogin') {
                    console.warn(`API (${url}) ha richiesto il relogin (401).`);
                    // La gestione UI del relogin viene fatta da checkUserSessionOnLoad
                }
            } catch (e) {
                const errorText = await response.text().catch(() => response.statusText);
                errorData.message = errorText || errorData.message;
            }
            throw new Error(errorData.message);
        }
        return response.status === 204 ? null : await response.json();
    } catch (error) {
        console.error(`Fallimento chiamata API a ${url}:`, error.message ? error.message : error);
        throw error;
    }
}

/**
 * Normalizza il path di un'icona per la visualizzazione.
 * @param {string|null} path - Il path dell'icona.
 * @returns {string} - Il path normalizzato.
 */
function normalizeIconPath(path) {
    if (!path || typeof path !== 'string' || path.trim() === '') return defaultUserIconPath;
    if (path.startsWith('data:image/')) return path;
    return path.startsWith('/') ? path : `/${path}`; // Assicura che inizi con / se relativo
}

/**
 * Mostra un messaggio all'utente (es. per form, notifiche globali).
 * @param {string} elementId - ID dell'elemento HTML dove mostrare il messaggio.
 * @param {string} message - Il testo del messaggio.
 * @param {boolean} isSuccess - True per messaggio di successo, false per errore.
 * @param {boolean} clearAfter - Se true, il messaggio scompare dopo un timeout.
 * @param {boolean} isToastStyle - Se true, applica stili per un toast (richiede CSS).
 */
function displayMessage(elementId, message, isSuccess, clearAfter = true, isToastStyle = false) {
    const el = document.getElementById(elementId);
    if (!el) {
        console.warn(`Elemento con ID '${elementId}' non trovato per displayMessage.`);
        return;
    }
    el.innerHTML = message; // Attenzione: se il messaggio contiene HTML, considera sanitizzazione
    el.className = 'form-message'; // Reset classi
    el.classList.add(isSuccess ? 'success' : 'error');

    if (isToastStyle) {
        el.classList.add('toast-style'); // Assicurati che .toast-style sia definito nel tuo CSS
        requestAnimationFrame(() => { el.classList.add('show'); }); // Per animazione fade-in
    } else {
        el.classList.remove('toast-style', 'show');
    }
    el.style.display = 'block';

    if (clearAfter) {
        const timeoutDuration = isToastStyle ? 3500 : (isSuccess ? 5000 : 7000);
        setTimeout(() => {
            if (el) {
                if (isToastStyle && el.classList.contains('toast-style')) {
                    el.classList.remove('show');
                    // Attendi la fine della transizione CSS per nascondere l'elemento
                    el.addEventListener('transitionend', () => {
                        if (el.classList.contains('toast-style')) { el.style.display = 'none'; }
                    }, { once: true });
                } else if (!isToastStyle) {
                    el.style.display = 'none';
                }
            }
        }, timeoutDuration);
    }
}

/**
 * Verifica se c'è qualche popup attivo sulla pagina.
 * @returns {boolean} True se almeno un popup è attivo.
 */
function isAnyPopupActive() {
    const popups = [
        document.getElementById('loginPopup'),
        document.getElementById('forgotPasswordPopup'),
        document.getElementById('bookingPopup'),
        document.getElementById('waitlistPopup'),
        document.getElementById('dashAdminEventPopup'),
        document.getElementById('globalFlyerOverlayEff'), // Per il popup volantino globale
        document.getElementById('customConfirmOverlay'), // Per il confirm custom
        document.getElementById('editContentModal'), // Per modifica contenuti index
        document.getElementById('ai-chat-popup') // Per assistente AI
        // Aggiungi altri ID di popup se necessario
    ];
    return popups.some(p => p && (p.classList.contains('active') || p.style.display === 'flex' || p.style.display === 'block'));
}


// --- Gestione Sessione Utente ---

/**
 * Pulisce i dati utente lato client.
 */
function clearClientSideUser() {
    currentUserData = null;
    localStorage.removeItem('userDataEFF');
    // localStorage.removeItem('pending_action_eff'); // Rimuovi anche azioni pendenti
}

/**
 * Controlla la sessione utente con il server al caricamento della pagina.
 * Aggiorna currentUserData e l'UI.
 */
async function checkUserSessionOnLoad() {
    console.log("checkUserSessionOnLoad: Inizio controllo sessione.");
    try {
        // Il path all'API è relativo alla root del sito.
        const sessionData = await callApi('/api/api_check_session.php');

        if (sessionData && sessionData.active && sessionData.user && sessionData.user.id) {
            currentUserData = { ...sessionData.user }; // Copia i dati utente
            currentUserData.iconPath = normalizeIconPath(currentUserData.iconPath);
            localStorage.setItem('userDataEFF', JSON.stringify(currentUserData));
            console.log("checkUserSessionOnLoad: Sessione attiva, utente:", currentUserData.email);
        } else {
            clearClientSideUser();
            console.log("checkUserSessionOnLoad: Sessione non attiva o utente non loggato.");
            if (sessionData && sessionData.logout_reason) {
                let reasonMsg = "Sessione terminata";
                if(sessionData.logout_reason === 'inactivity') reasonMsg = "Sessione scaduta per inattività.";
                else if(sessionData.logout_reason === 'duration') reasonMsg = "Sessione scaduta per durata massima.";
                // Potresti mostrare un messaggio all'utente qui, es. usando displayMessage
                console.warn(`Motivo logout: ${sessionData.logout_reason}`);
            }
        }
    } catch (error) {
        console.error('Errore grave durante checkUserSessionOnLoad:', error.message);
        clearClientSideUser(); // Logout forzato lato client in caso di errore API
    } finally {
        updateNavbarUI(); // Aggiorna sempre la navbar
        // Chiama una funzione specifica della pagina per inizializzare l'UI post-check sessione
        if (typeof initializePageSpecificUI === 'function') {
            initializePageSpecificUI();
        } else {
            console.warn("Funzione 'initializePageSpecificUI' non definita per questa pagina.");
        }
    }
}

/**
 * Gestisce il logout dell'utente.
 * @param {boolean} showAlert - Se mostrare un alert di conferma.
 */
async function handleLogout(showAlert = true) {
    if (showAlert) {
        // Potresti usare un custom confirm qui se vuoi
        const confirmed = window.confirm("Sei sicuro di voler uscire?");
        if (!confirmed) return;
    }

    try {
        await callApi('/api/api_logout.php', 'POST');
        if (showAlert) {
            // Non usare alert se showAlert è false (es. per eliminazione account)
            // Potresti usare displayMessage per una notifica più elegante
            console.log('Logout dal server riuscito.');
        }
    } catch (error) {
        console.error("Errore API logout:", error.message);
        if (showAlert) {
            // alert('Errore durante il logout dal server, ma verrai disconnesso localmente.');
            console.warn('Errore durante il logout dal server, ma verrai disconnesso localmente.');
        }
    } finally {
        clearClientSideUser();
        updateNavbarUI(); // Aggiorna subito la UI
        // Reindirizza alla homepage
        if (window.location.pathname !== '/index.html' && window.location.pathname !== '/') {
            window.location.href = '/index.html';
        } else {
            // Se già sulla homepage, ricarica o aggiorna la UI specifica della homepage
            if (typeof initializePageSpecificUI === 'function') {
                initializePageSpecificUI(); // Per aggiornare la homepage per utente sloggato
            }
        }
    }
}


// --- Gestione UI Globale (Navbar, Popup Login) ---

/**
 * Aggiorna l'interfaccia utente della Navbar in base allo stato di login.
 */
function updateNavbarUI() {
    const el = {
        loginBtnDesktop: document.getElementById('login-btn-desktop'),
        loginBtnMobile: document.getElementById('login-btn-mobile'),
        userDropdownContainer: document.getElementById('userDropdownContainer'),
        navbarUsername: document.getElementById('navbar-username'),
        navbarAvatarImg: document.querySelector('#navbar-avatar img'),
        // Link specifici del menu utente
        navProfilo: document.getElementById('nav-profilo'),
        mobileNavProfilo: document.getElementById('mobile-nav-profilo'),
        navMiePrenotazioni: document.getElementById('nav-mie-prenotazioni'),
        mobileNavMiePrenotazioni: document.getElementById('mobile-nav-mie-prenotazioni'),
        navDashboard: document.getElementById('nav-dashboard'), // Admin
        mobileNavDashboard: document.getElementById('mobile-nav-dashboard'), // Admin
        navGestioneEventiAdmin: document.getElementById('nav-gestione-eventi-admin'), // Admin
        mobileNavGestioneEventiAdmin: document.getElementById('mobile-nav-gestione-eventi-admin'), // Admin (o link specifico se diverso)
        logoutLinkDesktop: document.getElementById('logout-link-desktop'), // Assumendo che esista
        mobileLogoutLink: document.getElementById('mobile-logout-link'),
        mobileMenuSeparator: document.getElementById('mobile-menu-separator'),
        navbarTitle: document.getElementById('navbar-title') // Titolo Eremo / Eremo (Admin)
    };

    // Nascondi tutto di default, poi mostra in base allo stato
    if (el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'none';
    if (el.loginBtnMobile) el.loginBtnMobile.style.display = 'none';
    if (el.userDropdownContainer) el.userDropdownContainer.style.display = 'none';
    if (el.navProfilo) el.navProfilo.style.display = 'none';
    if (el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'none';
    if (el.navMiePrenotazioni) el.navMiePrenotazioni.style.display = 'none';
    if (el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'none';
    if (el.navDashboard) el.navDashboard.style.display = 'none';
    if (el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'none';
    if (el.navGestioneEventiAdmin) el.navGestioneEventiAdmin.style.display = 'none';
    if (el.mobileNavGestioneEventiAdmin) el.mobileNavGestioneEventiAdmin.style.display = 'none';
    if (el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'none';
    if (el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'none';

    if (currentUserData && currentUserData.id) { // Utente Loggato
        if (el.userDropdownContainer) el.userDropdownContainer.style.display = 'inline-block'; // o 'flex'
        if (el.navbarUsername) el.navbarUsername.textContent = currentUserData.nome || currentUserData.email.split('@')[0];
        if (el.navbarAvatarImg) {
            el.navbarAvatarImg.src = currentUserData.iconPath || defaultUserIconPath;
            el.navbarAvatarImg.alt = `Avatar di ${currentUserData.nome || 'utente'}`;
            el.navbarAvatarImg.onerror = function() { this.src = defaultUserIconPath; };
        }

        if (el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'block';
        if (el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'block';
        if (el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'block';
        if (el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'block';

        if (currentUserData.isAdmin) {
            if (el.navProfilo) el.navProfilo.style.display = 'block'; // Anche l'admin ha un profilo
            if (el.navDashboard) el.navDashboard.style.display = 'block';
            if (el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'block';
            // Gestione eventi per admin (se il link è diverso da dashboard)
            if (el.navGestioneEventiAdmin) el.navGestioneEventiAdmin.style.display = 'block';
            if (el.mobileNavGestioneEventiAdmin) el.mobileNavGestioneEventiAdmin.style.display = 'block';
            if (el.navbarTitle) el.navbarTitle.textContent = "Eremo (Admin)";
        } else { // Utente normale loggato
            if (el.navProfilo) el.navProfilo.style.display = 'block';
            if (el.navMiePrenotazioni) el.navMiePrenotazioni.style.display = 'block';
            if (el.navbarTitle) el.navbarTitle.textContent = "Eremo Frate Francesco";
        }
    } else { // Utente NON Loggato
        if (el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'inline-block';
        if (el.loginBtnMobile) el.loginBtnMobile.style.display = 'block';
        if (el.navbarTitle) el.navbarTitle.textContent = "Eremo Frate Francesco";
    }
    // Assicurati che i listener per il logout siano attivi se i pulsanti sono visibili
    if (el.logoutLinkDesktop) el.logoutLinkDesktop.onclick = (e) => { e.preventDefault(); handleLogout(); };
    if (el.mobileLogoutLink) el.mobileLogoutLink.onclick = (e) => { e.preventDefault(); handleLogout(); closeMobileMenu(); }; // Aggiungi closeMobileMenu se necessario
}


/**
 * Gestisce l'apertura e la chiusura del menu mobile.
 */
function toggleMobileMenu() {
    const mobileMenuEl = document.getElementById("mobileMenu");
    const hamburgerBtnEl = document.getElementById("hamburgerBtn"); // Assumendo che il bottone hamburger abbia questo ID
    if (mobileMenuEl && hamburgerBtnEl) {
        const isOpen = mobileMenuEl.classList.toggle("open");
        hamburgerBtnEl.setAttribute("aria-expanded", String(isOpen));
        document.body.style.overflow = isOpen ? "hidden" : "";
    }
}

/**
 * Chiude il menu mobile (es. al click su un link).
 */
function closeMobileMenu() {
    const mobileMenuEl = document.getElementById("mobileMenu");
    const hamburgerBtnEl = document.getElementById("hamburgerBtn");
    if (mobileMenuEl && hamburgerBtnEl && mobileMenuEl.classList.contains("open")) {
        mobileMenuEl.classList.remove("open");
        hamburgerBtnEl.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }
}

/**
 * Gestisce l'apertura/chiusura del dropdown utente.
 * @param {Event} event - L'evento click.
 */
function toggleUserMenu(event) {
    if (event) event.stopPropagation(); // Previene la chiusura immediata dal listener globale
    const userDropdownMenuEl = document.getElementById('userDropdownMenu');
    const userBtnTriggerEl = document.getElementById('userBtnTrigger'); // Assumendo che il bottone trigger abbia questo ID

    if (userDropdownMenuEl && userBtnTriggerEl) {
        const isCurrentlyOpen = userDropdownMenuEl.style.display === 'block';
        userDropdownMenuEl.style.display = isCurrentlyOpen ? 'none' : 'block';
        userBtnTriggerEl.setAttribute('aria-expanded', String(!isCurrentlyOpen));
    }
}


/**
 * Gestisce il popup di login/registrazione.
 */
function setupLoginRegistrationPopup() {
    const loginOverlayEl = document.getElementById('loginOverlay');
    const loginPopupEl = document.getElementById('loginPopup');
    const closeLoginPopupBtnEl = document.getElementById('closeLoginPopupBtn');
    const loginTabsNodeList = document.querySelectorAll('.login-tab'); // NodeList
    const loginFormsNodeList = document.querySelectorAll('.login-popup .login-form'); // NodeList

    const loginFormEl = document.getElementById('loginForm');
    const registerFormEl = document.getElementById('registerForm');
    const loginErrorMessageEl = document.getElementById('login-error-message');
    const registerErrorMessageEl = document.getElementById('register-error-message');

    const forgotPasswordLinkEl = document.getElementById('forgotPasswordLink');
    const forgotPasswordOverlayEl = document.getElementById('forgotPasswordOverlay');
    const forgotPasswordPopupEl = document.getElementById('forgotPasswordPopup');
    const closeForgotPasswordPopupBtnEl = document.getElementById('closeForgotPasswordPopupBtn');
    const forgotPasswordFormEl = document.getElementById('forgotPasswordForm');
    const forgotPasswordMessageEl = document.getElementById('forgot-password-message');

    function openLoginPopup() {
        if (loginOverlayEl) loginOverlayEl.classList.add('active');
        if (loginPopupEl) loginPopupEl.classList.add('active');
        document.body.style.overflow = 'hidden';
        resetFormErrorsAndMessages();
        // Attiva il tab di login di default e focus sul campo email
        const loginTab = Array.from(loginTabsNodeList).find(tab => tab.dataset.tab === 'login');
        if (loginTab) loginTab.click();
        const loginEmailInput = document.getElementById('login-email');
        if (loginEmailInput) loginEmailInput.focus();
    }

    function closeLoginPopup() {
        if (loginOverlayEl) loginOverlayEl.classList.remove('active');
        if (loginPopupEl) loginPopupEl.classList.remove('active');
        if (!isAnyPopupActive()) document.body.style.overflow = '';
        resetFormErrorsAndMessages();
    }
    
    function resetFormErrorsAndMessages() {
        loginFormsNodeList.forEach(form => {
            form.querySelectorAll('.form-control.error-border').forEach(input => input.classList.remove('error-border'));
        });
        if (loginErrorMessageEl) loginErrorMessageEl.style.display = 'none';
        if (registerErrorMessageEl) registerErrorMessageEl.style.display = 'none';
        if (forgotPasswordMessageEl) forgotPasswordMessageEl.style.display = 'none';
    }

    // Listener per aprire il popup di login
    document.getElementById('login-btn-desktop')?.addEventListener('click', openLoginPopup);
    const loginBtnMobile = document.getElementById('login-btn-mobile');
    if (loginBtnMobile) {
        loginBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            openLoginPopup();
            closeMobileMenu(); // Chiudi il menu mobile se aperto
        });
    }
    // Listener per link "Accedi" specifico in evento_dettaglio_accesso.html
    document.getElementById('loginLinkFromEventPage')?.addEventListener('click', (e) => {
        e.preventDefault();
        openLoginPopup();
    });


    if (closeLoginPopupBtnEl) closeLoginPopupBtnEl.addEventListener('click', closeLoginPopup);
    if (loginOverlayEl) {
        loginOverlayEl.addEventListener('click', (e) => {
            if (e.target === loginOverlayEl) closeLoginPopup();
        });
    }

    loginTabsNodeList.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.dataset.tab;
            loginTabsNodeList.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            loginFormsNodeList.forEach(form => {
                form.classList.remove('active');
                if (form.id === `${tabId}Form`) {
                    form.classList.add('active');
                    const firstInput = form.querySelector('input:not([type="hidden"]):not([type="checkbox"])');
                    if (firstInput) firstInput.focus();
                }
            });
            resetFormErrorsAndMessages();
        });
    });

    loginFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetFormErrorsAndMessages();
        const submitButton = loginFormEl.querySelector('button[type="submit"]');
        const originalButtonHTML = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Accesso...<div class="spinner-mini-light"></div>';

        try {
            const formData = new FormData(loginFormEl);
            const result = await callApi('/login.php', 'POST', formData, true); // Usa il path corretto

            if (result.success && result.user) {
                currentUserData = { ...result.user };
                currentUserData.iconPath = normalizeIconPath(currentUserData.iconPath);
                localStorage.setItem('userDataEFF', JSON.stringify(currentUserData));
                updateNavbarUI();
                closeLoginPopup();

                // Gestione azione pendente dopo il login
                const pendingActionJSON = localStorage.getItem("pending_action_eff");
                if (pendingActionJSON) {
                    localStorage.removeItem("pending_action_eff");
                    const pendingAction = JSON.parse(pendingActionJSON);
                    if (typeof handlePendingAction === "function") {
                        handlePendingAction(pendingAction);
                    } else {
                        console.warn("Funzione handlePendingAction non trovata per gestire azione pendente:", pendingAction);
                    }
                } else if (result.redirect_url) {
                    window.location.href = result.redirect_url; // Reindirizza se specificato dal server
                } else {
                    // Ricarica la pagina corrente o aggiorna UI specifica se necessario
                    if (typeof initializePageSpecificUI === 'function') {
                        initializePageSpecificUI();
                    } else {
                        // window.location.reload(); // Fallback se non c'è una funzione specifica
                    }
                }
                 // Mostra un messaggio di benvenuto se necessario
                if (typeof showPageStatus === 'function' && currentUserData.nome) { // showPageStatus è specifica di index.html
                    showPageStatus(`Bentornato/a, ${currentUserData.nome}!`, "success");
                }


            } else {
                loginFormEl.querySelectorAll('#login-email, #login-password').forEach(el => el.classList.add('error-border'));
                if (loginErrorMessageEl) {
                    loginErrorMessageEl.textContent = result.message || "Credenziali errate o utente non trovato.";
                    loginErrorMessageEl.style.display = 'block';
                }
            }
        } catch (error) {
            console.error("Errore durante il login:", error);
            if (loginErrorMessageEl) {
                loginErrorMessageEl.textContent = error.message || "Errore di connessione o del server.";
                loginErrorMessageEl.style.display = 'block';
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
        }
    });

    registerFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetFormErrorsAndMessages();
        const submitButton = registerFormEl.querySelector('button[type="submit"]');
        const originalButtonHTML = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Registrazione...<div class="spinner-mini-light"></div>';

        try {
            const formData = new FormData(registerFormEl);
            const result = await callApi('/registrati.php', 'POST', formData, true); // Usa il path corretto

            if (result.success) {
                alert(result.message || "Registrazione completata! Ora puoi accedere.");
                const loginTab = Array.from(loginTabsNodeList).find(tab => tab.dataset.tab === 'login');
                if (loginTab) loginTab.click();
                const loginEmailInput = document.getElementById('login-email');
                const registerEmailInput = document.getElementById('register-email');
                if (loginEmailInput && registerEmailInput) loginEmailInput.value = registerEmailInput.value;
                registerFormEl.reset();
            } else {
                if (registerErrorMessageEl) {
                    registerErrorMessageEl.textContent = result.message || "Errore durante la registrazione.";
                    registerErrorMessageEl.style.display = 'block';
                }
                if (result.errors) { // Se il PHP restituisce un oggetto 'errors'
                    for (const fieldKey in result.errors) {
                        const inputEl = document.getElementById(`register-${fieldKey}`);
                        if (inputEl) inputEl.classList.add('error-border');
                    }
                }
            }
        } catch (error) {
            console.error("Errore durante la registrazione:", error);
            if (registerErrorMessageEl) {
                registerErrorMessageEl.textContent = error.message || "Errore di connessione o del server.";
                registerErrorMessageEl.style.display = 'block';
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
        }
    });

    // Gestione Password Dimenticata
    function openForgotPasswordPopup() {
        if (loginPopupEl?.classList.contains('active')) closeLoginPopup();
        if (forgotPasswordOverlayEl) forgotPasswordOverlayEl.classList.add('active');
        if (forgotPasswordPopupEl) forgotPasswordPopupEl.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (forgotPasswordMessageEl) forgotPasswordMessageEl.style.display = 'none';
        const forgotEmailInput = document.getElementById('forgot-email');
        if (forgotEmailInput) forgotEmailInput.focus();
    }

    function closeForgotPasswordPopup() {
        if (forgotPasswordOverlayEl) forgotPasswordOverlayEl.classList.remove('active');
        if (forgotPasswordPopupEl) forgotPasswordPopupEl.classList.remove('active');
        if (!isAnyPopupActive()) document.body.style.overflow = '';
        if (forgotPasswordMessageEl) forgotPasswordMessageEl.style.display = 'none';
        if (forgotPasswordFormEl) forgotPasswordFormEl.reset();
    }

    if (forgotPasswordLinkEl) {
        forgotPasswordLinkEl.addEventListener('click', (e) => {
            e.preventDefault();
            openForgotPasswordPopup();
        });
    }
    if (closeForgotPasswordPopupBtnEl) closeForgotPasswordPopupBtnEl.addEventListener('click', closeForgotPasswordPopup);
    if (forgotPasswordOverlayEl) {
        forgotPasswordOverlayEl.addEventListener('click', (e) => {
            if (e.target === forgotPasswordOverlayEl) closeForgotPasswordPopup();
        });
    }

    forgotPasswordFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (forgotPasswordMessageEl) {
            forgotPasswordMessageEl.style.display = 'none';
            forgotPasswordMessageEl.className = 'form-message'; // Reset classi
        }
        const emailInput = document.getElementById('forgot-email');
        const submitBtn = forgotPasswordFormEl.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Invio...<div class="spinner-mini-light"></div>';

        try {
            const formData = new FormData(forgotPasswordFormEl);
            const result = await callApi('/richiesta_recupero_password.php', 'POST', formData, true); // Usa il path corretto

            if (forgotPasswordMessageEl) {
                forgotPasswordMessageEl.textContent = result.message || "Operazione completata.";
                forgotPasswordMessageEl.classList.add(result.success ? 'success' : 'error');
                forgotPasswordMessageEl.style.display = 'block';
            }
            if (result.success && emailInput) {
                emailInput.value = ''; // Pulisci l'input email in caso di successo
            }
        } catch (error) {
            console.error('Errore richiesta recupero password:', error);
            if (forgotPasswordMessageEl) {
                forgotPasswordMessageEl.textContent = error.message || "Errore di connessione o del server.";
                forgotPasswordMessageEl.classList.add('error');
                forgotPasswordMessageEl.style.display = 'block';
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
}


/**
 * Carica dinamicamente il logo della navbar.
 */
async function loadNavbarLogo() {
    const logoImgTag = document.getElementById('navbarLogoImgTag'); // Assicurati che il tag <img> del logo abbia questo ID
    if (!logoImgTag) {
        console.warn("Elemento immagine logo navbar (<img id='navbarLogoImgTag'>) non trovato.");
        return;
    }

    try {
        // L'API per i contenuti della pagina index.html contiene il path del logo
        const result = await callApi('/api_get_page_content.php?page=index');
        if (result.success && result.contents && result.contents.navbarLogo) {
            logoImgTag.src = normalizeIconPath(result.contents.navbarLogo);
        } else {
            console.warn("URL del logo navbar non trovato o errore API. Uso il logo di default.");
            logoImgTag.src = defaultNavbarLogoPath;
        }
    } catch (error) {
        console.error("Errore nel caricare dinamicamente il logo navbar:", error.message);
        logoImgTag.src = defaultNavbarLogoPath; // Fallback
    }
    logoImgTag.onerror = function() { this.src = defaultNavbarLogoPath; this.onerror = null; };
}


// --- Inizializzazione Globale ---
document.addEventListener('DOMContentLoaded', async () => {
    const currentYearEl = document.getElementById('currentYear');
    if (currentYearEl) currentYearEl.textContent = new Date().getFullYear();

    // Inizializza i gestori per i popup di login/registrazione
    setupLoginRegistrationPopup();

    // Associa i gestori per il menu mobile e dropdown utente
    const hamburgerBtnEl = document.getElementById("hamburgerBtn");
    if (hamburgerBtnEl) hamburgerBtnEl.addEventListener("click", toggleMobileMenu);

    const userBtnTriggerEl = document.getElementById('userBtnTrigger');
    if (userBtnTriggerEl) userBtnTriggerEl.addEventListener('click', toggleUserMenu);

    // Chiudi dropdown e menu mobile al click esterno
    document.addEventListener('click', function(event) {
        const userDropdownMenuEl = document.getElementById('userDropdownMenu');
        const userDropdownContainerEl = document.getElementById('userDropdownContainer');
        if (userDropdownMenuEl && userDropdownMenuEl.style.display === 'block') {
            if (userDropdownContainerEl && !userDropdownContainerEl.contains(event.target)) {
                userDropdownMenuEl.style.display = 'none';
                const userBtnTriggerEl = document.getElementById('userBtnTrigger');
                if (userBtnTriggerEl) userBtnTriggerEl.setAttribute('aria-expanded', 'false');
            }
        }

        const mobileMenuEl = document.getElementById("mobileMenu");
        const hamburgerBtnElForClose = document.getElementById("hamburgerBtn"); // Riferimento fresco
        if (mobileMenuEl && mobileMenuEl.classList.contains("open")) {
            // Se il click è su un link del menu mobile (che non sia login/logout)
            if (event.target.closest('#mobileMenu a') && !event.target.closest('#login-btn-mobile') && !event.target.closest('#mobile-logout-link')) {
                closeMobileMenu();
            } 
            // Se il click è fuori dal menu e non sull'hamburger
            else if (hamburgerBtnElForClose && !hamburgerBtnElForClose.contains(event.target) && !mobileMenuEl.contains(event.target)) {
                closeMobileMenu();
            }
        }
    });
    
    // Gestione tasto Escape per chiudere popup attivi
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            if (document.getElementById('loginPopup')?.classList.contains('active')) {
                closeLoginPopup(); // Usa la funzione definita in setupLoginRegistrationPopup
            } else if (document.getElementById('forgotPasswordPopup')?.classList.contains('active')) {
                // Assumendo che closeForgotPasswordPopup sia definita in setupLoginRegistrationPopup
                // e che gestisca la chiusura del suo overlay.
                const closeForgotBtn = document.getElementById('closeForgotPasswordPopupBtn');
                if (closeForgotBtn) closeForgotBtn.click(); // Simula click sul bottone di chiusura
            }
            // Aggiungi qui la logica per chiudere altri popup globali se necessario
            // es. if (document.getElementById('bookingPopup')?.classList.contains('active')) closeBookingPopup();
        }
    });

    // Carica il logo della navbar dinamicamente
    await loadNavbarLogo();

    // Controlla la sessione utente (QUESTA È LA CHIAMATA CHIAVE)
    await checkUserSessionOnLoad();
    // A questo punto, currentUserData è aggiornato e la navbar riflette lo stato.
    // La funzione initializePageSpecificUI() (se definita nella pagina specifica)
    // sarà stata chiamata da checkUserSessionOnLoad per gestire il resto.

    console.log("app_global.js: Inizializzazione completata.");
});

