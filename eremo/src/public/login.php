<?php
header('Content-Type: application/json');
session_start();

// Configurazione database
$host = "localhost";
$user = "eremofratefrancesco";
$password = ""; // Inserisci la tua password
$dbname = "my_eremofratefrancesco";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connessione al database fallita']));
}

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Metodo non consentito']));
}

$email = $conn->real_escape_string($_POST['login-email']);
$password_input = $_POST['login-password'];

// Modifica la query per includere IsAdmin
$stmt = $conn->prepare("SELECT Contatto, Password, IsAdmin FROM utentiregistrati WHERE Contatto = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email non trovata']);
    exit();
}

$user = $result->fetch_assoc();

if (password_verify($password_input, $user['Password'])) {
    $_SESSION['user_email'] = $user['Contatto'];
    $_SESSION['is_admin'] = (bool)$user['IsAdmin']; // Salva lo stato admin in sessione

    echo json_encode([
        'success' => true,
        'message' => 'Login riuscito!',
        'is_admin' => $user['IsAdmin'] // Invia anche al frontend
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Password errata']);
}

$stmt->close();
$conn->close();
?>