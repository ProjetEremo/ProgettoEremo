<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config_session.php'; // Include per gestire correttamente la sessione

// Non è necessario require_login() qui, perché stiamo per fare logout.

// Salva eventuali informazioni prima di distruggere la sessione, se necessario per logging.
// $logged_out_user_id = $_SESSION['user_id'] ?? 'N/A';

session_unset();     // Rimuove tutte le variabili di sessione
session_destroy();   // Distrugge la sessione sul server

// Opzionale: se vuoi invalidare anche il cookie di sessione dal browser immediatamente,
// anche se session.cookie_lifetime è lungo. Questo è più aggressivo.
/*
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
*/

// Riavvia una sessione vuota se vuoi impostare un messaggio di logout,
// altrimenti puoi ometterlo se il client gestisce tutto.
// session_start();
// $_SESSION['logout_message'] = "Logout effettuato con successo.";


echo json_encode(['success' => true, 'message' => 'Logout effettuato con successo.']);
?>
