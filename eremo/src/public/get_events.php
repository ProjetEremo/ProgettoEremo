<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$config = [
    'host' => 'localhost',
    'db' => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => ''
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
            Volantino AS volantino,
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
        // Gestione immagine copertina (come prima)
        if (!empty($event['immagine'])) {
            $binaryData = $event['immagine'];
            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_buffer($finfo, $binaryData);
                finfo_close($finfo);
                if (strpos($mime_type, 'image/') === 0) {
                    $event['immagine'] = 'data:' . $mime_type . ';base64,' . base64_encode($binaryData);
                } else {
                    $event['immagine'] = null;
                }
            } else {
                $event['immagine'] = null;
            }
        }

        // Gestione volantino PDF
        if (!empty($event['volantino'])) {
            $event['volantino'] = 'data:application/pdf;base64,' . base64_encode($event['volantino']);
        }
    }
    unset($event);

    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore PDO in get_events.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore durante il recupero degli eventi dal database.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Errore generico in get_events.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto durante il caricamento degli eventi.'
    ]);
}
?>