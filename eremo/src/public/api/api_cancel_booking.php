<?php
// File: api_cancel_booking.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Usiamo POST per coerenza con invio dati
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
$idPrenotazione = $data['idPrenotazione'] ?? null;

if (empty($idPrenotazione) || !filter_var($idPrenotazione, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Prenotazione non valido.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Verifica che la prenotazione appartenga all'utente e recupera IDEvento e NumeroPosti
    $stmt_check = $conn->prepare("SELECT IDEvento, NumeroPosti FROM prenotazioni WHERE Progressivo = ? AND Contatto = ?");
    if(!$stmt_check) throw new Exception("Errore prepare check: " . $conn->error);
    $stmt_check->bind_param("is", $idPrenotazione, $user_email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $booking_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$booking_data) {
        http_response_code(403); // Forbidden o 404 Not Found
        throw new Exception('Prenotazione non trovata o non autorizzata.');
    }

    $idEvento = $booking_data['IDEvento'];
    $numeroPostiAnnullati = $booking_data['NumeroPosti'];

    // 2. Elimina i partecipanti associati (se la tabella Partecipanti usa Progressivo da prenotazioni come FK)
    // La tua tabella Partecipanti ha `Progressivo` come FK a `prenotazioni.Progressivo`
    $stmt_del_part = $conn->prepare("DELETE FROM Partecipanti WHERE Progressivo = ?");
    if(!$stmt_del_part) throw new Exception("Errore prepare del_part: " . $conn->error);
    $stmt_del_part->bind_param("i", $idPrenotazione);
    $stmt_del_part->execute();
    $stmt_del_part->close();
    // Non è un errore se non ci sono partecipanti da cancellare

    // 3. Elimina la prenotazione
    $stmt_del_book = $conn->prepare("DELETE FROM prenotazioni WHERE Progressivo = ?");
    if(!$stmt_del_book) throw new Exception("Errore prepare del_book: " . $conn->error);
    $stmt_del_book->bind_param("i", $idPrenotazione);
    $stmt_del_book->execute();
    if ($stmt_del_book->affected_rows === 0) {
        throw new Exception('Errore durante la cancellazione della prenotazione.');
    }
    $stmt_del_book->close();

    // 4. Aggiorna i posti disponibili nell'evento
    $stmt_update_event = $conn->prepare("UPDATE eventi SET PostiDisponibili = PostiDisponibili + ? WHERE IDEvento = ?");
    if(!$stmt_update_event) throw new Exception("Errore prepare update_event: " . $conn->error);
    $stmt_update_event->bind_param("ii", $numeroPostiAnnullati, $idEvento);
    $stmt_update_event->execute();
    $stmt_update_event->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Prenotazione annullata con successo.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Errore cancellazione prenotazione: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>