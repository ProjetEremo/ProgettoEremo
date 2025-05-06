<?php
// Impostazioni per il debug (considera di modificarle per l'ambiente di produzione)
ini_set('display_errors', 1); // Imposta a 0 in produzione
ini_set('display_startup_errors', 1); // Imposta a 0 in produzione
error_reporting(E_ALL);
// In produzione, considera di loggare gli errori su file invece di mostrarli:
// ini_set('log_errors', 1);
// ini_set('error_log', '/percorso/del/tuo/logfile_php.log');

header('Content-Type: application/json');

$config = [
    'host' => 'localhost',
    'db' => 'my_eremofratefrancesco', // Assicurati che il nome del DB sia corretto
    'user' => 'eremofratefrancesco', // Assicurati che l'utente sia corretto
    'pass' => '' // Assicurati che la password sia corretta
];

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Buona pratica per la sicurezza
        ]
    );

    // Verifica se la tabella 'eventi' esiste (opzionale, ma può prevenire errori)
    // $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'eventi'");
    // if ($tableCheckStmt->rowCount() == 0) {
    //     throw new Exception("La tabella 'eventi' non esiste nel database.");
    // }

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

    // Elaborazione delle immagini per includerle come data URI
    foreach ($events as &$event) { // Usa il riferimento (&) per modificare direttamente l'array
        if (!empty($event['immagine'])) {
            $binaryData = $event['immagine']; // Dati binari grezzi dalla colonna FotoCopertina (MEDIUMBLOB)

            if (extension_loaded('fileinfo')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_buffer($finfo, $binaryData);
                finfo_close($finfo);

                if (strpos($mime_type, 'image/') === 0) { // Verifica che sia un tipo di immagine valido
                    $event['immagine'] = 'data:' . $mime_type . ';base64,' . base64_encode($binaryData);
                } else {
                    // Non è un'immagine riconosciuta o dati corrotti
                    error_log("Tipo di file non immagine ('{$mime_type}') o dati corrotti per l'evento ID: " . ($event['idevento'] ?? 'N/A'));
                    $event['immagine'] = null; // O un URL di immagine placeholder
                }
            } else {
                // Fallback se l'estensione fileinfo non è disponibile
                // Questo è meno affidabile e potrebbe necessitare di logica più complessa per i magic numbers
                error_log("L'estensione PHP 'fileinfo' non è abilitata. Impossibile determinare il tipo MIME dell'immagine in modo affidabile per l'evento ID: " . ($event['idevento'] ?? 'N/A'));
                // Potresti tentare un'ipotesi (es. image/jpeg) o lasciare l'immagine a null
                // Per semplicità, la impostiamo a null se fileinfo non c'è
                $event['immagine'] = null;
            }
        }
    }
    // Rimuovi il riferimento per evitare modifiche accidentali successive
    unset($event);


    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);

} catch (PDOException $e) {
    // Errore specifico di PDO (es. connessione, query)
    http_response_code(500); // Internal Server Error
    error_log("Errore PDO in get_events.php: " . $e->getMessage()); // Logga l'errore reale
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore durante il recupero degli eventi dal database.',
        // 'debug_message' => $e->getMessage() // Rimuovi o commenta in produzione
    ]);
} catch (Exception $e) {
    // Altre eccezioni generiche
    http_response_code(500); // Internal Server Error
    error_log("Errore generico in get_events.php: " . $e->getMessage()); // Logga l'errore reale
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto durante il caricamento degli eventi.',
        // 'debug_message' => $e->getMessage(), // Rimuovi o commenta in produzione
        // 'trace' => $e->getTraceAsString() // Rimuovi o commenta in produzione
    ]);
}

// Non ci deve essere altro codice PHP o output HTML dopo questo punto.
?>