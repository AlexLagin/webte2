<?php

class Laureate {

    private $db; // PDO inštancia

    public function __construct($db) {
        $this->db = $db;
    }

    // Zoznam laureátov so stránkovaním
    public function index($page = 1, $limit = 10) {
        // 1. Ošetrenie parametrov
        if ($page < 1) {
            $page = 1;
        }
        if ($limit < 1) {
            $limit = 10;
        }
        $offset = ($page - 1) * $limit;
    
        // 2. Zisti celkový počet záznamov (len z tabuľky laureates)
        $countStmt = $this->db->query("SELECT COUNT(*) FROM laureates");
        $total = (int) $countStmt->fetchColumn();
    
        // 3. SELECT s LEFT JOIN na countries a GROUP_CONCAT pre krajiny
        //    Vďaka GROUP_CONCAT dostaneme prípadné viaceré krajiny v jednom stĺpci (oddelíme čiarkou).
        //    Dôležitá je klauzula GROUP BY l.id.
        $sql = "
            SELECT
                l.id,
                l.sex,
                l.birth_year,
                l.death_year,
                l.fullname,
                l.organisation,
                GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ', ') AS country
            FROM laureates l
            LEFT JOIN laureate_country lc ON l.id = lc.laureate_id
            LEFT JOIN countries c ON lc.country_id = c.id
            GROUP BY l.id
            ORDER BY l.id
            LIMIT :limit OFFSET :offset
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // 4. Vrátime dáta + info o stránkovaní
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

    // Vytvorenie nového záznamu
    public function store($gender, $birth, $death, $fullname = null, $organisation = null) {
        // POZOR: stĺpce musia existovať v DB
        $stmt = $this->db->prepare("
            INSERT INTO laureates (fullname, organisation, gender, birth, death) 
            VALUES (:fullname, :organisation, :gender, :birth, :death)
        ");
        
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

        return $this->db->lastInsertId();
    }

    // Aktualizácia záznamu
    public function update($id, $gender, $birth, $death, $fullname = null, $organisation = null) {
        $stmt = $this->db->prepare("
            UPDATE laureates 
            SET fullname = :fullname, 
                organisation = :organisation, 
                gender = :gender, 
                birth = :birth, 
                death = :death 
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

    // Zmazanie záznamu
    public function destroy($id) {
        $stmt = $this->db->prepare("DELETE FROM laureates WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        return 0;
    }

    public function getPrizes($id) {
        $sql = "
            SELECT p.id, p.year, p.category, p.contrib_sk, p.contrib_en,
                   d.language_sk, d.language_en, d.genre_sk, d.genre_en
            FROM laureates_prizes lp
            JOIN prizes p ON lp.prize_id = p.id
            LEFT JOIN prize_details d ON p.details_id = d.id
            WHERE lp.laureate_id = :id
            ORDER BY p.year"
            ;
    
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
