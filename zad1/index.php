<?php
require_once("config.php");
$db = connectDatabase($hostname, $database, $username, $password);

// Získanie filterov a parametrov stránkovania z GET
$yearFilter = isset($_GET['year']) ? $_GET['year'] : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$pageSizeParam = isset($_GET['pageSize']) ? $_GET['pageSize'] : '20';
$pageSize = ($pageSizeParam === 'all') ? 0 : intval($pageSizeParam);

// Príprava WHERE klauzuly pre filtery
$whereClauses = [];
$params = [];

if ($yearFilter !== '') {
    $whereClauses[] = "p.year = :year";
    $params[':year'] = $yearFilter;
}
if ($categoryFilter !== '') {
    $whereClauses[] = "p.category = :category";
    $params[':category'] = $categoryFilter;
}
$where = '';
if (!empty($whereClauses)) {
    $where = "WHERE " . implode(" AND ", $whereClauses);
}

// Zostavenie základného SQL dotazu (bez LIMIT)
$sql = "
    SELECT 
        l.id AS laureate_id,
        COALESCE(l.fullname, l.organisation) AS display_name,
        GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ', ') AS countries,
        p.year,
        p.category,
        p.contrib_sk,
        p.contrib_en
    FROM laureates l
    LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
    LEFT JOIN countries c ON lc.country_id = c.id
    LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
    LEFT JOIN prizes p ON lp.prize_id = p.id
    $where
    GROUP BY 
        l.id,
        display_name,
        p.year,
        p.category,
        p.contrib_sk,
        p.contrib_en
    ORDER BY 
        p.year,
        display_name
";

// Ak nie je zvolená možnosť "Všetky", pridáme stránkovanie
if ($pageSize > 0) {
    $offset = ($page - 1) * $pageSize;
    $sql .= " LIMIT :offset, :limit";
}

$stmt = $db->prepare($sql);
// Naviažeme filterové parametre
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
// Naviažeme parametre stránkovania, ak sa používajú
if ($pageSize > 0) {
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Spočítanie celkového počtu záznamov pre stránkovanie
$countSql = "
    SELECT COUNT(*) as total FROM (
        SELECT 
            l.id
        FROM laureates l
        LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
        LEFT JOIN countries c ON lc.country_id = c.id
        LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
        LEFT JOIN prizes p ON lp.prize_id = p.id
        $where
        GROUP BY 
            l.id,
            p.year,
            p.category,
            p.contrib_sk,
            p.contrib_en
    ) as subquery
";
$countStmt = $db->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalRecords = $totalResult['total'];
$totalPages = ($pageSize > 0) ? ceil($totalRecords / $pageSize) : 1;

// Získanie unikátnych hodnôt pre roky a kategórie (pre filtery)
$yearsSql = "SELECT DISTINCT p.year FROM prizes p ORDER BY p.year";
$yearsStmt = $db->query($yearsSql);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

$categoriesSql = "SELECT DISTINCT p.category FROM prizes p ORDER BY p.category";
$categoriesStmt = $db->query($categoriesSql);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nobeloví laureáti</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f5f5;
        }
        nav {
            background: linear-gradient(135deg, #2c3e50, #2f4254);
            color: #fff;
            padding: 10px 20px;
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
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 20px;
        }
        /* Nastavenie flex kontajnera pre všetky filterové prvky */
        #filters {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }
        #filters label {
            margin-right: 5px;
        }
        #filters select {
            margin-right: 20px;
        }
        #filters button {
            padding: 5px 10px;
        }
        /* Zabezpečíme, že combobox pre počet záznamov bude inline */
        #pageSizeContainer {
            display: inline-flex;
            align-items: center;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            border: 1px solid #000;
            background-color: #fff;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        #pagination {
            text-align: center;
            margin-top: 10px;
        }
        #pagination a, #pagination span {
            display: inline-block;
            margin: 0 5px;
            padding: 5px 10px;
            text-decoration: none;
            border: 1px solid #000;
        }
        #pagination span.current-page {
            background-color: #ddd;
            font-weight: bold;
        }
    </style>
</head>
<body>
<nav>
    <div class="navbar-container">
        <div class="navbar-title">Nobeloví laureáti</div>
        <ul class="navbar-links">
            <li><a href="index.php">Laureáti</a></li>
            <li><a href="login.php">Prihlásenie</a></li>
            <li><a href="register.php">Registrácia</a></li>
        </ul>
    </div>
</nav>
<div class="container">
    <form method="GET" id="filterForm">
        <div id="filters">
            <label for="yearFilter">Rok:</label>
            <select id="yearFilter" name="year">
                <option value="">Všetky</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo htmlspecialchars($year); ?>" <?php if ($year == $yearFilter) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($year); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="categoryFilter">Kategória:</label>
            <select id="categoryFilter" name="category">
                <option value="">Všetky</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($cat == $categoryFilter) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="pageSizeContainer">
                <label for="pageSizeSelect">Počet záznamov na stránku:</label>
                <select id="pageSizeSelect" name="pageSize">
                    <option value="20" <?php if($pageSizeParam == '20') echo 'selected'; ?>>20</option>
                    <option value="50" <?php if($pageSizeParam == '50') echo 'selected'; ?>>50</option>
                    <option value="100" <?php if($pageSizeParam == '100') echo 'selected'; ?>>100</option>
                    <option value="500" <?php if($pageSizeParam == '500') echo 'selected'; ?>>500</option>
                    <option value="all" <?php if($pageSizeParam == 'all') echo 'selected'; ?>>Všetky</option>
                </select>
            </div>
            <button type="submit">Filtrovať</button>
        </div>
    </form>
    <table id="laureatesTable">
        <thead>
            <tr>
                <th>Rok</th>
                <th>Kategória</th>
                <th>Meno / Organizácia</th>
                <th>Krajina</th>
                <th>Ocenenie (SK)</th>
                <th>Ocenenie (EN)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['year']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td>
                    <a href="details.php?laureate_id=<?php echo urlencode($row['laureate_id']); ?>">
                        <?php echo htmlspecialchars($row['display_name']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($row['countries']); ?></td>
                <td><?php echo htmlspecialchars($row['contrib_sk']); ?></td>
                <td><?php echo htmlspecialchars($row['contrib_en']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pageSize > 0 && $totalPages > 1): ?>
    <div id="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Predchádzajúca</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current-page"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Ďalšia</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
