<?php
// Funzione per offuscare la chiave API (XOR + Base64)
function simpleXorEncryptServerSide($text, $key) {
    $outText = '';
    $textLen = strlen($text);
    $keyLen = strlen($key);
    for ($i = 0; $i < $textLen; $i++) {
        $outText .= $text[$i] ^ $key[$i % $keyLen];
    }
    return base64_encode($outText);
}

$api_key_originale = '';
$chiave_offuscamento = '';
$api_key_offuscata = '';
$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key_originale = isset($_POST['api_key_originale']) ? trim($_POST['api_key_originale']) : '';
    $chiave_offuscamento = isset($_POST['chiave_offuscamento']) ? trim($_POST['chiave_offuscamento']) : '';

    if (empty($api_key_originale) || empty($chiave_offuscamento)) {
        $messaggio = '<p style="color: red;">Per favore, inserisci sia la API Key originale sia la chiave di offuscamento.</p>';
    } else {
        $api_key_offuscata = simpleXorEncryptServerSide($api_key_originale, $chiave_offuscamento);
        $messaggio = '<p style="color: green;">Chiave API offuscata generata con successo!</p>';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generatore Chiave API Offuscata</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        h1 { color: #333; text-align: center; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .risultato { margin-top: 20px; padding: 15px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px; word-wrap: break-word; }
        .risultato strong { display: inline-block; min-width: 180px; }
        code { background-color: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generatore Chiave API Offuscata</h1>
        
        <?php if (!empty($messaggio)) echo $messaggio; ?>

        <form method="POST" action="">
            <div>
                <label for="api_key_originale">API Key Originale:</label>
                <input type="text" id="api_key_originale" name="api_key_originale" value="<?php echo htmlspecialchars($api_key_originale); ?>" required>
            </div>
            <div>
                <label for="chiave_offuscamento">Chiave di Offuscamento (es: PasswordEremo25!):</label>
                <input type="text" id="chiave_offuscamento" name="chiave_offuscamento" value="<?php echo htmlspecialchars($chiave_offuscamento); ?>" required>
            </div>
            <div>
                <input type="submit" value="Genera Chiave Offuscata">
            </div>
        </form>

        <?php if (!empty($api_key_offuscata)): ?>
        <div class="risultato">
            <h3>Risultati:</h3>
            <p><strong>API Key Originale:</strong> <code><?php echo htmlspecialchars($api_key_originale); ?></code></p>
            <p><strong>Chiave di Offuscamento:</strong> <code><?php echo htmlspecialchars($chiave_offuscamento); ?></code></p>
            <p><strong>API Key Offuscata (da salvare nel DB):</strong><br>
               <code><?php echo htmlspecialchars($api_key_offuscata); ?></code>
            </p>
            <hr>
            <p><strong>Come usare nel database (tabella <code>pagina_contenuti</code>):</strong></p>
            <p>Per il servizio 'NOME_SERVIZIO' (es. 'murf', 'elevenlabs', 'groq'):</p>
            <ul>
                <li>Riga 1:
                    <ul>
                        <li><code>pagina_nome</code>: system_config</li>
                        <li><code>chiave_contenuto</code>: NOME_SERVIZIO_api_key_obfuscated (es. <code>murf_api_key_obfuscated</code>)</li>
                        <li><code>valore_contenuto</code>: <strong><?php echo htmlspecialchars($api_key_offuscata); ?></strong></li>
                    </ul>
                </li>
                <li>Riga 2:
                    <ul>
                        <li><code>pagina_nome</code>: system_config</li>
                        <li><code>chiave_contenuto</code>: NOME_SERVIZIO_obfuscation_key (es. <code>murf_obfuscation_key</code>)</li>
                        <li><code>valore_contenuto</code>: <strong><?php echo htmlspecialchars($chiave_offuscamento); ?></strong></li>
                    </ul>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
