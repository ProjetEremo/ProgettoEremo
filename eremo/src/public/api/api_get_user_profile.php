<?php
// File: api_get_user_profile.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php'; // O il percorso corretto al tuo file di config DB

if (!isset($_SESSION['user_email'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

$user_email = $_SESSION['user_email'];

$stmt = $conn->prepare("SELECT Nome, Cognome, Contatto, Icon FROM utentiregistrati WHERE Contatto = ?");
if (!$stmt) {
    error_log("Errore prepare: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server (DB prepare).']);
    exit;
}
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'data' => [
            'nome' => $user['Nome'],
            'cognome' => $user['Cognome'],
            'email' => $user['Contatto'],
            'icon' => $user['Icon'] // Assumendo che hai un campo 'Icon' in utentiregistrati
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Profilo utente non trovato.']);
}

$stmt->close();
$conn->close();
?>