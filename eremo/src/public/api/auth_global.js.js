// auth_global.js

// Valori di fallback se non definiti globalmente dalla pagina specifica
const DEFAULT_USER_ICON_PATH = 'uploads/icons/default_user.png';
const PAGE_NAME_FOR_CONTENT = window.PAGE_NAME_FOR_CONTENT || 'default_page'; // Prendilo da window o usa un fallback

let globalCurrentUser = null;
let globalIsEditModeActive = false; // Per la modalità di modifica pagina admin

/* Elementi DOM Comuni (cerca di ottenerli una sola volta se possibile) */
const commonDOMElements = {
    loginBtnDesktop: document.getElementById('login-btn-desktop'),
    loginBtnMobile: document.getElementById('login-btn-mobile'),
    userDropdownContainer: document.getElementById('userDropdownContainer'),
    navbarUsername: document.getElementById('navbar-username'),
    navbarAvatarImg: document.querySelector('#navbar-avatar img'),
    navDashboard: document.getElementById('nav-dashboard'),
    mobileNavDashboard: document.getElementById('mobile-nav-dashboard'),
    mobileNavProfilo: document.getElementById('mobile-nav-profilo'),
    mobileNavMiePrenotazioni: document.getElementById('mobile-nav-mie-prenotazioni'),
    mobileLogoutLink: document.getElementById('mobile-logout-link'),
    mobileMenuSeparator: document.getElementById('mobile-menu-separator'),
    navbarTitle: document.getElementById('navbar-title'),
    editPageBtnContainer: document.getElementById('editPageBtnContainer'),
    navGestioneEventiAdmin: document.getElementById('nav-gestione-eventi-admin'),
    mobileNavGestioneEventiAdminLink: document.getElementById('mobile-nav-gestione-eventi-admin-link'),

    // Popup Login/Register
    loginOverlay: document.getElementById('loginOverlay'),
    loginPopup: document.getElementById('loginPopup'),
    closeLoginPopupBtn: document.getElementById('closeLoginPopupBtn'),
    loginTabs: document.querySelectorAll('.login-tab'),
    loginForms: document.querySelectorAll('.login-popup .login-form'),
    loginFormEl: document.getElementById('loginForm'),
    registerFormEl: document.getElementById('registerForm'),
    loginErrorMessageEl: document.getElementById('login-error-message'),
    registerErrorMessageEl: document.getElementById('register-error-message'),

    // Popup Forgot Password
    forgotPasswordLink: document.getElementById('forgotPasswordLink'),
    forgotPasswordOverlay: document.getElementById('forgotPasswordOverlay'),
    forgotPasswordPopup: document.getElementById('forgotPasswordPopup'),
    closeForgotPasswordPopupBtn: document.getElementById('closeForgotPasswordPopupBtn'),
    forgotPasswordForm: document.getElementById('forgotPasswordForm'),
    forgotPasswordMessage: document.getElementById('forgot-password-message'),

    // Logout links
    logoutLinkDesktop: document.getElementById('logout-link-desktop'),
    mobileLogoutLinkRef: document.getElementById('mobile-logout-link'), // Già in commonDOMElements.mobileLogoutLink

    // Page Status
    pageOverallStatus: document.getElementById('pageOverallStatus')
};

function showGlobalPageStatus(message, type = 'info', duration = 3000) {
    if (!commonDOMElements.pageOverallStatus) return;
    commonDOMElements.pageOverallStatus.textContent = message;
    commonDOMElements.pageOverallStatus.className = 'form-message';
    if (type === 'success') commonDOMElements.pageOverallStatus.classList.add('success');
    else if (type === 'error') commonDOMElements.pageOverallStatus.classList.add('error');
    else {
        commonDOMElements.pageOverallStatus.style.backgroundColor = 'var(--primary)';
        commonDOMElements.pageOverallStatus.classList.remove('success', 'error');
    }
    commonDOMElements.pageOverallStatus.style.color = 'white';
    commonDOMElements.pageOverallStatus.style.display = 'block';
    setTimeout(() => {
        commonDOMElements.pageOverallStatus.style.display = 'none';
    }, duration);
}

