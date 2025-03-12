<?php
require_once("config.php");
$db = connectDatabase($hostname, $database, $username, $password);

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

$stmt = $db->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Získanie unikátnych hodnôt pre rok a kategóriu na filtrovanie
$years = [];
$categories = [];
foreach ($results as $row) {
    if (!in_array($row['year'], $years)) {
        $years[] = $row['year'];
    }
    if (!in_array($row['category'], $categories)) {
        $categories[] = $row['category'];
    }
}
sort($years);
sort($categories);
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nobeloví laureáti</title>
    <!-- Google Fonts (voliteľné) -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    
    <style>
        /* Vynulovanie okrajov tela */
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
        
        /* Obalovací kontajner pre hlavný obsah */
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        
        /* Navigačný bar */
        nav {
            background-color: #2c3e50;
            color: #fff;
            padding: 10px 20px;
        }
        nav .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        nav .navbar-title {
            font-size: 1.5em;
            font-weight: bold;
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
        }

        /* Centrálne zarovnanie nadpisu a filtrov */
        h1 {
            text-align: center;
            margin-top: 20px;
        }
        #filters {
            margin-bottom: 20px;
            text-align: center;
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

        /* Blok s comboboxom pre počet záznamov */
        #pageSizeContainer {
            margin: 0 20px 10px;
            text-align: left;
        }

        /* Tabuľka na 100% šírky */
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
        /* Štýly pre klikateľné hlavičky */
        th.sortable {
            cursor: pointer;
        }
        th.sortable .sort-indicator {
            margin-left: 5px;
            font-size: 0.8em;
        }

        /* Štýly pre stránkovanie */
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

<!-- Navigačný bar -->
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

<!-- Obalovací kontajner pre hlavný obsah -->
<div class="container">

    <!-- Filtrovanie (Rok, Kategória, Filtrovať) hore -->
    <div id="filters">
        <label for="yearFilter">Rok:</label>
        <select id="yearFilter">
            <option value="">Všetky</option>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="categoryFilter">Kategória:</label>
        <select id="categoryFilter">
            <option value="">Všetky</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>
        
        <button id="applyFilters">Filtrovať</button>
    </div>

    <!-- Blok s comboboxom pre počet záznamov, umiestnený nad tabuľkou, vľavo zarovnaný -->
    <div id="pageSizeContainer">
        <label for="pageSizeSelect">Počet záznamov na stránku:</label>
        <select id="pageSizeSelect">
            <option value="20" selected>20</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="500">500</option>
            <option value="all">Všetky</option>
        </select>
    </div>

    <!-- Tabuľka (na celú šírku) -->
    <table id="laureatesTable">
        <thead>
        <tr>
            <th class="yearColumn sortable">Rok <span class="sort-indicator"></span></th>
            <th class="categoryColumn sortable">Kategória <span class="sort-indicator"></span></th>
            <th class="sortable">Meno / Organizácia <span class="sort-indicator"></span></th>
            <th>Krajina</th>
            <th>Príspevok (SK)</th>
            <th>Príspevok (EN)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td class="yearColumn"><?php echo htmlspecialchars($row['year']); ?></td>
                <td class="categoryColumn"><?php echo htmlspecialchars($row['category']); ?></td>
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

    <!-- Kontrolné prvky stránkovania -->
    <div id="pagination"></div>
</div>

