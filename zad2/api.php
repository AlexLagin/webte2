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
            $data = json_decode(file_get_contents('php://input'), true);
            foreach ($data as $key => $value) {
                if (!isset($data[$key]) || $data[$key] == '') {
                    $data[$key] = null;
                }
            }
            $newID = $laureate->store(
                $data['gender'],
                $data['birth'],
                $data['death'],
                $data['fullname'],
                $data['organisation']
            );
            if (!is_numeric($newID)) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $newID]);
                break;
            }
            // Ak boli poskytnuté aj informácie o cene, vložíme ich
            if (isset($data['prizes']) && is_array($data['prizes'])) {
                foreach ($data['prizes'] as $prize) {
                    $res = $laureate->addPrize($newID, $prize);
                    if ($res !== true) {
                        http_response_code(400);
                        echo json_encode(['message' => $res]);
                        exit;
                    }
                }
            }
            $new_laureate = $laureate->show($newID);
            http_response_code(201);
            echo json_encode([
                'message' => "Created successfully",
                'data' => $new_laureate
            ]);
            break;
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
            // Validácia:
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
