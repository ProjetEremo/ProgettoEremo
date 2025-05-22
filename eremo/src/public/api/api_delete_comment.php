<?php
// File: api/api_delete_comment.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE
];

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Devi essere autenticato per eliminare un commento.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$currentUserEmail = $_SESSION['user_email'];
$inputData = json_decode(file_get_contents('php://input'), true);
$commentId = filter_var($inputData['commentId'] ?? null, FILTER_VALIDATE_INT);

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

    // Verifica che il commento esista e appartenga all'utente
    $stmtCheck = $conn->prepare("SELECT Contatto FROM commenti WHERE Progressivo = :commentId");
    $stmtCheck->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtCheck->execute();
    $comment = $stmtCheck->fetch();

    if (!$comment) {
        $response['message'] = 'Commento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // Autorizzazione: solo il proprietario può eliminare (o un admin, non implementato qui)
    if ($comment['Contatto'] !== $currentUserEmail) {
        $response['message'] = 'Non sei autorizzato a eliminare questo commento.';
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit;
    }

    // Funzione ricorsiva per eliminare un commento e tutte le sue risposte
    function deleteCommentAndReplies($pdo, $commentIdToDelete) {
        // Trova e cancella tutte le risposte dirette prima
        $repliesStmt = $pdo->prepare("SELECT Progressivo FROM commenti WHERE CodRisposta = :parentId");
        $repliesStmt->bindParam(':parentId', $commentIdToDelete, PDO::PARAM_INT);
        $repliesStmt->execute();
        $replies = $repliesStmt->fetchAll();

        foreach ($replies as $reply) {
            deleteCommentAndReplies($pdo, $reply['Progressivo']); // Chiamata ricorsiva
        }

        // Dopo aver cancellato tutte le risposte, cancella il commento stesso
        $deleteStmt = $pdo->prepare("DELETE FROM commenti WHERE Progressivo = :commentId");
        $deleteStmt->bindParam(':commentId', $commentIdToDelete, PDO::PARAM_INT);
        $deleteStmt->execute();
        // Non è necessario controllare rowCount qui perché la ricorsione potrebbe aver già eliminato il commento
        // se in qualche modo fosse una risposta a se stesso (improbabile ma per sicurezza)
    }

    $conn->beginTransaction();
    deleteCommentAndReplies($conn, $commentId);
    $conn->commit();

    $response['success'] = true;
    $response['message'] = 'Commento e relative risposte eliminati con successo.';
    http_response_code(200);
    echo json_encode($response);

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore PDO in api_delete_comment.php (ID Commento: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'eliminazione del commento.';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) { // Cattura eccezioni generiche
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore generico in api_delete_comment.php (ID Commento: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>