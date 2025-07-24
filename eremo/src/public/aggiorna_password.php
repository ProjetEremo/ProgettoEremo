<?php
// NOME FILE: aggiorna_password.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_session.php'; // PRIMA COSA
require_login(); // Verifica se l'utente è loggato

$host = "localhost";
$username_db = "eremofratefrancesco";
$password_db = "";
$dbname_db = "my_eremofratefrancesco";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore DB (aggiorna_password): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server [DB_CONN_UPD].']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($new_password) || empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dati mancanti.']);
    exit;
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nuova password deve contenere almeno 8 caratteri.']);
    exit;
}

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le password non coincidono.']);
    exit;
}

$output = ['success' => false, 'message' => 'Errore durante l\'aggiornamento della password.'];

try {
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_reset_tokens WHERE token = :token");
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $token_data = $stmt->fetch();

    if (!$token_data) {
        $output['message'] = 'Token non valido o già utilizzato.';
        http_response_code(400);
        echo json_encode($output);
        exit;
    }

    $now = new DateTime();
    $expires = new DateTime($token_data['expires_at']);

    if ($now >= $expires) {
        $output['message'] = 'Token scaduto. Richiedi un nuovo link di recupero.';
        // Cancella token scaduto
        $stmtDel = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
        $stmtDel->bindParam(':token', $token, PDO::PARAM_STR);
        $stmtDel->execute();
        http_response_code(400);
        echo json_encode($output);
        exit;
    }

    $email_utente = $token_data['email'];
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    $stmtUpdateUser = $conn->prepare("UPDATE utentiregistrati SET Password = :password WHERE Contatto = :email");
    $stmtUpdateUser->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $stmtUpdateUser->bindParam(':email', $email_utente, PDO::PARAM_STR);
    
    if ($stmtUpdateUser->execute()) {
        // Password aggiornata, ora cancella il token
        $stmtDeleteToken = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
        $stmtDeleteToken->bindParam(':token', $token, PDO::PARAM_STR);
        $stmtDeleteToken->execute();

        $output = ['success' => true, 'message' => 'Password aggiornata con successo!'];
    } else {
        error_log("Aggiornamento password fallito per $email_utente con token $token.");
        $output['message'] = 'Impossibile aggiornare la password nel database.';
    }

} catch (PDOException $e) {
    error_log("Errore PDO in aggiorna_password.php: " . $e->getMessage());
    $output['message'] = 'Errore del server [PDO_EXC_UPD].';
    http_response_code(500);
} catch (Exception $e) {
    error_log("Errore generico in aggiorna_password.php: " . $e->getMessage());
    $output['message'] = 'Errore del server [GEN_EXC_UPD].';
    http_response_code(500);
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>