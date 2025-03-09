<?php

require_once("config.php");

$db = connectDatabase($hostname, $database, $username, $password);

function processStatement($stmt) {
    if ($stmt->execute()) {
        return "Record inserted successfully.";
    } else {
        return "Error inserting record: " . implode(", ", $stmt->errorInfo());
    }
}

function insertLaureate($db, $fullname, $organisation, $sex, $birth_year, $death_year) {
    $stmt = $db->prepare("INSERT INTO laureates (fullname, organisation, sex, birth_year, death_year) VALUES (:fullname, :organisation, :sex, :birth_year, :death_year)");

    //$fullname = $name . " " . $surname;

    $stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
    $stmt->bindParam(':organisation', $organisation, PDO::PARAM_STR);
    $stmt->bindParam(':sex', $sex, PDO::PARAM_STR);
    $stmt->bindParam(':birth_year', $birth_year, PDO::PARAM_STR);
    $stmt->bindParam(':death_year', $death_year, PDO::PARAM_STR);

    return processStatement($stmt);
}

function insertCountry($db, $country_name) {
    $stmt = $db->prepare("INSERT INTO countries (country_name) VALUES (:country_name)");

    $stmt->bindParam(':country_name', $country_name, PDO::PARAM_STR);

    return processStatement($stmt);
}

function boundCountry($db, $laureate_id, $country_id) {
    $stmt = $db->prepare("INSERT INTO laureate_country (laureate_id, country_id) VALUES (:laureate_id, :country_id)");

    $stmt->bindParam(':laureate_id', $laureate_id, PDO::PARAM_INT);
    $stmt->bindParam(':country_id', $country_id, PDO::PARAM_INT);

    return processStatement($stmt);
}

