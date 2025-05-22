<?php
header('Content-Type: application/json; charset=utf-8');
// file: get_dashboard_event_data.php

// Impostazioni PHP per Produzione (puoi decommentare se necessario per debug)
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

// Configurazione e connessione PDO
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
    error_log("Errore DB (get_dashboard_event_data): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server nella connessione al database.']);
    exit;
}

$output = ['success' => false, 'upcoming_events' => [], 'past_events' => [], 'stats' => []];

try {
    // 1. Recupera tutti gli eventi con i campi aggiuntivi
    // Query aggiornata in base al dump SQL fornito
    $stmtEvents = $conn->query(
        "SELECT e.IDEvento, e.Titolo, e.Data, e.Durata, e.PostiDisponibili, e.FlagPrenotabile,
                e.FotoCopertina AS immagine_url,
                e.Descrizione AS descrizione,
                e.DescrizioneEstesa AS descrizione_estesa,
                e.PrefissoRelatore AS prefisso_relatore,
                e.Relatore AS relatore,                -- Campo per ricerca/ordinamento relatore
                e.Associazione AS associazione,
                e.Costo AS costo,
                e.VolantinoUrl AS volantino_url,       -- Corretto nome colonna
                e.IDCategoria
         FROM eventi e
         ORDER BY e.Data DESC, e.IDEvento DESC"
    );
    $eventsRaw = $stmtEvents->fetchAll();
    $detailedEvents = [];

    foreach ($eventsRaw as $event) {
        $idEvento = $event['IDEvento'];
        $eventData = $event;

        // 2. Recupera Prenotazioni Raggruppate per questo evento
        $stmtBookings = $conn->prepare(
            "SELECT p.Progressivo as idPrenotazione, p.Contatto, p.NumeroPosti,
                    ur.Nome as NomeUtenteRegistrato, ur.Cognome as CognomeUtenteRegistrato
             FROM prenotazioni p
             LEFT JOIN utentiregistrati ur ON p.Contatto = ur.Contatto
             WHERE p.IDEvento = :idEvento
             ORDER BY ur.Cognome, ur.Nome, p.Progressivo"
        );
        $stmtBookings->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtBookings->execute();
        $bookingsRawDetails = $stmtBookings->fetchAll();

        $eventData['elenco_prenotazioni'] = [];
        $currentTotalBookedSeats = 0;

        foreach ($bookingsRawDetails as $booking) {
            $stmtParticipants = $conn->prepare(
                "SELECT Nome, Cognome FROM Partecipanti WHERE Progressivo = :idPrenotazione ORDER BY Cognome, Nome ASC"
            );
            $stmtParticipants->bindParam(':idPrenotazione', $booking['idPrenotazione'], PDO::PARAM_INT);
            $stmtParticipants->execute();
            $booking['partecipanti_della_prenotazione'] = $stmtParticipants->fetchAll();
            $eventData['elenco_prenotazioni'][] = $booking;
            $currentTotalBookedSeats += (int)$booking['NumeroPosti'];
        }

        $eventData['posti_occupati'] = $currentTotalBookedSeats;

        // PostiDisponibili dalla tabella eventi è il numero di posti *attualmente* liberi.
        // Quindi posti_configurati_totali = posti_attualmente_liberi + posti_occupati.
        $postiDisponibiliAttualiDaDB = (int)$event['PostiDisponibili'];
        $eventData['posti_configurati_totali'] = $postiDisponibiliAttualiDaDB + $currentTotalBookedSeats;


        // 3. Conteggio Commenti per questo evento
        $stmtCommentCount = $conn->prepare("SELECT COUNT(*) as total_comments FROM commenti WHERE IDEvento = :idEvento");
        $stmtCommentCount->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtCommentCount->execute();
        $commentCountData = $stmtCommentCount->fetch();
        $eventData['comment_count'] = $commentCountData ? (int)$commentCountData['total_comments'] : 0;

        // 4. Conteggio Media per questo evento
        $stmtMediaCount = $conn->prepare("SELECT COUNT(*) as total_media FROM media WHERE IDEvento = :idEvento");
        $stmtMediaCount->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtMediaCount->execute();
        $mediaCountData = $stmtMediaCount->fetch();
        $eventData['media_count'] = $mediaCountData ? (int)$mediaCountData['total_media'] : 0;

        $detailedEvents[] = $eventData;
    }

    // Divisione eventi futuri e passati
    $upcomingEvents = [];
    $pastEvents = [];
    $currentDateOnly = new DateTime();
    $currentDateOnly->setTime(0,0,0);


    foreach ($detailedEvents as $event) {
        $eventDate = new DateTime($event['Data']);
        $eventDate->setTime(0,0,0);

        if ($eventDate >= $currentDateOnly) {
            $upcomingEvents[] = $event;
        } else {
            $pastEvents[] = $event;
        }
    }
    $output['upcoming_events'] = $upcomingEvents;
    $output['past_events'] = $pastEvents;


    // 5. Statistiche Globali
    $stmtTotalEvents = $conn->query("SELECT COUNT(*) FROM eventi");
    $output['stats']['totalEvents'] = $stmtTotalEvents->fetchColumn();

    $stmtTotalBookings = $conn->query("SELECT COUNT(*) FROM prenotazioni");
    $output['stats']['totalBookings'] = $stmtTotalBookings->fetchColumn();

    $stmtTotalParticipants = $conn->query("SELECT SUM(NumeroPosti) FROM prenotazioni");
    $totalParticipants = $stmtTotalParticipants->fetchColumn();
    $output['stats']['totalParticipants'] = $totalParticipants ? (int)$totalParticipants : 0;

    $stmtTotalComments = $conn->query("SELECT COUNT(*) FROM commenti");
    $output['stats']['totalComments'] = $stmtTotalComments->fetchColumn();

    $stmtTotalMedia = $conn->query("SELECT COUNT(*) FROM media");
    $output['stats']['totalMedia'] = $stmtTotalMedia->fetchColumn();


    $output['success'] = true;

} catch (PDOException $e) {
    error_log("Errore PDO in get_dashboard_event_data.php (ciclo/statistiche): " . $e->getMessage());
    $output['message'] = 'Errore nel recupero dei dettagli per la dashboard: ' . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("Errore generico in get_dashboard_event_data.php: " . $e->getMessage());
    $output['message'] = 'Si è verificato un errore imprevisto: ' . $e->getMessage();
    http_response_code(500);
}

if (!$output['success'] && empty($output['message'])) {
    $output['message'] = 'Errore sconosciuto durante l\'elaborazione dei dati.';
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
$conn = null;
?>