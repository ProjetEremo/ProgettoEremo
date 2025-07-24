<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Errore sconosciuto durante l\'eliminazione.'];

// Verifica autenticazione utente
if (!isset($_SESSION['user_email'])) {
    $response['message'] = 'Autenticazione richiesta per eliminare l\'icona.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $iconPathToDelete = isset($data['iconPath']) ? $data['iconPath'] : null; // e.g., 'uploads/icons/ai_icon_user_12345.png'

    if (empty($iconPathToDelete)) {
        $response['message'] = 'Nessun percorso icona specificato per l\'eliminazione.';
        echo json_encode($response);
        exit;
    }

    // --- Validazione e Sicurezza ---
    // 1. Normalizza il path per sicurezza (rimuovi tentativi di directory traversal)
    $iconPathToDelete = str_replace(['../', '..\\'], '', $iconPathToDelete);
    if (strpos($iconPathToDelete, 'uploads/icons/') !== 0) {
        $response['message'] = 'Percorso icona non valido o non consentito.';
        echo json_encode($response);
        exit;
    }

    // 2. Verifica che l'utente possa eliminare questa icona
    //    Solo le icone AI generate dall'utente loggato possono essere eliminate.
    //    Pattern: uploads/icons/ai_icon_{emailsanitized}_{timestamp}.png
    $currentUserEmail = $_SESSION['user_email'];
    $emailParts = explode('@', $currentUserEmail);
    $currentUserEmailSanitized = preg_replace("/[^a-zA-Z0-9_]+/", "", $emailParts[0]);

    $filename = basename($iconPathToDelete);
    $expectedPrefix = 'ai_icon_' . $currentUserEmailSanitized . '_';

    if (strpos($filename, $expectedPrefix) !== 0) {
        $response['message'] = 'Non sei autorizzato ad eliminare questa icona o il formato non è corretto.';
        // Potresti voler loggare questo tentativo
        error_log("Tentativo di eliminazione non autorizzato: Utente {$_SESSION['user_email']} ha provato a eliminare {$iconPathToDelete}");
        echo json_encode($response);
        exit;
    }
    
    // 3. Lista di icone di default non eliminabili (nomi file semplici)
    $nonDeletableDefaults = [
        'default_user.png', '1.jpg', '3.jpg', 'ata.png', 'spiritual.png' 
        // Aggiungi altri nomi di file di icone di sistema che non devono essere eliminabili
    ];
    if (in_array($filename, $nonDeletableDefaults)) {
        $response['message'] = 'Le icone di default del sistema non possono essere eliminate.';
        echo json_encode($response);
        exit;
    }


    // --- Eliminazione File ---
    $baseDir = __DIR__ . '/../'; // Assumendo che 'api' sia una sottocartella della root del sito
    $filepathPhysical = $baseDir . $iconPathToDelete;

    if (file_exists($filepathPhysical)) {
        if (unlink($filepathPhysical)) {
            $response['success'] = true;
            $response['message'] = 'Icona eliminata con successo dal server.';

            // Opzionale: Aggiorna il database se l'icona dell'utente era questa
            // Questo dipende da come gestisci l'icona attiva dell'utente nel DB
            // Ad esempio, potresti voler impostare l'icona dell'utente a quella di default
            // require_once 'db_config.php'; // Il tuo file di connessione al DB
            // $conn = // ... (stabilisci connessione)
            // $stmt = $conn->prepare("UPDATE utentiregistrati SET Icon = ? WHERE Contatto = ? AND Icon = ?");
            // $defaultIconDbPath = '/uploads/icons/default_user.png'; // Path come memorizzato nel DB
            // $iconPathDbFormat = '/' . $iconPathToDelete; // Assumendo che il DB memorizzi con '/' iniziale
            // $stmt->bind_param("sss", $defaultIconDbPath, $_SESSION['user_email'], $iconPathDbFormat);
            // $stmt->execute();
            // $stmt->close();
            // $conn->close();

        } else {
            $response['message'] = 'Impossibile eliminare il file dell\'icona dal server.';
            error_log("Errore unlink per {$filepathPhysical}");
        }
    } else {
        $response['message'] = 'File icona non trovato sul server per l\'eliminazione.';
        error_log("File non trovato per eliminazione: {$filepathPhysical}");
    }

} else {
    $response['message'] = 'Metodo di richiesta non valido per l\'eliminazione.';
}

echo json_encode($response);
?>
