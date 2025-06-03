<?php
$session_duration_seconds = 24 * 60 * 60; // 24 ore

// Queste impostazioni DEVONO essere chiamate PRIMA di session_start()
ini_set('session.gc_maxlifetime', $session_duration_seconds);
ini_set('session.cookie_lifetime', $session_duration_seconds); // 0 per sessione del browser
// Opzionale: se hai problemi con sottodomini o path specifici
// session_set_cookie_params($session_duration_seconds, '/', '.tuodominio.com', isset($_SERVER["HTTPS"]), true);


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Opzionale: meccanismo di "timeout" per inattività
// Se vuoi che la sessione scada dopo un certo periodo di inattività,
// anche se gc_maxlifetime e cookie_lifetime sono lunghi.
$activity_timeout_seconds = 30 * 60; // 30 minuti di inattività
$_SESSION['last_activity'] = time(); // Aggiorna il timestamp dell'ultima attività

// Funzione per verificare se l'utente è loggato (da usare nelle API e pagine protette)
function require_login($is_api_call = true) {
    if (!isset($_SESSION['user_email'])) {
        if ($is_api_call) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Sessione scaduta o login richiesto.']);
            exit;
        } else {
            // Reindirizza alla pagina di login o mostra un messaggio
            header('Location: index.html'); // O una pagina di login specifica
            exit;
        }
    }
    // Se è admin e la pagina lo richiede, aggiungi un altro controllo
    // if ($requires_admin && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) { ... }
}
?>