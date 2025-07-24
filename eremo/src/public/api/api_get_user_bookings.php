<?php
// File: api_get_user_bookings.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.', 'bookings' => []]);
    exit;
}
$user_email = $_SESSION['user_email'];

$sql = "SELECT 
            p.Progressivo AS idPrenotazione,
            p.NumeroPosti AS numeroPostiPrenotati,
            p.IDEvento AS idEvento,
            e.Titolo AS eventTitolo,
            e.Data AS eventDataInizio,
            e.FotoCopertina AS eventFotoCopertina
        FROM prenotazioni p
        JOIN eventi e ON p.IDEvento = e.IDEvento
        WHERE p.Contatto = ?
        ORDER BY e.Data DESC"; // Ordina per data evento, le più recenti prima

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Errore prepare: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server (DB).', 'bookings' => []]);
    exit;
}
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    // Determina stato prenotazione (semplificato)
    $event_date = new DateTime($row['eventDataInizio']);
    $today = new DateTime();
    $row['statoPrenotazione'] = ($event_date < $today) ? "Concluso" : "Confermata";
    // Potresti aggiungere logica per "Annullata" se hai un campo stato in prenotazioni
    $bookings[] = $row;
}

echo json_encode(['success' => true, 'bookings' => $bookings]);

$stmt->close();
$conn->close();
?>