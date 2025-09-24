/**
 * auth.js
 * * Gestisce tutte le funzionalità di autenticazione per il sito dell'Eremo.
 * Include login, registrazione, recupero password, logout, e aggiornamento
 * dell'interfaccia utente in base allo stato di autenticazione.
 */

// Variabili globali per lo stato dell'utente
let currentUserData = null;
const defaultUserIconPath = 'uploads/icons/default_user.png';

/**
 * Crea e inietta l'HTML per i popup di autenticazione nel body del documento.
 * Questo evita la duplicazione del codice HTML nelle varie pagine.
 */
function createAuthPopupsHTML() {
    if (document.getElementById('auth-popup-container')) {
        return; // Popups già creati
    }

    const container = document.createElement('div');
    container.id = 'auth-popup-container';

    container.innerHTML = `
        <div class="login-popup-overlay" id="loginOverlay"></div>
        <div class="login-popup" id="loginPopup">
            <button class="close-popup" id="closeLoginPopupBtn" aria-label="Chiudi popup">&times;</button>
            <div class="login-tabs">
                <div class="login-tab active" data-tab="login">Accedi</div>
                <div class="login-tab" data-tab="register">Registrati</div>
            </div>
            <form class="login-form active" id="loginForm" method="POST">
                <div class="form-group mb-3"><label for="login-email">Email</label><input type="email" id="login-email" name="login-email" class="form-control" required autocomplete="email"></div>
                <div class="form-group mb-3"><label for="login-password">Password</label><input type="password" id="login-password" name="login-password" class="form-control" required autocomplete="current-password"></div>
                <div style="text-align: right; margin-top: -10px; margin-bottom: 10px;">
                    <a href="#" id="forgotPasswordLink" style="font-size: 0.9em; color: var(--primary);">Password dimenticata?</a>
                </div>
                <div id="login-error-message" class="form-message error"></div>
                <div class="form-actions"><button type="submit" class="btn-popup">Accedi</button></div>
            </form>
            <form class="login-form" id="registerForm" method="POST">
                <div class="form-group mb-3"><label for="register-name">Nome</label><input type="text" id="register-name" name="register-name" class="form-control" required autocomplete="given-name"></div>
                <div class="form-group mb-3"><label for="register-surname">Cognome</label><input type="text" id="register-surname" name="register-surname" class="form-control" required autocomplete="family-name"></div>
                <div class="form-group mb-3"><label for="register-email">Email</label><input type="email" id="register-email" name="register-email" class="form-control" required autocomplete="email"></div>
                <div class="form-group mb-3"><label for="register-password">Password</label><input type="password" id="register-password" name="register-password" class="form-control" required minlength="8" autocomplete="new-password"><small>Minimo 8 caratteri.</small></div>
                <div class="terms-checkbox mb-3"><input type="checkbox" id="register-terms" name="register-terms" required><label for="register-terms">Accetto i <a href="termini.html" target="_blank">termini e condizioni</a></label></div>
                <!-- MODIFICA: Aggiunto checkbox per notifica eventi -->
                <div class="terms-checkbox mb-3">
                    <input type="checkbox" id="register-notify" name="register-notify" checked>
                    <label for="register-notify">Avvisami quando vengono pubblicati nuovi eventi</label>
                </div>
                <!-- FINE MODIFICA -->
                <div id="register-error-message" class="form-message error"></div>
                <div class="form-actions"><button type="submit" class="btn-popup">Registrati</button></div>
            </form>
        </div>

        <div class="login-popup-overlay" id="forgotPasswordOverlay"></div>
        <div class="login-popup" id="forgotPasswordPopup">
            <button class="close-popup" id="closeForgotPasswordPopupBtn" aria-label="Chiudi popup">&times;</button>
            <h3 style="text-align:center; margin-bottom: 1rem; color: var(--primary);">Recupera Password</h3>
            <p style="font-size:0.95em; color:var(--gray-dark); margin-bottom:1.5rem; text-align:left;">Inserisci l'indirizzo email associato al tuo account. Se presente nel nostro sistema, ti invieremo un link per reimpostare la password.</p>
            <form id="forgotPasswordForm" method="POST">
                <div class="form-group mb-3">
                    <label for="forgot-email">Email</label>
                    <input type="email" id="forgot-email" name="forgot-email" class="form-control" required autocomplete="email">
                </div>
                <div id="forgot-password-message" class="form-message" style="text-align:left;"></div>
                <div class="form-actions">
                    <button type="submit" class="btn-popup">Invia link di recupero</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(container);
}


/**
 * Carica i dati dell'utente dal localStorage, se presenti.
 */
function loadUserData() {
    const uds = localStorage.getItem('userDataEFF');
    if (uds) {
        try {
            currentUserData = JSON.parse(uds);
            if (!currentUserData || typeof currentUserData.email === 'undefined') {
                currentUserData = null;
                localStorage.removeItem('userDataEFF');
            }
        } catch (e) {
            console.error("Errore parsing userDataEFF:", e);
            currentUserData = null;
            localStorage.removeItem('userDataEFF');
        }
    } else {
        currentUserData = null;
    }
}

/**
 * Aggiorna gli elementi della navbar e della pagina in base allo stato di login dell'utente.
 */
function updateNavbarUI() {
    const el = {
        loginBtnDesktop: document.getElementById('login-btn-desktop'),
        loginBtnMobile: document.getElementById('login-btn-mobile'),
        userDropdownContainer: document.getElementById('userDropdownContainer'),
        navbarUsername: document.getElementById('navbar-username'),
        navbarAvatarImg: document.querySelector('#navbar-avatar img'),
        navProfilo: document.getElementById('nav-profilo'),
        navMiePrenotazioni: document.getElementById('nav-mie-prenotazioni'),
        navDashboard: document.getElementById('nav-dashboard'),
        navGestioneEventiAdmin: document.getElementById('nav-gestione-eventi-admin'),
        mobileNavProfilo: document.getElementById('mobile-nav-profilo'),
        mobileNavMiePrenotazioni: document.getElementById('mobile-nav-mie-prenotazioni'),
        mobileNavDashboard: document.getElementById('mobile-nav-dashboard'),
        mobileNavGestioneEventiAdmin: document.getElementById('mobile-nav-gestione-eventi-admin'),
        mobileNavGestioneEventiAdminLink: document.getElementById('mobile-nav-gestione-eventi-admin-link'), // Specifico per index
        mobileLogoutLink: document.getElementById('mobile-logout-link'),
        mobileMenuSeparator: document.getElementById('mobile-menu-separator'),
        navbarTitle: document.getElementById('navbar-title'),
        mainPageTitle: document.getElementById('mainPageTitle'),
        addEventFabContainerWrapper: document.getElementById('addEventFabContainerWrapper'), // Specifico per eventi
        editPageBtnContainer: document.getElementById('editPageBtnContainer') // Specifico per index
    };

    // Nascondi tutto di default e mostra solo ciò che serve
    const allUserSpecificElements = [
        el.loginBtnDesktop, el.loginBtnMobile, el.userDropdownContainer, el.navProfilo, el.navMiePrenotazioni,
        el.navDashboard, el.navGestioneEventiAdmin, el.mobileNavProfilo, el.mobileNavMiePrenotazioni,
        el.mobileNavDashboard, el.mobileNavGestioneEventiAdmin, el.mobileNavGestioneEventiAdminLink,
        el.mobileLogoutLink, el.mobileMenuSeparator
    ];

    // Stato di default: utente non loggato
    if(el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'inline-block';
    if(el.loginBtnMobile) el.loginBtnMobile.style.display = 'block';
    if(el.userDropdownContainer) el.userDropdownContainer.style.display = 'none';
    if(el.navProfilo) el.navProfilo.style.display = 'none';
    if(el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'none';
    if(el.navMiePrenotazioni) el.navMiePrenotazioni.style.display = 'none';
    if(el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'none';
    if(el.navDashboard) el.navDashboard.style.display = 'none';
    if(el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'none';
    if(el.navGestioneEventiAdmin) el.navGestioneEventiAdmin.style.display = 'none';
    if(el.mobileNavGestioneEventiAdmin) el.mobileNavGestioneEventiAdmin.style.display = 'none';
    if(el.mobileNavGestioneEventiAdminLink) el.mobileNavGestioneEventiAdminLink.style.display = 'none';
    if(el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'none';
    if(el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'none';


    if (currentUserData) { // Utente loggato
        if(el.loginBtnDesktop) el.loginBtnDesktop.style.display = 'none';
        if(el.loginBtnMobile) el.loginBtnMobile.style.display = 'none';
        if(el.userDropdownContainer) el.userDropdownContainer.style.display = 'inline-block';
        if(el.navbarUsername) el.navbarUsername.textContent = currentUserData.nome || currentUserData.email.split('@')[0];
        if(el.navbarAvatarImg) {
            el.navbarAvatarImg.src = currentUserData.iconPath || defaultUserIconPath;
            el.navbarAvatarImg.alt = `Avatar di ${currentUserData.nome || 'utente'}`;
            el.navbarAvatarImg.onerror = function() { this.src = defaultUserIconPath; };
        }
        if(el.mobileLogoutLink) el.mobileLogoutLink.style.display = 'block';
        if(el.mobileMenuSeparator) el.mobileMenuSeparator.style.display = 'block';

        if(currentUserData.isAdmin) { // Utente Admin
            if(el.navProfilo) el.navProfilo.style.display = 'block';
            if(el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'block';
            if(el.navDashboard) el.navDashboard.style.display = 'block';
            if(el.mobileNavDashboard) el.mobileNavDashboard.style.display = 'block';
            if(el.navbarTitle) el.navbarTitle.textContent = "Eremo (Admin)";
            // UI specifiche per admin
            if(el.mainPageTitle && window.location.pathname.includes('eventiincorso')) el.mainPageTitle.textContent = "Gestione Calendario Attività";
            if(el.addEventFabContainerWrapper) el.addEventFabContainerWrapper.style.display = 'flex';
            if(el.editPageBtnContainer) el.editPageBtnContainer.classList.add('active');

        } else { // Utente normale
            if(el.navProfilo) el.navProfilo.style.display = 'block';
            if(el.mobileNavProfilo) el.mobileNavProfilo.style.display = 'block';
            if(el.navMiePrenotazioni) el.navMiePrenotazioni.style.display = 'block';
            if(el.mobileNavMiePrenotazioni) el.mobileNavMiePrenotazioni.style.display = 'block';
            if(el.navbarTitle) el.navbarTitle.textContent = "Eremo frate Francesco";
            // UI specifiche per utente normale
            if(el.mainPageTitle && window.location.pathname.includes('eventiincorso')) el.mainPageTitle.textContent = "Calendario Attività";
            if(el.addEventFabContainerWrapper) el.addEventFabContainerWrapper.style.display = 'none';
            if(el.editPageBtnContainer) el.editPageBtnContainer.classList.remove('active');
        }
    } else { // Utente non loggato
        if(el.navbarTitle) el.navbarTitle.textContent = "Eremo frate Francesco";
        if(el.mainPageTitle && window.location.pathname.includes('eventiincorso')) el.mainPageTitle.textContent = "Calendario Attività";
        if(el.addEventFabContainerWrapper) el.addEventFabContainerWrapper.style.display = 'none';
        if(el.editPageBtnContainer) el.editPageBtnContainer.classList.remove('active');
    }
}

/**
 * Gestisce il processo di logout dell'utente.
 */
function handleLogout() {
    localStorage.removeItem('userDataEFF');
    localStorage.removeItem('pending_action_eff');
    currentUserData = null;

    fetch('api/api_logout.php', { method: 'POST', credentials: 'same-origin' })
        .catch(error => console.error("Errore API logout:", error))
        .finally(() => {
            updateNavbarUI();
            // Notifica le altre parti dell'applicazione che il logout è avvenuto
            document.dispatchEvent(new CustomEvent('logoutSuccess'));
            alert('Logout effettuato.');
        });
}

/**
 * Controlla se c'è un qualsiasi popup attivo sulla pagina.
 * @returns {boolean} True se un popup è attivo, altrimenti false.
 */
function isAnyPopupActive() {
    const popups = document.querySelectorAll('.login-popup.active, .booking-popup.active, .dash-admin-event-popup.active, .flyer-overlay-eff.active, .custom-confirm-overlay.active, .edit-content-modal[style*="display: flex"]');
    return popups.length > 0;
}

/**
 * Inizializza tutti gli event listener per i componenti di autenticazione (pulsanti, form, etc.).
 */
function setupAuthEventListeners() {
    // Selezione elementi DOM
    const loginOverlay = document.getElementById('loginOverlay');
    const loginPopup = document.getElementById('loginPopup');
    const closeLoginPopupBtn = document.getElementById('closeLoginPopupBtn');
    const loginTabs = document.querySelectorAll('.login-tab');
    const loginForms = document.querySelectorAll('.login-popup .login-form');
    const loginFormEl = document.getElementById('loginForm');
    const registerFormEl = document.getElementById('registerForm');
    const loginErrorMessageEl = document.getElementById('login-error-message');
    const registerErrorMessageEl = document.getElementById('register-error-message');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotPasswordOverlay = document.getElementById('forgotPasswordOverlay');
    const forgotPasswordPopup = document.getElementById('forgotPasswordPopup');
    const closeForgotPasswordPopupBtn = document.getElementById('closeForgotPasswordPopupBtn');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const forgotPasswordMessage = document.getElementById('forgot-password-message');

    // Funzioni di utilità per i popup
    function resetFormErrorsAndMessages() {
        loginForms.forEach(f => f.querySelectorAll('.form-control.error-border').forEach(i => i.classList.remove('error-border')));
        if (loginErrorMessageEl) loginErrorMessageEl.style.display = 'none';
        if (registerErrorMessageEl) registerErrorMessageEl.style.display = 'none';
    }

    function openLoginPopup() {
        if (loginOverlay) loginOverlay.classList.add('active');
        if (loginPopup) loginPopup.classList.add('active');
        document.body.style.overflow = 'hidden';
        resetFormErrorsAndMessages();
        document.querySelector('.login-tab[data-tab="login"]')?.click();
        document.getElementById('login-email')?.focus();
    }

    function closeLoginPopup() {
        if (loginOverlay) loginOverlay.classList.remove('active');
        if (loginPopup) loginPopup.classList.remove('active');
        if (!isAnyPopupActive()) document.body.style.overflow = '';
        resetFormErrorsAndMessages();
    }

    function openForgotPasswordPopup() {
        if (loginPopup?.classList.contains('active')) closeLoginPopup();
        if (forgotPasswordOverlay) forgotPasswordOverlay.classList.add('active');
        if (forgotPasswordPopup) forgotPasswordPopup.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (forgotPasswordMessage) forgotPasswordMessage.style.display = 'none';
        document.getElementById('forgot-email')?.focus();
    }

    function closeForgotPasswordPopup() {
        if (forgotPasswordOverlay) forgotPasswordOverlay.classList.remove('active');
        if (forgotPasswordPopup) forgotPasswordPopup.classList.remove('active');
        if (!isAnyPopupActive()) document.body.style.overflow = '';
        if (forgotPasswordMessage) forgotPasswordMessage.style.display = 'none';
        if (forgotPasswordForm) forgotPasswordForm.reset();
    }

    // Collegamento eventi
    document.getElementById('login-btn-desktop')?.addEventListener('click', openLoginPopup);
    const loginBtnMobile = document.getElementById('login-btn-mobile');
    if(loginBtnMobile) {
        loginBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            openLoginPopup();
            if (typeof closeMobileMenuOnLinkClick === 'function') {
                closeMobileMenuOnLinkClick();
            }
        });
    }

    if (closeLoginPopupBtn) closeLoginPopupBtn.addEventListener('click', closeLoginPopup);
    if (loginOverlay) loginOverlay.addEventListener('click', (e) => { if (e.target === loginOverlay) closeLoginPopup(); });

    loginTabs.forEach(t => t.addEventListener('click', () => {
        const id = t.dataset.tab;
        loginTabs.forEach(tb => tb.classList.remove('active'));
        t.classList.add('active');
        loginForms.forEach(f => {
            f.classList.remove('active');
            if (f.id === `${id}Form`) {
                f.classList.add('active');
                f.querySelector('input:not([type="hidden"])')?.focus();
            }
        });
        resetFormErrorsAndMessages();
    }));

    loginFormEl?.addEventListener('submit', async(e) => {
        e.preventDefault();
        resetFormErrorsAndMessages();
        const b = loginFormEl.querySelector('button[type="submit"]');
        const o = b.innerHTML;
        b.disabled = true;
        b.innerHTML = 'Accesso...<div class="spinner-mini-light"></div>';
        try {
            const r = await fetch('login.php', { method: 'POST', body: new FormData(loginFormEl) });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || `Errore ${r.status}`);
            if (j.success) {
                currentUserData = { email: j.email, nome: j.nome, iconPath: j.iconPath || defaultUserIconPath, isAdmin: j.is_admin === true || j.is_admin === 1 };
                localStorage.setItem('userDataEFF', JSON.stringify(currentUserData));
                updateNavbarUI();
                closeLoginPopup();
                document.dispatchEvent(new CustomEvent('loginSuccess'));
            } else {
                loginFormEl.querySelectorAll('#login-email,#login-password').forEach(el => el.classList.add('error-border'));
                if (loginErrorMessageEl) { loginErrorMessageEl.textContent = j.message || "Credenziali errate."; loginErrorMessageEl.style.display = 'block'; }
            }
        } catch (err) {
            console.error("Errore login:", err);
            if (loginErrorMessageEl) { loginErrorMessageEl.textContent = "Errore connessione o server."; loginErrorMessageEl.style.display = 'block'; }
        } finally {
            b.disabled = false;
            b.innerHTML = o;
        }
    });

    registerFormEl?.addEventListener('submit', async(e) => {
        e.preventDefault();
        resetFormErrorsAndMessages();
        const b = registerFormEl.querySelector('button[type="submit"]');
        const o = b.innerHTML;
        b.disabled = true;
        b.innerHTML = 'Registrazione...<div class="spinner-mini-light"></div>';
        try {
            const r = await fetch('registrati.php', { method: 'POST', body: new FormData(registerFormEl) });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || `Errore ${r.status}`);
            if (j.success) {
                alert(j.message || "Registrazione completata! Ora puoi accedere.");
                document.querySelector('.login-tab[data-tab="login"]')?.click();
                document.getElementById('login-email').value = document.getElementById('register-email').value;
                registerFormEl.reset();
            } else {
                if (registerErrorMessageEl) { registerErrorMessageEl.textContent = j.message || "Errore registrazione."; registerErrorMessageEl.style.display = 'block'; }
                if (j.errors) {
                    for (const f in j.errors) document.getElementById(`register-${f}`)?.classList.add('error-border');
                }
            }
        } catch (err) {
            console.error("Errore registrazione:", err);
            if (registerErrorMessageEl) { registerErrorMessageEl.textContent = "Errore connessione o server."; registerErrorMessageEl.style.display = 'block'; }
        } finally {
            b.disabled = false;
            b.innerHTML = o;
        }
    });

    if (forgotPasswordLink) forgotPasswordLink.addEventListener('click', (e) => { e.preventDefault(); openForgotPasswordPopup(); });
    if (closeForgotPasswordPopupBtn) closeForgotPasswordPopupBtn.addEventListener('click', closeForgotPasswordPopup);
    if (forgotPasswordOverlay) forgotPasswordOverlay.addEventListener('click', (e) => { if (e.target === forgotPasswordOverlay) closeForgotPasswordPopup(); });

    if (forgotPasswordForm) forgotPasswordForm.addEventListener('submit', async(e) => {
        e.preventDefault();
        if (forgotPasswordMessage) { forgotPasswordMessage.style.display = 'none'; forgotPasswordMessage.className = 'form-message'; }
        const sB = forgotPasswordForm.querySelector('button[type="submit"]');
        const oT = sB.innerHTML;
        sB.disabled = true;
        sB.innerHTML = 'Invio...<div class="spinner-mini-light"></div>';
        try {
            const fD = new FormData(forgotPasswordForm);
            const rsp = await fetch('richiesta_recupero_password.php', { method: 'POST', body: fD });
            const res = await rsp.json();
            if (res.success) {
                forgotPasswordMessage.textContent = res.message;
                forgotPasswordMessage.classList.add('success');
                document.getElementById('forgot-email').value = '';
            } else {
                forgotPasswordMessage.textContent = res.message || "Si è verificato un errore.";
                forgotPasswordMessage.classList.add('error');
            }
        } catch (err) {
            console.error('Errore recupero password:', err);
            forgotPasswordMessage.textContent = "Errore di connessione o del server.";
            forgotPasswordMessage.classList.add('error');
        } finally {
            if (forgotPasswordMessage) forgotPasswordMessage.style.display = 'block';
            sB.disabled = false;
            sB.innerHTML = oT;
        }
    });

    document.getElementById('logout-link-desktop')?.addEventListener('click', (e) => { e.preventDefault(); handleLogout(); });
    const logoutMobile = document.getElementById('mobile-logout-link');
    if (logoutMobile) {
        logoutMobile.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogout();
            if (typeof closeMobileMenuOnLinkClick === 'function') {
                closeMobileMenuOnLinkClick();
            }
        });
    }

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            if (loginPopup?.classList.contains("active")) closeLoginPopup();
            else if (forgotPasswordPopup?.classList.contains("active")) closeForgotPasswordPopup();
        }
    });
}

// Inizializzazione globale dello script di autenticazione
document.addEventListener('DOMContentLoaded', () => {
    createAuthPopupsHTML();
    setupAuthEventListeners();
    loadUserData();
    updateNavbarUI();
});
