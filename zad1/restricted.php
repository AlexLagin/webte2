<?php
session_start();

// Over, či je používateľ prihlásený
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';
require_once 'vendor/autoload.php';

use Google\Client;

// Inicializácia Google Clienta, ak používate Google OAuth
$client = new Client();
$client->setAuthConfig('../../client_secret.json');

// Ak máme v session Google access_token, nastavíme ho do Clienta
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);

    // Získanie info o používateľovi z Google OAuth
    $oauth = new Google\Service\Oauth2($client);
    $account_info = $oauth->userinfo->get();

    // Uložíme do session (napr. ak by ste chceli využívať Google meno)
    $_SESSION['email'] = $account_info->email;
    $_SESSION['gid']   = $account_info->id;
}

// -----------------------------------------------------------
// Ak používateľ odoslal formulár na zmenu mena a priezviska
// -----------------------------------------------------------
$msgUpdate = "";  // správa o úspechu/chybe
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'updateName') {
    $first = trim($_POST['firstname'] ?? '');
    $last  = trim($_POST['lastname'] ?? '');

    if ($first === '' || $last === '') {
        $msgUpdate = "Meno a priezvisko nesmú byť prázdne.";
    } else {
        // Spojíme do jedného reťazca
        $newFullName = $first . ' ' . $last;

        // UPDATE v tabuľke "users"
        $sqlUpdate = "UPDATE users SET fullname = :fullname WHERE email = :email LIMIT 1";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindValue(':fullname', $newFullName, PDO::PARAM_STR);
        $stmtUpdate->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);

        if ($stmtUpdate->execute()) {
            // Po úspešnej zmene v 'users' zmeníme aj 'fullname' v 'users_login'
            $sqlUpdateLogin = "UPDATE users_login SET fullname = :fullname WHERE email = :email";
            $stmtUpdateLogin = $pdo->prepare($sqlUpdateLogin);
            $stmtUpdateLogin->bindValue(':fullname', $newFullName, PDO::PARAM_STR);
            $stmtUpdateLogin->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);
            $stmtUpdateLogin->execute();

            $msgUpdate = "Meno a priezvisko boli úspešne zmenené.";
        } else {
            $msgUpdate = "Nepodarilo sa zmeniť meno a priezvisko.";
        }
    }
}

// -----------------------------------------------------------
// 1) Načítanie mena a dátumu vytvorenia konta z tabuľky "users"
// -----------------------------------------------------------
$userFullname   = "";
$userCreatedAt  = "";
if (!empty($_SESSION['email'])) {
    $sqlUser = "
        SELECT fullname, created_at
        FROM users
        WHERE email = :email
        LIMIT 1
    ";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmtUser->execute();
    $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($rowUser) {
        $userFullname  = $rowUser['fullname'];
        $userCreatedAt = $rowUser['created_at'];
    }
}

// Rozdelíme "fullname" na meno a priezvisko (jednoduché split na prvú medzeru)
$userFirstName = "";
$userLastName  = "";
if (!empty($userFullname)) {
    // explode s limit=2, aby sme rozdelili len na 2 časti
    $parts = explode(' ', $userFullname, 2);
    $userFirstName = $parts[0] ?? "";
    $userLastName  = $parts[1] ?? "";
}

