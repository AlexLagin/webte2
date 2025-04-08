<?php

require_once('config.php');  // $pdo
require_once('Laureate.class.php');

$laureate = new Laureate($pdo);

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$route = explode('/', $_GET['route']);

switch ($method) {
    case 'GET':
        // 1) Zoznam všetkých laureátov (s podporou stránkovania a filtrov)
        if ($route[0] == 'laureates' && count($route) == 1) {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response = $laureate->index($page, $limit);
            http_response_code(200);
            echo json_encode($response);
            break;
        }
        // 2) Konkrétny laureát podľa ID
        elseif ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $data = $laureate->show($id);
            if ($data) {
                http_response_code(200);
                echo json_encode($data);
                break;
            }
            http_response_code(404);
            echo json_encode(['message' => 'Not found']);
            break;
        }
        // 3) Zoznam cien pre konkrétneho laureáta: /laureates/{id}/prizes
        elseif ($route[0] == 'laureates' && count($route) == 3 && is_numeric($route[1]) && $route[2] === 'prizes') {
            $id = (int)$route[1];
            $exist = $laureate->show($id);
            if (!$exist) {
                http_response_code(404);
                echo json_encode(['message' => 'Laureate not found']);
                break;
            }
            $prizes = $laureate->getPrizes($id);
            http_response_code(200);
            echo json_encode($prizes);
            break;
        }
        // 4) Nový endpoint: /prizes/years – vráti distinct roky
        elseif ($route[0] == 'prizes' && count($route) == 2 && $route[1] === 'years') {
            $stmt = $pdo->query("SELECT DISTINCT year FROM prizes ORDER BY year");
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            http_response_code(200);
            echo json_encode($years);
            break;
        }
        // 5) Nový endpoint: /prizes/categories – vráti distinct kategórie
        elseif ($route[0] == 'prizes' && count($route) == 2 && $route[1] === 'categories') {
            $stmt = $pdo->query("SELECT DISTINCT category FROM prizes ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            http_response_code(200);
            echo json_encode($categories);
            break;
        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;

    case 'POST':
        if ($route[0] == 'laureates' && count($route) == 1) {
            // Ak URL obsahuje "addMore.php", predpokladáme, že ide o viaceré záznamy
            if (strpos($_SERVER['REQUEST_URI'], 'addMore.php') !== false) {
                try {
                    $pdo->beginTransaction();
                    $data = json_decode(file_get_contents('php://input'), true);
                    if ($data === null) {
                        throw new Exception('Invalid JSON data');
                    }
                    // Očakávame, že data obsahuje pole 'laureates'
                    if (!isset($data['laureates']) || !is_array($data['laureates'])) {
                        throw new Exception("Invalid data: 'laureates' must be an array");
                    }
                    // Tu môžeme pridať validáciu pre viacerých laureátov (neskôr)
                    
                    $insertedIDs = $laureate->storeMultiple($data['laureates']);
                    $pdo->commit();
                    http_response_code(201);
                    echo json_encode([
                        'message' => "Multiple laureates created successfully",
                        'data' => $insertedIDs
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['message' => $e->getMessage()]);
                }
                break;
            } else {
                // Existujúca logika pre jediného laureáta
                try {
                    $pdo->beginTransaction();
                    $data = json_decode(file_get_contents('php://input'), true);
                    if ($data === null) {
                        throw new Exception('Invalid JSON data');
                    }
                    // Nahradíme prázdne reťazce hodnotou null
                    foreach ($data as $key => $value) {
                        if (!isset($data[$key]) || $data[$key] === '') {
                            $data[$key] = null;
                        }
                    }
                    /* Validácia laureáta:
                       - Vyplňte buď "fullname" alebo "organisation", nie oboje, aspoň jedno musí byť vyplnené.
                       - Ak je vyplnené "fullname", overí sa jeho dĺžka (max 255) a "birth" (rok narodenia) musí byť zadaný, číselný, ≤ 9999.
                       - Ak je zadaná "organisation", nastaví sa "fullname" na null.
                    */
                    if ((isset($data['fullname']) && trim($data['fullname']) !== '') &&
                        (isset($data['organisation']) && trim($data['organisation']) !== '')) {
                        throw new Exception("Vyplňte buď meno, alebo organizáciu, nie oboje");
                    }
                    if ((!isset($data['fullname']) || trim($data['fullname']) === '') &&
                        (!isset($data['organisation']) || trim($data['organisation']) === '')) {
                        throw new Exception("Musíte zadať meno alebo organizáciu");
                    }
                    if (isset($data['fullname']) && trim($data['fullname']) !== '') {
                        if (strlen(trim($data['fullname'])) > 255) {
                            throw new Exception("Meno nesmie byť dlhšie ako 255 znakov");
                        }
                        if (!isset($data['birth']) || trim($data['birth']) === '') {
                            throw new Exception("Rok narodenia nesmie byť prázdny");
                        }
                        if (!is_numeric($data['birth']) || (int)$data['birth'] > 9999) {
                            throw new Exception("Rok narodenia musí byť číslo s najviac 4 ciframi");
                        }
                        $data['organisation'] = null;
                    }
                    if (isset($data['organisation']) && trim($data['organisation']) !== '') {
                        if (strlen(trim($data['organisation'])) > 255) {
                            throw new Exception("Organizácia nesmie byť dlhšia ako 255 znakov");
                        }
                        $data['birth'] = null;
                        $data['death'] = null;
                        $data['fullname'] = null;
                    }
                    // Validácia roku úmrtia (ak je zadaný)
                    if (isset($data['death']) && trim($data['death']) !== '') {
                        if (!is_numeric($data['death']) || (int)$data['death'] > 9999) {
                            throw new Exception("Rok úmrtia musí byť číslo s najviac 4 ciframi");
                        }
                        if ((int)$data['death'] <= (int)$data['birth']) {
                            throw new Exception("Rok úmrtia musí byť väčší ako rok narodenia");
                        }
                    }
                    // Validácia cien
                    if (isset($data['prizes']) && is_array($data['prizes'])) {
                        foreach ($data['prizes'] as $prize) {
                            if (!isset($prize['category']) || trim($prize['category']) === '') {
                                throw new Exception("Kategória ceny musí byť vybratá");
                            }
                            if (!isset($prize['year']) || trim($prize['year']) === '') {
                                throw new Exception("Rok získania ceny nesmie byť prázdny");
                            }
                            if (!is_numeric($prize['year'])) {
                                throw new Exception("Rok získania ceny musí byť číselný");
                            }
                            if (isset($data['birth']) && is_numeric($data['birth'])) {
                                if ((int)$prize['year'] <= (int)$data['birth']) {
                                    throw new Exception("Rok získania ceny musí byť väčší ako rok narodenia");
                                }
                            }
                            if (!isset($prize['award']) || trim($prize['award']) === '') {
                                throw new Exception("Ocenenie nesmie byť prázdne");
                            }
                            if (strlen(trim($prize['award'])) > 2048) {
                                throw new Exception("Ocenenie nesmie byť dlhšie ako 2048 znakov");
                            }
                        }
                    }
                    // Validácia krajiny (voliteľné, ale ak je zadaná, orežeme ju)
                    if (isset($data['country']) && trim($data['country']) !== '') {
                        $data['country'] = trim($data['country']);
                    }
                    
                    // Kontrola, či už laureát existuje (podľa fullname alebo organisation)
                    $newID = null;
                    if (isset($data['fullname']) && trim($data['fullname']) !== '') {
                        $stmt = $pdo->prepare("SELECT * FROM laureates WHERE fullname = :fullname");
                        $stmt->execute([':fullname' => $data['fullname']]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $newID = $existing['id'];
                        }
                    } elseif (isset($data['organisation']) && trim($data['organisation']) !== '') {
                        $stmt = $pdo->prepare("SELECT * FROM laureates WHERE organisation = :organisation");
                        $stmt->execute([':organisation' => $data['organisation']]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $newID = $existing['id'];
                        }
                    }
                    // Ak laureát neexistuje, vytvoríme ho
                    if ($newID === null) {
                        $newID = $laureate->store(
                            $data['gender'],
                            $data['birth'],
                            $data['death'],
                            $data['fullname'],
                            $data['organisation']
                        );
                        if (!is_numeric($newID)) {
                            throw new Exception("Bad request pri vytváraní laureáta: " . $newID);
                        }
                    }
                    
                    // Pridanie krajiny (ak je zadaná)
                    if (isset($data['country']) && trim($data['country']) !== '') {
                        $res = $laureate->addCountry($newID, $data['country']);
                        if ($res !== true) {
                            throw new Exception("Chyba pri prepojení krajiny: " . $res);
                        }
                    }
                    
                    // Pridanie cien (ak existujú)
                    if (isset($data['prizes']) && is_array($data['prizes'])) {
                        foreach ($data['prizes'] as $prize) {
                            $res = $laureate->addPrize($newID, $prize);
                            if ($res !== true) {
                                throw new Exception($res);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    $new_laureate = $laureate->show($newID);
                    http_response_code(201);
                    echo json_encode([
                        'message' => "Created successfully",
                        'data' => $new_laureate
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['message' => $e->getMessage()]);
                }
                break;
            }
        }
        http_response_code(400);
        echo json_encode(['message' => 'Bad request']);
        break;
        
    case 'PUT':
        if ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $currentID = $route[1];
            $currentData = $laureate->show($currentID);
            if (!$currentData) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                break;
            }
            $updatedData = json_decode(file_get_contents('php://input'), true);
            $currentData = array_merge($currentData, $updatedData);
            // Validácia pre PUT:
            if (($currentData['fullname'] === null) || (trim($currentData['fullname']) === "")) {
                if (!isset($currentData['organisation']) || trim($currentData['organisation']) === "") {
                    http_response_code(400);
                    echo json_encode(['message' => "Organizácia nesmie byť prázdna"]);
                    break;
                }
            } else {
                if (trim($currentData['fullname']) === "") {
                    http_response_code(400);
                    echo json_encode(['message' => "Meno nesmie byť prázdne"]);
                    break;
                }
                if (!isset($currentData['birth_year']) || trim($currentData['birth_year'] . "") === "") {
                    http_response_code(400);
                    echo json_encode(['message' => "Rok narodenia nesmie byť prázdny"]);
                    break;
                }
                if (!is_numeric($currentData['birth_year']) || (int)$currentData['birth_year'] > 9999) {
                    http_response_code(400);
                    echo json_encode(['message' => "Rok narodenia musí byť číslo s najviac 4 ciframi"]);
                    break;
                }
            }
            if (isset($currentData['death_year']) && trim($currentData['death_year'] . "") !== "") {
                if (!is_numeric($currentData['death_year']) || (int)$currentData['death_year'] > 9999) {
                    http_response_code(400);
                    echo json_encode(['message' => "Rok úmrtia musí byť číslo s najviac 4 ciframi"]);
                    break;
                }
                if ((int)$currentData['death_year'] <= (int)$currentData['birth_year']) {
                    http_response_code(400);
                    echo json_encode(['message' => "Rok úmrtia musí byť väčší ako rok narodenia"]);
                    break;
                }
            }
            if (isset($currentData['fullname']) && trim($currentData['fullname']) !== "" && strlen(trim($currentData['fullname'])) > 255) {
                http_response_code(400);
                echo json_encode(['message' => "Meno nesmie byť dlhšie ako 255 znakov"]);
                break;
            }
            if (isset($currentData['organisation']) && trim($currentData['organisation']) !== "" && strlen(trim($currentData['organisation'])) > 255) {
                http_response_code(400);
                echo json_encode(['message' => "Organizácia nesmie byť dlhšia ako 255 znakov"]);
                break;
            }

            $status = $laureate->update(
                $currentID,
                $currentData['sex'],
                $currentData['birth_year'],
                $currentData['death_year'],
                $currentData['fullname'],
                $currentData['organisation']
            );
            if ($status != 0) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $status]);
                break;
            }
            http_response_code(201);
            echo json_encode([
                'message' => "Updated successfully",
                'data' => $currentData
            ]);
            break;
        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;

    case 'DELETE':
        if ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $exist = $laureate->show($id);
            if (!$exist) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                break;
            }
            $status = $laureate->destroy($id);
            if ($status != 0) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $status]);
                break;
            }
            http_response_code(201);
            echo json_encode(['message' => "Deleted successfully"]);
            break;
        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
