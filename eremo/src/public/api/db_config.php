<?php
// File: db_config.php
$host = "localhost";
$user = "eremofratefrancesco"; // Il tuo utente DB
$db_password = ""; // LA TUA PASSWORD DB
$dbname = "my_eremofratefrancesco"; // Il tuo nome DB

$conn = new mysqli($host, $user, $db_password, $dbname);

if ($conn->connect_error) {
    // Non usare die() qui se questo file è incluso, gestisci l'errore nello script chiamante
    // o logga l'errore e esci in modo controllato se è uno script API diretto.
    // Per gli script API, è meglio uscire con un JSON di errore.
    error_log("Connessione al database fallita: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server (DB connection).']);
    exit;
}
$conn->set_charset("utf8mb4");
?>