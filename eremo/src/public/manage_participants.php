<?php
header('Content-Type: application/json; charset=utf-8');
// DB Config and connection (come sopra)
$host = "localhost"; $username = "eremofratefrancesco"; $password = ""; $dbname = "my_eremofratefrancesco";
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode(['success' => false, 'message' => "Errore DB."])); }
$conn->set_charset("utf8mb4");

$action = $_POST['action'] ?? '';
$output = ['success' => false, 'message' => 'Azione non valida.'];

if ($action === 'remove_participant') {
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $participantId = filter_input(INPUT_POST, 'participant_id', FILTER_VALIDATE_INT); // Questo è ProgressivoPart

    if (!$eventId || !$participantId) {
        $output['message'] = 'ID Evento o Partecipante mancante.';
        echo json_encode($output);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Trova il Progressivo della prenotazione a cui il partecipante è legato
        $stmtProg = $conn->prepare("SELECT Progressivo FROM Partecipanti WHERE ProgressivoPart = ?");
        $stmtProg->bind_param("i", $participantId);
        $stmtProg->execute();
        $resultProg = $stmtProg->get_result();
        if ($resultProg->num_rows === 0) {
            throw new Exception("Partecipante non trovato per ottenere il progressivo prenotazione.");
        }
        $prenotazioneProgressivo = $resultProg->fetch_assoc()['Progressivo'];
        $stmtProg->close();

        // 2. Elimina il partecipante
        $stmtDelete = $conn->prepare("DELETE FROM Partecipanti WHERE ProgressivoPart = ?");
        $stmtDelete->bind_param("i", $participantId);
        $stmtDelete->execute();

        if ($stmtDelete->affected_rows > 0) {
            // 3. Decrementa NumeroPosti nella tabella prenotazioni
            // E se NumeroPosti diventa 0, considera se eliminare la prenotazione o gestirla diversamente.
            // Qui, decrementiamo e basta.
            $stmtUpdatePrenotazione = $conn->prepare("UPDATE prenotazioni SET NumeroPosti = NumeroPosti - 1 WHERE Progressivo = ? AND IDEvento = ?");
            $stmtUpdatePrenotazione->bind_param("ii", $prenotazioneProgressivo, $eventId);
            $stmtUpdatePrenotazione->execute();
            $stmtUpdatePrenotazione->close();

            // 4. Incrementa PostiDisponibili nell'evento
            $stmtUpdateEvent = $conn->prepare("UPDATE eventi SET PostiDisponibili = PostiDisponibili + 1 WHERE IDEvento = ?");
            $stmtUpdateEvent->bind_param("i", $eventId);
            $stmtUpdateEvent->execute();
            $stmtUpdateEvent->close();

            $conn->commit();
            $output = ['success' => true, 'message' => 'Partecipante rimosso con successo. Posti aggiornati.'];
        } else {
            throw new Exception("Impossibile rimuovere il partecipante o partecipante non trovato.");
        }
        $stmtDelete->close();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Errore remove_participant: " . $e->getMessage());
        $output['message'] = 'Errore durante la rimozione: ' . $e->getMessage();
    }
}

$conn->close();
echo json_encode($output);
?>