<?php
// Abilita la visualizzazione di tutti gli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Verifica se la tabella esiste
    $tableCheck = $conn->query("SHOW TABLES LIKE 'eventi'")->fetch();
    if (!$tableCheck) {
        throw new Exception("La tabella 'eventi' non esiste nel database");
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
            'message' => 'Nessun evento trovato'
        ]);
        exit;
    }

    foreach ($events as &$event) {
        if (!empty($event['immagine'])) {
            $event['immagine'] = 'data:image/png;base64,' . base64_encode($event['immagine']);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTrace() // Solo per debug, rimuovi in produzione
    ]);
}

foreach ($events as &$event) {
    if (!empty($event['immagine'])) {
        // Controlla se i dati binari sembrano un'immagine valida
        if (strpos($event['immagine'], '\xFF\xD8\xFF') === 0) { // JPEG
            $event['immagine'] = 'data:image/jpeg;base64,' . base64_encode($event['immagine']);
        } elseif (strpos($event['immagine'], '\x89PNG') === 0) { // PNG
            $event['immagine'] = 'data:image/png;base64,' . base64_encode($event['immagine']);
        } else {
            // Ignora immagini non valide
            error_log("Immagine non valida per l'evento ID: " . $event['idevento']);
            $event['immagine'] = null;
        }
    }
}
?>