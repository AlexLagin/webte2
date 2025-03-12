<?php
session_start();

// Ak nie je používateľ prihlásený, presmerujeme na login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';  // pripojenie k DB
require_once 'vendor/autoload.php'; // ak potrebujete

// Zistíme, či ide o Google alebo lokálny účet.
// Tu predpokladáme, že $_SESSION['gid'] existuje pri Google prihlásení.
$isGoogle = isset($_SESSION['gid']);

// ---------------------------------------------------------
// 1) AJAX - overenie starého hesla (len pre lokálne účty)
// ---------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'verifyOldPW') {
    // Ak je používateľ Google, rovno vrátime chybu (nemôže meniť heslo)
    if ($isGoogle) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Tento účet je prihlásený cez Google účet, nie je možné meniť heslo.'
        ]);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $oldPassword = $_POST['old_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $row = $stmt->fetch();
        $hashedPW = $row['password'];

        // Porovnanie
        if (password_verify($oldPassword, $hashedPW)) {
            echo json_encode([
                'success' => true,
                'message' => 'Staré heslo je správne.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Zadané heslo nie je správne.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Chyba: používateľ nenájdený.'
        ]);
    }
    exit;
}

// ---------------------------------------------------------
// 2) POST - zmena hesla (po úspešnom overení starého hesla)
// ---------------------------------------------------------
$msgChange = "";  // správa o úspechu alebo chybe

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePW'])) {
    // Ak je používateľ Google, nemôže meniť heslo
    if ($isGoogle) {
        $msgChange = "Tento účet je prihlásený cez Google, nie je možné meniť heslo.";
    } else {
        // Lokálne konto -> môžeme zmeniť heslo
        $newPassword  = trim($_POST['new_password'] ?? '');
        $newPassword2 = trim($_POST['new_password2'] ?? '');

        if ($newPassword === '' || $newPassword2 === '') {
            $msgChange = "Nové heslo a zopakovanie hesla nesmú byť prázdne.";
        } elseif ($newPassword !== $newPassword2) {
            $msgChange = "Nové heslá sa nezhodujú.";
        } else {
            // OK, môžeme uložiť do DB
            $hashed = password_hash($newPassword, PASSWORD_ARGON2ID);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = :pw WHERE email = :email LIMIT 1");
            $stmtUpdate->bindValue(':pw', $hashed, PDO::PARAM_STR);
            $stmtUpdate->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);

            if ($stmtUpdate->execute()) {
                $msgChange = "Heslo bolo úspešne zmenené.";
            } else {
                $msgChange = "Nepodarilo sa zmeniť heslo.";
            }
        }
    }
}

?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Zmena hesla</title>
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
        .alert {
            margin-top: 1em;
        }
    </style>
</head>
<body>

<!-- Navigačný bar -->
<nav>
    <div class="navbar-container">
        <div class="navbar-title">Zmena hesla</div>
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
                  <h3>Zmena hesla</h3>
              </div>
              <div class="card-body">
                  <!-- Zobrazíme prípadnú správu (o úspechu / neúspechu zmeny hesla) -->
                  <?php if (!empty($msgChange)): ?>
                      <div class="alert alert-info">
                          <?php echo htmlspecialchars($msgChange); ?>
                      </div>
                  <?php endif; ?>

                  <?php if ($isGoogle): ?>
                      <!-- Ak je používateľ Google, zobrazíme len správu -->
                      <div class="alert alert-warning">
                          Tento účet je prihlásený cez Google, nie je možné meniť heslo.
                      </div>
                  <?php else: ?>
                      <!-- Ak je lokálny účet, zobrazíme formuláre -->
                      
                      <!-- FORM na zmenu hesla (len pre nové heslo) 
                           - Bude zobrazený až po úspešnom overení starého hesla (cez JavaScript).
                      -->
                      <form method="post" id="changePWForm" style="display: none;">
                          <input type="hidden" name="changePW" value="1">

                          <div class="mb-3">
                              <label for="new_password" class="form-label">Nové heslo:</label>
                              <input type="password" name="new_password" id="new_password" class="form-control">
                          </div>
                          <div class="mb-3">
                              <label for="new_password2" class="form-label">Zopakovať nové heslo:</label>
                              <input type="password" name="new_password2" id="new_password2" class="form-control">
                          </div>
                          <button type="submit" class="btn btn-primary">Zmeniť heslo</button>
                      </form>

                      <!-- FORM pre overenie starého hesla (AJAX) -->
                      <div class="mb-3" id="verifyOldPWGroup">
                          <label for="old_password" class="form-label">Staré heslo:</label>
                          <input type="password" id="old_password" class="form-control">
                          <button type="button" id="verifyOldBtn" class="btn btn-secondary mt-3">
                              Overiť staré heslo
                          </button>
                          <div id="verifyOldMsg" class="alert alert-danger mt-2" style="display: none;"></div>
                      </div>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- Načítanie Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ak je Google účet, netreba nič, formulár je skrytý a je tam len varovanie.
    // Ak je lokálny účet, povolíme overenie starého hesla:
    const verifyOldPWGroup = document.getElementById('verifyOldPWGroup');
    if (!verifyOldPWGroup) {
        // ak neexistuje, zrejme je google => nič nerobíme
        return;
    }

    const verifyOldBtn     = document.getElementById('verifyOldBtn');
    const oldPWInput       = document.getElementById('old_password');
    const verifyOldMsg     = document.getElementById('verifyOldMsg');

    const changePWForm     = document.getElementById('changePWForm');

    verifyOldBtn.addEventListener('click', function() {
        verifyOldMsg.style.display = 'none';
        verifyOldMsg.textContent = '';

        const oldPWVal = oldPWInput.value.trim();
        if (!oldPWVal) {
            verifyOldMsg.textContent = 'Prosím, zadajte staré heslo.';
            verifyOldMsg.style.display = 'block';
            return;
        }

        // Pošleme AJAX požiadavku: resetPW.php?ajax=verifyOldPW
        fetch("resetPW.php?ajax=verifyOldPW", {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'old_password=' + encodeURIComponent(oldPWVal)
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                // Staré heslo je správne -> skryjeme verifyOldPWGroup, zobrazíme changePWForm
                verifyOldPWGroup.style.display = 'none';
                changePWForm.style.display = 'block';
            } else {
                // Nesprávne staré heslo alebo iná chyba
                verifyOldMsg.textContent = data.message || 'Staré heslo nie je správne.';
                verifyOldMsg.style.display = 'block';
            }
        })
        .catch(err => {
            verifyOldMsg.textContent = 'Chyba: ' + err;
            verifyOldMsg.style.display = 'block';
        });
    });
});
</script>
</body>
</html>
