<?php
// NOME FILE: reset_password.php

// === INIZIO IMPOSTAZIONI DEBUG PHP (Rimuovere/Commentare in Produzione) ===
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// === FINE IMPOSTAZIONI DEBUG PHP ===

// Configurazione DB
$host = "localhost";
$username_db = "eremofratefrancesco"; // Il tuo username DB Altervista
$password_db = "";                   // La tua password DB Altervista
$dbname_db = "my_eremofratefrancesco";   // Il tuo nome DB Altervista (es. my_tuonomeutente)

$token_valido = false;
$messaggio_errore_token = 'Si è verificato un errore imprevisto. Impossibile verificare il token.'; // Messaggio di default
$token_url = '';

if (isset($_GET['token'])) {
    $token_url = trim($_GET['token']);
    // error_log("Token ricevuto dall'URL: " . $token_url); // Utile per debug

    if (!empty($token_url) && ctype_alnum($token_url) && strlen($token_url) == 64) {
        try {
            $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // error_log("Connessione al DB riuscita per reset_password.php");

            $stmt = $conn->prepare("SELECT email, expires_at FROM password_reset_tokens WHERE token = :token");
            $stmt->bindParam(':token', $token_url, PDO::PARAM_STR);
            $stmt->execute();
            $token_data = $stmt->fetch();

            if ($token_data) {
                // error_log("Token trovato nel DB: " . print_r($token_data, true));
                // Imposta esplicitamente il timezone per coerenza. Sostituisci 'Europe/Rome' se necessario.
                $server_timezone = new DateTimeZone('Europe/Rome');
                $now = new DateTime("now", $server_timezone);
                $expires = new DateTime($token_data['expires_at'], $server_timezone);
                
                // error_log("Data/Ora Corrente (Europe/Rome): " . $now->format('Y-m-d H:i:s'));
                // error_log("Data/Ora Scadenza Token (Europe/Rome): " . $expires->format('Y-m-d H:i:s'));

                if ($now < $expires) {
                    $token_valido = true;
                    // error_log("Token VALIDO e non scaduto.");
                } else {
                    $messaggio_errore_token = "Questo link di recupero password è scaduto. Per favore, richiedine uno nuovo.";
                    // error_log("Token SCADUTO. Messaggio: " . $messaggio_errore_token);
                    $stmtDel = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
                    $stmtDel->bindParam(':token', $token_url, PDO::PARAM_STR);
                    $stmtDel->execute();
                    // error_log("Token scaduto eliminato dal DB.");
                }
            } else {
                $messaggio_errore_token = "Link di recupero password non valido o già utilizzato. [TOKEN_NOT_FOUND]";
                // error_log("Token NON TROVATO nel DB. Messaggio: " . $messaggio_errore_token);
            }
        } catch (PDOException $e) {
            error_log("Errore DB (reset_password.php GET): " . $e->getMessage());
            $messaggio_errore_token = "Errore del server durante la verifica del token. Riprova più tardi. [DB_EXCEPTION]";
        }
        $conn = null;
    } else {
        $messaggio_errore_token = "Il formato del token nel link non è valido. [INVALID_TOKEN_FORMAT]";
        // error_log("Formato token non valido. Lunghezza: " . strlen($token_url) . ", ctype_alnum: " . ctype_alnum($token_url) . ". Messaggio: " . $messaggio_errore_token);
    }
} else {
    $messaggio_errore_token = "Nessun token di recupero fornito nel link. [NO_TOKEN_IN_URL]";
    // error_log("Nessun token nell'URL. Messaggio: " . $messaggio_errore_token);
}
// error_log("Fine blocco PHP: token_valido = " . ($token_valido ? 'true' : 'false') . ", messaggio_errore_token = " . $messaggio_errore_token);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="images/logo.png" type="image/x-icon">
  <title>Reimposta Password - Eremo Frate Francesco</title>
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;400;500;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background-color: var(--light); padding: 20px; box-sizing: border-box;}
    .reset-container { background-color: var(--white); padding: 2rem 2.5rem; border-radius: var(--border-radius-medium); box-shadow: var(--shadow-medium); width: 100%; max-width: 500px; text-align: center; }
    .reset-container h1 { color: var(--primary); margin-bottom: 1.5rem; font-size: 1.8rem; }
    .form-group { margin-bottom: 1.2rem; text-align: left; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark); }
    .form-group input.form-control { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--gray-medium); border-radius: 8px; font-size: 1rem; background-color: var(--white); box-sizing: border-box; }
    .form-group input.form-control:focus { border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(var(--secondary-rgb, 141, 177, 135), 0.25); outline: none; }
    .btn-popup { padding: 0.9rem 1.9rem; border-radius: 9px; border: none; font-weight: 500; cursor: pointer; background: var(--secondary); color: white; transition: var(--transition-medium); font-size: 1.1rem; width:100%; }
    .btn-popup:hover { background: var(--primary); }
    .form-message { display: none; padding: 0.75rem; border-radius: 8px; font-size: 0.9em; margin-top: 0.5em; margin-bottom:1rem; text-align: left;}
    .form-message.error { color: var(--danger, red); background-color: rgba(var(--danger-rgb, 231, 76, 60), 0.1); border: 1px solid rgba(var(--danger-rgb, 231, 76, 60), 0.3); }
    .form-message.success { color: var(--success, green); background-color: rgba(var(--success-rgb, 46, 204, 113), 0.1); border: 1px solid rgba(var(--success-rgb, 46, 204, 113), 0.3); }
    .logo-container { margin-bottom: 1.5rem; }
    .logo-container img { max-width: 80px; height: auto; }
    .spinner-mini-light { display: inline-block; width: 1em; height: 1em; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; vertical-align: middle; margin-left: 5px; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="reset-container">
    <div class="logo-container" id="resetLogoContainer">
      <a href="index.html"><img src="images/logo.png" alt="Logo Eremo"></a>
    </div>
    <h1 id="resetPageTitle">Reimposta la tua Password</h1>

    <?php if ($token_valido): ?>
      <p id="resetInstructions" style="font-size:0.95em; color:var(--gray-dark); margin-bottom:1.5rem; text-align:left;">Crea una nuova password per il tuo account.</p>
      <form id="resetPasswordForm">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_url); ?>">
        <div class="form-group">
          <label for="new_password">Nuova Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
          <small>Minimo 8 caratteri.</small>
        </div>
        <div class="form-group">
          <label for="confirm_password">Conferma Nuova Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
        </div>
        <div id="reset-password-message" class="form-message" style="text-align:left;"></div>
        <button type="submit" class="btn-popup">Salva Nuova Password</button>
      </form>
    <?php else: ?>
      <div id="tokenErrorMessageContainer">
        <div class="form-message error" style="display:block; text-align:center;">
          <?php
            if (empty(trim($messaggio_errore_token))) {
              echo "Si è verificato un errore imprevisto o il token fornito non è valido. Per favore, prova a richiedere un nuovo link di recupero password.";
            } else {
              echo htmlspecialchars($messaggio_errore_token);
            }
          ?>
        </div>
        <p style="margin-top:1.5rem; font-size:0.9em;"><a href="index.html" style="color:var(--primary);">Torna alla Home Page e richiedi un nuovo link</a></p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const resetPasswordMessage = document.getElementById('reset-password-message');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const resetInstructionsP = document.getElementById('resetInstructions');
    const resetPageTitleH1 = document.getElementById('resetPageTitle'); 
    const resetLogoContainerDiv = document.getElementById('resetLogoContainer'); 
    const tokenErrorMessageContainerDiv = document.getElementById('tokenErrorMessageContainer'); 

    if (resetPasswordForm) {
      resetPasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (resetPasswordMessage) {
            resetPasswordMessage.style.display = 'none';
            resetPasswordMessage.className = 'form-message';
        }

        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (newPassword.length < 8) {
            if(resetPasswordMessage) {
                resetPasswordMessage.textContent = 'La password deve contenere almeno 8 caratteri.';
                resetPasswordMessage.classList.add('error');
                resetPasswordMessage.style.display = 'block';
            }
            return;
        }
        if (newPassword !== confirmPassword) {
            if(resetPasswordMessage) {
                resetPasswordMessage.textContent = 'Le password non coincidono.';
                resetPasswordMessage.classList.add('error');
                resetPasswordMessage.style.display = 'block';
            }
            return;
        }

        const submitBtn = resetPasswordForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Salvataggio...<div class="spinner-mini-light"></div>';

        try {
          const formData = new FormData(resetPasswordForm);
          const response = await fetch('aggiorna_password.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();

          if (result.success) {
            // Nascondi tutti gli elementi precedenti nel container
            if (resetLogoContainerDiv) resetLogoContainerDiv.style.display = 'none';
            if (resetPageTitleH1) resetPageTitleH1.textContent = 'Password Modificata'; // Cambia titolo
            if (resetInstructionsP) resetInstructionsP.style.display = 'none';
            if (resetPasswordForm) resetPasswordForm.style.display = 'none';
            if (tokenErrorMessageContainerDiv) tokenErrorMessageContainerDiv.style.display = 'none'; // Nascondi anche il box errore token se esisteva


            if(resetPasswordMessage) {
                resetPasswordMessage.textContent = result.message; // Messaggio da aggiorna_password.php ("Password aggiornata con successo!")
                resetPasswordMessage.classList.remove('error'); 
                resetPasswordMessage.classList.add('success');
                resetPasswordMessage.style.display = 'block';
                resetPasswordMessage.style.textAlign = 'center'; 
            }
            
            const pLink = document.createElement('p');
            pLink.style.marginTop = '1.5rem';
            pLink.style.fontSize = '0.9em';
            pLink.style.textAlign = 'center'; 
            const aLink = document.createElement('a');
            aLink.href = 'index.html'; 
            aLink.textContent = 'Vai alla Home Page per Accedere';
            aLink.style.color = 'var(--primary)';
            pLink.appendChild(aLink);
            
            const resetContainer = document.querySelector('.reset-container');
            if (resetContainer) {
                // Assicurati che il messaggio di successo sia visibile e aggiunto al contenitore principale
                // se non lo è già.
                if(resetPasswordMessage && !resetPasswordMessage.parentNode) {
                     resetContainer.appendChild(resetPasswordMessage);
                } else if (resetPasswordMessage && resetPasswordMessage.parentNode !== resetContainer) {
                    // Se è figlio di qualcos'altro (improbabile qui ma per sicurezza)
                    resetPasswordMessage.parentNode.removeChild(resetPasswordMessage);
                    resetContainer.appendChild(resetPasswordMessage);
                }
                resetContainer.appendChild(pLink);
            }

          } else { 
            if(resetPasswordMessage) {
                resetPasswordMessage.textContent = result.message || "Si è verificato un errore durante l'aggiornamento.";
                resetPasswordMessage.classList.remove('success');
                resetPasswordMessage.classList.add('error');
                resetPasswordMessage.style.display = 'block';
            }
          }
        } catch (error) {
          console.error('Errore aggiornamento password:', error);
          if(resetPasswordMessage) {
            resetPasswordMessage.textContent = "Errore di connessione o del server durante l'aggiornamento.";
            resetPasswordMessage.classList.remove('success');
            resetPasswordMessage.classList.add('error');
            resetPasswordMessage.style.display = 'block';
          }
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        }
      });
    }
  </script>
</body>
</html>