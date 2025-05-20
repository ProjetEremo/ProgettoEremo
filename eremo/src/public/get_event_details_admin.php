<?php
header('Content-Type: application/json; charset=utf-8');
// Impostazioni PHP per Produzione (come negli altri tuoi script)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_dashboard.log');

// Configurazione DB (come negli altri tuoi script)
$host = "localhost";
$username = "eremofratefrancesco";
$password = "";
$dbname = "my_eremofratefrancesco";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Connessione DB fallita: " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => "Errore DB."]));
}
$conn->set_charset("utf8mb4");

$output = ['success' => false, 'events' => [], 'stats' => ['totalEvents' => 0, 'totalBookings' => 0, 'totalParticipants' => 0, 'totalComments' => 0], 'message' => ''];

try {
    // Fetch Events
    // Ordina per data, magari i più recenti o futuri per primi
    $sqlEvents = "SELECT IDEvento as id_evento, Titolo as titolo, Data as data, Durata as orario, PostiDisponibili as posti_disponibili, DescrizioneEstesa as descrizione_estesa,
                  (SELECT SUM(NumeroPosti) FROM prenotazioni WHERE IDEvento = eventi.IDEvento) as posti_prenotati_attualmente,
                  (SELECT COUNT(*) FROM prenotazioni pr JOIN Partecipanti p ON pr.Progressivo = p.Progressivo WHERE pr.IDEvento = eventi.IDEvento) as numero_partecipanti_effettivi,
                  (SELECT COUNT(*) FROM commenti WHERE IDEvento = eventi.IDEvento) as numero_commenti,
                  (SELECT PostiDisponibili FROM eventi e_orig WHERE e_orig.IDEvento = eventi.IDEvento) as posti_disponibili_iniziali_o_configurati
                  FROM eventi ORDER BY Data DESC";
    $resultEvents = $conn->query($sqlEvents);
    $events = [];
    $totalBookingsOverall = 0;
    $totalParticipantsOverall = 0;
    $totalCommentsOverall = 0;

    if ($resultEvents && $resultEvents->num_rows > 0) {
        while ($rowEvent = $resultEvents->fetch_assoc()) {
            // Fetch Participants for each event
            $stmtParticipants = $conn->prepare(
                "SELECT p.ProgressivoPart as id_partecipante, p.Nome as nome, p.Cognome as cognome, pr.Contatto as contatto_prenotazione
                 FROM Partecipanti p
                 JOIN prenotazioni pr ON p.Progressivo = pr.Progressivo
                 WHERE pr.IDEvento = ? ORDER BY p.Cognome, p.Nome"
            );
            $stmtParticipants->bind_param("i", $rowEvent['id_evento']);
            $stmtParticipants->execute();
            $resultParticipants = $stmtParticipants->get_result();
            $participants = [];
            while ($rowParticipant = $resultParticipants->fetch_assoc()) {
                $participants[] = $rowParticipant;
            }
            $stmtParticipants->close();
            $rowEvent['participants'] = $participants;

            // Calcola i posti totali configurati per l'evento, se non già fatto o se il campo 'posti_disponibili' viene decrementato.
            // Per ora usiamo 'posti_disponibili_iniziali_o_configurati' che idealmente dovrebbe essere il valore originale.
            // Oppure, se PostiDisponibili in 'eventi' è il *rimanente*, allora Posti Totali = PostiDisponibili + PostiPrenotati
            // Qui presumo che tu abbia un campo che tiene i posti totali o che PostiDisponibili sia il rimanente
            // Per semplicità, se hai 'posti_disponibili_iniziali_o_configurati' è meglio.
            // Altrimenti, calcolalo:
            // $postiEffettivamentePrenotati = 0;
            // if($rowEvent['participants']) {
            //     foreach($rowEvent['participants'] as $participant_entry) { $postiEffettivamentePrenotati++; }
            // }
            // $rowEvent['posti_disponibili_totali'] = $rowEvent['posti_disponibili'] + $postiEffettivamentePrenotati;

            // Per i totali delle stats
            // $totalBookingsOverall += $rowEvent['posti_prenotati_attualmente'] ? intval($rowEvent['posti_prenotati_attualmente']) : 0; // Questo è basato su NumeroPosti in prenotazioni
            $totalBookingsOverall += $resultParticipants->num_rows > 0 ? 1 : 0; // Contiamo il numero di record di prenotazione distinti
            $totalParticipantsOverall += count($participants);
            $totalCommentsOverall += $rowEvent['numero_commenti'] ? intval($rowEvent['numero_commenti']) : 0;


            $events[] = $rowEvent;
        }
        $output['success'] = true;
        $output['events'] = $events;
    } else {
        $output['message'] = 'Nessun evento trovato.';
    }
    if ($resultEvents) $resultEvents->free();

    // Stats (Questi sono esempi, potresti volerli più precisi o da query separate)
    $output['stats']['totalEvents'] = count($events);

    // Per totalBookings (numero di righe in 'prenotazioni')
    $resBookings = $conn->query("SELECT COUNT(*) as count FROM prenotazioni");
    if($resBookings) $output['stats']['totalBookings'] = $resBookings->fetch_assoc()['count'];

    // Per totalParticipants (numero di righe in 'Partecipanti')
    $resParticipants = $conn->query("SELECT COUNT(*) as count FROM Partecipanti");
    if($resParticipants) $output['stats']['totalParticipants'] = $resParticipants->fetch_assoc()['count'];

    // Per totalComments (numero di righe in 'commenti', se hai una tabella commenti)
    // Assumendo una tabella 'commenti' con un IDEvento
    $resComments = $conn->query("SELECT COUNT(*) as count FROM commenti"); // Adatta se la tabella è diversa
    if($resComments) $output['stats']['totalComments'] = $resComments->fetch_assoc()['count'];


} catch (Exception $e) {
    error_log("Errore Dashboard Data: " . $e->getMessage());
    $output['message'] = "Errore durante il recupero dei dati: " . $e->getMessage();
}

$conn->close();
echo json_encode($output);
?>