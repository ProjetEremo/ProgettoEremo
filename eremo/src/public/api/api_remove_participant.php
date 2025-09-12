<?php
// File: api_remove_participant.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php'; // Assumendo che questo file gestisca la connessione $conn

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
$participantId = filter_var($data['participantId'] ?? null, FILTER_VALIDATE_INT);

if (!$participantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Partecipante non valido.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Trova la prenotazione e l'evento, e VERIFICA che l'utente sia il proprietario
    // CORREZIONE: Utilizzato par.ProgressivoPart al posto del non esistente par.ID
    $sql_check = "SELECT p.Progressivo, p.Contatto, p.IDEvento, p.NumeroPosti 
                  FROM Partecipanti par 
                  JOIN prenotazioni p ON par.Progressivo = p.Progressivo 
                  WHERE par.ProgressivoPart = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $participantId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $booking_info = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$booking_info) {
        throw new Exception("Partecipante non trovato.", 404);
    }
    if ($booking_info['Contatto'] !== $user_email) {
        throw new Exception("Non hai i permessi per modificare questa prenotazione.", 403);
    }
    if ($booking_info['NumeroPosti'] <= 1) {
        throw new Exception("Non puoi rimuovere l'ultimo partecipante. Se necessario, annulla l'intera prenotazione.", 400);
    }

    $idPrenotazione = $booking_info['Progressivo'];
    $idEvento = $booking_info['IDEvento'];

    // 2. Elimina il partecipante
    // CORREZIONE: Utilizzato ProgressivoPart al posto del non esistente ID
    $sql_delete = "DELETE FROM Partecipanti WHERE ProgressivoPart = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $participantId);
    $stmt_delete->execute();
    if ($stmt_delete->affected_rows === 0) {
        throw new Exception("Impossibile eliminare il partecipante.", 500);
    }
    $stmt_delete->close();

    // 3. Aggiorna il numero di posti nella prenotazione (-1)
    $sql_update_booking = "UPDATE prenotazioni SET NumeroPosti = NumeroPosti - 1 WHERE Progressivo = ?";
    $stmt_update_booking = $conn->prepare($sql_update_booking);
    $stmt_update_booking->bind_param("i", $idPrenotazione);
    $stmt_update_booking->execute();
    $stmt_update_booking->close();

    // 4. Aggiorna i posti disponibili nell'evento (+1)
    $sql_update_event = "UPDATE eventi SET PostiDisponibili = PostiDisponibili + 1 WHERE IDEvento = ?";
    $stmt_update_event = $conn->prepare($sql_update_event);
    $stmt_update_event->bind_param("i", $idEvento);
    $stmt_update_event->execute();
    $stmt_update_event->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Partecipante rimosso con successo!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>