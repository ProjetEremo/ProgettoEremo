<?php
// Impostazioni per il debug (considera di modificarle per l'ambiente di produzione)
ini_set('display_errors', 1); // Imposta a 0 in produzione
ini_set('display_startup_errors', 1); // Imposta a 0 in produzione
error_reporting(E_ALL);
// In produzione, considera di loggare gli errori su file invece di mostrarli:
// ini_set('log_errors', 1);
// ini_set('error_log', '/percorso/del/tuo/logfile_php.log');

// !!! ASSICURATI CHE NON CI SIA ALCUN OUTPUT PRIMA DI QUESTA LINEA (nemmeno spazi o BOM) !!!
header('Content-Type: application/json');

$config = [
    'host' => 'localhost',
    'db' => 'my_eremofratefrancesco', // Assicurati che il nome del DB sia corretto
    'user' => 'eremofratefrancesco', // Assicurati che l'utente sia corretto
    'pass' => '' // !!! VERIFICA CHE LA PASSWORD SIA CORRETTA (vuota se non richiesta) !!!
];

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

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
            Relatore AS relatore,
            Associazione AS associazione,
            FotoCopertina AS immagine,
            IDCategoria AS idcategoria
        FROM eventi
        WHERE Data >= CURDATE()
        ORDER BY Data ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $events = $stmt->fetchAll();

    if (empty($events)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'count' => 0,
            'message' => 'Nessun evento futuro trovato.'
        ]);
        exit;
    }

    foreach ($events as &$event) {
        if (!empty($event['immagine'])) {
            $binaryData = $event['immagine'];

            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_buffer($finfo, $binaryData);
                finfo_close($finfo);

                if (strpos($mime_type, 'image/') === 0) {
                    $base64Image = base64_encode($binaryData);
                    if ($base64Image === false) {
                        // Errore nella codifica base64, forse dati corrotti o troppo grandi
                        error_log("Errore base64_encode per l'evento ID: " . ($event['idevento'] ?? 'N/A'));
                        $event['immagine'] = null;
                    } else {
                        $event['immagine'] = 'data:' . $mime_type . ';base64,' . $base64Image;
                    }
                } else {
                    error_log("Tipo di file non immagine ('{$mime_type}') o dati corrotti per l'evento ID: " . ($event['idevento'] ?? 'N/A'));
                    $event['immagine'] = null;
                }
            } else {
                error_log("L'estensione PHP 'fileinfo' non è abilitata. Impossibile determinare il tipo MIME per l'evento ID: " . ($event['idevento'] ?? 'N/A'));
                $event['immagine'] = null; // Fallback: considera se tentare un base64 generico o meno
            }
        }
    }
    unset($event);

    // ---- CORREZIONE: Controllo dell'output JSON ----
    $responseData = [
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ];
    $jsonOutput = json_encode($responseData);

    if ($jsonOutput === false) {
        $jsonErrorMsg = json_last_error_msg();
        error_log("Errore JSON Encode in get_events.php: " . $jsonErrorMsg . " - Dati (parziali) che hanno causato l'errore: " . mb_substr(print_r($responseData, true), 0, 1000));
        http_response_code(500);
        // Assicurati che questo messaggio di errore sia JSON encodabile!
        echo json_encode([
            'success' => false,
            'error' => 'Errore interno del server durante la formattazione dei dati (JSON encode).',
            'debug_json_error' => $jsonErrorMsg
        ]);
    } else {
        echo $jsonOutput;
    }
    // ---- FINE CORREZIONE ----

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore PDO in get_events.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore durante il recupero degli eventi dal database.',
        // 'debug_message' => $e->getMessage() // Scommenta solo per debug approfondito
    ]);
} catch (Throwable $e) { // Usa Throwable per catturare anche Error in PHP 7+
    http_response_code(500);
    error_log("Errore generico in get_events.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto durante il caricamento degli eventi.',
        // 'debug_message' => $e->getMessage(), // Scommenta solo per debug approfondito
    ]);
}
// Non ci deve essere altro codice PHP o output HTML dopo questo punto.
?>