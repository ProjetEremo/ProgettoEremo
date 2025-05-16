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

    // Query per recuperare gli eventi
    // Per la pagina pubblica, potresti volere solo eventi futuri: WHERE Data >= CURDATE()
    // Per la pagina admin, potresti volere tutti gli eventi per la gestione.
    // Modifica la clausola WHERE e ORDER BY secondo le necessità della pagina che chiama questo script.

    // Assumiamo che questo script sia chiamato sia da eventiincorso.html (solo futuri)
    // che da eventiincorsoAdmin.html (tutti, o comunque con logica diversa).
    // Per semplicità, qui recuperiamo tutti gli eventi futuri.
    // Se la pagina Admin necessita di TUTTI gli eventi, dovrai creare un endpoint separato
    // o passare un parametro per modificare la query.

    $showAll = isset($_GET['admin_view']) && $_GET['admin_view'] === 'true'; // Esempio parametro per admin

    $sql_where = "WHERE Data >= CURDATE()";
    $sql_orderby = "ORDER BY Data ASC";

    if ($showAll) {
        $sql_where = ""; // Nessun filtro sulla data per admin
        $sql_orderby = "ORDER BY Data DESC"; // Admin vede i più recenti prima, per esempio
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
            /* Aggiungi altri campi se necessario, es. un flag per 'offerta libera' separato dal costo=0 */
        FROM eventi
        {$sql_where}
        {$sql_orderby}
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $events = $stmt->fetchAll();

    // I percorsi immagine_url e volantino_url sono già relativi e corretti.
    // Non è necessaria ulteriore elaborazione qui a meno che non si vogliano URL assoluti.
    // Se necessario, si potrebbe anteporre l'URL base del sito:
    // define('BASE_URL', 'http://tuosito.altervista.org/');
    // foreach ($events as &$event) {
    //     if ($event['immagine_url'] && !filter_var($event['immagine_url'], FILTER_VALIDATE_URL)) {
    //         $event['immagine_url'] = BASE_URL . ltrim($event['immagine_url'], '/');
    //     }
    //     if ($event['volantino_url'] && !filter_var($event['volantino_url'], FILTER_VALIDATE_URL)) {
    //         $event['volantino_url'] = BASE_URL . ltrim($event['volantino_url'], '/');
    //     }
    // }
    // unset($event);


    $responseData = [
        'success' => true,
        'data' => $events,
        'count' => count($events),
        'message' => (count($events) === 0) ? 'Nessun evento trovato.' : ''
    ];

    // JSON_UNESCAPED_UNICODE per i caratteri accentati, JSON_UNESCAPED_SLASHES per gli URL
    $jsonOutput = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonOutput === false) {
        $jsonErrorMsg = json_last_error_msg();
        error_log("Errore JSON Encode in get_events.php: " . $jsonErrorMsg . " (Errore PHP: " . json_last_error() . ")");
        // Non inviare $responseData grezzo nei log se potrebbe contenere dati sensibili estesi
        // error_log("Dati che hanno causato l'errore (parziale): " . mb_substr(print_r($responseData, true), 0, 2000));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Errore interno del server durante la formattazione dei dati (JSON).',
            // 'debug_json_error' => $jsonErrorMsg // Da commentare in produzione
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
        // 'debug_message' => $e->getMessage() // Da commentare in produzione
    ]);
} catch (Throwable $e) { // Cattura anche Error in PHP 7+
    http_response_code(500);
    error_log("Errore generico in get_events.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto durante il caricamento degli eventi.'
        // 'debug_message' => $e->getMessage() // Da commentare in produzione
    ]);
}
?>