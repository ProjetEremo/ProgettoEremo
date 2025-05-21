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
    'db'   => 'my_eremofratefrancesco', // Verificato da SQL dump
    'user' => 'eremofratefrancesco',    // Verificato da SQL dump
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

$output = ['success' => false, 'events' => [], 'stats' => []];

try {
    // 1. Recupera tutti gli eventi con i campi aggiuntivi
    // MODIFICA: Aggiunto e.FotoCopertina AS immagine_url
    $stmtEvents = $conn->query(
        "SELECT e.IDEvento, e.Titolo, e.Data, e.Durata, e.PostiDisponibili, e.FlagPrenotabile,
                e.FotoCopertina AS immagine_url  -- <<< CAMPO NECESSARIO PER L'IMMAGINE DI COPERTINA
         FROM eventi e
         ORDER BY e.Data DESC, e.IDEvento DESC"
    );
    $events = $stmtEvents->fetchAll();
    $detailedEvents = [];

    foreach ($events as $event) {
        $idEvento = $event['IDEvento'];
        $eventData = $event; // Contiene già immagine_url dalla query principale

        // 2. Recupera Prenotazioni Raggruppate per questo evento
        $stmtBookings = $conn->prepare(
            "SELECT p.Progressivo as idPrenotazione, p.Contatto, p.NumeroPosti,
                    ur.Nome as NomeUtenteRegistrato, ur.Cognome as CognomeUtenteRegistrato
             FROM prenotazioni p
             LEFT JOIN utentiregistrati ur ON p.Contatto = ur.Contatto
             WHERE p.IDEvento = :idEvento
             ORDER BY ur.Cognome, ur.Nome, p.Progressivo" // Ordina per Cognome, Nome per una lista più leggibile
        );
        $stmtBookings->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtBookings->execute();
        $bookingsRaw = $stmtBookings->fetchAll();

        $eventData['elenco_prenotazioni'] = [];
        $currentTotalBookedSeats = 0;

        foreach ($bookingsRaw as $booking) {
            $stmtParticipants = $conn->prepare(
                "SELECT Nome, Cognome FROM Partecipanti WHERE Progressivo = :idPrenotazione ORDER BY Cognome, Nome ASC" // Ordina per Cognome, Nome
            );
            $stmtParticipants->bindParam(':idPrenotazione', $booking['idPrenotazione'], PDO::PARAM_INT);
            $stmtParticipants->execute();
            $booking['partecipanti_della_prenotazione'] = $stmtParticipants->fetchAll();
            $eventData['elenco_prenotazioni'][] = $booking;
            $currentTotalBookedSeats += (int)$booking['NumeroPosti'];
        }

        $eventData['posti_occupati'] = $currentTotalBookedSeats;

        // Ottieni PostiConfiguratiTotali direttamente se esiste, altrimenti calcola
        // (Assumendo che tu abbia una colonna PostiConfiguratiTotali o un modo per determinarla)
        // Se non hai PostiConfiguratiTotali nella tabella eventi, puoi sommare posti_disponibili + posti_occupati
        // come facevi prima, ma questo assume che PostiDisponibili sia aggiornato correttamente.
        // Il tuo SQL dump mostra `PostiDisponibili` nella tabella `eventi`.
        // La logica originale era: (int)$event['PostiDisponibili'] + $currentTotalBookedSeats;
        // Questo è corretto se PostiDisponibili rappresenta i posti *attualmente* liberi.
        $stmtPostiConfig = $conn->prepare("SELECT PostiDisponibili FROM eventi WHERE IDEvento = :idEvento"); // O il campo che usi per i posti totali originali
        $stmtPostiConfig->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtPostiConfig->execute();
        $postiConfigRow = $stmtPostiConfig->fetch();
        $postiDisponibiliAttuali = $postiConfigRow ? (int)$postiConfigRow['PostiDisponibili'] : 0;

        $eventData['posti_configurati_totali'] = $postiDisponibiliAttuali + $currentTotalBookedSeats;


        // 3. Conteggio Commenti per questo evento
        $stmtCommentCount = $conn->prepare("SELECT COUNT(*) as total_comments FROM commenti WHERE IDEvento = :idEvento");
        $stmtCommentCount->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtCommentCount->execute();
        $commentCountData = $stmtCommentCount->fetch();
        $eventData['comment_count'] = $commentCountData ? (int)$commentCountData['total_comments'] : 0;

        // 4. MODIFICA: Conteggio Media per questo evento
        // La tabella media ha Progressivo, Percorso, Descrizione, IDEvento
        $stmtMediaCount = $conn->prepare("SELECT COUNT(*) as total_media FROM media WHERE IDEvento = :idEvento");
        $stmtMediaCount->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtMediaCount->execute();
        $mediaCountData = $stmtMediaCount->fetch();
        $eventData['media_count'] = $mediaCountData ? (int)$mediaCountData['total_media'] : 0;

        $detailedEvents[] = $eventData;
    }
    $output['events'] = $detailedEvents;

    // 5. Statistiche Globali (invariate, ma verificate)
    $stmtTotalEvents = $conn->query("SELECT COUNT(*) FROM eventi");
    $output['stats']['totalEvents'] = $stmtTotalEvents->fetchColumn();

    $stmtTotalBookings = $conn->query("SELECT COUNT(*) FROM prenotazioni"); // Conteggio delle righe di prenotazione
    $output['stats']['totalBookings'] = $stmtTotalBookings->fetchColumn();

    $stmtTotalParticipants = $conn->query("SELECT SUM(NumeroPosti) FROM prenotazioni"); // Somma dei posti prenotati
    $totalParticipants = $stmtTotalParticipants->fetchColumn();
    $output['stats']['totalParticipants'] = $totalParticipants ? (int)$totalParticipants : 0;

    $stmtTotalComments = $conn->query("SELECT COUNT(*) FROM commenti");
    $output['stats']['totalComments'] = $stmtTotalComments->fetchColumn();

    // Potresti aggiungere anche un conteggio totale dei media se utile nelle stats globali
    $stmtTotalMedia = $conn->query("SELECT COUNT(*) FROM media");
    $output['stats']['totalMedia'] = $stmtTotalMedia->fetchColumn();


    $output['success'] = true;

} catch (PDOException $e) {
    error_log("Errore PDO in get_dashboard_event_data.php (ciclo/statistiche): " . $e->getMessage());
    $output['message'] = 'Errore nel recupero dei dettagli per la dashboard.';
    http_response_code(500); // Imposta il codice di stato HTTP per errori server
} catch (Exception $e) { // Cattura eccezioni generiche
    error_log("Errore generico in get_dashboard_event_data.php: " . $e->getMessage());
    $output['message'] = 'Si è verificato un errore imprevisto.';
    http_response_code(500);
}

// Assicurati che l'output sia sempre JSON valido, anche in caso di errore parziale
if (!$output['success'] && empty($output['message'])) {
    $output['message'] = 'Errore sconosciuto durante l\'elaborazione dei dati.';
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); // JSON_PRETTY_PRINT rimosso per produzione, aggiunto JSON_INVALID_UTF8_SUBSTITUTE
$conn = null;
?>