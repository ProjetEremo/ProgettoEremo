<?php
// Per debugging: mostra tutti gli errori PHP. Rimuovi/commenta in produzione.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Impostazioni di sicurezza e gestione sessione. DEVONO essere chiamate PRIMA di session_set_cookie_params e session_start().
ini_set('session.use_cookies', 1); // Assicura che le sessioni usino i cookie.
ini_set('session.use_only_cookies', 1); // Forza le sessioni a usare solo i cookie (più sicuro).
ini_set('session.use_strict_mode', 1); // Abilita la modalità rigorosa per le sessioni (maggiore sicurezza, previene il session fixation).

$session_duration_seconds = 3 * 60 * 60; // 3 ore

// Imposta il tempo massimo di vita dei dati di sessione sul server (garbage collection).
ini_set('session.gc_maxlifetime', $session_duration_seconds);

// Imposta parametri specifici per il cookie di sessione:
session_set_cookie_params([
    'lifetime' => $session_duration_seconds, // Durata del cookie nel browser
    'path' => '/',                           // Il cookie è valido per l'intero dominio
    'domain' => '',                          // Lascia vuoto per localhost o imposta il tuo dominio specifico. Corretto per la maggior parte delle configurazioni localhost.
    'secure' => isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on', // Imposta a true solo se sei su HTTPS
    'httponly' => true,                      // Il cookie non è accessibile tramite JavaScript (maggiore sicurezza)
    'samesite' => 'Lax'                      // Protezione CSRF. 'Lax' è un buon compromesso. 'Strict' è più sicuro ma può avere impatti.
]);

// Avvia la sessione solo se non è già attiva.
if (session_status() == PHP_SESSION_NONE) {
    if (!session_start()) {
        // Se session_start() fallisce, logga l'errore e termina.
        // Questo di solito indica problemi con session.save_path (non scrivibile, non esiste).
        error_log("ERRORE CRITICO: session_start() è fallito in config_session.php. Controllare session.save_path e i relativi permessi.");
        // Se gli header non sono ancora stati inviati, prova a inviare una risposta di errore JSON.
        if (!headers_sent()) {
            http_response_code(500); // Internal Server Error
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Errore interno del server (avvio sessione fallito). Contattare l\'amministratore.']);
        }
        exit; // Termina lo script per prevenire ulteriori problemi.
    }
}

// Meccanismo di "timeout" per inattività.
$activity_timeout_seconds = 30 * 60; // 30 minuti di inattività

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $activity_timeout_seconds) {
    // L'ultima attività è stata troppo tempo fa, distruggi la sessione.
    session_unset();     // Rimuovi tutte le variabili di sessione.
    session_destroy();   // Distruggi i dati della sessione sul server.

    // È importante riavviare la sessione dopo averla distrutta se si intende
    // impostare messaggi flash o gestire reindirizzamenti basati su una nuova sessione (vuota).
    if (session_status() == PHP_SESSION_NONE) { // Sarà PHP_SESSION_NONE dopo session_destroy()
        session_start(); // Avvia una nuova sessione pulita
    }
    // Esempio: $_SESSION['logout_message'] = "Sessione scaduta per inattività.";
    // La funzione require_login gestirà poi il reindirizzamento o la risposta API.
}
// Aggiorna il timestamp dell'ultima attività ad ogni caricamento pagina che include questo file e ha una sessione attiva.
// Questo deve avvenire DOPO il controllo di inattività.
if (isset($_SESSION['user_email'])) { // Aggiorna solo se l'utente è effettivamente loggato
    $_SESSION['last_activity'] = time();
}


// Funzione per verificare se l'utente è loggato (da usare nelle API e pagine protette)
function require_login($is_api_call = true) {
    // Controlla prima se la sessione utente esiste (es. $_SESSION['user_email'] è impostata).
    if (!isset($_SESSION['user_email'])) {
        if ($is_api_call) {
            http_response_code(401); // Unauthorized
            if (!headers_sent()) { // Evita errori se gli header sono già stati inviati
                 header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Sessione scaduta o login richiesto.']);
            exit;
        } else {
            // Per pagine non API, reindirizza alla pagina di login.
            // Potresti voler conservare l'URL richiesto per reindirizzare l'utente dopo il login.
            header('Location: index.html?reason=session_expired_or_not_logged_in');
            exit;
        }
    }

    // Controllo di inattività aggiuntivo all'interno di require_login per robustezza.
    // Utile se questo file viene incluso ma il blocco di controllo inattività globale non è stato eseguito
    // o se la logica dell'applicazione lo richiede.
    global $activity_timeout_seconds; // Rendi visibile la variabile globale definita sopra.
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $activity_timeout_seconds) {
        $original_session_id = session_id(); // Conserva l'ID per logging se necessario

        session_unset();    // Rimuovi tutte le variabili di sessione.
        session_destroy();  // Distruggi i dati della sessione.

        // Log dell'evento di timeout per inattività
        error_log("Sessione ID $original_session_id terminata per inattività prolungata (" . $activity_timeout_seconds . "s) all'interno di require_login.");

        if ($is_api_call) {
            http_response_code(401); // Unauthorized
            if (!headers_sent()) {
                 header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => 'Sessione scaduta per inattività. Effettuare nuovamente il login.']);
            exit;
        } else {
            header('Location: index.html?reason=inactivity_timeout');
            exit;
        }
    }
    // Non aggiornare $_SESSION['last_activity'] = time(); qui dentro require_login,
    // perché questa funzione potrebbe essere chiamata più volte. L'aggiornamento principale
    // avviene una volta per script nel blocco globale sopra, se l'utente è loggato.
}
?>