function loadCurrentUserData() {
    const uds = localStorage.getItem('userDataEFF');
    if (uds) {
        try {
            globalCurrentUser = JSON.parse(uds);
            if (!globalCurrentUser || typeof globalCurrentUser.email === 'undefined') {
                globalCurrentUser = null;
                localStorage.removeItem('userDataEFF');
            }
        } catch (e) {
            console.error("Error parsing userDataEFF from localStorage:", e);
            globalCurrentUser = null;
            localStorage.removeItem('userDataEFF');
        }
    } else {
        globalCurrentUser = null;
    }
}

function updateGlobalNavbarUI() {
    const el = commonDOMElements;

    // Reset to default (logged out state)
    if (el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'inline-block';
    if (el.loginBtnMobile) el.loginBtnMobile.style.display = 'block';
    if (el.userDropdownContainer) el.userDropdownContainer.style.display = 'none';
    if (el.navDashboard) el.navDashboard.style.display = 'none';
    if (el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'none';
    if (el.navGestioneEventiAdmin) el.navGestioneEventiAdmin.style.display = 'none';
    if (el.mobileNavGestioneEventiAdminLink) el.mobileNavGestioneEventiAdminLink.style.display = 'none';
    if (el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'none';
    if (el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'none';
    if (el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'none';
    if (el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'none';
    if (el.navbarTitle) el.navbarTitle.textContent = "Eremo Frate Francesco";
    if (el.editPageBtnContainer) el.editPageBtnContainer.style.display = 'none';


    if (globalCurrentUser) {
        if (el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'none';
        if (el.loginBtnMobile) el.loginBtnMobile.style.display = 'none';
        if (el.userDropdownContainer) el.userDropdownContainer.style.display = 'inline-block';
        if (el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'block';
        if (el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'block';
        if (el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'block';
        if (el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'block';

        if (el.navbarUsername) el.navbarUsername.textContent = globalCurrentUser.nome || globalCurrentUser.email.split('@')[0];
        if (el.navbarAvatarImg) {
            el.navbarAvatarImg.src = globalCurrentUser.iconPath || DEFAULT_USER_ICON_PATH;
            el.navbarAvatarImg.alt = `Avatar di ${globalCurrentUser.nome || 'utente'}`;
            el.navbarAvatarImg.onerror = function() { this.src = DEFAULT_USER_ICON_PATH; };
        }

        if (globalCurrentUser.isAdmin) {
            if (el.navDashboard) el.navDashboard.style.display = 'block';
            if (el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'block';
            if (el.navGestioneEventiAdmin) el.navGestioneEventiAdmin.style.display = 'block';
            if (el.mobileNavGestioneEventiAdminLink) el.mobileNavGestioneEventiAdminLink.style.display = 'block';
            if (el.navbarTitle) el.navbarTitle.textContent = "Eremo (Admin)";
            if (el.editPageBtnContainer) {
                el.editPageBtnContainer.style.display = 'flex';
                // La gestione di edit/save/cancel buttons per la modalità admin
                // può rimanere nella logica specifica della pagina (index.html)
                // o essere spostata qui se è completamente generica.
                // Per ora, assumiamo che la logica di index.html la gestisca.
                const editBtn = document.getElementById('editPageBtn');
                const saveBtn = document.getElementById('savePageChangesBtn');
                const cancelBtn = document.getElementById('cancelPageChangesBtn');
                if(editBtn) editBtn.style.display = globalIsEditModeActive ? 'none' : 'inline-block';
                if(saveBtn) saveBtn.style.display = globalIsEditModeActive ? 'inline-block' : 'none';
                if(cancelBtn) cancelBtn.style.display = globalIsEditModeActive ? 'inline-block' : 'none';
            }
        } else {
            if (el.navbarTitle) el.navbarTitle.textContent = "Eremo Frate Francesco";
        }
    }
}

function handleGlobalLogout() {
    localStorage.removeItem('userDataEFF');
    globalCurrentUser = null;
    globalIsEditModeActive = false;
    updateGlobalNavbarUI();

    // Chiama la funzione per ricaricare i contenuti della pagina se esiste
    if (typeof window.loadPageContentsFromServer === 'function') {
        window.loadPageContentsFromServer();
    }
    showGlobalPageStatus('Logout effettuato.', 'info');
    if (commonDOMElements.editPageBtnContainer) commonDOMElements.editPageBtnContainer.style.display = 'none';
}

function isAnyPopupActive() {
    const popupsToCheck = [
        commonDOMElements.loginPopup,
        commonDOMElements.forgotPasswordPopup,
        document.getElementById('editContentModal'), // Manteniamo controllo specifico per questo
        document.getElementById('ai-chat-popup') // e per questo
    ];
    return popupsToCheck.some(p => p && (p.classList.contains('active') || p.style.display === 'flex' || p.style.display === 'block'));
}


function openGlobalLoginPopup() {
    if (commonDOMElements.loginOverlay) commonDOMElements.loginOverlay.classList.add('active');
    if (commonDOMElements.loginPopup) commonDOMElements.loginPopup.classList.add('active');
    document.body.style.overflow = 'hidden';
    resetGlobalFormErrorsAndMessages();
    document.querySelector('.login-tab[data-tab="login"]')?.click();
    document.getElementById('login-email')?.focus();
}

function closeGlobalLoginPopup() {
    if (commonDOMElements.loginOverlay) commonDOMElements.loginOverlay.classList.remove('active');
    if (commonDOMElements.loginPopup) commonDOMElements.loginPopup.classList.remove('active');
    if (!isAnyPopupActive()) document.body.style.overflow = '';
    resetGlobalFormErrorsAndMessages();
}

function resetGlobalFormErrorsAndMessages() {
    commonDOMElements.loginForms.forEach(f => f.querySelectorAll('.form-control.error-border').forEach(i => i.classList.remove('error-border')));
    if (commonDOMElements.loginErrorMessageEl) commonDOMElements.loginErrorMessageEl.style.display = 'none';
    if (commonDOMElements.registerErrorMessageEl) commonDOMElements.registerErrorMessageEl.style.display = 'none';
}

function openGlobalForgotPasswordPopup() {
    closeGlobalLoginPopup();
    if (commonDOMElements.forgotPasswordOverlay) commonDOMElements.forgotPasswordOverlay.classList.add('active');
    if (commonDOMElements.forgotPasswordPopup) commonDOMElements.forgotPasswordPopup.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (commonDOMElements.forgotPasswordMessage) commonDOMElements.forgotPasswordMessage.style.display = 'none';
    document.getElementById('forgot-email')?.focus();
}

function closeGlobalForgotPasswordPopup() {
    if (commonDOMElements.forgotPasswordOverlay) commonDOMElements.forgotPasswordOverlay.classList.remove('active');
    if (commonDOMElements.forgotPasswordPopup) commonDOMElements.forgotPasswordPopup.classList.remove('active');
    if (!isAnyPopupActive()) document.body.style.overflow = '';
    if (commonDOMElements.forgotPasswordMessage) commonDOMElements.forgotPasswordMessage.style.display = 'none';
    commonDOMElements.forgotPasswordForm?.reset();
}

function setupGlobalAuthEventListeners() {
    if (commonDOMElements.loginBtnDesktop) commonDOMElements.loginBtnDesktop.addEventListener('click', openGlobalLoginPopup);
    if (commonDOMElements.loginBtnMobile) {
        commonDOMElements.loginBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            openGlobalLoginPopup();
            if (typeof window.closeMobileMenuOnLinkClick === 'function') { // Le funzioni di index.html devono essere esposte globalmente o passate
                window.closeMobileMenuOnLinkClick();
            }
        });
    }

    if (commonDOMElements.closeLoginPopupBtn) commonDOMElements.closeLoginPopupBtn.addEventListener('click', closeGlobalLoginPopup);
    if (commonDOMElements.loginOverlay) commonDOMElements.loginOverlay.addEventListener('click', (e) => {
        if (e.target === commonDOMElements.loginOverlay) closeGlobalLoginPopup();
    });

    commonDOMElements.loginTabs.forEach(t => t.addEventListener('click', () => {
        const id = t.dataset.tab;
        commonDOMElements.loginTabs.forEach(tb => tb.classList.remove('active'));
        t.classList.add('active');
        commonDOMElements.loginForms.forEach(f => {
            f.classList.remove('active');
            if (f.id === `${id}Form`) {
                f.classList.add('active');
                f.querySelector('input:not([type="hidden"])')?.focus();
            }
        });
        resetGlobalFormErrorsAndMessages();
    }));

    commonDOMElements.loginFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetGlobalFormErrorsAndMessages();
        const submitButton = commonDOMElements.loginFormEl.querySelector('button[type="submit"]');
        const originalButtonHTML = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Accesso...<div class="spinner-mini-light"></div>';
        try {
            const response = await fetch('login.php', { method: 'POST', body: new FormData(commonDOMElements.loginFormEl) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || `Errore ${response.status}`);

            if (result.success) {
                globalCurrentUser = {
                    email: result.email,
                    nome: result.nome,
                    iconPath: result.iconPath || DEFAULT_USER_ICON_PATH,
                    isAdmin: result.is_admin === true || result.is_admin === 1
                };
                localStorage.setItem('userDataEFF', JSON.stringify(globalCurrentUser));
                updateGlobalNavbarUI();
                closeGlobalLoginPopup();
                if (typeof window.loadPageContentsFromServer === 'function') {
                     await window.loadPageContentsFromServer();
                }
                showGlobalPageStatus(`Benvenuto/a ${globalCurrentUser.nome}!`, "success");
            } else {
                commonDOMElements.loginFormEl.querySelectorAll('#login-email, #login-password').forEach(el => el.classList.add('error-border'));
                if (commonDOMElements.loginErrorMessageEl) {
                    commonDOMElements.loginErrorMessageEl.textContent = result.message || "Credenziali errate.";
                    commonDOMElements.loginErrorMessageEl.style.display = 'block';
                }
            }
        } catch (err) {
            console.error("Errore login:", err);
            if (commonDOMElements.loginErrorMessageEl) {
                commonDOMElements.loginErrorMessageEl.textContent = "Errore connessione o server.";
                commonDOMElements.loginErrorMessageEl.style.display = 'block';
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
        }
    });

    commonDOMElements.registerFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetGlobalFormErrorsAndMessages();
        const submitButton = commonDOMElements.registerFormEl.querySelector('button[type="submit"]');
        const originalButtonHTML = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Registrazione...<div class="spinner-mini-light"></div>';
        try {
            const response = await fetch('registrati.php', { method: 'POST', body: new FormData(commonDOMElements.registerFormEl) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || `Errore ${response.status}`);

            if (result.success) {
                alert(result.message || "Registrazione completata! Ora puoi accedere.");
                document.querySelector('.login-tab[data-tab="login"]')?.click();
                const loginEmailInput = document.getElementById('login-email');
                const registerEmailInput = document.getElementById('register-email');
                if (loginEmailInput && registerEmailInput) {
                    loginEmailInput.value = registerEmailInput.value;
                }
                commonDOMElements.registerFormEl.reset();
            } else {
                if (commonDOMElements.registerErrorMessageEl) {
                    commonDOMElements.registerErrorMessageEl.textContent = result.message || "Errore registrazione.";
                    commonDOMElements.registerErrorMessageEl.style.display = 'block';
                }
                if (result.errors) {
                    for (const field in result.errors) {
                        document.getElementById(`register-${field}`)?.classList.add('error-border');
                    }
                }
            }
        } catch (err) {
            console.error("Errore registrazione:", err);
            if (commonDOMElements.registerErrorMessageEl) {
                commonDOMElements.registerErrorMessageEl.textContent = "Errore connessione o server.";
                commonDOMElements.registerErrorMessageEl.style.display = 'block';
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
        }
    });

    if (commonDOMElements.forgotPasswordLink) {
        commonDOMElements.forgotPasswordLink.addEventListener('click', (e) => { e.preventDefault(); openGlobalForgotPasswordPopup(); });
    }
    if (commonDOMElements.closeForgotPasswordPopupBtn) commonDOMElements.closeForgotPasswordPopupBtn.addEventListener('click', closeGlobalForgotPasswordPopup);
    if (commonDOMElements.forgotPasswordOverlay) commonDOMElements.forgotPasswordOverlay.addEventListener('click', (e) => {
        if (e.target === commonDOMElements.forgotPasswordOverlay) closeGlobalForgotPasswordPopup();
    });

    commonDOMElements.forgotPasswordForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (commonDOMElements.forgotPasswordMessage) {
            commonDOMElements.forgotPasswordMessage.style.display = 'none';
            commonDOMElements.forgotPasswordMessage.className = 'form-message';
        }
        const emailInput = document.getElementById('forgot-email');
        const submitBtn = commonDOMElements.forgotPasswordForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Invio...<div class="spinner-mini-light"></div>';
        try {
            const formData = new FormData(commonDOMElements.forgotPasswordForm);
            const response = await fetch('richiesta_recupero_password.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                commonDOMElements.forgotPasswordMessage.textContent = result.message;
                commonDOMElements.forgotPasswordMessage.classList.add('success');
                if(emailInput) emailInput.value = '';
            } else {
                commonDOMElements.forgotPasswordMessage.textContent = result.message || "Si è verificato un errore.";
                commonDOMElements.forgotPasswordMessage.classList.add('error');
            }
        } catch (error) {
            console.error('Errore richiesta recupero password:', error);
            commonDOMElements.forgotPasswordMessage.textContent = "Errore di connessione o del server.";
            commonDOMElements.forgotPasswordMessage.classList.add('error');
        } finally {
            if (commonDOMElements.forgotPasswordMessage) commonDOMElements.forgotPasswordMessage.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });

    if (commonDOMElements.logoutLinkDesktop) commonDOMElements.logoutLinkDesktop.addEventListener('click', (e) => { e.preventDefault(); handleGlobalLogout(); });
    if (commonDOMElements.mobileLogoutLink) {
        commonDOMElements.mobileLogoutLink.addEventListener('click', (e) => {
            e.preventDefault();
            handleGlobalLogout();
            if (typeof window.closeMobileMenuOnLinkClick === 'function') {
                window.closeMobileMenuOnLinkClick();
            }
        });
    }
}

// Inizializzazione globale dell'autenticazione
document.addEventListener('DOMContentLoaded', () => {
    loadCurrentUserData();
    updateGlobalNavbarUI();
    setupGlobalAuthEventListeners();

    // Meccanismo di keep-alive sessione (opzionale, vedi Passo 3 sotto)
    if (globalCurrentUser) {
        initGlobalSessionKeepAlive(10); // Ping ogni 10 minuti
    }
});

// Meccanismo Keep-Alive (da aggiungere a auth_global.js)
function initGlobalSessionKeepAlive(intervalMinutes = 15) {
    if (globalCurrentUser) {
        const intervalMilliseconds = intervalMinutes * 60 * 1000;
        setInterval(async () => {
            if (!globalCurrentUser) return; // Interrompi se l'utente fa logout
            try {
                const response = await fetch('keep_session_alive.php', { method: 'POST' });
                const data = await response.json();
                if (data.success) {
                    console.log('Session keep-alive ping successful.');
                } else {
                    console.warn('Session keep-alive ping failed or session expired on server.');
                    // Potresti voler informare l'utente o fare un logout forzato
                    // handleGlobalLogout();
                    // showGlobalPageStatus("La tua sessione è scaduta. Effettua nuovamente il login.", "error", 7000);
                }
            } catch (error) {
                console.error('Error during session keep-alive ping:', error);
            }
        }, intervalMilliseconds);
    }
}

// Esponi le funzioni necessarie globalmente se servono ad altri script specifici della pagina
window.authGlobal = {
    getCurrentUser: () => globalCurrentUser,
    isEditModeActive: () => globalIsEditModeActive,
    setEditModeActive: (status) => {
        globalIsEditModeActive = status;
        updateGlobalNavbarUI(); // Aggiorna la UI per riflettere il cambio
    },
    updateNavbarUI: updateGlobalNavbarUI, // Se serve chiamarla da fuori
    showPageStatus: showGlobalPageStatus, // Utile globalmente
    handleLogout: handleGlobalLogout // Se serve un logout programmatico da altre parti
};