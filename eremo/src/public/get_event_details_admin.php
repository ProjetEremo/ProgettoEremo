<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0); // In produzione, gli errori non dovrebbero essere mostrati all'utente
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_dashboard_events.log'); // Nome file log specifico

// Configurazione DB (coerente con gli altri script PDO)
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE
];

$output = [
    'success' => false, 
    'upcoming_events' => [], // Per dashboard.html JS
    'past_events' => [],     // Per dashboard.html JS
    'stats' => [
        'totalEvents' => 0, 
        'totalBookingsOverall' => 0, 
        'totalParticipantsOverall' => 0, 
        'totalCommentsOverall' => 0
    ], 
    'message' => ''
];

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

    // Recupera tutti gli eventi con dettagli aggiuntivi
    // La dashboard JS si aspetta 'IDEvento', 'Titolo', 'Data', 'Durata', 'DescrizioneEstesa' etc.
    // e 'elenco_prenotazioni', 'posti_occupati', 'comment_count', 'media_count', 'posti_configurati_totali'
    $sqlEvents = "
        SELECT 
            e.IDEvento, e.Titolo, e.Data, e.Durata, e.Descrizione AS descrizione, e.DescrizioneEstesa AS descrizione_estesa,
            e.PrefissoRelatore AS prefisso_relatore, e.Relatore AS relatore, e.Associazione AS associazione,
            e.PostiDisponibili AS PostiDisponibili, -- Posti attualmente rimanenti
            e.FotoCopertina AS immagine_url, e.VolantinoUrl AS volantino_url,
            e.FlagPrenotabile, e.Costo AS costo,
            (SELECT SUM(pr.NumeroPosti) FROM prenotazioni pr WHERE pr.IDEvento = e.IDEvento) AS posti_attualmente_prenotati_da_numeroposti,
            (SELECT COUNT(p.ProgressivoPart) FROM prenotazioni pr_p JOIN Partecipanti p ON pr_p.Progressivo = p.Progressivo WHERE pr_p.IDEvento = e.IDEvento) AS posti_occupati, -- Numero effettivo di partecipanti
            (SELECT COUNT(*) FROM commenti c WHERE c.IDEvento = e.IDEvento) AS comment_count,
            (SELECT COUNT(*) FROM media m WHERE m.IDEvento = e.IDEvento) AS media_count
        FROM eventi e
        ORDER BY e.Data DESC"; 
        // Nota: 'posti_configurati_totali' andrebbe calcolato o letto da un campo apposito.
        // Se PostiDisponibili è il *rimanente*, allora PostiConfigurati = PostiDisponibili + posti_occupati (o somma di NumeroPosti).
        // Per ora, il JS della dashboard sembra usare PostiDisponibili o un campo 'posti_configurati_totali' se esiste in eventData.
        // Per semplicità, lo aggiungiamo qui come PostiDisponibili + posti_occupati se PostiDisponibili è il rimanente.
        // Se PostiDisponibili è il totale iniziale, allora il JS deve fare il calcolo dei rimanenti.
        // Assumiamo che 'PostiDisponibili' in tabella 'eventi' sia il numero *totale iniziale* di posti.
        // Quindi, 'posti_configurati_totali' è e.PostiDisponibili
    
    $stmtEvents = $conn->query($sqlEvents);
    $allEvents = $stmtEvents->fetchAll();

    $upcomingEvents = [];
    $pastEvents = [];
    $currentDate = new DateTime();
    $currentDate->setTime(0,0,0); // Per confronto solo data

    foreach ($allEvents as $event) {
        // Calcola 'posti_configurati_totali'
        // Se e.PostiDisponibili è il numero totale iniziale di posti:
        $event['posti_configurati_totali'] = (int)$event['PostiDisponibili'];

        // Recupera l'elenco delle prenotazioni per questo evento (richiesto dal JS della dashboard)
        $stmtBookings = $conn->prepare(
            "SELECT 
                pr.Progressivo AS idPrenotazione, pr.Contatto, pr.NumeroPosti, pr.DataPrenotazione,
                ur.Nome AS NomeUtenteRegistrato, ur.Cognome AS CognomeUtenteRegistrato
             FROM prenotazioni pr
             LEFT JOIN utentiregistrati ur ON pr.Contatto = ur.Contatto
             WHERE pr.IDEvento = :idEvento
             ORDER BY pr.DataPrenotazione DESC"
        );
        $stmtBookings->bindParam(':idEvento', $event['IDEvento'], PDO::PARAM_INT);
        $stmtBookings->execute();
        $bookingsForEvent = $stmtBookings->fetchAll();

        // Per ogni prenotazione, recupera i partecipanti
        foreach ($bookingsForEvent as $key => $booking) {
            $stmtParticipants = $conn->prepare(
                "SELECT ProgressivoPart, Nome, Cognome FROM Partecipanti WHERE Progressivo = :idPrenotazione ORDER BY Cognome, Nome"
            );
            $stmtParticipants->bindParam(':idPrenotazione', $booking['idPrenotazione'], PDO::PARAM_INT);
            $stmtParticipants->execute();
            $bookingsForEvent[$key]['partecipanti_della_prenotazione'] = $stmtParticipants->fetchAll();
        }
        $event['elenco_prenotazioni'] = $bookingsForEvent;
        
        // Converti NULL a 0 per i conteggi se necessario
        $event['posti_occupati'] = (int)($event['posti_occupati'] ?? 0);
        $event['comment_count'] = (int)($event['comment_count'] ?? 0);
        $event['media_count'] = (int)($event['media_count'] ?? 0);

        $eventDate = new DateTime($event['Data']);
        $eventDate->setTime(0,0,0);

        if ($eventDate >= $currentDate) {
            $upcomingEvents[] = $event;
        } else {
            $pastEvents[] = $event;
        }
    }
    
    // Ordina gli eventi futuri per data ascendente, passati per data discendente (come nel JS)
    usort($upcomingEvents, function($a, $b) { return strtotime($a['Data']) - strtotime($b['Data']); });
    // $pastEvents sono già DESC dalla query SQL

    $output['success'] = true;
    $output['upcoming_events'] = $upcomingEvents;
    $output['past_events'] = $pastEvents;

    // Calcolo statistiche generali
    $output['stats']['totalEvents'] = count($allEvents);
    
    $resBookings = $conn->query("SELECT COUNT(*) as count FROM prenotazioni")->fetchColumn();
    $output['stats']['totalBookingsOverall'] = (int)$resBookings;

    $resParticipants = $conn->query("SELECT COUNT(*) as count FROM Partecipanti")->fetchColumn();
    $output['stats']['totalParticipantsOverall'] = (int)$resParticipants;
    
    $resComments = $conn->query("SELECT COUNT(*) as count FROM commenti")->fetchColumn();
    $output['stats']['totalCommentsOverall'] = (int)$resComments;


} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details_admin.php: " . $e->getMessage());
    $output['message'] = "Errore database durante il recupero dei dati dashboard: " . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("Errore generico in get_event_details_admin.php: " . $e->getMessage());
    $output['message'] = "Errore generale durante il recupero dei dati dashboard: " . $e->getMessage();
    http_response_code(500);
}

$conn = null;
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>