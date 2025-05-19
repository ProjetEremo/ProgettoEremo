<?php
header('Content-Type: application/json; charset=utf-8');
// Includi qui la tua configurazione del database (o il file di connessione)
// Esempio basato sui tuoi script precedenti:
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco', // Il tuo nome DB
    'user' => 'eremofratefrancesco',    // Il tuo username DB
    'pass' => ''                        // La tua password DB - LASCIA VUOTA SE NON HAI PASSWORD PER QUESTO UTENTE
];

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $response['message'] = 'ID Evento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
$idEvento = (int)$_GET['id'];

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

    // 1. Recupera i dettagli dell'evento
    // Assicurati che i nomi delle colonne corrispondano al tuo database
    // (es. FotoCopertina, Titolo, Data, Durata, Relatore, PrefissoRelatore, Associazione, DescrizioneEstesa, Descrizione, VolantinoUrl)
    $stmtDetails = $conn->prepare("SELECT * FROM eventi WHERE IDEvento = :idEvento");
    $stmtDetails->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtDetails->execute();
    $eventDetails = $stmtDetails->fetch();

    if (!$eventDetails) {
        $response['message'] = 'Evento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // 2. Recupera i media per l'evento
    // Assumendo che la tabella 'Media' abbia colonne 'Percorso' e opzionalmente 'TitoloMedia'
    // L'immagine image_11b1a2.png mostra 'Progressivo', 'Percorso', 'IDEvento'
    $stmtMedia = $conn->prepare("SELECT Progressivo, Percorso FROM Media WHERE IDEvento = :idEvento ORDER BY Progressivo ASC");
    $stmtMedia->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtMedia->execute();
    $mediaItems = $stmtMedia->fetchAll();

    // 3. Recupera i commenti per l'evento
    // L'immagine image_11b1c3.png mostra 'Progressivo', 'Descrizione', 'Data', 'CodRisposta', 'Contatto', 'IDEvento'
    $stmtComments = $conn->prepare(
        "SELECT Progressivo, Descrizione, DATE_FORMAT(Data, '%d/%m/%Y %H:%i') AS Data, CodRisposta, Contatto, IDEvento
         FROM Commenti
         WHERE IDEvento = :idEvento
         ORDER BY IFNULL(CodRisposta, Progressivo), Data ASC" // Ordina per radice e poi per data
    );
    $stmtComments->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtComments->execute();
    $allCommentsRaw = $stmtComments->fetchAll();

    // Organizza i commenti in una struttura annidata (se lo vuoi fare qui)
    $commentsThreaded = [];
    $commentsMap = [];

    foreach ($allCommentsRaw as $comment) {
        // $comment['Data'] è già formattata da SQL
        $commentsMap[$comment['Progressivo']] = $comment;
        $commentsMap[$comment['Progressivo']]['replies'] = [];
    }

    foreach ($commentsMap as $commentId => $comment) {
        if ($comment['CodRisposta'] !== null && isset($commentsMap[$comment['CodRisposta']])) {
            $commentsMap[$comment['CodRisposta']]['replies'][] = &$commentsMap[$commentId]; // Usa riferimento per annidare
        } else {
            $commentsThreaded[] = &$commentsMap[$commentId]; // Commento principale
        }
    }
    // Filtra per ottenere solo i commenti principali (quelli non annidati direttamente)
    $finalComments = [];
    foreach($commentsThreaded as $c){
        if($c['CodRisposta'] === null){
            $finalComments[] = $c;
        }
    }


    $response['success'] = true;
    $response['data'] = [
        'details'  => $eventDetails,
        'media'    => $mediaItems,
        'comments' => $finalComments // Invia solo i commenti principali, le risposte sono annidate dentro
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details.php: " . $e->getMessage());
    $response['message'] = 'Errore database durante il recupero dei dettagli evento.';
    // $response['debug_error'] = $e->getMessage(); // Non inviare in produzione
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Errore generico in get_event_details.php: " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>