// -----------------------------------------------------------
// 2) Načítanie histórie prihlásení z tabuľky "users_login"
// -----------------------------------------------------------
$loginHistory = [];
if (!empty($_SESSION['email'])) {
    $sqlLog = "
        SELECT login_type, email, fullname, login_time
        FROM users_login
        WHERE email = :email
        ORDER BY login_time DESC
    ";
    $stmtLog = $pdo->prepare($sqlLog);
    $stmtLog->bindValue(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmtLog->execute();
    $loginHistory = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Zabezpečená stránka</title>
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
        .table thead th {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>

<!-- Navigačný bar s použitím rovnakého štýlu -->
<nav>
    <div class="navbar-container">
        <div class="navbar-title">Zabezpečená stránka</div>
        <ul class="navbar-links">
            <li><a href="index.php">Úvodná stránka</a></li>
        </ul>
    </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
      <div class="col-md-10">
          <div class="card">
              <div class="card-header text-center">
                  <h3>Obsah dostupný len po prihlásení</h3>
              </div>
              <div class="card-body">

                  <!-- Ak bol heslo úspešne zmenené, zobrazíme hlásenie -->
                  <?php if (isset($_GET['pw']) && $_GET['pw'] === 'changed'): ?>
                      <div class="alert alert-success">Heslo bolo úspešne zmenené.</div>
                  <?php endif; ?>

                  <!-- Zobrazenie mena z DB (prípadne fallback) -->
                  <?php if (!empty($userFullname)): ?>
                      <h4>Vitaj, <?php echo htmlspecialchars($userFullname); ?>!</h4>
                  <?php else: ?>
                      <h4>Vitaj, neznámy používateľ</h4>
                  <?php endif; ?>

                  <!-- Vypíšeme e-mail -->
                  <p>
                      <strong>E-mail:</strong>
                      <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Neznámy e-mail'; ?>
                  </p>

                  <!-- Dátum vytvorenia konta -->
                  <?php if (!empty($userCreatedAt)): ?>
                      <p><strong>Dátum vytvorenia konta:</strong> 
                         <?php echo htmlspecialchars($userCreatedAt); ?>
                      </p>
                  <?php endif; ?>

                  <hr>
                  <!-- Formulár na zmenu mena a priezviska -->
                  <h5>Zmena mena a priezviska</h5>

                  <!-- Zobrazíme prípadnú správu (o úspechu alebo chybe) -->
                  <?php if (!empty($msgUpdate)): ?>
                      <div class="alert alert-info"><?php echo htmlspecialchars($msgUpdate); ?></div>
                  <?php endif; ?>

                  <form action="" method="post" class="row g-3 mb-4">
                      <input type="hidden" name="action" value="updateName">

                      <div class="col-md-6">
                          <label for="firstname" class="form-label">Meno:</label>
                          <input 
                              type="text" 
                              class="form-control"
                              id="firstname" 
                              name="firstname"
                              value="<?php echo htmlspecialchars($userFirstName); ?>"
                          >
                      </div>
                      <div class="col-md-6">
                          <label for="lastname" class="form-label">Priezvisko:</label>
                          <input 
                              type="text" 
                              class="form-control"
                              id="lastname" 
                              name="lastname"
                              value="<?php echo htmlspecialchars($userLastName); ?>"
                          >
                      </div>
                      <div class="col-12">
                          <button type="submit" class="btn btn-primary">Uložiť</button>
                      </div>
                  </form>

                  <!-- História prihlásení z DB (login_time) -->
                  <?php if (!empty($loginHistory)): ?>
                      <hr>
                      <h5>História prihlásení</h5>
                      <div class="table-responsive">
                          <table class="table table-bordered table-striped">
                              <thead>
                              <tr>
                                  <th>Typ prihlásenia</th>
                                  <th>E-mail</th>
                                  <th>Celé meno</th>
                                  <th>Dátum a čas prihlásenia</th>
                              </tr>
                              </thead>
                              <tbody>
                              <?php foreach ($loginHistory as $row): ?>
                                  <tr>
                                      <td><?php echo htmlspecialchars($row['login_type']); ?></td>
                                      <td><?php echo htmlspecialchars($row['email']); ?></td>
                                      <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                      <td><?php echo htmlspecialchars($row['login_time']); ?></td>
                                  </tr>
                              <?php endforeach; ?>
                              </tbody>
                          </table>
                      </div>
                  <?php else: ?>
                      <p>Ešte nemáte žiadnu históriu prihlásení.</p>
                  <?php endif; ?>

                  <hr>
                  <!-- Tlačidlá: Odhlásenie, Zmena hesla, Reset 2FA -->
                  <p>
                      <a href="logout.php" class="btn btn-danger">Odhlásenie</a>
                      <a href="resetPW.php" class="btn btn-secondary">Zmena hesla</a>
                      <!-- Nový button na reset 2FA -->
                      <a href="reset2FA.php" class="btn btn-warning">Reset 2FA</a>
                  </p>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- Načítanie Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
