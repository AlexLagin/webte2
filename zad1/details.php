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

// Upravený dopyt, kde pridávame l.birth_year, l.death_year a l.gender (pohlavie)
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
        p.contrib_sk
    FROM laureates l
    LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
    LEFT JOIN countries c ON lc.country_id = c.id
    LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
    LEFT JOIN prizes p ON lp.prize_id = p.id
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
        p.contrib_sk
    ORDER BY
        p.year,
        l.fullname
";

$stmt = $db->prepare($sql);
$stmt->bindParam(':laureateId', $laureateId, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Detail laureáta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            margin-bottom: 10px;
        }
        .info-box {
            /* border: 1px solid #ccc;  ak nechcete žiadne orámovanie, nechajte zakomentované alebo vymazané */
            padding: 10px;
            margin-bottom: 20px;
        }
        .info-box h2 {
            margin: 0 0 5px;
        }
        .info-box p {
            margin: 5px 0;
        }
        /* Štýly pre tabuľku ocenení */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #eee;
        }
    </style>
</head>
<body>

<h1>Detail laureáta</h1>

<?php if (count($results) === 0): ?>
    <p>Laureát s ID <strong><?php echo htmlspecialchars($laureateId); ?></strong> neexistuje.</p>
<?php else: ?>

    <?php
        // Predpokladáme, že aspoň prvý záznam existuje
        $firstRow = $results[0];

        // Osobné údaje zobrazíme len raz (meno, krajina, rok narodenia, rok úmrtia, pohlavie)
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
            <?php
                echo $birthYear ? htmlspecialchars($birthYear) : '—';
            ?>
        </p>
        <p><strong>Rok úmrtia:</strong>
            <?php
                echo $deathYear ? htmlspecialchars($deathYear) : '—';
            ?>
        </p>
        <p><strong>Pohlavie:</strong>
            <?php
                echo $gender ? htmlspecialchars($gender) : '—';
            ?>
        </p>
    </div>

    <!-- Tabuľka "Ocenenie" -->
    <h1>Ocenenie</h1>
    <table>
        <thead>
            <tr>
                <th>Rok</th>
                <th>Kategória</th>
                <th>Príspevok (EN)</th>
                <th>Príspevok (SK)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['year']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['contrib_en']); ?></td>
                    <td><?php echo htmlspecialchars($row['contrib_sk']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

</body>
</html>
