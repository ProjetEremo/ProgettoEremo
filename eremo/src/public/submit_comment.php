<?php
session_start(); // DEVE ESSERE LA PRIMA ISTRUZIONE
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Leggi l'input JSON inviato da JavaScript
$inputData = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Devi essere autenticato per commentare.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
$contatto_sessione = $_SESSION['user_email'];

// Usa i dati dall'input JSON
$idEvento = filter_var($inputData['IDEvento'] ?? null, FILTER_VALIDATE_INT);
$descrizione = isset($inputData['Descrizione']) ? trim($inputData['Descrizione']) : '';
$codRisposta = filter_var($inputData['CodRisposta'] ?? null, FILTER_VALIDATE_INT);


if (!$idEvento || $idEvento <= 0) {
    $response['message'] = 'ID Evento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (empty($descrizione)) {
    $response['message'] = 'Il testo del commento non può essere vuoto.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (mb_strlen($descrizione) > 2000) {
    $response['message'] = 'Il commento è troppo lungo (max 2000 caratteri).';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

if ($codRisposta !== null && ($codRisposta === false || $codRisposta <= 0)) {
    $codRisposta = null;
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

    $stmtCheckEvent = $conn->prepare("SELECT IDEvento FROM eventi WHERE IDEvento = :idEvento");
    $stmtCheckEvent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtCheckEvent->execute();
    if ($stmtCheckEvent->fetchColumn() === false) {
        throw new Exception("L'evento specificato non esiste.");
    }

    if ($codRisposta !== null) {
        $stmtCheckParent = $conn->prepare("SELECT Progressivo FROM commenti WHERE Progressivo = :codRisposta AND IDEvento = :idEvento");
        $stmtCheckParent->bindParam(':codRisposta', $codRisposta, PDO::PARAM_INT);
        $stmtCheckParent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtCheckParent->execute();
        if ($stmtCheckParent->fetchColumn() === false) {
            throw new Exception("Il commento a cui stai cercando di rispondere non esiste o non appartiene a questo evento.");
        }
    }

    $sql_insert = "INSERT INTO commenti (Descrizione, Data, DataPubb, OraPubb, CodRisposta, Contatto, IDEvento, NumLike)
                   VALUES (:descrizione, CURDATE(), NOW(), CURTIME(), :codRisposta, :contatto_sessione, :idEvento, 0)";
    $stmt = $conn->prepare($sql_insert);

    $stmt->bindParam(':descrizione', $descrizione, PDO::PARAM_STR);
    $stmt->bindParam(':codRisposta', $codRisposta, $codRisposta === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':contatto_sessione', $contatto_sessione, PDO::PARAM_STR);
    $stmt->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);

    $stmt->execute();
    $newCommentId = $conn->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Commento inviato con successo!';
    $response['new_comment_id'] = $newCommentId;
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in submit_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore database durante l\'invio del commento.';
    http_response_code(500);
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    error_log("Errore generico in submit_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore: ' . $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$conn = null;
?>