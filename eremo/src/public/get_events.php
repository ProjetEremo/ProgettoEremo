<?php
// Impostazioni PHP per Produzione
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
// ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/php_error_log.txt');
ini_set('error_log', __DIR__ . '/php_errors_get_events.log');


header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db' => 'my_eremofratefrancesco', // Sostituisci con il tuo nome DB
    'user' => 'eremofratefrancesco', // Sostituisci con il tuo username DB
    'pass' => ''                   // Sostituisci con la tua password DB
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

    // Determina se la vista admin è richiesta, per un eventuale diverso ordinamento
    $isAdminView = isset($_GET['admin_view']) && $_GET['admin_view'] === 'true';

    // Filtra SEMPRE per eventi da oggi in poi
    $sql_where = "WHERE Data >= CURDATE()";

    // L'ordinamento può ancora dipendere dalla vista, se lo desideri
    // Ad esempio, l'admin potrebbe volerli vedere in ordine di inserimento o i più recenti prima (tra quelli futuri)
    // Mentre la vista pubblica li vede dal più prossimo al più lontano.
    $sql_orderby = "";
    if ($isAdminView) {
        // Per l'admin, mostriamo gli eventi futuri ordinati per data decrescente (dal più lontano al più vicino),
        // oppure per ID decrescente se vuoi vedere gli ultimi inseriti per primi.
        // Scegli quello che ha più senso per la gestione admin.
        // Esempio: order by data decrescente tra quelli futuri
        $sql_orderby = "ORDER BY Data DESC";
        // Oppure, se vuoi per data ascendente anche per admin:
        // $sql_orderby = "ORDER BY Data ASC";
    } else {
        // La vista pubblica vede gli eventi futuri ordinati per data ascendente (dal più vicino al più lontano)
        $sql_orderby = "ORDER BY Data ASC";
    }


    $query = "
        SELECT
            IDEvento AS idevento,
            Titolo AS titolo,
            Data AS datainizio,
            Durata AS durata,
            Descrizione AS descrizione,
            DescrizioneEstesa AS descrizione_estesa,
            PostiDisponibili AS posti_disponibili,
            FlagPrenotabile AS flagprenotabile,
            Costo AS costo,
            PrefissoRelatore AS prefisso_relatore,
            Relatore AS relatore,
            Associazione AS associazione,
            FotoCopertina AS immagine_url,
            VolantinoUrl AS volantino_url,
            IDCategoria AS idcategoria
        FROM eventi
        {$sql_where}    -- Applica SEMPRE il filtro per data >= CURDATE()
        {$sql_orderby}  -- Applica l'ordinamento scelto
    ";

    $stmt = $conn->prepare($query);
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