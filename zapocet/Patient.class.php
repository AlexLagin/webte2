<?php

class Patient {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Získa zoznam VŠETKÝCH pacientov a ich termínov.
     * Vráti pole, kde každý prvok obsahuje údaje pacienta
     * a pole "appointments" s termínmi.
     *
     * @return array|string Pole pacientov alebo chybová správa (string).
     */
    public function index() {
        // Načítame všetkých pacientov a prípadne aj ich termíny
        $sql = "
            SELECT 
                p.id AS patient_id,
                p.fullname,
                p.diagnose,
                p.birth_year,
                a.id AS appointment_id,
                a.procedure,
                a.appointmentDate
            FROM patient p
            LEFT JOIN patient_appointment pa ON pa.patient_id = p.id
            LEFT JOIN appointment a ON a.id = pa.appointment_id
            ORDER BY p.id
        ";

        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        // rows môže obsahovať viac riadkov na jedného pacienta, ak má viac termínov
        $patients = [];

        foreach ($rows as $row) {
            $pid = $row['patient_id'];
            if (!isset($patients[$pid])) {
                // Ešte sme tohto pacienta nepridali
                $patients[$pid] = [
                    'id'         => $row['patient_id'],
                    'fullname'   => $row['fullname'],
                    'diagnose'   => $row['diagnose'],
                    'birth_year' => $row['birth_year'],
                    'appointments' => []
                ];
            }

            // Ak má pacient priradený termín (appointment_id nie je null), pridáme ho
            if ($row['appointment_id'] !== null) {
                $patients[$pid]['appointments'][] = [
                    'id'              => $row['appointment_id'],
                    'procedure'       => $row['procedure'],
                    'appointmentDate' => $row['appointmentDate']
                ];
            }
        }

        // Vrátime ako indexované pole (nie asociatívne s ID kľúčmi)
        return array_values($patients);
    }

    /**
     * Získa konkrétneho pacienta podľa ID, vrátane všetkých jeho termínov.
     *
     * @param int $id
     * @return array|string|null Pacient + appointments alebo chybovú správu alebo null, ak neexistuje.
     */
    public function show($id) {
        $sql = "
            SELECT
                p.id AS patient_id,
                p.fullname,
                p.diagnose,
                p.birth_year,
                a.id AS appointment_id,
                a.procedure,
                a.appointmentDate
            FROM patient p
            LEFT JOIN patient_appointment pa ON pa.patient_id = p.id
            LEFT JOIN appointment a ON a.id = pa.appointment_id
            WHERE p.id = :pid
            ORDER BY p.id
        ";

        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute([':pid' => $id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                // Pacient s daným ID neexistuje
                return null;
            }

            // Prvý riadok obsahuje údaje pacienta
            $patient = [
                'id'         => $rows[0]['patient_id'],
                'fullname'   => $rows[0]['fullname'],
                'diagnose'   => $rows[0]['diagnose'],
                'birth_year' => $rows[0]['birth_year'],
                'appointments' => []
            ];

            // Pridáme appointments
            foreach ($rows as $row) {
                if ($row['appointment_id'] !== null) {
                    $patient['appointments'][] = [
                        'id'              => $row['appointment_id'],
                        'procedure'       => $row['procedure'],
                        'appointmentDate' => $row['appointmentDate']
                    ];
                }
            }

            return $patient;
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Vloží nového pacienta do tabuľky `patient` a priradí mu appointments (ak sú).
     * Predpokladá, že $appointments je pole s prvkami:
     *   [
     *     ['procedure' => '...', 'appointmentDate' => 'YYYY-MM-DD ...'],
     *     ...
     *   ]
     *
     * @param string $fullname
     * @param string $diagnose
     * @param string $birth_year (napr. vo formáte YYYY-MM-DD)
     * @param array $appointments (pole termínov)
     * @return int|string ID nového záznamu alebo chybová správa.
     */
    public function store($fullname, $diagnose, $birth_year, array $appointments = []) {
        try {
            $this->db->beginTransaction();

            // 1) Najskôr vložíme pacienta
            $stmtP = $this->db->prepare("
                INSERT INTO patient (fullname, diagnose, birth_year)
                VALUES (:fullname, :diagnose, :birth_year)
            ");
            $stmtP->execute([
                ':fullname'   => $fullname,
                ':diagnose'   => $diagnose,
                ':birth_year' => $birth_year
            ]);

            $patientId = $this->db->lastInsertId();

            // 2) Potom pre každý termín vložíme záznam do `appointment` a priradíme v pivot tabuľke
            foreach ($appointments as $app) {
                $procedure       = $app['procedure']       ?? '';
                $appointmentDate = $app['appointmentDate'] ?? null;

                // Vložíme do appointment
                $stmtA = $this->db->prepare("
                    INSERT INTO appointment (`procedure`, appointmentDate)
                    VALUES (:proc, :appDate)
                ");
                $stmtA->execute([
                    ':proc'    => $procedure,
                    ':appDate' => $appointmentDate
                ]);

                $appointmentId = $this->db->lastInsertId();

                // Vložíme do pivot tabuľky patient_appointment
                $stmtPA = $this->db->prepare("
                    INSERT INTO patient_appointment (patient_ID, appointment_ID)
                    VALUES (:pid, :aid)
                ");
                $stmtPA->execute([
                    ':pid' => $patientId,
                    ':aid' => $appointmentId
                ]);
            }

            $this->db->commit();
            return $patientId;
        } catch (PDOException $e) {
            // Ak nastane chyba, rollback transakcie
            $this->db->rollBack();
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Vymaže pacienta z tabuľky `patient` podľa ID.
     * Vďaka cudzím kľúčom s ON DELETE CASCADE sa vymažú aj záznamy
     * z pivot tabuľky patient_appointment (a prípadne z appointment,
     * ak ste tak nastavili).
     *
     * @param int $id
     * @return int|string 0 pri úspechu alebo chybovú správu pri neúspechu
     */
    public function destroy($id) {
        try {
            // Môžeme, ale nemusíme použiť transakciu, podľa toho, ako máte DB nastavenú
            $this->db->beginTransaction();

            // Vymažeme pacienta (ak je ON DELETE CASCADE, pivot záznamy sa zmažú automaticky)
            $stmt = $this->db->prepare("DELETE FROM patient WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $count = $stmt->rowCount();

            $this->db->commit();

            if ($count > 0) {
                return 0; // OK
            } else {
                return "Patient not found or already deleted.";
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            return "Error: " . $e->getMessage();
        }
    }

    public function destroyAppointment($patientId, $appointmentId) {
        try {
            // Vymažeme záznam z pivot tabuľky
            $stmt = $this->db->prepare("
                DELETE FROM patient_appointment
                WHERE patient_ID = :pid
                  AND appointment_ID = :aid
            ");
            $stmt->execute([
                ':pid' => $patientId,
                ':aid' => $appointmentId
            ]);

            // rowCount() vráti počet vymazaných riadkov
            if ($stmt->rowCount() > 0) {
                return 0; // Úspech
            } else {
                // Pacient a/alebo appointment neexistuje (alebo nebol prepojený)
                return "Appointment or Patient not found";
            }
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