function getLaureatesWithCountry($db) {
    $stmt = $db->prepare("
    SELECT laureates.fullname, laureates.sex, laureates.birth_year, laureates.death_year, countries.country_name 
    FROM laureates 
    LEFT JOIN laureate_country 
        INNER JOIN countries
        ON laureate_country.country_id = countries.id
    ON laureates.id = laureate_country.laureate_id");

    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $result;
}

function insertLaureateWithCountry($db, $name, $surname, $organisation, $sex, $birth_year, $death_year, $country_name) {
    $db->beginTransaction();

    $status = insertLaureate($db, $name, $surname, $organisation, $sex, $birth_year, $death_year);

    if (strpos($status, "Error") !== false) {
        $db->rollBack();
        return $status;
    }

    $laureate_id = $db->lastInsertId();

    $status = insertCountry($db, $country_name);

    if (strpos($status, "Error") !== false) {
        $db->rollBack();
        return $status;
    }

    $country_id = $db->lastInsertId();

    $status = boundCountry($db, $laureate_id, $country_id);

    if (strpos($status, "Error") !== false) {
        $db->rollBack();
        return $status;
    }

    $db->commit();


    return $status;
}

function insertPrize($db, $year, $category, $contrib_sk, $contrib_en, $details_id) {
    $stmt = $db->prepare("INSERT INTO prizes (year, category, contrib_sk, contrib_en, details_id)
                          VALUES (:year, :category, :contrib_sk, :contrib_en, :details_id)");
    $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $stmt->bindParam(':contrib_sk', $contrib_sk, PDO::PARAM_STR);
    $stmt->bindParam(':contrib_en', $contrib_en, PDO::PARAM_STR);
    $stmt->bindParam(':details_id', $details_id, PDO::PARAM_INT);
    if($stmt->execute()){
        return "Prize inserted successfully.";
    } else {
        return "Error inserting prize: " . implode(", ", $stmt->errorInfo());
    }
}

function insertPrizeDetails($db, $language_sk, $language_en, $genre_sk, $genre_en) {
    $stmt = $db->prepare("INSERT INTO prize_details (language_sk, language_en, genre_sk, genre_en)
                          VALUES (:language_sk, :language_en, :genre_sk, :genre_en)");
    $stmt->bindParam(':language_sk', $language_sk, PDO::PARAM_STR);
    $stmt->bindParam(':language_en', $language_en, PDO::PARAM_STR);
    $stmt->bindParam(':genre_sk', $genre_sk, PDO::PARAM_STR);
    $stmt->bindParam(':genre_en', $genre_en, PDO::PARAM_STR);
    if ($stmt->execute()) {
        return $db->lastInsertId();
    } else {
        return false;
    }
}



function boundPrize($db, $laureate_id, $prize_id) {
    $stmt = $db->prepare("INSERT INTO laureates_prizes (laureate_id, prize_id) VALUES (:laureate_id, :prize_id)");
    $stmt->bindParam(':laureate_id', $laureate_id, PDO::PARAM_INT);
    $stmt->bindParam(':prize_id', $prize_id, PDO::PARAM_INT);
    return processStatement($stmt);
}


function insertLaureateWithCountryAndPrize($db, $name, $surname, $organisation, $sex, $birth_year, $death_year,
                                           $country_name, $language_sk, $language_en, $genre_sk, $genre_en,
                                           $contrib_sk, $contrib_en, $year, $category) {
    // Začneme transakciu
    $db->beginTransaction();
                                            
    // Rozhodnutie, či ide o organizáciu alebo o osobu
    if ($organisation != NULL) {
        echo "\nCau\n";
        // Ak je vyplnená organizácia, ignorujeme meno a priezvisko
        $checkQuery = "SELECT id FROM laureates WHERE organisation = ?";
        $fullname = NULL;
        $stmt = $db->prepare($checkQuery);
        $stmt->execute([$organisation]);
    } else {
        // Ak nie je organizácia, berieme osobu podľa mena a priezviska
        $fullname = $name . " " . $surname; 
        $checkQuery = "SELECT id FROM laureates WHERE fullname = ?";
        $stmt = $db->prepare($checkQuery);
        $stmt->execute([$fullname]);
    }

    // Získame prípadný existujúci záznam
    $existingLaureate = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ak existuje, vezmeme jeho ID, inak ho vložíme
    if ($existingLaureate) {
        $laureate_id = $existingLaureate['id'];
    } else {
        // Zavoláme pôvodnú funkciu insertLaureate, ktorá vloží záznam do tabuľky laureates
        $status = insertLaureate($db, $fullname, $organisation, $sex, $birth_year, $death_year);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
        $laureate_id = $db->lastInsertId();
    }

    // Skontrolujeme, či už má tento laureát (osoba/organizácia) priradenú krajinu
    $checkCountryBindingQuery = "SELECT country_id FROM laureate_country WHERE laureate_id = ?";
    $stmt = $db->prepare($checkCountryBindingQuery);
    $stmt->execute([$laureate_id]);
    $existingCountryBinding = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ak ešte nie je priradená krajina, vložíme/vyberieme existujúcu krajinu a prepojíme
    if (!$existingCountryBinding) {
        $checkCountryQuery = "SELECT id FROM countries WHERE country_name = ?";
        $stmt = $db->prepare($checkCountryQuery);
        $stmt->execute([$country_name]);
        $existingCountry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCountry) {
            $country_id = $existingCountry['id'];
        } else {
            $status = insertCountry($db, $country_name);
            if (strpos($status, "Error") !== false) {
                $db->rollBack();
                return $status;
            }
            $country_id = $db->lastInsertId();
        }

        $status = boundCountry($db, $laureate_id, $country_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
    }

    // Skontrolujeme, či už existuje cena pre daného laureáta za daný rok a kategóriu
    $prizeBoundQuery = "SELECT p.id FROM prizes p
                        INNER JOIN laureates_prizes lp ON p.id = lp.prize_id
                        WHERE lp.laureate_id = ? AND p.category = ? AND p.year = ?";
    $stmt = $db->prepare($prizeBoundQuery);
    $stmt->execute([$laureate_id, $category, $year]);
    $existingPrize = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ak existuje, len aktualizujeme detaily
    if ($existingPrize) {
        $updatePrizeQuery = "UPDATE prizes
                             SET contrib_sk = ?, contrib_en = ?
                             WHERE id = ?";
        $updateStmt = $db->prepare($updatePrizeQuery);
        $updateStmt->execute([$contrib_sk, $contrib_en, $existingPrize['id']]);
        $prize_id = $existingPrize['id'];
    } else {
        // Ak neexistuje, vložíme nový záznam o cene
        $details_id = NULL; // Ak by ste potrebovali vkladať detailnejšie info, doplňte
        $status = insertPrize($db, $year, $category, $contrib_sk, $contrib_en, $details_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
        $prize_id = $db->lastInsertId();

        // Prepojíme cenu s laureátom
        $status = boundPrize($db, $laureate_id, $prize_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
    }

    // Ak všetko prebehlo v poriadku, potvrdíme transakciu
    $db->commit();

    echo "\nImport dokončený.\n";
    return $status;
}




//Example usage

//$status = insertLaureate($db, "Alex", "Doe", NULL, "M", "1918", "1999");
//$status = insertCountry($db, "United Kingdom");
//$status = boundCountry($db, 3, 1);

//$status = insertLaureateWithCountry($db, "Susane", "Doe", NULL, "F", "1922", "1999", "Germany");
//$status = getLaureatesWithCountry($db);