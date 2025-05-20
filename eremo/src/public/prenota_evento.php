<?php
header('Content-Type: application/json');
// Avvia la sessione se non già attiva, per coerenza, anche se l'email viene passata dal client
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurazione del database
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // INSERISCI QUI LA TUA PASSWORD DEL DATABASE
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
    error_log("Errore di connessione al database: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
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
$contattoUtente = filter_input(INPUT_POST, 'contatto', FILTER_VALIDATE_EMAIL);

$nomiPartecipanti = isset($_POST['partecipanti_nomi']) && is_array($_POST['partecipanti_nomi']) ? $_POST['partecipanti_nomi'] : [];
$cognomiPartecipanti = isset($_POST['partecipanti_cognomi']) && is_array($_POST['partecipanti_cognomi']) ? $_POST['partecipanti_cognomi'] : [];

$errorMessages = [];
if (!$eventId) {
    $errorMessages[] = 'ID Evento non valido.';
}
if (!$numeroPostiRichiesti || $numeroPostiRichiesti < 1 || $numeroPostiRichiesti > 5) {
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
    // NUOVO CONTROLLO: Limite massimo di 5 posti per utente per evento
    $stmtCheckUserBookings = $conn->prepare("SELECT SUM(NumeroPosti) AS total_booked_by_user FROM prenotazioni WHERE Contatto = :contatto AND IDEvento = :eventId");
    $stmtCheckUserBookings->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtCheckUserBookings->bindParam(':eventId', $eventId, PDO::PARAM_INT);
    $stmtCheckUserBookings->execute();
    $userBookingData = $stmtCheckUserBookings->fetch();
    $postiGiaPrenotatiUtente = $userBookingData ? (int)$userBookingData['total_booked_by_user'] : 0;

    if (($postiGiaPrenotatiUtente + $numeroPostiRichiesti) > 5) {
        $postiAncoraPrenotabili = 5 - $postiGiaPrenotatiUtente;
        $messaggioErrore = "Limite massimo di 5 posti per utente per questo evento superato. ";
        if ($postiAncoraPrenotabili > 0) {
            $messaggioErrore .= "Hai già prenotato {$postiGiaPrenotatiUtente} posti. Puoi prenotarne al massimo altri {$postiAncoraPrenotabili}.";
        } else {
            $messaggioErrore .= "Hai già raggiunto il limite di {$postiGiaPrenotatiUtente} posti.";
        }
        throw new Exception($messaggioErrore);
    }

    $conn->beginTransaction();

    // 1. Controlla posti disponibili e FlagPrenotabile per l'evento
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
        throw new Exception("Non ci sono abbastanza posti disponibili per '" . htmlspecialchars($evento['Titolo']) . "'. Posti rimasti: " . htmlspecialchars($evento['PostiDisponibili']) . ". Richiesti: " . $numeroPostiRichiesti);
    }

    // 2. Inserisci nella tabella prenotazioni
    $sqlPrenotazione = "INSERT INTO prenotazioni (NumeroPosti, Contatto, IDEvento) VALUES (:numPosti, :contatto, :idEvento)";
    $stmtPrenotazione = $conn->prepare($sqlPrenotazione);
    $stmtPrenotazione->bindParam(':numPosti', $numeroPostiRichiesti, PDO::PARAM_INT);
    $stmtPrenotazione->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtPrenotazione->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
    $stmtPrenotazione->execute();

    $idPrenotazione = $conn->lastInsertId();

    // 3. Inserisci i partecipanti nella tabella Partecipanti
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

    // 4. Aggiorna i posti disponibili nella tabella eventi
    $nuoviPostiDisponibili = (int)$evento['PostiDisponibili'] - $numeroPostiRichiesti;
    $sqlAggiornaEvento = "UPDATE eventi SET PostiDisponibili = :nuoviPosti WHERE IDEvento = :idEvento";
    $stmtAggiornaEvento = $conn->prepare($sqlAggiornaEvento);
    $stmtAggiornaEvento->bindParam(':nuoviPosti', $nuoviPostiDisponibili, PDO::PARAM_INT);
    $stmtAggiornaEvento->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
    $stmtAggiornaEvento->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Prenotazione per l'evento '" . htmlspecialchars($evento['Titolo']) . "' effettuata con successo per {$numeroPostiRichiesti} partecipante/i!",
        'idPrenotazione' => $idPrenotazione
    ]);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore durante la prenotazione: " . $e->getMessage() . " (Evento ID: " . $eventId . ", Utente: " . $contattoUtente . ")");
    http_response_code(400); // Spesso 400 per errori di validazione o di logica di business
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn = null;
}
?>