<?php
// Impostazioni PHP per Produzione
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_get_events.log');


header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db' => 'my_eremofratefrancesco', // Sostituisci con il tuo nome DB
    'user' => 'eremofratefrancesco', // Sostituisci con il tuo username DB
    'pass' => ''                   // INSERISCI QUI LA TUA PASSWORD DEL DATABASE
];

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Usa prepared statements nativi
        ]
    );

    // Recupera l'email dell'utente corrente dal parametro GET
    $currentUserEmail = null;
    if (isset($_GET['user_email'])) {
        $filteredEmail = filter_var($_GET['user_email'], FILTER_VALIDATE_EMAIL);
        if ($filteredEmail !== false) {
            $currentUserEmail = $filteredEmail;
        }
    }

    // Determina se la vista admin è richiesta
    $isAdminView = isset($_GET['admin_view']) && $_GET['admin_view'] === 'true';

    // Filtra SEMPRE per eventi da oggi in poi
    $sql_where = "WHERE e.Data >= CURDATE()"; // Aggiunto alias 'e' per la tabella eventi

    $sql_orderby = "";
    if ($isAdminView) {
        // Per l'admin, ordinamento per data decrescente (dal più lontano al più vicino)
        $sql_orderby = "ORDER BY e.Data DESC, e.IDEvento DESC"; // Aggiunto alias 'e'
    } else {
        // Vista pubblica, ordinamento per data ascendente (dal più vicino al più lontano)
        $sql_orderby = "ORDER BY e.Data ASC, e.IDEvento ASC"; // Aggiunto alias 'e'
    }

    // Query principale aggiornata per includere posti_gia_prenotati_utente
    $query = "
        SELECT
            e.IDEvento AS idevento,
            e.Titolo AS titolo,
            e.Data AS datainizio,
            e.Durata AS durata,
            e.Descrizione AS descrizione,
            e.DescrizioneEstesa AS descrizione_estesa,
            e.PostiDisponibili AS posti_disponibili,
            e.FlagPrenotabile AS flagprenotabile,
            e.Costo AS costo,
            e.PrefissoRelatore AS prefisso_relatore,
            e.Relatore AS relatore,
            e.Associazione AS associazione,
            e.FotoCopertina AS immagine_url,
            e.VolantinoUrl AS volantino_url,
            e.IDCategoria AS idcategoria,
            (SELECT COALESCE(SUM(p.NumeroPosti), 0)
             FROM prenotazioni p
             WHERE p.IDEvento = e.IDEvento AND p.Contatto = :currentUserEmail
            ) AS posti_gia_prenotati_utente
        FROM eventi e  -- Alias 'e' per la tabella eventi
        {$sql_where}
        {$sql_orderby}
    ";

    $stmt = $conn->prepare($query);

    // Associa il parametro currentUserEmail. Se $currentUserEmail è null,
    // la condizione p.Contatto = NULL non troverà corrispondenze (risultando correttamente 0).
    $stmt->bindParam(':currentUserEmail', $currentUserEmail, PDO::PARAM_STR);
    
    $stmt->execute();
    $events = $stmt->fetchAll();

    $responseData = [
        'success' => true,
        'data' => $events,
        'count' => count($events),
        'message' => (count($events) === 0) ? 'Nessun evento futuro o odierno trovato.' : ''
    ];

    $jsonOutput = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonOutput === false) {
        $jsonErrorMsg = json_last_error_msg();
        error_log("Errore JSON Encode in get_events.php: " . $jsonErrorMsg . " (Errore PHP: " . json_last_error() . ")");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Errore interno del server durante la formattazione dei dati (JSON).',
        ]);
    } else {
        echo $jsonOutput;
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore PDO in get_events.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante il recupero degli eventi dal database.'
    ]);
} catch (Throwable $e) { // Cattura anche Error in PHP 7+
    http_response_code(500);
    error_log("Errore generico in get_events.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto durante il caricamento degli eventi.'
    ]);
}
?>