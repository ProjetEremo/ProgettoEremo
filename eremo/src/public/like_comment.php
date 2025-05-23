<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <--- INSERISCI QUI LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => '', 'newLikeCount' => 0, 'liked' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Devi essere autenticato per mettere "Mi piace".';
    http_response_code(401);
    echo json_encode($response);
    exit;
}
$user_email = $_SESSION['user_email'];

$inputData = json_decode(file_get_contents('php://input'), true);
$commentId = filter_var($inputData['commentId'] ?? null, FILTER_VALIDATE_INT);
// L'azione viene determinata dal client in base allo stato attuale del pulsante
$action = isset($inputData['action']) ? $inputData['action'] : 'like';

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

    $conn->beginTransaction();

    // 1. Controlla se il commento esiste
    $stmtCheckComment = $conn->prepare("SELECT Progressivo FROM commenti WHERE Progressivo = :commentId FOR UPDATE");
    $stmtCheckComment->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtCheckComment->execute();
    if (!$stmtCheckComment->fetch()) {
        $conn->rollBack();
        $response['message'] = 'Commento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // 2. Controlla se l'utente ha già messo "Mi piace"
    $stmtCheckLike = $conn->prepare("SELECT COUNT(*) as count FROM likes_commenti WHERE Contatto = :userEmail AND IDCommento = :commentId");
    $stmtCheckLike->bindParam(':userEmail', $user_email, PDO::PARAM_STR);
    $stmtCheckLike->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtCheckLike->execute();
    $alreadyLiked = (int)$stmtCheckLike->fetch()['count'] > 0;

    // 3. Esegui l'azione
    if ($action === 'like' && !$alreadyLiked) {
        // Aggiungi il "Mi piace"
        $stmtInsert = $conn->prepare("INSERT INTO likes_commenti (Contatto, IDCommento) VALUES (:userEmail, :commentId)");
        $stmtInsert->bindParam(':userEmail', $user_email, PDO::PARAM_STR);
        $stmtInsert->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmtInsert->execute();
        $response['message'] = 'Like aggiunto!';
        $response['liked'] = true;
    } elseif ($action === 'unlike' && $alreadyLiked) {
        // Rimuovi il "Mi piace"
        $stmtDelete = $conn->prepare("DELETE FROM likes_commenti WHERE Contatto = :userEmail AND IDCommento = :commentId");
        $stmtDelete->bindParam(':userEmail', $user_email, PDO::PARAM_STR);
        $stmtDelete->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmtDelete->execute();
        $response['message'] = 'Like rimosso!';
        $response['liked'] = false;
    } else {
        // Stato incoerente o nessuna azione necessaria, ma ricalcola comunque per sicurezza.
        $response['liked'] = $alreadyLiked;
        $response['message'] = $alreadyLiked ? 'Like già presente.' : 'Like non presente.';
        // Non facciamo modifiche, ma procediamo a ricalcolare e restituire lo stato attuale.
    }

    // 4. Aggiorna il contatore NumLike nella tabella commenti
    $stmtUpdateCount = $conn->prepare("
        UPDATE commenti c
        SET c.NumLike = (SELECT COUNT(*) FROM likes_commenti lc WHERE lc.IDCommento = c.Progressivo)
        WHERE c.Progressivo = :commentId
    ");
    $stmtUpdateCount->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtUpdateCount->execute();

    // 5. Recupera il nuovo conteggio e conferma lo stato del like
    $stmtSelectNew = $conn->prepare("
        SELECT
            c.NumLike,
            (SELECT COUNT(*) FROM likes_commenti lc WHERE lc.IDCommento = c.Progressivo AND lc.Contatto = :userEmail) as currentUserLiked
        FROM commenti c
        WHERE c.Progressivo = :commentId
    ");
    $stmtSelectNew->bindParam(':userEmail', $user_email, PDO::PARAM_STR);
    $stmtSelectNew->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtSelectNew->execute();
    $updatedComment = $stmtSelectNew->fetch();

    if ($updatedComment) {
        $response['success'] = true;
        $response['newLikeCount'] = (int)$updatedComment['NumLike'];
        $response['liked'] = (int)$updatedComment['currentUserLiked'] > 0; // Aggiorna lo stato liked basato sul DB
    } else {
        throw new Exception('Commento non trovato dopo l\'aggiornamento del like.');
    }

    $conn->commit();
    echo json_encode($response);

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    error_log("Errore PDO in like_comment.php (CommentID: {$commentId}, User: {$user_email}): " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'aggiornamento del like.';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    error_log("Errore generico in like_comment.php (CommentID: {$commentId}, User: {$user_email}): " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>