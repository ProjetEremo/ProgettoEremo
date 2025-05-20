<?php
// File: api_update_user_profile.php
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

$nome = $data['nome'] ?? null;
$cognome = $data['cognome'] ?? null;

if (empty($nome) || empty($cognome)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e cognome sono obbligatori.']);
    exit;
}

$stmt = $conn->prepare("UPDATE utentiregistrati SET Nome = ?, Cognome = ? WHERE Contatto = ?");
if (!$stmt) {
    error_log("Errore prepare: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server (DB prepare).']);
    exit;
}
$stmt->bind_param("sss", $nome, $cognome, $user_email);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Aggiorna anche i dati nella sessione se necessario, e in userDataEFF in localStorage (lato client)
        $_SESSION['user_nome'] = $nome; // Se usi $_SESSION['user_nome'] altrove
        echo json_encode(['success' => true, 'message' => 'Profilo aggiornato con successo.']);
    } else {
        // Nessuna riga aggiornata, potrebbe significare che i dati erano già gli stessi
        // o che l'utente non è stato trovato (improbabile se la sessione è valida)
        echo json_encode(['success' => true, 'message' => 'Nessuna modifica apportata o dati identici.']);
    }
} else {
    error_log("Errore execute: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento del profilo.']);
}

$stmt->close();
$conn->close();
?>