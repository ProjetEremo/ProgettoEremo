<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurazione del database
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco', // Assicurati che sia il nome corretto del DB
    'user' => 'eremofratefrancesco',    // Il tuo username DB
    'pass' => ''                       // !!! INSERISCI QUI LA TUA PASSWORD DEL DATABASE !!!
];

$conn = null;

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore di connessione al database (prenota_evento.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database. Riprova più tardi.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

// Recupero e validazione dati
$eventId = filter_input(INPUT_POST, 'eventId', FILTER_VALIDATE_INT);
$numeroPostiRichiesti = filter_input(INPUT_POST, 'numeroPosti', FILTER_VALIDATE_INT);
$contattoUtente = filter_input(INPUT_POST, 'contatto', FILTER_VALIDATE_EMAIL); // Email dell'utente che prenota

$nomiPartecipanti = isset($_POST['partecipanti_nomi']) && is_array($_POST['partecipanti_nomi']) ? $_POST['partecipanti_nomi'] : [];
$cognomiPartecipanti = isset($_POST['partecipanti_cognomi']) && is_array($_POST['partecipanti_cognomi']) ? $_POST['partecipanti_cognomi'] : [];

$errorMessages = [];
if (!$eventId) {
    $errorMessages[] = 'ID Evento non valido.';
}
if (!$numeroPostiRichiesti || $numeroPostiRichiesti < 1 || $numeroPostiRichiesti > 5) { // Limite posti per singola prenotazione
    $errorMessages[] = 'Il numero di posti richiesti deve essere tra 1 e 5.';
}
if (!$contattoUtente) {
    $errorMessages[] = 'Email del contatto non valida.';
}
if (count($nomiPartecipanti) !== $numeroPostiRichiesti || count($cognomiPartecipanti) !== $numeroPostiRichiesti) {
    $errorMessages[] = 'Il numero di partecipanti non corrisponde ai nomi/cognomi forniti.';
} else {
    for ($i = 0; $i < $numeroPostiRichiesti; $i++) {
        if (empty(trim($nomiPartecipanti[$i])) || empty(trim($cognomiPartecipanti[$i]))) {
            $errorMessages[] = 'Nome e cognome sono obbligatori per tutti i partecipanti.';
            break;
        }
    }
}

if (!empty($errorMessages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errorMessages)]);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Controlla se l'utente ha già prenotato il massimo dei posti consentiti per questo evento
    $stmtCheckUserBookings = $conn->prepare("SELECT SUM(NumeroPosti) AS total_booked_by_user FROM prenotazioni WHERE Contatto = :contatto AND IDEvento = :eventId");
    $stmtCheckUserBookings->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtCheckUserBookings->bindParam(':eventId', $eventId, PDO::PARAM_INT);
    $stmtCheckUserBookings->execute();
    $userBookingData = $stmtCheckUserBookings->fetch();
    $postiGiaPrenotatiUtente = $userBookingData ? (int)$userBookingData['total_booked_by_user'] : 0;

    if (($postiGiaPrenotatiUtente + $numeroPostiRichiesti) > 5) { // Limite totale per utente per evento
        $postiAncoraPrenotabili = 5 - $postiGiaPrenotatiUtente;
        $messaggioErrore = "Limite massimo di 5 posti per utente per questo evento superato. ";
        if ($postiAncoraPrenotabili > 0) {
            $messaggioErrore .= "Hai già prenotato {$postiGiaPrenotatiUtente} posti. Puoi prenotarne al massimo altri {$postiAncoraPrenotabili}.";
        } else {
            $messaggioErrore .= "Hai già raggiunto il limite di {$postiGiaPrenotatiUtente} posti.";
        }
        throw new Exception($messaggioErrore);
    }

    // 2. Controlla posti disponibili e FlagPrenotabile per l'evento (con lock per concorrenza)
    $stmtEvento = $conn->prepare("SELECT Titolo, PostiDisponibili, FlagPrenotabile FROM eventi WHERE IDEvento = :idevento FOR UPDATE");
    $stmtEvento->bindParam(':idevento', $eventId, PDO::PARAM_INT);
    $stmtEvento->execute();
    $evento = $stmtEvento->fetch();

    if (!$evento) {
        throw new Exception("Evento non trovato (ID: " . htmlspecialchars($eventId) . ").");
    }

    if (!$evento['FlagPrenotabile']) {
        throw new Exception("L'evento '" . htmlspecialchars($evento['Titolo']) . "' non è attualmente prenotabile.");
    }

    if ((int)$evento['PostiDisponibili'] < $numeroPostiRichiesti) {
        throw new Exception("Spiacenti, non ci sono abbastanza posti disponibili per '" . htmlspecialchars($evento['Titolo']) . "'. Posti rimasti: " . htmlspecialchars($evento['PostiDisponibili']) . ". Richiesti: " . $numeroPostiRichiesti);
    }

    // 3. Inserisci nella tabella prenotazioni
    $sqlPrenotazione = "INSERT INTO prenotazioni (NumeroPosti, Contatto, IDEvento) VALUES (:numPosti, :contatto, :idEvento)";
    $stmtPrenotazione = $conn->prepare($sqlPrenotazione);
    $stmtPrenotazione->bindParam(':numPosti', $numeroPostiRichiesti, PDO::PARAM_INT);
    $stmtPrenotazione->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtPrenotazione->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
    $stmtPrenotazione->execute();

    $idPrenotazione = $conn->lastInsertId(); // Ottieni l'ID della prenotazione appena inserita

    // 4. Inserisci i partecipanti nella tabella Partecipanti
    $sqlPartecipante = "INSERT INTO Partecipanti (Nome, Cognome, Progressivo) VALUES (:nome, :cognome, :idPrenotazione)";
    $stmtPartecipante = $conn->prepare($sqlPartecipante);

    for ($i = 0; $i < $numeroPostiRichiesti; $i++) {
        $nomeSanificato = htmlspecialchars(trim($nomiPartecipanti[$i]), ENT_QUOTES, 'UTF-8');
        $cognomeSanificato = htmlspecialchars(trim($cognomiPartecipanti[$i]), ENT_QUOTES, 'UTF-8');

        $stmtPartecipante->bindParam(':nome', $nomeSanificato, PDO::PARAM_STR);
        $stmtPartecipante->bindParam(':cognome', $cognomeSanificato, PDO::PARAM_STR);
        $stmtPartecipante->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
        $stmtPartecipante->execute();
    }

    // 5. Aggiorna i posti disponibili nella tabella eventi
    $nuoviPostiDisponibili = (int)$evento['PostiDisponibili'] - $numeroPostiRichiesti;
    $sqlAggiornaEvento = "UPDATE eventi SET PostiDisponibili = :nuoviPosti WHERE IDEvento = :idEvento";
    $stmtAggiornaEvento = $conn->prepare($sqlAggiornaEvento);
    $stmtAggiornaEvento->bindParam(':nuoviPosti', $nuoviPostiDisponibili, PDO::PARAM_INT);
    $stmtAggiornaEvento->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
    $stmtAggiornaEvento->execute();

    // Fine transazione principale
    $conn->commit();

    // 6. AGGIUNTA: Rimuovi l'utente dalla coda di attesa per questo evento, se presente
    // Questo blocco viene eseguito DOPO che la prenotazione è stata confermata.
    try {
        $stmtRimuoviCoda = $conn->prepare("DELETE FROM utentiincoda WHERE Contatto = :contatto AND IDEvento = :idEvento");
        $stmtRimuoviCoda->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
        $stmtRimuoviCoda->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtRimuoviCoda->execute();
        if ($stmtRimuoviCoda->rowCount() > 0) {
            error_log("Utente {$contattoUtente} rimosso con successo dalla coda per evento {$eventId} dopo aver effettuato la prenotazione.");
        }
    } catch (PDOException $eCoda) {
        // Logga l'errore ma non far fallire la risposta di successo della prenotazione principale per questo.
        // L'utente ha comunque prenotato.
        error_log("ATTENZIONE: Errore durante la rimozione automatica dalla coda per utente {$contattoUtente}, evento {$eventId}: " . $eCoda->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => "Prenotazione per l'evento '" . htmlspecialchars($evento['Titolo']) . "' effettuata con successo per {$numeroPostiRichiesti} partecipante/i!",
        'idPrenotazione' => $idPrenotazione
    ]);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore durante la prenotazione (prenota_evento.php): " . $e->getMessage() . " (Evento ID: " . htmlspecialchars($eventId ?? 'N/D') . ", Utente: " . htmlspecialchars($contattoUtente ?? 'N/D') . ")");
    http_response_code(400); // Errore client o di logica di business
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn = null;
}
?>