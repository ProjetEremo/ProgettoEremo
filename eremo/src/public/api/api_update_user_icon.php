<?php
// File: api_update_user_icon.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

$user_email = $_SESSION['user_email'];
$data = json_decode(file_get_contents('php://input'), true);
$icon_path = $data['icon'] ?? null;

if (empty($icon_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Percorso icona mancante.']);
    exit;
}

// Validazione di base del percorso per sicurezza (assicurati che inizi con il percorso atteso)
$valid_icon_path_prefix = '/uploads/icons/';
if (strpos($icon_path, $valid_icon_path_prefix) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Percorso icona non valido.']);
    exit;
}
// Ulteriore controllo: verifica che il file esista effettivamente
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $icon_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Il file icona specificato non esiste.']);
    exit;
}


$stmt = $conn->prepare("UPDATE utentiregistrati SET Icon = ? WHERE Contatto = ?");
if (!$stmt) {
    error_log("Errore prepare: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server (DB).']);
    exit;
}
$stmt->bind_param("ss", $icon_path, $user_email);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Icona aggiornata con successo.']);
} else {
    error_log("Errore execute: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'icona.']);
}
$stmt->close();
$conn->close();
?>