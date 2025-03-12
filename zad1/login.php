<?php
session_start();

// Ak je užívateľ prihlásený, presmeruj ho na restricted.php.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: restricted.php");
    exit;
}

require_once "config.php";
require_once 'vendor/autoload.php';
require_once 'utilities.php';

use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

// Zapneme ERRMODE_EXCEPTION, aby sme videli chyby pri DB operáciách.
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// URL pre prihlásenie pomocou Google OAuth 2.0
$redirect_uri = "https://node73.webte.fei.stuba.sk/zad1/oauth2callback.php";

// Premenná, do ktorej uložíme typ prihlásenia.
$loginType = 'local';  // predvolene predpokladáme lokálne prihlásenie

// Skontrolujeme, či session hovorí, že ide o Google login
if (isset($_SESSION['google_login']) && $_SESSION['google_login'] === true) {
    $loginType = 'google';
}

$errors = "";

// Ak je to Google login, spracujeme ho
if ($loginType === 'google') {
    // Predpoklad: v session máme google_user_id, google_email, google_name
    // (nastavil si to v oauth2callback.php)
    if (!isset($_SESSION['google_user_id'], $_SESSION['google_email'], $_SESSION['google_name'])) {
        $errors = "Chýbajú údaje z Google OAuth. Prihlásenie zlyhalo.";
    } else {
        // Tu môžeme napr. vyhľadať, či user existuje v DB, prípadne ho vytvoriť
        // Na ukážku predpokladáme, že user existuje v DB a $_SESSION['google_user_id'] = users.id

        // Vložíme do tabuľky users_login
        try {
            $sqlLogin = "
                INSERT INTO users_login (user_id, login_type, email, fullname)
                VALUES (:user_id, :login_type, :email, :fullname)
            ";
            $stmtLogin = $pdo->prepare($sqlLogin);
            $stmtLogin->bindValue(':user_id', $_SESSION['google_user_id'], PDO::PARAM_INT);
            $stmtLogin->bindValue(':login_type', 'google', PDO::PARAM_STR);
            $stmtLogin->bindValue(':email', $_SESSION['google_email'], PDO::PARAM_STR);
            $stmtLogin->bindValue(':fullname', $_SESSION['google_name'], PDO::PARAM_STR);
            $stmtLogin->execute();

            // Nastavíme session pre bežné prihlásenie
            $_SESSION["loggedin"] = true;
            $_SESSION["fullname"] = $_SESSION['google_name'];
            $_SESSION["email"] = $_SESSION['google_email'];
            $_SESSION["created_at"] = date('Y-m-d H:i:s'); // Alebo z DB

            // Pre istotu zmažeme google_login z session, aby sme to nespúšťali znovu
            unset($_SESSION['google_login'], $_SESSION['google_user_id'], $_SESSION['google_email'], $_SESSION['google_name']);

            // Presmerujeme
            header("location: restricted.php");
            exit;
        } catch (PDOException $e) {
            $errors = "Chyba pri ukladaní Google loginu: " . $e->getMessage();
        }
    }
}
// Inak (ak $loginType === 'local'), spracujeme lokálne prihlásenie cez POST
else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sql = "SELECT id, fullname, email, password, 2fa_code, created_at FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":email", $_POST["email"], PDO::PARAM_STR);

    if ($stmt->execute()) {
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            $hashed_password = $row["password"];

            if (password_verify($_POST['password'], $hashed_password)) {
                // Heslo je správne, overíme 2FA
                $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());
                if ($tfa->verifyCode($row["2fa_code"], $_POST['2fa'], 2)) {
                    // Prihlasovacie údaje sú správne – ulož do session
                    $_SESSION["loggedin"] = true;
                    $_SESSION["fullname"] = $row['fullname'];
                    $_SESSION["email"] = $row['email'];
                    $_SESSION["created_at"] = $row['created_at'];

                    // Vloženie do tabuľky users_login
                    try {
                        $sqlLogin = "
                            INSERT INTO users_login (user_id, login_type, email, fullname)
                            VALUES (:user_id, :login_type, :email, :fullname)
                        ";
                        $stmtLogin = $pdo->prepare($sqlLogin);
                        $stmtLogin->bindValue(':user_id', $row['id'], PDO::PARAM_INT);
                        $stmtLogin->bindValue(':login_type', 'local', PDO::PARAM_STR);
                        $stmtLogin->bindValue(':email', $row['email'], PDO::PARAM_STR);
                        $stmtLogin->bindValue(':fullname', $row['fullname'], PDO::PARAM_STR);
                        $stmtLogin->execute();
                    } catch (PDOException $e) {
                        $errors = "Chyba pri ukladaní local loginu: " . $e->getMessage();
                    }

                    // Presmerovanie na restricted.php
                    if (empty($errors)) {
                        header("location: restricted.php");
                        exit;
                    }
                } else {
                    $errors = "Neplatný 2FA kód.";
                }
            } else {
                $errors = "Nesprávne meno alebo heslo.";
            }
        } else {
            $errors = "Nesprávne meno alebo heslo.";
        }
    } else {
        $errors = "Ups. Niečo sa pokazilo...";
    }

    unset($stmt);
}

// Od tohto miesta je HTML kód
unset($pdo);
?>

<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prihlásenie</title>
    <!-- Načítanie Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
        }
        /* Navigačný bar zo details.php */
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
    <!-- Navigačný bar s použitým štýlom -->
    <nav>
        <div class="navbar-container">
            <div class="navbar-title">Názov stránky</div>
            <ul class="navbar-links">
                <li><a href="index.php">Zoznam laureátov</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hlavný obsah stránky -->
    <div class="container">
      <div class="row justify-content-center">
          <div class="col-md-6">
              <div class="card">
                  <div class="card-header text-center">
                      <h3>Prihlásenie</h3>
                      <p>Prihlásenie registrovaného používateľa</p>
                  </div>
                  <div class="card-body">
                      <?php if (!empty($errors)) { ?>
                          <div class="alert alert-danger"><?php echo $errors; ?></div>
                      <?php } ?>

                      <!-- FORMULÁR pre lokálne prihlásenie (heslo, 2FA).
                           Pri google logine sa tu reálne nič nevyplňuje -->
                      <form action="" method="post">
                          <div class="mb-3">
                              <label for="email" class="form-label">E-Mail:</label>
                              <input type="email" name="email" id="email" class="form-control">
                          </div>
                          <div class="mb-3">
                              <label for="password" class="form-label">Heslo:</label>
                              <input type="password" name="password" id="password" class="form-control">
                          </div>
                          <div class="mb-3">
                              <label for="2fa" class="form-label">2FA kód:</label>
                              <input 
                                  type="password" 
                                  name="2fa" 
                                  id="2fa" 
                                  class="form-control" 
                                  pattern="\d*" 
                                  inputmode="numeric"
                              >
                          </div>
                          <div class="d-grid gap-2">
                              <button type="submit" class="btn btn-primary">Prihlásiť sa</button>
                          </div>
                      </form>
                      <hr>
                      <p class="text-center">
                          Alebo sa prihláste pomocou 
                          <a href="<?php echo filter_var($redirect_uri, FILTER_SANITIZE_URL) ?>">Google konta</a>
                      </p>
                      <p class="text-center">
                          Nemáte vytvorené konto? <a href="register.php">Zaregistrujte sa tu.</a>
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
