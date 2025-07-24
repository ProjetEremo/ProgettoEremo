<?php
// File: api/api_get_icons.php
header('Content-Type: application/json');
session_start(); // Necessario per identificare l'utente loggato

// Path fisico alla cartella 'uploads/icons/' relativo alla root del sito.
// __DIR__ è la cartella corrente (es. /membri/tuosito/api)
// dirname(__DIR__) è la cartella genitore (es. /membri/tuosito/)
$base_path = dirname(__DIR__); 
$icons_directory_path = $base_path . '/uploads/icons/'; // Percorso FISICO sul server
$icons_web_path_prefix = 'uploads/icons/'; // Prefisso del percorso WEB (relativo alla root del sito, senza slash iniziale qui perché lo aggiungiamo dopo)


$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
$public_icons = [];
$user_ai_icons = [];

$defaultUserIconName = 'default_user.png'; // Icona da escludere sempre dalla lista delle scelte

// Ottieni l'email sanitizzata dell'utente loggato, se presente
$currentUserEmailSanitized = null;
if (isset($_SESSION['user_email'])) {
    // Sanitizza l'email nello stesso modo in cui viene fatto quando si crea il nome del file dell'icona AI
    // Ad esempio, rimuovendo caratteri speciali e la parte @dominio.com
    $currentUserEmailSanitized = preg_replace("/[^a-zA-Z0-9_]+/", "", strtok($_SESSION['user_email'], '@'));
}

if (!is_dir($icons_directory_path)) {
    error_log("La directory delle icone non esiste o non è leggibile: " . $icons_directory_path);
    // Non inviare http_response_code qui se vuoi che il client gestisca 'success:false'
    echo json_encode(['success' => false, 'message' => 'Directory icone non trovata sul server. Percorso controllato: ' . $icons_directory_path, 'icons' => []]);
    exit;
}

if ($handle = opendir($icons_directory_path)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." && $entry != $defaultUserIconName) { // Escludi . .. e default_user.png
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($extension, $allowed_extensions)) {
                $web_path_for_icon = $icons_web_path_prefix . $entry;

                // Controlla se è un'icona AI
                if (strpos($entry, 'ai_icon_') === 0) {
                    if ($currentUserEmailSanitized) {
                        // Estrai la parte dell'email dal nome del file
                        // Formato atteso: ai_icon_EMAILSANITIZZATA_timestamp.png
                        $parts = explode('_', $entry); // es: [ai, icon, emailsanitizzata, timestamp.png]
                        if (count($parts) >= 3) {
                            $fileOwnerEmailSanitized = $parts[2];
                            if ($fileOwnerEmailSanitized === $currentUserEmailSanitized) {
                                $user_ai_icons[] = $web_path_for_icon;
                            }
                        }
                    }
                } else {
                    // È un'icona pubblica/predefinita
                    $public_icons[] = $web_path_for_icon;
                }
            }
        }
    }
    closedir($handle);
} else {
    error_log("Impossibile aprire la directory delle icone: " . $icons_directory_path);
    echo json_encode(['success' => false, 'message' => 'Errore server nel leggere le icone.', 'icons' => []]);
    exit;
}

// Unisci le icone pubbliche con quelle AI specifiche dell'utente loggato
// Rimuovi eventuali duplicati (improbabile ma sicuro) e riordina
$final_icons = array_values(array_unique(array_merge($public_icons, $user_ai_icons)));
sort($final_icons); // Opzionale: ordina le icone alfabeticamente

if (empty($final_icons) && empty($public_icons) && empty($user_ai_icons)) { // Controlla se effettivamente non c'è nulla da mostrare
     echo json_encode(['success' => true, 'message' => 'Nessuna icona personalizzata o pubblica trovata.', 'icons' => []]);
} else {
    echo json_encode(['success' => true, 'icons' => $final_icons]);
}
?>