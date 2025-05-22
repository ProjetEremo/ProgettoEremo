<?php
// File: api/api_update_comment.php
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
    $response['message'] = 'Devi essere autenticato per modificare un commento.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$currentUserEmail = $_SESSION['user_email'];
$inputData = json_decode(file_get_contents('php://input'), true);

$commentId = filter_var($inputData['commentId'] ?? null, FILTER_VALIDATE_INT);
$newDescription = isset($inputData['newDescription']) ? trim($inputData['newDescription']) : '';

if (!$commentId || $commentId <= 0) {
    $response['message'] = 'ID Commento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

if (empty($newDescription)) {
    $response['message'] = 'Il testo del commento non può essere vuoto.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

if (mb_strlen($newDescription) > 2000) { // Come in submit_comment.php
    $response['message'] = 'Il commento è troppo lungo (max 2000 caratteri).';
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
    $stmtCheck = $conn->prepare("SELECT Contatto, Descrizione FROM commenti WHERE Progressivo = :commentId");
    $stmtCheck->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtCheck->execute();
    $comment = $stmtCheck->fetch();

    if (!$comment) {
        $response['message'] = 'Commento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    if ($comment['Contatto'] !== $currentUserEmail) {
        $response['message'] = 'Non sei autorizzato a modificare questo commento.';
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit;
    }

    // Se il nuovo testo è identico al vecchio, considera l'operazione riuscita senza fare update
    if ($comment['Descrizione'] === $newDescription) {
        $response['success'] = true;
        $response['message'] = 'Nessuna modifica apportata, il testo è identico.';
        $response['updatedDescription'] = $newDescription; // Restituisci comunque il testo
        http_response_code(200); // OK, ma nessuna modifica effettiva
        echo json_encode($response);
        exit;
    }

    // Potresti aggiungere un campo DataModifica alla tabella commenti
    // e aggiornarlo qui con NOW()
    $stmtUpdate = $conn->prepare("UPDATE commenti SET Descrizione = :newDescription WHERE Progressivo = :commentId");
    $stmtUpdate->bindParam(':newDescription', $newDescription, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    if ($stmtUpdate->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Commento aggiornato con successo.';
        $response['updatedDescription'] = $newDescription;
        http_response_code(200);
    } else {
        // Questo caso è ora gestito dal controllo di identicità sopra,
        // ma lo teniamo per robustezza se quel controllo venisse rimosso.
        $response['message'] = 'Aggiornamento commento fallito o nessuna modifica necessaria.';
        http_response_code(304); // Not Modified o 500 se errore inaspettato
    }
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in api_update_comment.php (ID Commento: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'aggiornamento del commento.';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Errore generico in api_update_comment.php (ID Commento: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>