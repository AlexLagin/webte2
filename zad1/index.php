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

function insertLaureate($db, $name, $surname, $organisation, $sex, $birth_year, $death_year) {
    $stmt = $db->prepare("INSERT INTO laureates (fullname, organisation, sex, birth_year, death_year) VALUES (:fullname, :organisation, :sex, :birth_year, :death_year)");

    $fullname = $name . " " . $surname;

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
    $db->beginTransaction();
    $fullname = $name . " " . $surname;
    // Skontrolujeme, či už existuje laureát podľa mena a priezviska
    $checkQuery = "SELECT id FROM laureates WHERE fullname= ? AND organisation = ?";
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([$fullname, $organisation]);
    $existingLaureate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingLaureate) {
        // Laureát už existuje, použijeme existujúce id
        $laureate_id = $existingLaureate['id'];
    } else {
        // Vložíme nového laureáta
        $status = insertLaureate($db, $name, $surname, $organisation, $sex, $birth_year, $death_year);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
        $laureate_id = $db->lastInsertId();
    }

    // Skontrolujeme, či už má daný laureát priradenú krajinu (napr. v prepojovacej tabuľke laureate_country)
    $checkCountryBindingQuery = "SELECT country_id FROM laureate_country WHERE laureate_id = ?";
    $stmt = $db->prepare($checkCountryBindingQuery);
    $stmt->execute([$laureate_id]);
    $existingCountryBinding = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingCountryBinding) {
        // Najprv skontrolujeme, či záznam o danej krajine už existuje
        $checkCountryQuery = "SELECT id FROM countries WHERE country_name = ?";
        $stmt = $db->prepare($checkCountryQuery);
        $stmt->execute([$country_name]);
        $existingCountry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCountry) {
            $country_id = $existingCountry['id'];
        } else {
            // Ak krajina ešte neexistuje, vložíme nový záznam do tabuľky country
            $status = insertCountry($db, $country_name);
            if (strpos($status, "Error") !== false) {
                $db->rollBack();
                return $status;
            }
            $country_id = $db->lastInsertId();
        }
        // Prepojíme laureáta s danou krajinou
        $status = boundCountry($db, $laureate_id, $country_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
    }
    // Ak už má prepojenú krajinu, nič nemeníme.

    // Spracujeme cenu: Skontrolujeme, či už existuje záznam o cene pre tohto laureáta, daný rok a kategóriu
    $prizeBoundQuery = "SELECT p.id FROM prizes p
                        INNER JOIN laureates_prizes lp ON p.id = lp.prize_id
                        WHERE lp.laureate_id = ? AND p.category = ? AND p.year = ?";
    $stmt = $db->prepare($prizeBoundQuery);
    $stmt->execute([$laureate_id, $category, $year]);
    $existingPrize = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPrize) {
        // Ak už záznam o cene existuje, aktualizujeme len detaily (napr. príspevok)
        $updatePrizeQuery = "UPDATE prizes SET contrib_sk = ?, contrib_en = ? WHERE id = ?";
        $updateStmt = $db->prepare($updatePrizeQuery);
        $updateStmt->execute([$contrib_sk, $contrib_en, $existingPrize['id']]);
        $prize_id = $existingPrize['id'];
    } else {
        // Ak cena ešte neexistuje, vložíme nový záznam o cene a prepojíme ho s laureátom
        $details_id = NULL; // Ak by ste chceli vkladať podrobnosti, môžete túto časť rozvinúť
        $status = insertPrize($db, $year, $category, $contrib_sk, $contrib_en, $details_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
        $prize_id = $db->lastInsertId();

        $status = boundPrize($db, $laureate_id, $prize_id);
        if (strpos($status, "Error") !== false) {
            $db->rollBack();
            return $status;
        }
    }

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