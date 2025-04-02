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
            $this->db->beginTransaction();

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
                // Vymažeme orphan ceny
                $prizeIds = array_column($orphanPrizes, 'id');
                $placeholders = implode(',', array_fill(0, count($prizeIds), '?'));
                $stmt = $this->db->prepare("DELETE FROM prizes WHERE id IN ($placeholders)");
                $stmt->execute($prizeIds);

                // Vymažeme súvisiace prize_details, ak existujú
                foreach ($orphanPrizes as $prize) {
                    if ($prize['details_id']) {
                        $stmt = $this->db->prepare("DELETE FROM prize_details WHERE id = ?");
                        $stmt->execute([$prize['details_id']]);
                    }
                }
            }
            
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            return "Error: " . $e->getMessage();
        }
        return 0;
    }

    // Pridanie ceny pre daného laureáta
    public function addPrize($laureateID, $prize) {
        $this->db->beginTransaction();
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
            // Vloženie do tabuľky prizes – použijeme hodnotu z "award" (ktorá sa uloží do oboch stĺpcov contrib_sk a contrib_en)
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
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return "Error: " . $e->getMessage();
        }
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
}
