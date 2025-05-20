<?php
session_start(); // DEVE ESSERE LA PRIMA ISTRUZIONE
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => '', 'newLikeCount' => 0, 'liked' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// --- AUTENTICAZIONE UTENTE ---
if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Devi essere autenticato per mettere "Mi piace".';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
// $user_email_session = $_SESSION['user_email']; // Puoi usarlo se implementi tabella separata per i likes per utente

// Leggi l'input JSON inviato da JavaScript
$inputData = json_decode(file_get_contents('php://input'), true);

$commentId = filter_var($inputData['commentId'] ?? null, FILTER_VALIDATE_INT);
$action = isset($inputData['action']) ? $inputData['action'] : 'like'; // 'like' o 'unlike'

if (!$commentId || $commentId <= 0) {
    $response['message'] = 'ID Commento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// NOTA: Per un sistema di like/unlike completo (un utente un voto),
// servirebbe una tabella separata (es. 'likes_commenti') per tracciare chi ha messo like a cosa.
// Questo script, come quello originale, gestisce solo un contatore generale.
// La logica 'liked' restituita è una simulazione basata sull'azione inviata dal client.

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

    // Recupera il conteggio attuale dei like per determinare l'operazione
    // e per verificare che il commento esista
    $stmtSelectCurrent = $conn->prepare("SELECT NumLike FROM commenti WHERE Progressivo = :commentId");
    $stmtSelectCurrent->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtSelectCurrent->execute();
    $currentCommentState = $stmtSelectCurrent->fetch();

    if (!$currentCommentState) {
        $conn->rollBack();
        $response['message'] = 'Commento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }
    $currentLikes = (int)$currentCommentState['NumLike'];

    if ($action === 'like') {
        $stmtUpdate = $conn->prepare("UPDATE commenti SET NumLike = NumLike + 1 WHERE Progressivo = :commentId");
        $response['message'] = 'Like aggiunto!';
        $response['liked'] = true; // L'utente ora ha messo like
    } elseif ($action === 'unlike' && $currentLikes > 0) { // Solo se ci sono like da rimuovere
        $stmtUpdate = $conn->prepare("UPDATE commenti SET NumLike = NumLike - 1 WHERE Progressivo = :commentId");
        $response['message'] = 'Like rimosso!';
        $response['liked'] = false; // L'utente ora non ha messo like
    } else if ($action === 'unlike' && $currentLikes === 0) {
        // Nessuna azione sul DB, il conteggio è già 0
        $conn->rollBack(); // Nessuna modifica necessaria
        $response['success'] = true; // L'operazione è "logicamente" riuscita
        $response['message'] = 'Il commento non aveva like da rimuovere.';
        $response['newLikeCount'] = 0;
        $response['liked'] = false;
        echo json_encode($response);
        exit;
    } else {
        $conn->rollBack();
        $response['message'] = 'Azione non valida o non riconosciuta.';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $stmtUpdate->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    // Recupera il nuovo conteggio dei like dopo l'aggiornamento
    $stmtSelectNew = $conn->prepare("SELECT NumLike FROM commenti WHERE Progressivo = :commentId");
    $stmtSelectNew->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtSelectNew->execute();
    $updatedComment = $stmtSelectNew->fetch();

    if ($updatedComment) {
        $response['success'] = true;
        $response['newLikeCount'] = (int)$updatedComment['NumLike'];
    } else {
        // Non dovrebbe succedere se il commento esisteva
        $conn->rollBack();
        throw new Exception('Commento non trovato dopo l\'aggiornamento del like.');
    }

    $conn->commit();
    echo json_encode($response);

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    error_log("Errore PDO in like_comment.php (CommentID: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'aggiornamento del like.';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    error_log("Errore generico in like_comment.php (CommentID: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>