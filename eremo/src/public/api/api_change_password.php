<?php
// File: api_change_password.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$oldPassword = $data['oldPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

if (empty($oldPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tutti i campi password sono obbligatori.']);
    exit;
}
if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nuova password deve essere di almeno 8 caratteri.']);
    exit;
}

// Recupera la password attuale dell'utente
$stmt_get = $conn->prepare("SELECT Password FROM utentiregistrati WHERE Contatto = ?");
if(!$stmt_get) { /* errore */ http_response_code(500); echo json_encode(['success' => false, 'message' => 'Errore server.']); exit; }
$stmt_get->bind_param("s", $user_email);
$stmt_get->execute();
$result = $stmt_get->get_result();
$user = $result->fetch_assoc();
$stmt_get->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Utente non trovato.']);
    exit;
}

if (password_verify($oldPassword, $user['Password'])) {
    $newPasswordHashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt_update = $conn->prepare("UPDATE utentiregistrati SET Password = ? WHERE Contatto = ?");
    if(!$stmt_update) { /* errore */ http_response_code(500); echo json_encode(['success' => false, 'message' => 'Errore server.']); exit; }
    $stmt_update->bind_param("ss", $newPasswordHashed, $user_email);
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password aggiornata con successo.']);
    } else {
        error_log("Errore execute update pass: " . $stmt_update->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento della password.']);
    }
    $stmt_update->close();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password attuale errata.']);
}
$conn->close();
?>