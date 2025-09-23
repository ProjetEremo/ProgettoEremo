<?php
// Aggiungi questa riga IN CIMA al file per catturare tutti gli output
ob_start();

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// Funzione centralizzata per terminare lo script con un messaggio JSON
function terminate_script($success, $message, $errors = []) {
    $response = ['success' => $success, 'message' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    // Pulisce qualsiasi output precedente prima di inviare la risposta JSON
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Configurazione del database
$host = "localhost";
$user = "eremofratefrancesco"; 
$password = "";    
$dbname = "my_eremofratefrancesco";

// Connessione al database
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    terminate_script(false, 'Connessione al database fallita: ' . $conn->connect_error);
}

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    terminate_script(false, 'Metodo non consentito.');
}

// --- VALIDAZIONE DEI DATI ---
$errors = [];
$required_fields = ['register-name', 'register-surname', 'register-email', 'register-password', 'register-terms'];

foreach ($required_fields as $field) {
    if (empty(trim($_POST[$field]))) {
        $errors[str_replace('register-', '', $field)] = 'Questo campo è obbligatorio.';
    }
}

// Validazione specifica per l'email
if (!isset($errors['email']) && !filter_var($_POST['register-email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Formato email non valido.';
}

// Validazione lunghezza password
if (!isset($errors['password']) && strlen($_POST['register-password']) < 8) {
    $errors['password'] = 'La password deve contenere almeno 8 caratteri.';
}

// Se ci sono errori, termina lo script
if (!empty($errors)) {
    terminate_script(false, 'Per favore, correggi gli errori nel modulo.', $errors);
}

// --- SANITIZZAZIONE E PREPARAZIONE DATI ---
$nome = trim($_POST['register-name']);
$cognome = trim($_POST['register-surname']);
$email = trim($_POST['register-email']);
$password_hashed = password_hash($_POST['register-password'], PASSWORD_DEFAULT);
$isAdmin = 0; // Utenti standard

// MODIFICA: Recupera il valore della checkbox per le notifiche
// Se la checkbox 'register-notify' è stata inviata (spuntata), il valore è 1, altrimenti 0.
$avvisi_eventi = isset($_POST['register-notify']) ? 1 : 0;


// --- CONTROLLO EMAIL DUPLICATA ---
$stmt = $conn->prepare("SELECT Contatto FROM utentiregistrati WHERE Contatto = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    terminate_script(false, 'Questa email è già registrata. Prova ad accedere.', ['email' => 'Email già in uso.']);
}
$stmt->close();


// --- INSERIMENTO NEL DATABASE ---
// MODIFICA: Aggiunta la colonna 'avvisi_eventi' alla query e un segnaposto (?)
$stmt = $conn->prepare("INSERT INTO utentiregistrati (Contatto, Nome, Cognome, Password, IsAdmin, avvisi_eventi) VALUES (?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    terminate_script(false, 'Errore nella preparazione della query: ' . $conn->error);
}

// MODIFICA: Aggiunto il tipo 'i' per l'intero e la variabile $avvisi_eventi
$stmt->bind_param("ssssii", $email, $nome, $cognome, $password_hashed, $isAdmin, $avvisi_eventi);


if ($stmt->execute()) {
    terminate_script(true, 'Registrazione completata con successo! Ora puoi accedere.');
} else {
    // Non mostrare $stmt->error in produzione per motivi di sicurezza
    error_log("Errore DB registrazione: " . $stmt->error); // Logga l'errore per il debug
    terminate_script(false, 'Si è verificato un errore durante la registrazione. Riprova più tardi.');
}

$stmt->close();
$conn->close();
?>
