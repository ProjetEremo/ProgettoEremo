<?php
// File: config/db_config.php
$host = "localhost";
$user = "eremofratefrancesco"; // Il tuo utente DB
$password_db = ""; // La tua password DB
$dbname = "my_eremofratefrancesco"; // Il tuo nome DB

$conn = new mysqli($host, $user, $password_db, $dbname);

if ($conn->connect_errno) {
    // Non usare die() in file inclusi in API, gestisci l'errore nell'API chiamante
    error_log("Connessione al database fallita (db_config.php): (" . $conn->connect_errno . ") " . $conn->connect_error);
    // Potresti lanciare un'eccezione qui per gestirla nel file API principale
    // throw new Exception("Errore di connessione al database.");
    // Per ora, l'API controllerà se $conn è valido.
} else {
    $conn->set_charset("utf8mb4");
}
?>