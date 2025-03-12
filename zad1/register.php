<?php
session_start();

// Check if the user is already logged in, if yes then redirect him to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'utilities.php';

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

$redirect_uri = "https://node73.webte.fei.stuba.sk/zad1/oauth2callback.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = "";

    // ---------------------------
    // 1) Validácia MENO (server-side)
    // ---------------------------
    if (isEmpty($_POST['firstname']) === true) {
        $errors .= "Nevyplnené meno.\n";
    } else {
        // dĺžka
        if (mb_strlen($_POST['firstname']) > 50) {
            $errors .= "Meno môže mať najviac 50 znakov.\n";
        }
        // len písmená (vrátane diakritiky)
        if (!preg_match('/^[\p{L}]+$/u', $_POST['firstname'])) {
            $errors .= "Meno môže obsahovať iba písmená (vrátane diakritiky).\n";
        }
    }

    // ---------------------------
    // 2) Validácia PRIEZVISKO (server-side)
    // ---------------------------
    if (isEmpty($_POST['lastname']) === true) {
        $errors .= "Nevyplnené priezvisko.\n";
    } else {
        // dĺžka
        if (mb_strlen($_POST['lastname']) > 50) {
            $errors .= "Priezvisko môže mať najviac 50 znakov.\n";
        }
        // len písmená (vrátane diakritiky)
        if (!preg_match('/^[\p{L}]+$/u', $_POST['lastname'])) {
            $errors .= "Priezvisko môže obsahovať iba písmená (vrátane diakritiky).\n";
        }
    }

    // ---------------------------
    // 3) Validácia E-MAIL (server-side)
    // ---------------------------
    if (isEmpty($_POST['email']) === true) {
        $errors .= "Nevyplnený e-mail.\n";
    } else {
        // Validate if user entered correct e-mail format (server-side)
        $emailPattern = '/^[A-Za-z0-9.]{3,}@[a-z]+\.[a-z]+$/';
        if (!preg_match($emailPattern, $_POST['email'])) {
            $errors .= "Zadaný e-mail nemá správny formát.\n";
        }
    }

    // Overenie, či už existuje
    if (userExist($pdo, $_POST['email']) === true) {
        $errors .= "Používateľ s týmto e-mailom už existuje.\n";
    }

    // ---------------------------
    // 4) Validácia HESLA
    // ---------------------------
    if (isEmpty($_POST['password']) === true) {
        $errors .= "Nevyplnené heslo.\n";
    }
    // (Nepovinné, ale vhodné: Skontrolovať aj $_POST['password2'] na server-side)
    // TODO: Implement repeat password validation on server side as well.
    // TODO: Sanitize and validate all user inputs.

    // ---------------------------
    // 5) Ak nie sú chyby -> INSERT do DB
    // ---------------------------
    if (empty($errors)) {
        $sql = "INSERT INTO users (fullname, email, password, 2fa_code) VALUES (:fullname, :email, :password, :2fa_code)";

        $fullname = $_POST['firstname'] . ' ' . $_POST['lastname'];
        $email = $_POST['email'];
        $pw_hash = password_hash($_POST['password'], PASSWORD_ARGON2ID);

        $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());
        $user_secret = $tfa->createSecret();
        $qr_code = $tfa->getQRCodeImageAsDataUri('Nobel Prizes', $user_secret);

        // Bind parameters to SQL
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password", $pw_hash, PDO::PARAM_STR);
        $stmt->bindParam(":2fa_code", $user_secret, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $reg_status = "Registracia prebehla uspesne.";
        } else {
            $reg_status = "Ups. Nieco sa pokazilo...";
        }

        unset($stmt);
    }
    unset($pdo);
}
?>

