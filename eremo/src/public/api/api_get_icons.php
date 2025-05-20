<?php
// File: api/api_get_icons.php
header('Content-Type: application/json');
session_start(); // Aggiungi session_start() se le API delle icone dovessero mai diventare protette

// Il percorso alla cartella 'uploads/icons/' DEVE essere relativo alla posizione di QUESTO script (api_get_icons.php)
// Se 'api' e 'uploads' sono entrambe sottocartelle della root del sito:
// __DIR__ è la cartella corrente (es. /membri/eremofratefrancesco/api)
// dirname(__DIR__) è la cartella genitore (es. /membri/eremofratefrancesco/)
$base_path = dirname(__DIR__); // Questa dovrebbe essere la root del tuo sito su Altervista
$icons_directory_path = $base_path . '/uploads/icons/'; // Percorso FISICO sul server
$icons_web_path = '/uploads/icons/'; // Percorso WEB per i client (relativo alla root del dominio)


$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
$icons = [];

if (!is_dir($icons_directory_path)) {
    error_log("La directory delle icone non esiste o non è leggibile: " . $icons_directory_path);
    http_response_code(404); // Imposta il codice di stato corretto
    echo json_encode(['success' => false, 'message' => 'Directory icone non trovata sul server (' . $icons_directory_path . ').', 'icons' => []]);
    exit;
}

if ($handle = opendir($icons_directory_path)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($extension, $allowed_extensions)) {
                // Assicurati che $icons_web_path inizi con / se è assoluto dal dominio
                // o che sia corretto se relativo
                $icons[] = rtrim($icons_web_path, '/') . '/' . $entry;
            }
        }
    }
    closedir($handle);
} else {
    error_log("Impossibile aprire la directory delle icone: " . $icons_directory_path);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server nel leggere le icone.', 'icons' => []]);
    exit;
}

if (empty($icons)) {
     echo json_encode(['success' => true, 'message' => 'Nessuna icona personalizzata trovata nella directory.', 'icons' => []]);
} else {
    echo json_encode(['success' => true, 'icons' => $icons]);
}
?>