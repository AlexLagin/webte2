<?php
    require_once('index.php');
    function parseCSV($filename)
    {
        $handle = fopen($filename, "r");
        $data = array();
        while (($row = fgetcsv($handle, 0, ";")) !== FALSE) {
            $data[] = array_filter($row);
        }
        fclose($handle);
        unset($data[0]);
        return $data;
    }


    $csvData = parseCSV('csvs/nobel_v5.2_FYZ.csv');

    // Pre každý riadok v CSV zavoláme funkciu na vloženie záznamu
    foreach ($csvData as $row) {
        // CSV stĺpce (poradie podľa hlavičky):
        // 0 => prize_year, 1 => name, 2 => surname, 3 => sex, 4 => birth, 5 => death,
        // 6 => country, 7 => contribution-sk, 8 => contribution-en,
        // 9 => language-sk, 10 => language-en, 11 => genre-sk, 12 => genre-en

        list($prize_year, $name, $surname, $sex, $birth, $death, $country, $contrib_sk, $contrib_en) = $row;

        // Pre organizáciu nastavíme NULL; pre kategóriu nastavíme "Literature"
        $status = insertLaureateWithCountryAndPrize($db, $name, $surname, NULL, $sex, $birth, $death,
            $country, NULL, NULL, NULL, NULL,
            $contrib_sk, $contrib_en, $prize_year, "Chemistry");
        echo $status . "\n";
    }
