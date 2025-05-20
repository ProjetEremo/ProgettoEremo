<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    error_log("Errore DB in gestisci_coda.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server durante l\'aggiunta alla coda.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$eventId = filter_input(INPUT_POST, 'eventId', FILTER_VALIDATE_INT);
$numeroPostiRichiestiCoda = filter_input(INPUT_POST, 'numeroPosti', FILTER_VALIDATE_INT);
$contattoUtente = filter_input(INPUT_POST, 'contatto', FILTER_VALIDATE_EMAIL);

$errorMessages = [];
if (!$eventId) {
    $errorMessages[] = 'ID Evento per la coda non valido.';
}
if (!$numeroPostiRichiestiCoda || $numeroPostiRichiestiCoda < 1 || $numeroPostiRichiestiCoda > 5) {
    $errorMessages[] = 'Il numero di posti per la coda deve essere tra 1 e 5.';
}
if (!$contattoUtente) {
    $errorMessages[] = 'Email del contatto per la coda non valida.';
}

if (!empty($errorMessages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errorMessages)]);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Verifica se l'utente ha già raggiunto il limite di 5 prenotazioni CONFERMATE per l'evento.
    // Se sì, non può nemmeno mettersi in coda per altri posti (politica da confermare, ma sembra logica).
    $stmtCheckUserBookings = $conn->prepare("SELECT SUM(NumeroPosti) AS total_booked_by_user FROM prenotazioni WHERE Contatto = :contatto AND IDEvento = :eventId");
    $stmtCheckUserBookings->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtCheckUserBookings->bindParam(':eventId', $eventId, PDO::PARAM_INT);
    $stmtCheckUserBookings->execute();
    $userBookingData = $stmtCheckUserBookings->fetch();
    $postiGiaPrenotatiUtente = $userBookingData ? (int)$userBookingData['total_booked_by_user'] : 0;

    if ($postiGiaPrenotatiUtente >= 5) {
         throw new Exception("Hai già raggiunto il limite massimo di 5 prenotazioni confermate per questo evento. Non è possibile aggiungersi ulteriormente alla lista d'attesa.");
    }

    // (Opzionale) Potremmo anche limitare il numero di posti in coda + prenotati a 5.
    // Per ora, il controllo è solo sui prenotati.

    // 2. Controlla lo stato dell'evento
    $stmtEvento = $conn->prepare("SELECT Titolo, PostiDisponibili, FlagPrenotabile FROM eventi WHERE IDEvento = :idevento");
    $stmtEvento->bindParam(':idevento', $eventId, PDO::PARAM_INT);
    $stmtEvento->execute();
    $evento = $stmtEvento->fetch();

    if (!$evento) {
        throw new Exception("Evento non trovato.");
    }

    // Se l'evento per qualche motivo ha posti e l'utente ha provato a mettersi in coda (improbabile con la UI aggiornata)
    if ($evento['FlagPrenotabile'] && (int)$evento['PostiDisponibili'] > 0) {
        // throw new Exception("L'evento ha ancora posti disponibili. Si prega di prenotare direttamente.");
        // Oppure si potrebbe procedere, ma la UI dovrebbe prevenire questo scenario.
    }

    // 3. Inserisci o aggiorna la richiesta nella tabella utentiincoda
    // La PK è (Contatto, IDEvento), quindi un utente ha una sola entry per evento in coda.
    // Se l'utente è già in coda, aggiorniamo il NumeroInCoda sommando i nuovi posti richiesti,
    // ma non superando un massimo di 5 posti totali in coda per quella entry.
    $stmtExistingQueue = $conn->prepare("SELECT NumeroInCoda FROM utentiincoda WHERE Contatto = :contatto AND IDEvento = :eventId");
    $stmtExistingQueue->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtExistingQueue->bindParam(':eventId', $eventId, PDO::PARAM_INT);
    $stmtExistingQueue->execute();
    $queueEntry = $stmtExistingQueue->fetch();

    $postiFinaliInCoda = $numeroPostiRichiestiCoda;
    if ($queueEntry) { // L'utente è già in coda
        $postiFinaliInCoda = $queueEntry['NumeroInCoda'] + $numeroPostiRichiestiCoda;
    }

    // Limita i posti in coda a un massimo di 5 per questa richiesta/aggiornamento.
    if ($postiFinaliInCoda > 5) {
        $postiFinaliInCoda = 5;
    }
    // Esegui un UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
    $sqlUpsertCoda = "INSERT INTO utentiincoda (Contatto, IDEvento, NumeroInCoda)
                      VALUES (:contatto, :idEvento, :numInCoda)
                      ON DUPLICATE KEY UPDATE NumeroInCoda = :numInCodaUpdate";

    $stmtUpsertCoda = $conn->prepare($sqlUpsertCoda);
    $stmtUpsertCoda->bindParam(':contatto', $contattoUtente, PDO::PARAM_STR);
    $stmtUpsertCoda->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
    $stmtUpsertCoda->bindParam(':numInCoda', $postiFinaliInCoda, PDO::PARAM_INT); // Valore per INSERT
    $stmtUpsertCoda->bindParam(':numInCodaUpdate', $postiFinaliInCoda, PDO::PARAM_INT); // Valore per UPDATE
    $stmtUpsertCoda->execute();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Sei stato aggiunto/aggiornato nella lista d'attesa per l'evento '" . htmlspecialchars($evento['Titolo']) . "' con {$postiFinaliInCoda} posto/i."
    ]);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore in gestisci_coda.php: " . $e->getMessage() . " (Evento ID: " . $eventId . ", Utente: " . $contattoUtente . ")");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn = null;
}
?>