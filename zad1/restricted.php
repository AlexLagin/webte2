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

    // Uložíme do session
    $_SESSION['email'] = $account_info->email;
    // Pozor: Google meno (napr. "John Doe") nie je to isté ako v DB "users".
    // Môžete ho však uložiť do session, ak ho chcete používať.
    $_SESSION['gid'] = $account_info->id;
}

// -----------------------------------------------------------
// 1) Načítanie mena a dátumu vytvorenia konta z tabuľky "users"
// -----------------------------------------------------------
$userFullname = "";
$userCreatedAt = "";
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
        $userFullname = $rowUser['fullname'];
        $userCreatedAt = $rowUser['created_at'];
    }
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

// TODO: Možnosť dočasne vypnúť alebo resetovať 2FA
// TODO: Možnosť resetovať heslo

?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0,
                   maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Zabezpečená stránka</title>
    <style>
        html {
            max-width: 70ch;
            padding: 3em 1em;
            margin: auto;
            line-height: 1.75;
            font-size: 1.25em;
        }
        h1,h2,h3,h4,h5,h6 {
            margin: 3em 0 1em;
        }
        p, ul, ol {
            margin-bottom: 2em;
            color: #1d1d1d;
            font-family: sans-serif;
        }
        table {
            border-collapse: collapse;
            margin-bottom: 2em;
            width: 100%;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 0.5em 1em;
            text-align: left;
        }
        span, .err {
            color: red;
        }
    </style>
</head>

<body>
<header>
    <hgroup>
        <h1>Zabezpečená stránka</h1>
        <h2>Obsah tejto stránky je dostupný len po prihlásení.</h2>
    </hgroup>
</header>

<main>
    <?php if (!empty($userFullname)): ?>
        <h3>Vitaj, <?php echo htmlspecialchars($userFullname); ?></h3>
    <?php else: ?>
        <h3>Vitaj, neznámy používateľ</h3>
    <?php endif; ?>

    <!-- Vypíšeme e-mail (z DB alebo session) -->
    <p><strong>E-mail:</strong>
        <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Neznámy e-mail'; ?>
    </p>

    <!-- Vypíšeme "Dátum vytvorenia konta" z tabuľky "users" -->
    <?php if (!empty($userCreatedAt)): ?>
        <p><strong>Dátum vytvorenia konta:</strong> <?php echo htmlspecialchars($userCreatedAt); ?></p>
    <?php endif; ?>

    <!-- História prihlásení z DB (tabuľka "users_login" -> login_time) -->
    <?php if (!empty($loginHistory)): ?>
        <h4>História prihlásení:</h4>
        <table>
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
    <?php else: ?>
        <p>Ešte nemáte žiadnu históriu prihlásení.</p>
    <?php endif; ?>

    <p>
        <a href="logout.php">Odhlásenie</a> alebo
        <a href="index.php">Úvodná stránka</a>
    </p>
</main>
</body>
</html>
