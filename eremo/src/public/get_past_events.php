<?php
// Impostazioni PHP per Produzione
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
// Assicurati che il percorso del file di log sia scrivibile dal server
ini_set('error_log', __DIR__ . '/php_errors_get_past_events.log');

header('Content-Type: application/json; charset=utf-8');

// Configurazione del database (uguale a get_events.php)
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco', // Sostituisci con il tuo nome DB
    'user' => 'eremofratefrancesco',    // Sostituisci con il tuo username DB
    'pass' => ''                        // Sostituisci con la tua password DB
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

    // Query per recuperare gli eventi passati (Data < CURDATE())
    // Ordinati dal più recente dei passati
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
        WHERE Data < CURDATE()
        ORDER BY Data DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $events = $stmt->fetchAll();

    $responseData = [
        'success' => true,
        'data'    => $events,
        'count'   => count($events),
        'message' => (count($events) === 0) ? 'Nessun evento passato trovato.' : ''
    ];

    $jsonOutput = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonOutput === false) {
        $jsonErrorMsg = json_last_error_msg();
        error_log("Errore JSON Encode in get_past_events.php: " . $jsonErrorMsg . " (Errore PHP: " . json_last_error() . ")");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Errore interno del server durante la formattazione dei dati (JSON).'
        ]);
    } else {
        echo $jsonOutput;
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore PDO in get_past_events.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo json_encode([
        'success' => false,
        'error'   => 'Errore durante il recupero degli eventi passati dal database.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Errore generico in get_past_events.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo json_encode([
        'success' => false,
        'error'   => 'Si è verificato un errore imprevisto durante il caricamento degli eventi passati.'
    ]);
}
?>