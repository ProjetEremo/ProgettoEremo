<?php
header('Content-Type: application/json; charset=utf-8'); // Assicura charset UTF-8
session_start();

// Configurazione database
$host = "localhost";
$user = "eremofratefrancesco"; // Sostituisci con il tuo utente DB
$password = ""; // Inserisci la tua password se necessaria
$dbname = "my_eremofratefrancesco"; // Sostituisci con il tuo nome DB

$conn = new mysqli($host, $user, $password, $dbname);

// Imposta il charset per la connessione (buona pratica)
if ($conn->connect_errno) {
    error_log("Connessione al database fallita: (" . $conn->connect_errno . ") " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => 'Errore di connessione al database.']));
}
$conn->set_charset("utf8mb4");


// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isset($_POST['login-email']) || !isset($_POST['login-password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Email e password sono obbligatorie.']);
    exit;
}

$email = trim($_POST['login-email']); // Trim spazi
$password_input = $_POST['login-password'];

if (empty($email) || empty($password_input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email e password non possono essere vuote.']);
    exit;
}

// Assicurati che la colonna 'Nome' esista nella tabella 'utentiregistrati'
$stmt = $conn->prepare("SELECT Contatto, Password, Nome, IsAdmin FROM utentiregistrati WHERE Contatto = ?");
if (!$stmt) {
    error_log("Errore nella preparazione dello statement: " . $conn->error);
    die(json_encode(['success' => false, 'message' => 'Errore interno del server ( preparazione query).']));
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email non trovata.']);
    $stmt->close();
    $conn->close();
    exit();
}

$user_data = $result->fetch_assoc();

if (password_verify($password_input, $user_data['Password'])) {
    // Rigenera l'ID di sessione per prevenire session fixation
    session_regenerate_id(true);

    $_SESSION['user_email'] = $user_data['Contatto'];
    $_SESSION['user_nome'] = $user_data['Nome'];
    $_SESSION['is_admin'] = (bool)$user_data['IsAdmin'];
    // Potresti aggiungere un timestamp di login per la scadenza della sessione, se necessario
    $_SESSION['login_time'] = time();


    echo json_encode([
        'success' => true,
        'message' => 'Login riuscito!',
        'nome' => $user_data['Nome'],
        'email' => $user_data['Contatto'], // Invia anche l'email, utile per localStorage se diversa da quella inserita (es. case sensitivity)
        'is_admin' => (bool)$user_data['IsAdmin']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Password errata.']);
}

$stmt->close();
$conn->close();
?>