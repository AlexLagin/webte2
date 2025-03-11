<?php
require_once("config.php");
$db = connectDatabase($hostname, $database, $username, $password);

$sql = "
    SELECT 
    l.id AS laureate_id,
    l.fullname,
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
GROUP BY 
    l.id,
    l.fullname,
    p.year,
    p.category,
    p.contrib_sk,
    p.contrib_en
ORDER BY 
    p.year,
    l.fullname

";

$stmt = $db->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Nobeloví laureáti</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
</head>
<body>
<h1>Prehľad Nobelových laureátov</h1>

<table id="laureatesTable" class="display">
    <thead>
    <tr>
        <th>Rok</th>
        <th>Kategória</th>
        <th>Meno</th>
        <th>Krajina</th>
        <th>Príspevok (SK)</th>
        <th>Príspevok (EN)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $row): ?>
        <tr>
            <td>
                <a href="detail.php?year=<?php echo urlencode($row['year']); ?>">
                    <?php echo htmlspecialchars($row['year']); ?>
                </a>
            </td>
            <td>
                <a href="detail.php?category=<?php echo urlencode($row['category']); ?>">
                    <?php echo htmlspecialchars($row['category']); ?>
                </a>
            </td>
            <td>
                <a href="detail.php?laureate_id=<?php echo urlencode($row['laureate_id']); ?>">
                    <?php echo htmlspecialchars($row['fullname']); ?>
                </a>
            </td>
            <td><?php echo htmlspecialchars($row['countries']); ?></td>
            <td><?php echo htmlspecialchars($row['contrib_sk']); ?></td>
            <td><?php echo htmlspecialchars($row['contrib_en']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        $('#laureatesTable').DataTable({
            "pageLength": 20
        });
    });
</script>
</body>
</html>