<?php
header('Content-Type: application/json; charset=utf-8');
// file: get_dashboard_event_data.php

// Configurazione e connessione PDO (come nei tuoi script esistenti)
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // TUA PASSWORD DB
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
    error_log("Errore DB (get_dashboard_event_data): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server.']);
    exit;
}

$output = ['success' => false, 'events' => [], 'stats' => []];

try {
    // 1. Recupera tutti gli eventi
    $stmtEvents = $conn->query("SELECT IDEvento, Titolo, Data, Durata, PostiDisponibili, FlagPrenotabile FROM eventi ORDER BY Data DESC, IDEvento DESC");
    $events = $stmtEvents->fetchAll();
    $detailedEvents = [];

    foreach ($events as $event) {
        $idEvento = $event['IDEvento'];
        $eventData = $event;

        // 2. Recupera Prenotazioni Raggruppate per questo evento
        $stmtBookings = $conn->prepare(
            "SELECT p.Progressivo as idPrenotazione, p.Contatto, p.NumeroPosti,
                    ur.Nome as NomeUtenteRegistrato, ur.Cognome as CognomeUtenteRegistrato
             FROM prenotazioni p
             LEFT JOIN utentiregistrati ur ON p.Contatto = ur.Contatto
             WHERE p.IDEvento = :idEvento
             ORDER BY p.Contatto, p.Progressivo"
        );
        $stmtBookings->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtBookings->execute();
        $bookingsRaw = $stmtBookings->fetchAll();

        $eventData['elenco_prenotazioni'] = [];
        $currentTotalBookedSeats = 0;

        foreach ($bookingsRaw as $booking) {
            $stmtParticipants = $conn->prepare(
                "SELECT Nome, Cognome FROM Partecipanti WHERE Progressivo = :idPrenotazione ORDER BY ProgressivoPart ASC"
            );
            $stmtParticipants->bindParam(':idPrenotazione', $booking['idPrenotazione'], PDO::PARAM_INT);
            $stmtParticipants->execute();
            $booking['partecipanti_della_prenotazione'] = $stmtParticipants->fetchAll();
            $eventData['elenco_prenotazioni'][] = $booking;
            $currentTotalBookedSeats += (int)$booking['NumeroPosti'];
        }
        // Aggiungiamo i posti occupati e calcoliamo quelli totali (configurazione iniziale)
        $eventData['posti_occupati'] = $currentTotalBookedSeats;
        // Posti totali = quelli attualmente disponibili + quelli occupati
        $eventData['posti_configurati_totali'] = (int)$event['PostiDisponibili'] + $currentTotalBookedSeats;


        // 3. Conteggio Commenti per questo evento
        $stmtCommentCount = $conn->prepare("SELECT COUNT(*) as total_comments FROM commenti WHERE IDEvento = :idEvento");
        $stmtCommentCount->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtCommentCount->execute();
        $commentCountData = $stmtCommentCount->fetch();
        $eventData['comment_count'] = $commentCountData ? (int)$commentCountData['total_comments'] : 0;

        $detailedEvents[] = $eventData;
    }
    $output['events'] = $detailedEvents;

    // 4. Statistiche Globali
    $stmtTotalEvents = $conn->query("SELECT COUNT(*) FROM eventi");
    $output['stats']['totalEvents'] = $stmtTotalEvents->fetchColumn();

    $stmtTotalBookings = $conn->query("SELECT COUNT(*) FROM prenotazioni");
    $output['stats']['totalBookings'] = $stmtTotalBookings->fetchColumn();

    $stmtTotalParticipants = $conn->query("SELECT SUM(NumeroPosti) FROM prenotazioni");
    $totalParticipants = $stmtTotalParticipants->fetchColumn();
    $output['stats']['totalParticipants'] = $totalParticipants ? (int)$totalParticipants : 0;

    $stmtTotalComments = $conn->query("SELECT COUNT(*) FROM commenti");
    $output['stats']['totalComments'] = $stmtTotalComments->fetchColumn();

    $output['success'] = true;

} catch (PDOException $e) {
    error_log("Errore PDO in get_dashboard_event_data.php: " . $e->getMessage());
    $output['message'] = 'Errore nel recupero dei dati per la dashboard.';
    http_response_code(500);
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // JSON_PRETTY_PRINT utile per debug
$conn = null;
?>