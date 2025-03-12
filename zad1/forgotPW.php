<?php
session_start();

// Ak je používateľ prihlásený, môžete ho prípadne presmerovať na restricted.php
// if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
//     header("location: restricted.php");
//     exit;
// }

require_once "config.php";
require_once 'vendor/autoload.php';
require_once 'utilities.php';

use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

// Zapneme ERRMODE_EXCEPTION, aby sme videli chyby pri DB operáciách
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --------------------------------------------------------------------------------
// 1) AJAX – Overenie emailu a 2FA kódu
// --------------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'verify2FA') {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($_POST['email'] ?? '');
    $twofa = trim($_POST['twofa'] ?? '');

    if (empty($email) || empty($twofa)) {
        echo json_encode([
            'success' => false,
            'message' => 'Musíte zadať email aj 2FA kód.'
        ]);
        exit;
    }

    // Skontrolujeme, či účet existuje
    $stmt = $pdo->prepare("SELECT 2fa_code FROM users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() !== 1) {
        // Daný účet neexistuje
        echo json_encode([
            'success' => false,
            'message' => 'Daný účet neexistuje.'
        ]);
        exit;
    }

    $row = $stmt->fetch();
    $secretFromDB = $row['2fa_code'];

    // Overíme 2FA kód
    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());
    $isValid = $tfa->verifyCode($secretFromDB, $twofa, 2);

    if (!$isValid) {
        echo json_encode([
            'success' => false,
            'message' => 'Neplatný autentifikačný kód.'
        ]);
        exit;
    }

    // Všetko OK
    echo json_encode([
        'success' => true,
        'message' => 'Email a 2FA kód sú správne.'
    ]);
    exit;
}

// --------------------------------------------------------------------------------
// 2) AJAX – Zmena hesla v DB
// --------------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'changePW') {
    header('Content-Type: application/json; charset=utf-8');

    $email      = trim($_POST['email'] ?? '');
    $pw1        = trim($_POST['pw1'] ?? '');
    $pw2        = trim($_POST['pw2'] ?? '');

    if (empty($email) || empty($pw1) || empty($pw2)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nové heslo alebo email nie sú zadané.'
        ]);
        exit;
    }
    if ($pw1 !== $pw2) {
        echo json_encode([
            'success' => false,
            'message' => 'Heslá sa nezhodujú.'
        ]);
        exit;
    }

    // Update hesla v DB
    $hashed = password_hash($pw1, PASSWORD_ARGON2ID);
    $stmtUp = $pdo->prepare("UPDATE users SET password = :pw WHERE email = :email LIMIT 1");
    $stmtUp->bindValue(':pw', $hashed, PDO::PARAM_STR);
    $stmtUp->bindValue(':email', $email, PDO::PARAM_STR);

    if ($stmtUp->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Heslo bolo úspešne zmenené.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nepodarilo sa zmeniť heslo v databáze.'
        ]);
    }
    exit;
}

// --------------------------------------------------------------------------------
// 3) HTML stránka – Formulár
// --------------------------------------------------------------------------------
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabudnuté heslo – Overenie 2FA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
      body {
          background-color: #f4f7f9;
          margin: 0;
          padding: 0;
      }
      nav {
          background: linear-gradient(135deg, #2c3e50, #2f4254);
          color: #fff;
          padding: 10px 20px;
          margin: 0;
          box-shadow: 0 2px 5px rgba(0,0,0,0.3);
      }
      nav .navbar-container {
          display: flex;
          align-items: center;
          justify-content: space-between;
          max-width: 1200px;
          margin: 0 auto;
      }
      nav .navbar-title {
          font-size: 1.6em;
          font-weight: 600;
      }
      nav .navbar-links {
          list-style: none;
          margin: 0;
          padding: 0;
          display: flex;
      }
      nav .navbar-links li {
          margin-left: 20px;
      }
      nav .navbar-links a {
          color: #fff;
          text-decoration: none;
          font-weight: 500;
          transition: color 0.2s;
      }
      nav .navbar-links a:hover {
          color: #ddd;
      }
      .container {
          margin-top: 50px;
      }
      .card {
          box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }
      #changePWGroup {
          display: none; /* skrytý, kým neoveríme email a 2FA */
      }
    </style>
</head>
<body>

