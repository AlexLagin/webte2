<?php

class Laureate {

    private $db; // PDO inštancia

    public function __construct($db) {
        $this->db = $db;
    }

    // Zoznam laureátov so stránkovaním, vrátane filtrov pre rok, kategóriu a krajinu
    public function index($page = 1, $limit = 10) {
        if ($page < 1) {
            $page = 1;
        }
        if ($limit < 1) {
            $limit = 10;
        }
        $offset = ($page - 1) * $limit;

        // Získanie filter parametrov z GET (ak sú zadané)
        $filterYear = isset($_GET['year']) && $_GET['year'] !== "" ? $_GET['year'] : null;
        $filterCategory = isset($_GET['category']) && $_GET['category'] !== "" ? $_GET['category'] : null;
        $filterCountry = isset($_GET['country']) && $_GET['country'] !== "" ? $_GET['country'] : null;

        $whereClauses = [];
        $params = [];

        if ($filterYear) {
            $whereClauses[] = "p.year = :filter_year";
            $params[':filter_year'] = $filterYear;
        }
        if ($filterCategory) {
            $whereClauses[] = "p.category = :filter_category";
            $params[':filter_category'] = $filterCategory;
        }
        if ($filterCountry) {
            $whereClauses[] = "c.country_name LIKE :filter_country";
            $params[':filter_country'] = "%" . $filterCountry . "%";
        }

        $whereSQL = "";
        if (!empty($whereClauses)) {
            $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
        }

        // Získanie celkového počtu záznamov
        $countSQL = "
            SELECT COUNT(DISTINCT l.id)
            FROM laureates l
            LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
            LEFT JOIN countries c ON lc.country_id = c.id
            LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
            LEFT JOIN prizes p ON lp.prize_id = p.id
            " . $whereSQL;
        $countStmt = $this->db->prepare($countSQL);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT
                l.id,
                l.sex,
                l.birth_year,
                l.death_year,
                l.fullname,
                l.organisation,
                GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ', ') AS country,
                GROUP_CONCAT(DISTINCT p.year SEPARATOR ', ') AS year,
                GROUP_CONCAT(DISTINCT p.category SEPARATOR ', ') AS category
            FROM laureates l
            LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
            LEFT JOIN countries c ON lc.country_id = c.id
            LEFT JOIN laureates_prizes lp ON l.id = lp.laureate_id
            LEFT JOIN prizes p ON lp.prize_id = p.id
            " . $whereSQL . "
            GROUP BY l.id
            ORDER BY l.id
            LIMIT :limit OFFSET :offset
        ";
    
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        return [
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => ceil($total / $limit)
        ];
    }

    // Zobrazenie jedného záznamu
    public function show($id) {
        $stmt = $this->db->prepare("SELECT * FROM laureates WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Vytvorenie nového záznamu do tabuľky laureates
    public function store($gender, $birth, $death, $fullname = null, $organisation = null) {
        $stmt = $this->db->prepare("
            INSERT INTO laureates (fullname, organisation, sex, birth_year, death_year) 
            VALUES (:fullname, :organisation, :gender, :birth_year, :death_year)
        ");
        $stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $stmt->bindParam(':organisation', $organisation, PDO::PARAM_STR);
        $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
        $stmt->bindParam(':birth_year', $birth, PDO::PARAM_INT);
        $stmt->bindParam(':death_year', $death, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
        return $this->db->lastInsertId();
    }

    // Aktualizácia záznamu v tabuľke laureates
    public function update($id, $gender, $birth, $death, $fullname = null, $organisation = null) {
        $stmt = $this->db->prepare("
            UPDATE laureates 
            SET fullname = :fullname, 
                organisation = :organisation, 
                sex = :gender, 
                birth_year = :birth, 
                death_year = :death 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $stmt->bindParam(':organisation', $organisation, PDO::PARAM_STR);
        $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
        $stmt->bindParam(':birth', $birth, PDO::PARAM_INT);
        $stmt->bindParam(':death', $death, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
        return 0;    
    }

    // Odstránenie laureáta a súvisiacich záznamov
    public function destroy($id) {
        try {
            // Odstránenie prepojení s krajinami
            $stmt = $this->db->prepare("DELETE FROM laureate_country WHERE laureate_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Odstránenie prepojení s cenami
            $stmt = $this->db->prepare("DELETE FROM laureates_prizes WHERE laureate_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Odstránenie samotného záznamu v tabuľke laureates
            $stmt = $this->db->prepare("DELETE FROM laureates WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Získanie orphan cien – ceny, ktoré už nie sú priradené k žiadnemu laureátovi
            $orphanStmt = $this->db->query("SELECT id, details_id FROM prizes WHERE id NOT IN (SELECT prize_id FROM laureates_prizes)");
            $orphanPrizes = $orphanStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($orphanPrizes)) {
                $prizeIds = array_column($orphanPrizes, 'id');
                $placeholders = implode(',', array_fill(0, count($prizeIds), '?'));
                $stmt = $this->db->prepare("DELETE FROM prizes WHERE id IN ($placeholders)");
                $stmt->execute($prizeIds);

                foreach ($orphanPrizes as $prize) {
                    if ($prize['details_id']) {
                        $stmt = $this->db->prepare("DELETE FROM prize_details WHERE id = ?");
                        $stmt->execute([$prize['details_id']]);
                    }
                }
            }
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
        return 0;
    }

    // Pridanie ceny pre daného laureáta
    public function addPrize($laureateID, $prize) {
        try {
            $details_id = null;
            // Ak je kategória Literatúra, vlož do prize_details
            if (strtolower($prize['category']) === 'literatúra') {
                $stmt = $this->db->prepare("INSERT INTO prize_details (language_sk, genre_sk) VALUES (:language, :genre)");
                $stmt->execute([
                    ':language' => $prize['language'] ?? null,
                    ':genre' => $prize['genre'] ?? null,
                ]);
                $details_id = $this->db->lastInsertId();
            }
            // Vloženie do tabuľky prizes
            $stmt = $this->db->prepare("INSERT INTO prizes (year, category, contrib_sk, contrib_en, details_id) VALUES (:year, :category, :contrib_sk, :contrib_en, :details_id)");
            $award = isset($prize['award']) ? $prize['award'] : null;
            $stmt->execute([
                ':year' => $prize['year'],
                ':category' => $prize['category'],
                ':contrib_sk' => $award,
                ':contrib_en' => $award,
                ':details_id' => $details_id,
            ]);
            $prize_id = $this->db->lastInsertId();
            // Vloženie prepojenia do tabuľky laureates_prizes
            $stmt = $this->db->prepare("INSERT INTO laureates_prizes (laureate_id, prize_id) VALUES (:laureate_id, :prize_id)");
            $stmt->execute([
                ':laureate_id' => $laureateID,
                ':prize_id' => $prize_id,
            ]);
            return true;
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // Pridanie krajiny pre daného laureáta
    public function addCountry($laureateID, $countryName) {
        $countryName = trim($countryName);
        if ($countryName === "") {
            return "Krajina nesmie byť prázdna";
        }
        // Skontrolujeme, či už existuje prepojenie laureáta s touto krajinou
        $stmt = $this->db->prepare("SELECT c.id FROM countries c JOIN laureate_country lc ON c.id = lc.country_id WHERE lc.laureate_id = :laureate_id AND c.country_name = :country");
        $stmt->execute([':laureate_id' => $laureateID, ':country' => $countryName]);
        $existingLink = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingLink) {
            return "Laureát s danou krajinou už existuje";
        }
        // Skontrolujeme, či krajina už existuje
        $stmt = $this->db->prepare("SELECT id FROM countries WHERE country_name = :country");
        $stmt->bindParam(':country', $countryName, PDO::PARAM_STR);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $countryID = $existing['id'];
        } else {
            // Vložíme novú krajinu
            $stmt = $this->db->prepare("INSERT INTO countries (country_name) VALUES (:country)");
            $stmt->bindParam(':country', $countryName, PDO::PARAM_STR);
            $stmt->execute();
            $countryID = $this->db->lastInsertId();
        }
        // Prepojenie laureáta s krajinou
        $stmt = $this->db->prepare("INSERT INTO laureate_country (laureate_id, country_id) VALUES (:laureate_id, :country_id)");
        $stmt->bindParam(':laureate_id', $laureateID, PDO::PARAM_INT);
        $stmt->bindParam(':country_id', $countryID, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    }

    // Načítanie cien pre konkrétneho laureáta
    public function getPrizes($id) {
        $sql = "
            SELECT p.id, p.year, p.category, p.contrib_sk, p.contrib_en,
                   d.language_sk, d.language_en, d.genre_sk, d.genre_en
            FROM laureates_prizes lp
            JOIN prizes p ON lp.prize_id = p.id
            LEFT JOIN prize_details d ON p.details_id = d.id
            WHERE lp.laureate_id = :id
            ORDER BY p.year
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pridanie viacerých laureátov naraz s validáciou
    public function storeMultiple($laureates) {
        $insertedIDs = [];
        foreach ($laureates as $index => $data) {
            // Trim hodnoty
            $fullname = isset($data['fullname']) ? trim($data['fullname']) : "";
            $organisation = isset($data['organisation']) ? trim($data['organisation']) : "";
            // Overenie, že aspoň jedno pole je zadané
            if ($fullname === "" && $organisation === "") {
                return "Error: Laureát č. " . ($index + 1) . " - Musíte zadať buď celé meno alebo organizáciu";
            }
            // Ak je zadané celé meno, validujeme jednotlivé polia
            if ($fullname !== "") {
                if (strlen($fullname) > 255) {
                    return "Error: Laureát č. " . ($index + 1) . " - Celé meno nesmie byť dlhšie ako 255 znakov";
                }
                if (!isset($data['birth_year']) || trim($data['birth_year']) === "") {
                    return "Error: Laureát č. " . ($index + 1) . " - Rok narodenia musí byť zadaný";
                }
                $birth_year = trim($data['birth_year']);
                if (!is_numeric($birth_year) || (int)$birth_year > 9999) {
                    return "Error: Laureát č. " . ($index + 1) . " - Rok narodenia musí byť číslo s najviac 4 ciframi";
                }
                if (!isset($data['gender']) || (trim($data['gender']) !== "Muž" && trim($data['gender']) !== "Žena")) {
                    return "Error: Laureát č. " . ($index + 1) . " - Pohlavie musí byť vybrané ako 'Muž' alebo 'Žena'";
                }
                // Ak je zadany rok úmrtia, validujeme ho
                if (isset($data['death_year']) && trim($data['death_year']) !== "") {
                    $death_year = trim($data['death_year']);
                    if (!is_numeric($death_year) || (int)$death_year > 9999) {
                        return "Error: Laureát č. " . ($index + 1) . " - Rok úmrtia musí byť číslo s najviac 4 ciframi";
                    }
                    if ((int)$death_year <= (int)$birth_year) {
                        return "Error: Laureát č. " . ($index + 1) . " - Rok úmrtia musí byť väčší ako rok narodenia";
                    }
                }
            } else {
                // Ak je zadaná organizácia, validujeme jej dĺžku
                if (strlen($organisation) > 255) {
                    return "Error: Laureát č. " . ($index + 1) . " - Organizácia nesmie byť dlhšia ako 255 znakov";
                }
                // Pri organizácii sa predpokladá, že polia pre jednotlivca budú nastavené na NULL
                $data['birth_year'] = null;
                $data['death_year'] = null;
                $data['gender'] = null;
            }
            // Vloženie pomocou metódy store()
            $id = $this->store(
                isset($data['gender']) ? $data['gender'] : null,
                isset($data['birth_year']) ? $data['birth_year'] : null,
                isset($data['death_year']) ? $data['death_year'] : null,
                $fullname !== "" ? $fullname : null,
                $organisation !== "" ? $organisation : null
            );
            if (!is_numeric($id)) {
                $insertedIDs[] = null;
            } else {
                $insertedIDs[] = $id;
            }
        }
        return $insertedIDs;
    }
}