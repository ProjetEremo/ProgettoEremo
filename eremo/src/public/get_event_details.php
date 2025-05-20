<?php
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || (int)$_GET['id'] <= 0) {
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

    // 2. Recupera i media per l'evento (tabella 'media')
    $stmtMedia = $conn->prepare("SELECT Progressivo, Percorso FROM media WHERE IDEvento = :idEvento ORDER BY Progressivo ASC");
    $stmtMedia->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtMedia->execute();
    $mediaItems = $stmtMedia->fetchAll();

    // 3. Recupera i commenti, includendo DataPubb e NumLike (tabella 'commenti')
    $stmtComments = $conn->prepare(
        "SELECT Progressivo, Descrizione, DataPubb, CodRisposta, Contatto, IDEvento, NumLike
         FROM commenti
         WHERE IDEvento = :idEvento
         ORDER BY DataPubb ASC" // Ordina per data di pubblicazione effettiva
    );
    $stmtComments->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtComments->execute();
    $allCommentsRaw = $stmtComments->fetchAll();

    $commentsById = [];
    foreach ($allCommentsRaw as $comment) {
        // Formatta DataPubb per la visualizzazione
        if (isset($comment['DataPubb'])) {
            $comment['DataVisualizzata'] = date('d/m/Y H:i', strtotime($comment['DataPubb']));
        } else {
            $comment['DataVisualizzata'] = 'Data non disponibile';
        }
        // Assicura che NumLike sia un intero, default a 0 se null
        $comment['NumLike'] = isset($comment['NumLike']) ? (int)$comment['NumLike'] : 0;
        $comment['replies'] = [];
        $commentsById[$comment['Progressivo']] = $comment;
    }

    $commentsThreaded = [];
    foreach ($commentsById as $commentId => &$commentNode) { // Usa riferimento per modificare direttamente $commentsById
        if ($commentNode['CodRisposta'] !== null && isset($commentsById[$commentNode['CodRisposta']])) {
            $commentsById[$commentNode['CodRisposta']]['replies'][] = &$commentNode;
        } else {
            $commentsThreaded[] = &$commentNode;
        }
    }
    unset($commentNode);

    $response['success'] = true;
    $response['data'] = [
        'details'  => $eventDetails,
        'media'    => $mediaItems,
        'comments' => $commentsThreaded
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details.php: " . $e->getMessage());
    $response['message'] = 'Errore database: ' . $e->getMessage(); // Per debug
    http_response_code(500);
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    error_log("Errore generico in get_event_details.php: " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$conn = null;
?>