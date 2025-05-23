<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <--- INSERISCI QUI LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => '', 'likers' => []];

$commentId = filter_input(INPUT_GET, 'commentId', FILTER_VALIDATE_INT);

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

    $stmt = $conn->prepare("
        SELECT ur.Nome, ur.Cognome, ur.Icon
        FROM likes_commenti lc
        JOIN utentiregistrati ur ON lc.Contatto = ur.Contatto
        WHERE lc.IDCommento = :commentId
        ORDER BY lc.DataLike ASC
    ");
    $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    $stmt->execute();

    $likers = $stmt->fetchAll();

    $response['success'] = true;
    $response['likers'] = array_map(function($user) {
        // Usa '/uploads/icons/default_user.png' come fallback se l'icona non è presente o è vuota.
        // Assicurati che questo percorso sia corretto per il tuo progetto.
        $iconPath = (!empty($user['Icon']) && trim($user['Icon']) !== '') ? $user['Icon'] : '/uploads/icons/default_user.png';
        return [
            'nomeCompleto' => trim($user['Nome'] . ' ' . $user['Cognome']),
            'icon' => $iconPath
        ];
    }, $likers);

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in get_likers.php (CommentID: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore database durante il recupero dei "Mi piace".';
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Errore generico in get_likers.php (CommentID: {$commentId}): " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn = null;
?>