<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrácia</title>
    <!-- Načítanie Bootstrap CSS (rovnako ako v login(1).php) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Rovnaké CSS ako v login(1).php */
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
    <!-- Navigačný bar, rovnaký ako v login(1).php -->
    <nav>
        <div class="navbar-container">
            <div class="navbar-title">Názov stránky</div>
            <ul class="navbar-links">
                <li><a href="index.php">Zoznam laureátov</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
      <div class="row justify-content-center">
          <div class="col-md-6">
              <div class="card">
                  <div class="card-header text-center">
                      <h3>Registrácia</h3>
                      <p>Vytvorenie nového používateľského konta</p>
                  </div>
                  <div class="card-body">
                      <!-- Výpis prípadného statusu registrácie -->
                      <?php if (isset($reg_status)) { ?>
                          <div class="alert alert-success"><?php echo $reg_status; ?></div>
                      <?php } ?>

                      <!-- Výpis chýb, ak existujú (server-side) -->
                      <?php if (!empty($errors)) { ?>
                          <div class="alert alert-danger">
                              <strong>Chyby:</strong><br>
                              <?php echo nl2br($errors); ?>
                          </div>
                      <?php } ?>

                      <!-- Div pre JS chyby (client-side) -->
                      <div id="jsErrorMsg" class="alert alert-danger" style="display: none;"></div>

                      <!-- Formulár pre registráciu -->
                      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="regForm">
                          <div class="mb-3">
                              <label for="firstname" class="form-label">Meno:</label>
                              <input 
                                  type="text" 
                                  name="firstname" 
                                  id="firstname" 
                                  class="form-control" 
                                  placeholder="napr. John"
                                  maxlength="50"
                              >
                          </div>
                          <div class="mb-3">
                              <label for="lastname" class="form-label">Priezvisko:</label>
                              <input 
                                  type="text" 
                                  name="lastname" 
                                  id="lastname" 
                                  class="form-control" 
                                  placeholder="napr. Doe"
                                  maxlength="50"
                              >
                          </div>
                          <div class="mb-3">
                              <label for="email" class="form-label">E-mail:</label>
                              <input 
                                  type="email" 
                                  name="email" 
                                  id="email" 
                                  class="form-control" 
                                  placeholder="napr. johndoe@example.com"
                              >
                          </div>
                          <div class="mb-3">
                              <label for="password" class="form-label">Heslo:</label>
                              <input 
                                  type="password" 
                                  name="password" 
                                  id="password" 
                                  class="form-control"
                              >
                          </div>
                          <div class="mb-3">
                              <label for="password2" class="form-label">Zopakovať heslo:</label>
                              <input 
                                  type="password" 
                                  name="password2" 
                                  id="password2" 
                                  class="form-control"
                              >
                          </div>
                          <div class="d-grid gap-2">
                              <button type="submit" class="btn btn-primary">Vytvoriť konto</button>
                          </div>
                      </form>

                      <!-- Ak sa vygeneruje QR kód (po úspešnej registrácii), zobraz ho -->
                      <?php if (isset($qr_code)) : ?>
                          <hr>
                          <p>Zadajte kód: <strong><?php echo $user_secret; ?></strong> do aplikácie pre 2FA.</p>
                          <p>alebo naskenujte QR kód:<br>
                             <img src="<?php echo $qr_code; ?>" alt="qr kod pre aplikaciu authenticator">
                          </p>
                          <p>Teraz sa môžete prihlásiť: <a href="login.php">Login stránka</a></p>
                      <?php endif; ?>

                      <hr>
                      <p class="text-center">
                          Už máte vytvorené konto? <a href="login.php">Prihláste sa tu.</a>
                      </p>
                      <p class="text-center">
                          Alebo sa prihláste pomocou 
                          <a href="<?php echo filter_var($redirect_uri, FILTER_SANITIZE_URL) ?>">Google konta</a>
                      </p>
                  </div>
              </div>
          </div>
      </div>
    </div>

    <!-- Načítanie Bootstrap JS (rovnako ako v login(1).php) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript validácia Mena, Priezviska, Emailu, prázdnych polí, + zopakovanie hesla -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const regForm       = document.getElementById('regForm');
        const firstName     = document.getElementById('firstname');
        const lastName      = document.getElementById('lastname');
        const emailInput    = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const password2Input= document.getElementById('password2'); // Zopakovať heslo
        const jsErrorMsg    = document.getElementById('jsErrorMsg');

        // Regex pre meno/priezvisko (len písmená vrátane diakritiky, bez číslic):
        const nameRegex = /^[\p{L}]+$/u;

        // Regex pre email:
        // - Pred @ min. 3 znaky (A-Z, a-z, 0-9, .)
        // - Za @ len malé písmená + bodka + malé písmená
        const emailRegex = /^[A-Za-z0-9.]{3,}@[a-z]+\.[a-z]+$/;

        regForm.addEventListener('submit', function(e) {
          // Najprv vyresetujeme obsah JS chýb
          jsErrorMsg.style.display = 'none';
          jsErrorMsg.innerHTML = '';

          let errors = [];

          const fNameVal      = firstName.value.trim();
          const lNameVal      = lastName.value.trim();
          const emailVal      = emailInput.value.trim();
          const passwordVal   = passwordInput.value.trim();
          const password2Val  = password2Input.value.trim();

          // 1) Overenie, či polia nie sú prázdne
          if (!fNameVal) {
            errors.push("Pole 'Meno' nemôže ostať prázdne.");
          }
          if (!lNameVal) {
            errors.push("Pole 'Priezvisko' nemôže ostať prázdne.");
          }
          if (!emailVal) {
            errors.push("Pole 'E-mail' nemôže ostať prázdne.");
          }
          if (!passwordVal) {
            errors.push("Pole 'Heslo' nemôže ostať prázdne.");
          }
          if (!password2Val) {
            errors.push("Pole 'Zopakovať heslo' nemôže ostať prázdne.");
          }

          // 2) Ďalšie validácie Meno/Priezvisko (len písmená + max 50 znakov)
          if (fNameVal.length > 50) {
            errors.push("Meno môže mať najviac 50 znakov.");
          }
          if (lNameVal.length > 50) {
            errors.push("Priezvisko môže mať najviac 50 znakov.");
          }
          if (fNameVal && !nameRegex.test(fNameVal)) {
            errors.push("Meno môže obsahovať iba písmená (vrátane diakritiky).");
          }
          if (lNameVal && !nameRegex.test(lNameVal)) {
            errors.push("Priezvisko môže obsahovať iba písmená (vrátane diakritiky).");
          }

          // 3) Validácia formátu emailu (iba ak nie je prázdny)
          if (emailVal && !emailRegex.test(emailVal)) {
            errors.push("Nesprávny formát emailu. Pred @ aspoň 3 znaky (A-Z, 0-9, .), po @ len malé písmená + . + malé písmená.");
          }

          // 4) Kontrola zhody hesiel (ak obidve nie sú prázdne)
          if (passwordVal && password2Val && (passwordVal !== password2Val)) {
            errors.push("Zadané heslá sa nezhodujú.");
          }

          // Ak je nejaká chyba, zobrazíme ju a zastavíme odoslanie formulára
          if (errors.length > 0) {
            e.preventDefault();
            jsErrorMsg.innerHTML = errors.join("<br>");
            jsErrorMsg.style.display = 'block';
          }
        });
      });
    </script>
</body>
</html>