<nav>
    <div class="navbar-container">
        <div class="navbar-title">Zabudnuté heslo</div>
        <ul class="navbar-links">
            <li><a href="index.php">Úvodná stránka</a></li>
        </ul>
    </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h3>Obnovenie zabudnutého hesla</h3>
          <p>Zadajte email konta a 2FA kód.</p>
        </div>
        <div class="card-body">
          
          <!-- Div pre zobrazovanie chýb/správ -->
          <div id="errorMsg" class="alert alert-danger" style="display: none;"></div>
          <div id="successMsg" class="alert alert-success" style="display: none;"></div>

          <!-- Krok 1: Zadanie emailu a 2FA -->
          <div id="verifyGroup">
            <div class="mb-3">
              <label for="email" class="form-label">E-mail:</label>
              <input type="email" id="email" class="form-control" placeholder="napr. user@example.com">
            </div>
            <div class="mb-3">
              <label for="twofa" class="form-label">2FA kód:</label>
              <input type="text" id="twofa" class="form-control" placeholder="napr. 123456" inputmode="numeric">
            </div>
            <button type="button" class="btn btn-primary" id="verifyBtn">Overiť email a 2FA</button>
          </div>

          <!-- Krok 2: Zadanie nového hesla (zobrazí sa až po úspešnom overení) -->
          <div id="changePWGroup">
            <hr>
            <p>Prosím, zadajte nové heslo:</p>
            <div class="mb-3">
              <label for="pw1" class="form-label">Nové heslo:</label>
              <input type="password" id="pw1" class="form-control">
            </div>
            <div class="mb-3">
              <label for="pw2" class="form-label">Znova zadajte nové heslo:</label>
              <input type="password" id="pw2" class="form-control">
            </div>
            <button type="button" class="btn btn-success" id="changePWBtn">Zmena hesla</button>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Načítanie Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const verifyGroup   = document.getElementById('verifyGroup');
  const changePWGroup = document.getElementById('changePWGroup');

  const emailInput    = document.getElementById('email');
  const twofaInput    = document.getElementById('twofa');
  const verifyBtn     = document.getElementById('verifyBtn');

  const pw1Input      = document.getElementById('pw1');
  const pw2Input      = document.getElementById('pw2');
  const changePWBtn   = document.getElementById('changePWBtn');

  const errorMsg      = document.getElementById('errorMsg');
  const successMsg    = document.getElementById('successMsg');

  // Skryjeme prípadné staré správy
  function hideMessages() {
    errorMsg.style.display = 'none';
    errorMsg.textContent = '';
    successMsg.style.display = 'none';
    successMsg.textContent = '';
  }

  // 1) Po kliknutí na "Overiť email a 2FA"
  verifyBtn.addEventListener('click', function() {
    hideMessages();

    const emailVal = emailInput.value.trim();
    const twofaVal = twofaInput.value.trim();

    if (!emailVal || !twofaVal) {
      errorMsg.textContent = 'Musíte zadať email aj 2FA kód.';
      errorMsg.style.display = 'block';
      return;
    }

    // AJAX: forgotPW.php?ajax=verify2FA
    fetch("forgotPW.php?ajax=verify2FA", {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'email=' + encodeURIComponent(emailVal) + '&twofa=' + encodeURIComponent(twofaVal)
    })
    .then(resp => resp.json())
    .then(data => {
      if (data.success) {
        // Email a 2FA kód sú správne -> zobrazíme changePWGroup
        verifyGroup.style.display = 'none';
        changePWGroup.style.display = 'block';
      } else {
        // Daný účet neexistuje alebo neplatný 2FA
        errorMsg.textContent = data.message;
        errorMsg.style.display = 'block';
      }
    })
    .catch(err => {
      errorMsg.textContent = 'Chyba: ' + err;
      errorMsg.style.display = 'block';
    });
  });

  // 2) Po kliknutí na "Zmena hesla"
  changePWBtn.addEventListener('click', function() {
    hideMessages();

    const emailVal = emailInput.value.trim();
    const pw1Val   = pw1Input.value.trim();
    const pw2Val   = pw2Input.value.trim();

    if (!emailVal || !pw1Val || !pw2Val) {
      errorMsg.textContent = 'Musíte zadať email a obe polia pre nové heslo.';
      errorMsg.style.display = 'block';
      return;
    }

    // AJAX: forgotPW.php?ajax=changePW
    fetch("forgotPW.php?ajax=changePW", {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'email=' + encodeURIComponent(emailVal)
          + '&pw1=' + encodeURIComponent(pw1Val)
          + '&pw2=' + encodeURIComponent(pw2Val)
    })
    .then(resp => resp.json())
    .then(data => {
      if (data.success) {
        successMsg.textContent = data.message; // "Heslo bolo úspešne zmenené."
        successMsg.style.display = 'block';
        // Môžeme skryť formulár na zmenu hesla, ak chceme
        changePWGroup.style.display = 'none';
      } else {
        errorMsg.textContent = data.message;
        errorMsg.style.display = 'block';
      }
    })
    .catch(err => {
      errorMsg.textContent = 'Chyba: ' + err;
      errorMsg.style.display = 'block';
    });
  });
});
</script>
</body>
</html>
