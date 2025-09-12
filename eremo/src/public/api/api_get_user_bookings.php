<?php
// File: api_get_user_bookings.php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php'; // Assumendo che questo file gestisca la connessione $conn

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.', 'bookings' => []]);
    exit;
}
$user_email = $_SESSION['user_email'];

// --- QUERY CORRETTA ---
$sql = "SELECT 
            p.Progressivo AS idPrenotazione,
            p.NumeroPosti AS numeroPostiPrenotati,
            p.IDEvento AS idEvento,
            e.Titolo AS eventTitolo,
            e.Data AS eventDataInizio,
            e.FotoCopertina AS eventFotoCopertina,
            GROUP_CONCAT(par.ProgressivoPart SEPARATOR '||') AS participantIds, -- CORREZIONE: par.ProgressivoPart invece di par.ID
            GROUP_CONCAT(CONCAT(par.Nome, ' ', par.Cognome) SEPARATOR '||') AS participantNames
        FROM prenotazioni p
        JOIN eventi e ON p.IDEvento = e.IDEvento
        LEFT JOIN Partecipanti par ON par.Progressivo = p.Progressivo
        WHERE p.Contatto = ?
        GROUP BY p.Progressivo
        ORDER BY e.Data DESC";

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
    // Determina stato prenotazione
    $event_date = new DateTime($row['eventDataInizio']);
    $today = new DateTime();
    $row['statoPrenotazione'] = ($event_date < $today) ? "Concluso" : "Confermata";

    // --- NUOVA LOGICA PER PROCESSARE I PARTECIPANTI (invariata ma corretta grazie alla query) ---
    $row['participants'] = [];
    if (!empty($row['participantIds']) && !empty($row['participantNames'])) {
        $ids = explode('||', $row['participantIds']);
        $names = explode('||', ''.$row['participantNames']); // Cast a stringa per sicurezza
        
        if (count($ids) === count($names)) {
             $row['participants'] = array_map(function($id, $name) {
                return ['id' => (int)$id, 'name' => htmlspecialchars($name)];
            }, $ids, $names);
        }
    }
    unset($row['participantIds']);
    unset($row['participantNames']);
    
    $bookings[] = $row;
}

echo json_encode(['success' => true, 'bookings' => $bookings]);

$stmt->close();
$conn->close();
?>