<!-- jQuery knižnica -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var currentPage = 1;
    // Globálna premenná, ktorá bude obsahovať všetky filtrované riadky
    var filteredRows = $('#laureatesTable tbody tr');

    // Funkcia, ktorá aplikuje filtrovanie na riadky tabuľky
    function updateFilters() {
        var selectedYear = $('#yearFilter').val();
        var selectedCategory = $('#categoryFilter').val();

        // Zobrazíme všetky riadky (pracujeme so všetkými údajmi)
        $('#laureatesTable tbody tr').show();

        // Pre každý riadok overíme, či zodpovedá vybraným filtrom
        $('#laureatesTable tbody tr').each(function() {
            var row = $(this);
            var year = row.find('td.yearColumn').text().trim();
            var category = row.find('td.categoryColumn').text().trim();
            var showRow = true;
            
            if (selectedYear && year !== selectedYear) {
                showRow = false;
            }
            if (selectedCategory && category !== selectedCategory) {
                showRow = false;
            }
            if (!showRow) {
                row.hide();
            }
        });

        // Skrytie príslušných stĺpcov, ak je filter nastavený
        if (selectedYear) {
            $('.yearColumn').hide();
        } else {
            $('.yearColumn').show();
        }
        if (selectedCategory) {
            $('.categoryColumn').hide();
        } else {
            $('.categoryColumn').show();
        }

        // Uložíme si všetky riadky, ktoré sú viditeľné po filtrovaní
        filteredRows = $('#laureatesTable tbody tr:visible');
        currentPage = 1;
        paginateTable();
    }

    // Funkcia pre stránkovanie s dynamickým rozsahom 5 strán
    // a zobrazením bodiek a poslednej stránky, ak aktuálny rozsah neobsahuje poslednú stránku
    function paginateTable() {
        var pageSize = $('#pageSizeSelect').val();
        if(pageSize === "all") {
            filteredRows.show();
            $('#pagination').html('');
            return;
        }
        
        var numericPageSize = parseInt(pageSize);
        var totalRows = filteredRows.length;
        var totalPages = Math.ceil(totalRows / numericPageSize);

        filteredRows.hide();
        filteredRows.slice((currentPage - 1) * numericPageSize, currentPage * numericPageSize).show();

        var paginationHtml = '';

        // Odkaz na predchádzajúcu stránku
        if(currentPage > 1) {
            paginationHtml += '<a href="#" class="page" data-page="'+ (currentPage - 1) +'">Predchádzajúca</a> ';
        }

        // Dynamicky vypočítame rozsah strán: aktuálna stránka so 2 pred a 2 za ňou
        var startPage = currentPage - 2;
        var endPage = currentPage + 2;
        if(startPage < 1) {
            startPage = 1;
            endPage = Math.min(5, totalPages);
        }
        if(endPage > totalPages) {
            endPage = totalPages;
            startPage = Math.max(1, totalPages - 4);
        }

        // Vypíšeme čísla strán od startPage do endPage
        for(var i = startPage; i <= endPage; i++){
            if(i === currentPage) {
                paginationHtml += '<span class="current-page">' + i + '</span> ';
            } else {
                paginationHtml += '<a href="#" class="page" data-page="'+ i +'">' + i + '</a> ';
            }
        }

        // Ak endPage nie je posledná stránka, pridáme bodky a odkaz na poslednú stránku
        if(endPage < totalPages) {
            paginationHtml += '......';
            paginationHtml += '<a href="#" class="page" data-page="'+ totalPages +'">' + totalPages + '</a> ';
        }

        // Odkaz na ďalšiu stránku
        if(currentPage < totalPages) {
            paginationHtml += '<a href="#" class="page" data-page="'+ (currentPage + 1) +'">Ďalšia</a>';
        }
        
        $('#pagination').html(paginationHtml);
    }

    // Funkcia pre zoradenie tabuľky podľa stĺpca
    function sortTableByColumn(columnIndex, ascending) {
        var rowsArray = filteredRows.get();
        rowsArray.sort(function(a, b) {
            var aText = $(a).find('td').eq(columnIndex).text().trim().toLowerCase();
            var bText = $(b).find('td').eq(columnIndex).text().trim().toLowerCase();
            return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });
        filteredRows = $(rowsArray);
        $('#laureatesTable tbody').append(filteredRows);
        paginateTable();
    }

    // Event handler pre kliknutie na klikateľné hlavičky (Rok, Kategória, Meno / Organizácia)
    $('#laureatesTable thead th.sortable').on('click', function() {
        // Odstránime všetky indikátory zoradenia
        $('#laureatesTable thead th.sortable .sort-indicator').html('');
        
        var columnIndex = $(this).index();
        var currentOrder = $(this).data('sort-order') || 'asc';
        var newOrder = (currentOrder === 'asc') ? 'desc' : 'asc';
        $(this).data('sort-order', newOrder);
        
        var indicator = newOrder === 'asc' ? '▲' : '▼';
        $(this).find('.sort-indicator').html(indicator);
        
        sortTableByColumn(columnIndex, newOrder === 'asc');
    });

    // Event handler pre tlačidlo "Filtrovať"
    $('#applyFilters').on('click', function() {
        updateFilters();
    });

    // Event handler pre zmenu počtu záznamov na stránku
    $('#pageSizeSelect').on('change', function() {
        currentPage = 1;
        paginateTable();
    });

    // Event handler pre stránkovacie odkazy
    $('#pagination').on('click', 'a.page', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'));
        paginateTable();
    });

    // Inicializácia – nastavíme filteredRows na všetky riadky a spustíme stránkovanie
    filteredRows = $('#laureatesTable tbody tr');
    paginateTable();
});
</script>
</body>
</html>
