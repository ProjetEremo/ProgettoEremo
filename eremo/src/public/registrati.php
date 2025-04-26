<?php
ob_start();
header('Content-Type: application/json');

// Configurazione del database
$host = "localhost";
$user = "eremofratefrancesco"; //
$password = "";    //
$dbname = "my_eremofratefrancesco"; // Nome esatto del DB

// Connessione al database
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    // IMPORTANTE: usare json_encode() per gli errori
    die(json_encode([
        'success' => false,
        'message' => 'Connessione al database fallita: ' . $conn->connect_error
    ]));
}

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode([
        'success' => false,
        'message' => 'Metodo non consentito'
    ]));
}

// Recupera e sanitizza i dati
$required_fields = ['register-name', 'register-surname', 'register-email', 'register-password'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        die(json_encode([
            'success' => false,
            'message' => 'Compila tutti i campi obbligatori'
        ]));
    }
}

$nome = $conn->real_escape_string($_POST['register-name']);
$cognome = $conn->real_escape_string($_POST['register-surname']);
$email = $conn->real_escape_string($_POST['register-email']);
$password = password_hash($_POST['register-password'], PASSWORD_DEFAULT);
$isAdmin = 0;

// Query preparata per sicurezza
$stmt = $conn->prepare("INSERT INTO utentiregistrati (Contatto, Nome, Cognome, Password, IsAdmin) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $email, $nome, $cognome, $password, $isAdmin);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Registrazione completata!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Errore database: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
ob_end_flush(); // Pulisce il buffer di output
?>