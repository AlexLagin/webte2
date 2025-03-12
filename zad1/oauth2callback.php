<?php
session_start();

require_once 'vendor/autoload.php';
require_once 'config.php';

use Google\Client;
use Google\Service\Oauth2;

$client = new Client();

// Načítanie Google Client Credentials
$client->setAuthConfig('../../client_secret.json');
$redirect_uri = "https://node73.webte.fei.stuba.sk/zad1/oauth2callback.php";
$client->setRedirectUri($redirect_uri);

// Rozsah oprávnení
$client->addScope(["email", "profile"]);
// Povolenie incremental authorization
$client->setIncludeGrantedScopes(true);
// Získanie refresh tokenu
$client->setAccessType("offline");

// 1) Ak nie je code ani error, presmeruj používateľa na Google OAuth login
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(16));
    $client->setState($state);
    $_SESSION['state'] = $state;

    // Vygeneruj URL pre Google OAuth a presmeruj
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
}

// 2) Ak je v URL 'error', zobraz chybu
if (isset($_GET['error'])) {
    echo "Error: " . htmlspecialchars($_GET['error']);
    exit;
}

// 3) Máme 'code' -> Google vrátil autorizačný kód
if (isset($_GET['code'])) {
    // Overenie CSRF (state)
    if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['state']) {
        die('State mismatch. Possible CSRF attack.');
    }

    // Vymeníme kód za access token (a prípadne refresh token)
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['access_token'])) {
        $client->setAccessToken($token['access_token']);
    } else {
        die("Nepodarilo sa získať access token z Google.");
    }

    // Uložíme tokeny do session
    $_SESSION['access_token'] = $token['access_token'];
    if (isset($token['refresh_token'])) {
        $_SESSION['refresh_token'] = $token['refresh_token'];
    }

    // Nastavíme vlastnú session premennú, že je používateľ prihlásený
    $_SESSION['loggedin'] = true;

    // 4) Získame info o používateľovi z Google
    $oauth2 = new Oauth2($client);
    $userinfo = $oauth2->userinfo->get();
    // Napr. name, email, picture, atď.
    $googleFullName = $userinfo->name;
    $googleEmail    = $userinfo->email;

    // 5) Uložíme používateľa do DB (iba fullname, email).
    //    password a 2fa_code necháme prázdne reťazce, aby nevyhodilo chybu pri NOT NULL stĺpcoch.
    try {
        
        $checkSql = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(":email", $googleEmail);
        $checkStmt->execute();

        $userId = null; 

        if ($checkStmt->rowCount() === 0) {
            
            $insertSql = "
                INSERT INTO users (fullname, email, password, 2fa_code)
                VALUES (:fullname, :email, '', '')
            ";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->bindValue(":fullname", $googleFullName);
            $insertStmt->bindValue(":email", $googleEmail);
            $insertStmt->execute();

            
            $userId = $pdo->lastInsertId();
        } else {
            
            $row = $checkStmt->fetch();
            $userId = $row['id'];
           
        }

        
        if (!empty($userId)) {
            $insertLoginSql = "
                INSERT INTO users_login (user_id, login_type, email, fullname)
                VALUES (:user_id, :login_type, :email, :fullname)
            ";
            $stmtLogin = $pdo->prepare($insertLoginSql);
            $stmtLogin->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $stmtLogin->bindValue(':login_type', 'google', \PDO::PARAM_STR);
            $stmtLogin->bindValue(':email', $googleEmail, \PDO::PARAM_STR);
            $stmtLogin->bindValue(':fullname', $googleFullName, \PDO::PARAM_STR);
            $stmtLogin->execute();
        }

        
        $_SESSION['fullname'] = $googleFullName;
        $_SESSION['email']    = $googleEmail;

        
        $redirect_uri = "https://node73.webte.fei.stuba.sk/zad1/restricted.php";
        header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
        exit;

    } catch (Exception $e) {
        die("Chyba pri ukladaní do DB: " . $e->getMessage());
    }
}
