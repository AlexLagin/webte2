<?php
require_once("config.php");
$db = connectDatabase($hostname, $database, $username, $password);

// Skontrolujeme, či bol zadaný parameter 'laureate_id'
if (!isset($_GET['laureate_id'])) {
    echo "Nebol zadaný žiadny ID laureáta.";
    exit;
}

// Získame ID z parametra v URL
$laureateId = $_GET['laureate_id'];

/*
    Príklad SQL dopytu, ktorý JOINuje prize_details (alias pd).
    Musíte mať stĺpce pd.language_en, pd.language_sk, pd.genre_en, pd.genre_sk.
*/
$sql = "
    SELECT
        l.id,
        l.fullname,
        l.birth_year,
        l.death_year,
        l.sex,
        GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ', ') AS countries,
        
        p.year,
        p.category,
        p.contrib_en,
        p.contrib_sk,
        
        pd.language_en,
        pd.language_sk,
        pd.genre_en,
        pd.genre_sk
        
    FROM laureates l
    LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
    LEFT JOIN countries c ON lc.country_id = c.id
    LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
    LEFT JOIN prizes p ON lp.prize_id = p.id
    LEFT JOIN prize_details pd ON p.details_id = pd.id
    
    WHERE l.id = :laureateId
    
    GROUP BY
        l.id,
        l.fullname,
        l.birth_year,
        l.death_year,
        l.sex,
        
        p.year,
        p.category,
        p.contrib_en,
        p.contrib_sk,
        
        pd.language_en,
        pd.language_sk,
        pd.genre_en,
        pd.genre_sk
        
    ORDER BY
        p.year,
        l.fullname
";

$stmt = $db->prepare($sql);
$stmt->bindParam(':laureateId', $laureateId, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zistíme, či aspoň jedna kategória je "Literatúra"
$hasLiterature = false;
foreach ($results as $row) {
    // Porovnáme s presnou hodnotou, prípadne to upravte na nižšie/vyššie
    if (strtolower($row['category']) === 'literatúra') {
        $hasLiterature = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Detail laureáta</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            background-color: #f2f2f2;
            color: #333;
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

        .content-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .main-title {
            text-align: center;
            font-size: 2em;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .info-box {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-box h2 {
            margin: 0 0 10px;
            font-weight: 600;
        }
        .info-box p {
            margin: 6px 0;
        }
        .info-box p strong {
            width: 120px;
            display: inline-block;
        }
        h1.ocenenie-title {
            margin-top: 30px;
            margin-bottom: 10px;
            font-size: 1.5em;
            font-weight: 600;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background-color: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #eaeaea;
            font-weight: 600;
        }
        tbody tr:hover {
            background-color: #f2f2f2;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>

<!-- Navigačný bar -->
<nav>
    <div class="navbar-container">
        <div class="navbar-title">Nobelove stránky</div>
        <ul class="navbar-links">
            <li><a href="#">Laureáti</a></li>
            <li><a href="#">Prihlásenie</a></li>
            <li><a href="#">Registrácia</a></li>
        </ul>
    </div>
</nav>

<div class="content-container">
    <h1 class="main-title">Podrobnejšie údaje laureáta</h1>

    <?php if (count($results) === 0): ?>
        <p>Laureát s ID <strong><?php echo htmlspecialchars($laureateId); ?></strong> neexistuje.</p>
    <?php else: ?>
        <?php
            // Zobrazíme osobné údaje z prvého záznamu
            $firstRow = $results[0];
            $fullname    = $firstRow['fullname'];
            $countries   = $firstRow['countries'];
            $birthYear   = $firstRow['birth_year'];
            $deathYear   = $firstRow['death_year'];
            $gender      = $firstRow['sex'];
        ?>

        <div class="info-box">
            <h2><?php echo htmlspecialchars($fullname); ?></h2>
            <p><strong>Krajina:</strong> <?php echo htmlspecialchars($countries); ?></p>
            <p><strong>Rok narodenia:</strong>
                <?php echo $birthYear ? htmlspecialchars($birthYear) : '—'; ?>
            </p>
            <p><strong>Rok úmrtia:</strong>
                <?php echo $deathYear ? htmlspecialchars($deathYear) : '—'; ?>
            </p>
            <p><strong>Pohlavie:</strong>
                <?php echo $gender ? htmlspecialchars($gender) : '—'; ?>
            </p>
        </div>

        <h1 class="ocenenie-title">Ocenenie</h1>

        <table>
            <thead>
                <tr>
                    <th>Rok</th>
                    <th>Kategória</th>
                    <th>Príspevok (EN)</th>
                    <th>Príspevok (SK)</th>

                    <!-- IBA ak existuje aspoň 1 "Literatúra" -->
                    <?php if ($hasLiterature): ?>
                        <th>Jazyk (EN)</th>
                        <th>Jazyk (SK)</th>
                        <th>Žáner (EN)</th>
                        <th>Žáner (SK)</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['year']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['contrib_en']); ?></td>
                    <td><?php echo htmlspecialchars($row['contrib_sk']); ?></td>

                    <!-- IBA ak existuje aspoň 1 "Literatúra", zobrazíme stĺpce. -->
                    <?php if ($hasLiterature): ?>
                        <!-- Ak daný riadok NIE je Literatúra, dáme pomlčku, inak skutočné údaje -->
                        <?php if (strtolower($row['category']) === 'literatúra'): ?>
                            <td><?php echo htmlspecialchars($row['language_en']); ?></td>
                            <td><?php echo htmlspecialchars($row['language_sk']); ?></td>
                            <td><?php echo htmlspecialchars($row['genre_en']); ?></td>
                            <td><?php echo htmlspecialchars($row['genre_sk']); ?></td>
                        <?php else: ?>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                        <?php endif; ?>
                    <?php endif; ?>

                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>
</body>
</html>
