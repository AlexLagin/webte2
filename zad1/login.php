<?php
session_start();

// Ak je používateľ prihlásený, presmeruj ho na restricted.php.
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

// -----------------------------------------------------------------------------
// 1) AJAX kontrola používateľa (podľa e-mailu)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'checkUser') {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($_GET['email'] ?? '');
    if (empty($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'E-mail nie je zadaný.'
        ]);
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmtCheck->bindParam(":email", $email, PDO::PARAM_STR);
    $stmtCheck->execute();
    if ($stmtCheck->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => ''
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => ''
        ]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// 2) AJAX kontrola emailu a hesla (verifyLogin)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'verifyLogin') {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Email a heslo musia byť vyplnené.'
        ]);
        exit;
    }

    $stmtVerify = $pdo->prepare("SELECT id, password FROM users WHERE email = :email LIMIT 1");
    $stmtVerify->bindParam(":email", $email, PDO::PARAM_STR);
    $stmtVerify->execute();

    // Ak používateľ s daným e-mailom existuje, skontrolujeme heslo
    if ($stmtVerify->rowCount() == 1) {
        $row = $stmtVerify->fetch();
        if (password_verify($password, $row['password'])) {
            // Úspech
            echo json_encode([
                'success' => true, 
                'message' => 'Email a heslo sú správne.'
            ]);
        } else {
            // Nesprávne heslo
            echo json_encode([
                'success' => false, 
                'message' => 'Nesprávne meno alebo heslo.'
            ]);
        }
    } else {
        // Neexistujúci email
        echo json_encode([
            'success' => false, 
            'message' => 'Nesprávne meno alebo heslo.'
        ]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// URL pre prihlásenie pomocou Google OAuth 2.0
$redirect_uri = "https://node73.webte.fei.stuba.sk/zad1/oauth2callback.php";

// Premenná, do ktorej uložíme typ prihlásenia (local alebo google)
$loginType = 'local';
if (isset($_SESSION['google_login']) && $_SESSION['google_login'] === true) {
    $loginType = 'google';
}

// Správa o chybách pri POST spracovaní (final overenie s 2FA)
$errors = "";

// -----------------------------------------------------------------------------
// 3) Spracovanie POST požiadavky (final prihlásenie + 2FA)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sql = "SELECT id, fullname, email, password, 2fa_code, created_at FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":email", $_POST["email"], PDO::PARAM_STR);

    if ($stmt->execute()) {
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            $hashed_password = $row["password"];

            // Overenie hesla (ešte raz pre istotu)
            if (password_verify($_POST['password'], $hashed_password)) {
                // Overíme 2FA kód
                $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());
                if ($tfa->verifyCode($row["2fa_code"], $_POST['2fa'], 2)) {
                    // Úspešné prihlásenie
                    $_SESSION["loggedin"] = true;
                    $_SESSION["fullname"] = $row['fullname'];
                    $_SESSION["email"] = $row['email'];
                    $_SESSION["created_at"] = $row['created_at'];

                    // Logovanie prihlásenia do users_login
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

                    // Presmerovanie, ak neboli chyby
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
            <div class="navbar-title">Prihlásenie</div>
            <ul class="navbar-links">
                <li><a href="index.php">Zoznam laureátov</a></li>
            </ul>
        </div>
    </nav>

    <!-- MODAL o cookies -->
    <div class="modal fade" id="cookiesModal" tabindex="-1" aria-labelledby="cookiesModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="cookiesModalLabel">Informácia o cookies</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavrieť"></button>
          </div>
          <div class="modal-body">
            Táto stránka používa cookies na zabezpečenie plnej funkčnosti a zlepšenie vášho zážitku.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Rozumiem</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Koniec MODAL -->

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
                      <!-- PHP chyby z finálneho POST spracovania (napr. zlý 2FA) -->
                      <?php if (!empty($errors)) { ?>
                          <div class="alert alert-danger"><?php echo $errors; ?></div>
                      <?php } ?>

                      <!-- Div pre AJAX chyby (napr. zle zadaný email/heslo pri verifyLogin) -->
                      <div class="alert alert-danger" id="ajaxErrorMsg" style="display: none;"></div>

                      <!-- FORMULÁR pre lokálne prihlásenie -->
                      <form action="" method="post" id="loginForm">
                          <div class="mb-3">
                              <label for="email" class="form-label">E-Mail:</label>
                              <input 
                                  type="email" 
                                  name="email" 
                                  id="email" 
                                  class="form-control"
                              >
                              <div id="userCheckMessage" style="margin-top: 5px; font-weight: 500;"></div>
                          </div>
                          <div class="mb-3">
                              <label for="password" class="form-label">Heslo:</label>
                              <input type="password" name="password" id="password" class="form-control">
                          </div>

                          <!-- Skrytá sekcia pre 2FA (viditeľná až po úspešnom overení emailu a hesla) -->
                          <div class="mb-3" id="twofaGroup" style="display: none;">
                              <label for="2fa" class="form-label">2FA kód:</label>
                              <!-- Tu je pole rovno type="text", aby sme vždy videli, čo píšeme -->
                              <input 
                                  type="text" 
                                  name="2fa" 
                                  id="2fa" 
                                  class="form-control" 
                                  pattern="\d*" 
                                  inputmode="numeric"
                              >
                          </div>

                          <!-- Tlačidlá: najprv Overiť email a heslo, potom Prihlásiť sa -->
                          <div class="d-grid gap-2">
                              <button type="button" class="btn btn-primary" id="verifyBtn">
                                  Overiť email a heslo
                              </button>
                              <button type="submit" class="btn btn-primary" id="loginBtn" style="display: none;">
                                  Prihlásiť sa
                              </button>
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
                      <!-- Nový text "Zabudnuté heslo?" -> redirect na forgotPW.php -->
                      <p class="text-center">
                          <a href="forgotPW.php">Zabudnuté heslo?</a>
                      </p>
                  </div>
              </div>
          </div>
      </div>
    </div>

    <!-- Načítanie Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript: zobrazenie modalu cookies, AJAX kontrola používateľa, overenie emailu a hesla, zabránenie skorému submitu -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Zobrazíme modal s cookies hneď po načítaní
        var cookiesModal = new bootstrap.Modal(document.getElementById('cookiesModal'), {
          keyboard: false
        });
        cookiesModal.show();

        const loginForm      = document.getElementById('loginForm');
        const emailInput     = document.getElementById('email');
        const userCheckMsg   = document.getElementById('userCheckMessage');
        const verifyBtn      = document.getElementById('verifyBtn');
        const twofaGroup     = document.getElementById('twofaGroup');
        const loginBtn       = document.getElementById('loginBtn');
        const ajaxErrorMsg   = document.getElementById('ajaxErrorMsg');
        const passwordInput  = document.getElementById('password');
        const twofaInput     = document.getElementById('2fa');

        // 1) Zabrániť submitu, ak 2FA ešte nie je zobrazené
        loginForm.addEventListener('submit', function(event) {
          if (twofaGroup.style.display === 'none') {
            event.preventDefault();  // Zrušíme odoslanie
            verifyBtn.click();       // Spustíme AJAX overenie emailu a hesla
          }
        });

        // 2) AJAX kontrola používateľa (email existuje / neexistuje)
        emailInput.addEventListener('blur', function() {
          const emailValue = emailInput.value.trim();
          if (!emailValue) {
            userCheckMsg.textContent = '';
            return;
          }
          fetch("login.php?ajax=checkUser&email=" + encodeURIComponent(emailValue))
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                if (data.exists) {
                  userCheckMsg.textContent = '';
                } else {
                  userCheckMsg.style.color = 'red';
                  userCheckMsg.textContent = 'Tento používateľ neexistuje.';
                }
              } else {
                userCheckMsg.style.color = 'red';
                userCheckMsg.textContent = data.message || 'Chyba pri kontrole používateľa.';
              }
            })
            .catch(err => {
              userCheckMsg.style.color = 'red';
              userCheckMsg.textContent = 'Chyba: ' + err;
            });
        });

        // 3) Po kliknutí na "Overiť email a heslo" (verifyLogin)
        verifyBtn.addEventListener('click', function() {
            const email    = emailInput.value.trim();
            const password = passwordInput.value.trim();

            // Skryjeme prípadnú starú chybu
            ajaxErrorMsg.style.display = 'none';

            if (email === '' || password === '') {
                ajaxErrorMsg.textContent = 'Prosím, zadajte email a heslo.';
                ajaxErrorMsg.style.display = 'block';
                return;
            }

            // AJAX požiadavka na overenie emailu a hesla v DB
            fetch("login.php?ajax=verifyLogin", {
               method: 'POST',
               headers: {
                 'Content-Type': 'application/x-www-form-urlencoded'
               },
               body: 'email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password)
            })
            .then(response => response.json())
            .then(data => {
               if (data.success) {
                  // Email a heslo sú správne => zobrazíme 2FA sekciu a zmeníme tlačidlá
                  twofaGroup.style.display = 'block';
                  verifyBtn.style.display = 'none';
                  loginBtn.style.display = 'block';
                  // Môžeme automaticky focusnúť 2FA input:
                  // twofaInput.focus();
               } else {
                  // Chyba - nesprávne meno/heslo
                  ajaxErrorMsg.textContent = data.message;
                  ajaxErrorMsg.style.display = 'block';
               }
            })
            .catch(err => {
               ajaxErrorMsg.textContent = 'Chyba: ' + err;
               ajaxErrorMsg.style.display = 'block';
            });
        });
      });
    </script>
</body>
</html>
