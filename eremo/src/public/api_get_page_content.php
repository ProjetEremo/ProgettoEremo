<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_session.php'; 
require_once 'config/db_config.php'; 

$response = ['success' => false, 'contents' => null, 'message' => ''];
$pageName = isset($_GET['page']) ? trim($_GET['page']) : 'index'; 

if (!$conn || $conn->connect_errno) {
    error_log("Errore di connessione al database in api_get_page_content.php: " . ($conn ? $conn->connect_error : "Variabile \$conn non inizializzata"));
    $response['message'] = 'Errore di connessione al database.';
    http_response_code(500); 
    echo json_encode($response);
    exit;
}

if (empty($pageName)) {
    $response['message'] = 'Nome pagina non specificato.';
    http_response_code(400); 
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT chiave_contenuto, valore_contenuto FROM pagina_contenuti WHERE pagina_nome = ?");
    if (!$stmt) {
        throw new Exception("Errore preparazione query: " . $conn->error);
    }

    $stmt->bind_param("s", $pageName);
    $stmt->execute();
    $result = $stmt->get_result();

    $contents = [];
    while ($row = $result->fetch_assoc()) {
        $chiave = $row['chiave_contenuto'];
        $valore = $row['valore_contenuto'];

        // Tentativo di decodificare se il valore sembra una stringa JSON (es. per card1PhoneNumbers)
        // Questo è un approccio euristico. Sarebbe meglio sapere a priori quali chiavi sono JSON.
        // Per card1PhoneNumbers, sappiamo che dovrebbe essere un array.
        if ($chiave === 'card1PhoneNumbers') {
            $decodedValue = json_decode($valore, true); // true per array associativo
            // Se json_decode fallisce (es. il valore non era JSON valido o era una stringa normale), 
            // $decodedValue sarà null. In tal caso, potremmo voler usare il valore originale
            // o un array vuoto di default se ci aspettiamo sempre un array.
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValue)) {
                $contents[$chiave] = $decodedValue;
            } else {
                // Se non è un JSON valido o non è un array, o è vuoto,
                // potresti voler inizializzare con un array vuoto o gestire diversamente.
                // Per ora, se non è un JSON valido, lo trattiamo come stringa (o il client userà i default).
                // Se il campo nel DB potesse essere una stringa non-JSON per card1PhoneNumbers,
                // questo comporterebbe un errore nel JS. Assumiamo che se card1PhoneNumbers esiste, sia JSON.
                // Se $valore è una stringa vuota o non JSON, json_decode restituisce null.
                // Il client JS si aspetta un array, quindi forniamo un array vuoto se la decodifica fallisce o è vuota.
                 $contents[$chiave] = $decodedValue !== null ? $decodedValue : [];
            }
        } else {
            // Per tutte le altre chiavi, assegna il valore direttamente
            $contents[$chiave] = $valore;
        }
    }

    if (!empty($contents)) {
        $response['success'] = true;
        $response['contents'] = $contents;
    } else {
        $response['success'] = true;
        $response['contents'] = []; 
        $response['message'] = 'Nessun contenuto personalizzato trovato per la pagina: ' . htmlspecialchars($pageName) . '. Verranno usati i default se disponibili lato client.';
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Errore in api_get_page_content.php: " . $e->getMessage());
    $response['message'] = 'Errore del server durante il recupero dei contenuti.';
    http_response_code(500); 
}

if ($conn) {
    $conn->close();
}

echo json_encode($response);
?>
