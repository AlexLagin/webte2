<?php
session_start();

// Over, či je používateľ prihlásený
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';
require_once 'vendor/autoload.php';

use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Google\Client;

// (Nepovinné) inicializácia Google Clienta, ak potrebujete
$client = new Client();
$client->setAuthConfig('../../client_secret.json');

// Skontrolujeme, či ide o Google účet
$isGoogle = isset($_SESSION['gid']);

// Ak je Google účet, nebudeme generovať nový 2FA secret
$newSecret = null;
$qrDataUri = null;

// Ak je lokálne prihlásenie (nie Google), môžeme resetovať 2FA
if (!$isGoogle) {
    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());

    // Vytvoríme nový secret
    $newSecret = $tfa->createSecret();

    // Vygenerujeme QR kód (Data URI)
    $qrDataUri = $tfa->getQRCodeImageAsDataUri('Moja Aplikácia', $newSecret);

    // Uloženie nového 2fa_code do DB
    if (!empty($_SESSION['email'])) {
        $sqlUpdate = "UPDATE users SET 2fa_code = :newcode WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->bindValue(':newcode', $newSecret, PDO::PARAM_STR);
        $stmt->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);
        $stmt->execute();
    }
}

?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Reset 2FA</title>
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
    </style>
</head>
<body>

<!-- Navigačný bar -->
<nav>
    <div class="navbar-container">
        <div class="navbar-title">Reset 2FA</div>
        <ul class="navbar-links">
            <li><a href="index.php">Úvodná stránka</a></li>
        </ul>
    </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
      <div class="col-md-8">
          <div class="card">
              <div class="card-header text-center">
                  <h3>Resetovanie 2FA</h3>
              </div>
              <div class="card-body">

                  <?php if ($isGoogle): ?>
                      <!-- Google účet - nie je možné resetnúť 2FA -->
                      <div class="alert alert-warning">
                          Tento účet je prihlásený cez Google, nie je možné resetovať 2FA.
                      </div>
                      <hr>
                      <a href="restricted.php" class="btn btn-primary">Späť na privátnu stránku</a>

                  <?php else: ?>
                      <!-- Lokálne konto - zobraziť nový 2FA kód -->
                      <?php if ($newSecret && $qrDataUri): ?>
                          <p class="mb-4">
                              Nižšie vidíte <strong>nový kód</strong> pre 2FA, ktorý bol uložený do databázy.
                              V Authenticator aplikácii si zmažte starý záznam a pridajte si tento nový.
                          </p>
                          <div class="alert alert-info">
                              <p><strong>2FA Secret:</strong> <?php echo htmlspecialchars($newSecret); ?></p>
                          </div>
                          <p>
                              <strong>QR kód:</strong><br>
                              <img src="<?php echo $qrDataUri; ?>" alt="QR Code pre 2FA">
                          </p>
                      <?php else: ?>
                          <!-- Bezpečnostná fallback správa, nemalo by nastať -->
                          <div class="alert alert-danger">
                              Chyba pri generovaní nového 2FA kódu.
                          </div>
                      <?php endif; ?>

                      <hr>
                      <a href="restricted.php" class="btn btn-primary">Späť na privátnu stránku</a>
                  <?php endif; ?>

              </div>
          </div>
      </div>
  </div>
</div>

<!-- Načítanie Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
