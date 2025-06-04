<?php
// api/api_check_session.php
// Assicurati che il percorso a config_session.php sia corretto
// Se api_check_session.php è in una sottocartella 'api', e config_session.php è nella root:
require_once '../config_session.php';

// Questa funzione farà exit con 401 se la sessione non è valida.
// Il parametro 'true' indica che è una chiamata API, quindi restituirà JSON in caso di errore.
require_login(true);

// Se require_login() non ha interrotto lo script, la sessione è valida.
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Sessione attiva.']);
exit;
?>
