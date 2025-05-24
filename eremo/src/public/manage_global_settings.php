<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$username_db = "eremofratefrancesco"; // Assicurati che sia corretto
$password_db = "";                   // Assicurati che sia corretto
$dbname_db = "my_eremofratefrancesco"; // Assicurati che sia corretto

$conn = new mysqli($host, $username_db, $password_db, $dbname_db);

if ($conn->connect_error) {
    // Imposta l'header solo se stai per inviare JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connessione al DB fallita: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_setting') {
    header('Content-Type: application/json'); // Imposta header prima dell'output
    $key = $_GET['key'] ?? '';
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Chiave impostazione non fornita.']);
        $conn->close(); // Chiudi qui se esci
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'global_settings' AND chiave_contenuto = ?");
        if (!$stmt) {
            throw new Exception("Errore preparazione statement (get): " . $conn->error);
        }
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'value' => $row['valore_contenuto']]);
        } else {
            echo json_encode(['success' => true, 'value' => null, 'message' => 'Impostazione non trovata.']);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Errore DB (get): ' . $e->getMessage()]);
    }

} elseif ($action === 'update_setting') {
    header('Content-Type: application/json'); // Imposta header prima dell'output
    $key = $_POST['key'] ?? '';
    $value_is_provided = isset($_POST['value']); // Controlla se 'value' è stato inviato
    $value = $_POST['value'] ?? null; // Assegna null se non inviato, altrimenti il suo valore

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Chiave impostazione non fornita.']);
        $conn->close(); // Chiudi qui se esci
        exit;
    }

    // Il JavaScript invierà sempre '0', '1', '2', o '3', quindi $_POST['value'] sarà sempre impostato.
    // Se volessi essere più restrittivo e assicurarti che il valore sia uno di quelli attesi:
    // if (!$value_is_provided || !in_array((string)$value, ['0', '1', '2', '3'], true)) {
    //    echo json_encode(['success' => false, 'message' => 'Valore impostazione non fornito o non valido.']);
    //    $conn->close();
    //    exit;
    // }
    // Per ora, ci fidiamo che il JS invii un valore corretto tra quelli previsti.

    try {
        $pagina_nome_const = 'global_settings';
        $stmt_check = $conn->prepare("SELECT id FROM pagina_contenuti WHERE pagina_nome = ? AND chiave_contenuto = ?");
        if (!$stmt_check) {
            throw new Exception("Errore preparazione statement (check): " . $conn->error);
        }
        $stmt_check->bind_param("ss", $pagina_nome_const, $key);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $exists = $result_check->fetch_assoc();
        $stmt_check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE pagina_contenuti SET valore_contenuto = ? WHERE pagina_nome = ? AND chiave_contenuto = ?");
            if (!$stmt) {
                throw new Exception("Errore preparazione statement (update): " . $conn->error);
            }
            $stmt->bind_param("sss", $value, $pagina_nome_const, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO pagina_contenuti (pagina_nome, chiave_contenuto, valore_contenuto) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Errore preparazione statement (insert): " . $conn->error);
            }
            $stmt->bind_param("sss", $pagina_nome_const, $key, $value);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Impostazione salvata con successo.']);
        } else {
            throw new Exception("Errore esecuzione statement: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Errore DB (update): ' . $e->getMessage()]);
    }

} else {
    header('Content-Type: application/json'); // Imposta header prima dell'output
    echo json_encode(['success' => false, 'message' => 'Azione non valida.']);
}

$conn->close(); // Chiusura della connessione alla fine dello script
?>