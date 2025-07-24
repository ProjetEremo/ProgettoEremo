<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Errore sconosciuto.'];

// Verifica autenticazione utente (adattala al tuo sistema)
if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Autenticazione richiesta.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $imageBase64 = isset($data['imageBase64']) ? $data['imageBase64'] : null;

    if (empty($imageBase64)) {
        $response['message'] = 'Nessun dato immagine ricevuto.';
        echo json_encode($response);
        exit;
    }

    // Rimuovi l'intestazione data URL se presente (es. "data:image/png;base64,")
    if (strpos($imageBase64, 'base64,') !== false) {
        $imageBase64 = substr($imageBase64, strpos($imageBase64, 'base64,') + 7);
    }

    $imageData = base64_decode($imageBase64);
    if ($imageData === false) {
        $response['message'] = 'Dati immagine base64 non validi.';
        echo json_encode($response);
        exit;
    }

    $saveDirRootRelative = 'uploads/icons/'; // Salva direttamente in uploads/icons/
    $saveDirPhysical = __DIR__ . '/../' . $saveDirRootRelative; // Path fisico dal file PHP corrente (../ perché api è una sottocartella)

    // Assicurati che la directory esista e sia scrivibile
    if (!is_dir($saveDirPhysical)) {
        if (!mkdir($saveDirPhysical, 0775, true)) {
            $response['message'] = 'Impossibile creare la cartella per le icone sul server.';
            error_log($response['message'] . ' Percorso fisico tentato: ' . $saveDirPhysical);
            echo json_encode($response);
            exit;
        }
    }
    if (!is_writable($saveDirPhysical)) {
        $response['message'] = 'La cartella delle icone non è scrivibile dal server.';
        error_log($response['message'] . ' Percorso fisico: ' . $saveDirPhysical);
        echo json_encode($response);
        exit;
    }
    
    $userEmailSanitized = preg_replace("/[^a-zA-Z0-9_]+/", "", strtok($_SESSION['user_email'], '@'));
    $filename = 'ai_icon_' . $userEmailSanitized . '_' . time() . '.png'; // Salva sempre come PNG
    $filepathPhysical = $saveDirPhysical . $filename;
    $webPath = $saveDirRootRelative . $filename;

    if (file_put_contents($filepathPhysical, $imageData) !== false) {
        $response = ['success' => true, 'iconPath' => $webPath, 'message' => 'Icona AI salvata con successo sul server!'];
    } else {
        $response['message'] = 'Impossibile salvare l\'icona AI generata sul server.';
        error_log($response['message'] . ' Percorso fisico: ' . $filepathPhysical);
    }

} else {
    $response['message'] = 'Metodo di richiesta non valido.';
}

echo json_encode($response);
?>