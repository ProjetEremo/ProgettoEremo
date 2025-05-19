<?php
header('Content-Type: application/json');
session_start(); // Assicurati che la sessione sia attiva per recuperare l'utente loggato se necessario

// Configurazione del database (usa la tua configurazione)
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // La tua password del DB
];

// Connessione al database usando PDO per le transazioni
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
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

// Recupero e validazione dati
$eventId = filter_input(INPUT_POST, 'eventId', FILTER_VALIDATE_INT);
$numeroPostiRichiesti = filter_input(INPUT_POST, 'numeroPosti', FILTER_VALIDATE_INT);
$contattoUtente = filter_input(INPUT_POST, 'contatto', FILTER_VALIDATE_EMAIL); // Email dell'utente loggato

$nomiPartecipanti = isset($_POST['partecipanti_nomi']) ? $_POST['partecipanti_nomi'] : [];
$cognomiPartecipanti = isset($_POST['partecipanti_cognomi']) ? $_POST['partecipanti_cognomi'] : [];


if (!$eventId || !$numeroPostiRichiesti || !$contattoUtente) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dati mancanti o non validi (evento, posti, contatto).']);
    exit;
}

if ($numeroPostiRichiesti < 1 || $numeroPostiRichiesti > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Il numero di posti deve essere tra 1 e 5.']);
    exit;
}

if (count($nomiPartecipanti) !== $numeroPostiRichiesti || count($cognomiPartecipanti) !== $numeroPostiRichiesti) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Il numero di partecipanti non corrisponde ai nomi forniti.']);
    exit;
}

foreach ($nomiPartecipanti as $key => $nome) {
    if (empty(trim($nome)) || empty(trim($cognomiPartecipanti[$key]))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome e cognome sono obbligatori per tutti i partecipanti.']);
        exit;
    }
}


try {
    $conn->beginTransaction();

    // 1. Controlla posti disponibili e FlagPrenotabile per l'evento
    $stmtEvento = $conn->prepare("SELECT Titolo, PostiDisponibili, FlagPrenotabile FROM eventi WHERE IDEvento = ? FOR UPDATE"); // FOR UPDATE per lockare la riga
    $stmtEvento->execute([$eventId]);
    $evento = $stmtEvento->fetch();

    if (!$evento) {
        throw new Exception("Evento non trovato.");
    }

    if (!$evento['FlagPrenotabile']) {
        throw new Exception("L'evento '{$evento['Titolo']}' non è attualmente prenotabile.");
    }

    if ($evento['PostiDisponibili'] < $numeroPostiRichiesti) {
        throw new Exception("Non ci sono abbastanza posti disponibili per '{$evento['Titolo']}'. Posti rimasti: {$evento['PostiDisponibili']}.");
    }

    // 2. Inserisci nella tabella prenotazioni
    $sqlPrenotazione = "INSERT INTO prenotazioni (numeroPosti, contatto, idEvento, dataPrenotazione) VALUES (?, ?, ?, NOW())";
    $stmtPrenotazione = $conn->prepare($sqlPrenotazione);
    $stmtPrenotazione->execute([$numeroPostiRichiesti, $contattoUtente, $eventId]);
    $idPrenotazione = $conn->lastInsertId(); // ID della nuova prenotazione

    // 3. Inserisci i partecipanti nella tabella Partecipanti
    $sqlPartecipante = "INSERT INTO Partecipanti (nome, cognome, progressivo) VALUES (?, ?, ?)";
    $stmtPartecipante = $conn->prepare($sqlPartecipante);

    for ($i = 0; $i < $numeroPostiRichiesti; $i++) {
        $nome = trim($nomiPartecipanti[$i]);
        $cognome = trim($cognomiPartecipanti[$i]);
        $stmtPartecipante->execute([$nome, $cognome, $idPrenotazione]);
    }

    // 4. Aggiorna i posti disponibili nella tabella eventi
    $nuoviPostiDisponibili = $evento['PostiDisponibili'] - $numeroPostiRichiesti;
    $sqlAggiornaEvento = "UPDATE eventi SET PostiDisponibili = ? WHERE IDEvento = ?";
    $stmtAggiornaEvento = $conn->prepare($sqlAggiornaEvento);
    $stmtAggiornaEvento->execute([$nuoviPostiDisponibili, $eventId]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Prenotazione per l'evento '{$evento['Titolo']}' effettuata con successo per {$numeroPostiRichiesti} partecipante/i!",
        'idPrenotazione' => $idPrenotazione
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400); // Bad Request o 500 per Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn = null; // Chiudi connessione
?>