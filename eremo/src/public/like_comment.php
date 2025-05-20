<?php
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => '', 'newLikeCount' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$commentId = filter_input(INPUT_POST, 'commentId', FILTER_VALIDATE_INT);

if (!$commentId || $commentId <= 0) {
    $response['message'] = 'ID Commento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

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

    // Incrementa NumLike
    // Assicurati che il nome della tabella sia corretto (commenti vs Commenti)
    $stmtUpdate = $conn->prepare("UPDATE commenti SET NumLike = NumLike + 1 WHERE Progressivo = :commentId");
    $stmtUpdate->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    if ($stmtUpdate->rowCount() > 0) {
        // Recupera il nuovo conteggio dei like
        $stmtSelect = $conn->prepare("SELECT NumLike FROM commenti WHERE Progressivo = :commentId");
        $stmtSelect->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmtSelect->execute();
        $updatedComment = $stmtSelect->fetch();

        if ($updatedComment) {
            $response['success'] = true;
            $response['message'] = 'Like aggiunto!';
            $response['newLikeCount'] = (int)$updatedComment['NumLike'];
        } else {
            throw new Exception('Commento non trovato dopo l\'aggiornamento del like.');
        }
    } else {
        $stmtCheck = $conn->prepare("SELECT Progressivo FROM commenti WHERE Progressivo = :commentId");
        $stmtCheck->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() === false) {
             $response['message'] = 'Commento non trovato per aggiungere il like.';
             http_response_code(404);
        } else {
            // Questo caso potrebbe verificarsi se, per qualche motivo, l'UPDATE non ha modificato righe
            // pur esistendo il commento (improbabile con un semplice incremento se il commento esiste).
            // Potrebbe anche indicare che NumLike era NULL e l'incremento NumLike + 1 ha dato NULL.
            // Sarebbe meglio assicurarsi che NumLike non sia NULL nel database o gestirlo.
            // Tuttavia, se NumLike ha un DEFAULT 0, questo non dovrebbe accadere.
            $response['message'] = 'Impossibile aggiornare il like (nessuna riga modificata).';
            // Per sicurezza, recuperiamo comunque il conteggio attuale se il commento esiste
            $stmtSelectCurrent = $conn->prepare("SELECT NumLike FROM commenti WHERE Progressivo = :commentId");
            $stmtSelectCurrent->bindParam(':commentId', $commentId, PDO::PARAM_INT);
            $stmtSelectCurrent->execute();
            $currentCommentState = $stmtSelectCurrent->fetch();
            if($currentCommentState) {
                $response['newLikeCount'] = (int)$currentCommentState['NumLike'];
            } else {
                 $response['message'] = 'Commento non trovato.'; // Sovrascrive se anche il select fallisce
                 http_response_code(404);
            }
        }
    }
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in like_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'aggiornamento del like.';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Errore generico in like_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>