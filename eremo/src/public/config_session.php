<?php
// Durata della sessione in secondi (24 ore)
$session_duration_seconds = 24 * 60 * 60;
// Durata del timeout per inattività in secondi (30 minuti)
$activity_timeout_seconds = 30 * 60;

// Queste impostazioni DEVONO essere chiamate PRIMA di session_start()
// Imposta la durata massima della vita della sessione sul server
ini_set('session.gc_maxlifetime', $session_duration_seconds);
// Imposta la durata del cookie di sessione nel browser dell'utente
// Questo è cruciale per far persistere la sessione dopo la chiusura del browser
ini_set('session.cookie_lifetime', $session_duration_seconds);

// Configura i parametri del cookie di sessione per maggiore sicurezza e specificità.
session_set_cookie_params([
    'lifetime' => $session_duration_seconds,
    'path' => '/',
    'domain' => '.eremofratefrancesco.altervista.org', // Imposta il tuo dominio
    'secure' => true, // Imposta a true perché il sito usa HTTPS
    'httponly' => true, // Impedisce l'accesso al cookie tramite JavaScript (maggiore sicurezza)
    'samesite' => 'Lax' // 'Lax' è un buon compromesso per la sicurezza CSRF. Considera 'Strict' se applicabile.
]);

// Avvia la sessione se non è già attiva
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// NOTA: La riga `$_SESSION['last_activity'] = time();` è stata rimossa da qui.
// L'aggiornamento dell'ultima attività ora è gestito all'interno di `require_login()`
// e dovrebbe essere inizializzato nel tuo script di login.

/**
 * Verifica se l'utente è loggato e se la sessione è ancora attiva.
 * Gestisce anche il timeout per inattività.
 *
 * @param bool $is_api_call Indica se la chiamata proviene da un endpoint API (per formattare la risposta).
 */
function require_login($is_api_call = true) {
    global $activity_timeout_seconds; // Rende accessibile la variabile definita sopra

    // Caso 1: L'utente non è loggato (nessuna email in sessione)
    if (!isset($_SESSION['user_email'])) {
        if ($is_api_call) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Login richiesto.']);
            exit;
        } else {
            // Per le pagine normali, reindirizza alla pagina di login o alla home
            header('Location: index.html?status=login_required');
            exit;
        }
    }

    // Caso 2: L'utente è loggato, verifica il timeout per inattività
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > $activity_timeout_seconds) {
            // Sessione scaduta per inattività
            session_unset();     // Rimuove tutte le variabili di sessione
            session_destroy();   // Distrugge i dati della sessione sul server

            // Opzionale: puoi avviare una nuova sessione per passare messaggi di errore
            // if (session_status() == PHP_SESSION_NONE) { session_start(); }
            // $_SESSION['error_message'] = "Sessione scaduta per inattività.";

            if ($is_api_call) {
                http_response_code(401); // Unauthorized
                echo json_encode(['success' => false, 'message' => 'Sessione scaduta per inattività. Effettuare nuovamente il login.']);
                exit;
            } else {
                header('Location: index.html?status=session_timeout_inactive');
                exit;
            }
        }
    }
    // Se 'last_activity' non è settato, potrebbe essere un login fresco o una sessione precedente
    // alla modifica. Lo script di login dovrebbe inizializzarlo.

    // Se la sessione è valida e attiva, aggiorna il timestamp dell'ultima attività
    $_SESSION['last_activity'] = time();

    // Qui potresti aggiungere ulteriori controlli, ad esempio per i ruoli admin:
    // if ($requires_admin && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) { ... }
}
?>
