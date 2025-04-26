<?php
header('Content-Type: application/json');
session_start();

// Configurazione database (usa le stesse credenziali di registrati.php)
$host = "localhost";
$user = "eremofratefrancesco";
$password = "";
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
$password_input = $_POST['login-password']; // Non usare real_escape_string sulle password!

// Cerca l'utente nel database
$stmt = $conn->prepare("SELECT Contatto, Password FROM utentiregistrati WHERE Contatto = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email non trovata']);
    exit();
}

$user = $result->fetch_assoc();

// Verifica la password
if (password_verify($password_input, $user['Password'])) {
    $_SESSION['user_email'] = $user['Contatto']; // Salva in sessione
    echo json_encode(['success' => true, 'message' => 'Login riuscito!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Password errata']);
}

$stmt->close();
$conn->close();
?>