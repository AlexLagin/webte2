<?php

require_once('config.php');  // $pdo
require_once('Patient.class.php');

ini_set('display_errors', 0);       // Vypneme zobrazovanie chýb do HTML
ini_set('log_errors', 1);           // Logovať chyby do error_log
error_reporting(E_ALL);             // Reportovať všetky chyby

header("Content-Type: application/json; charset=utf-8");

$patient = new Patient($pdo);

// Skontrolujeme, či je parameter "route" nastavený
$routeParam = $_GET['route'] ?? '';
$route = explode('/', $routeParam);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // --------------------- GET ---------------------
    case 'GET':
        // GET /api.php?route=patients
        if (isset($route[0]) && $route[0] === 'patients' && count($route) === 1) {
            $patients = $patient->index();
            http_response_code(200);
            echo json_encode($patients);
            exit;
        }
        // GET /api.php?route=patients/5
        elseif (isset($route[0]) && $route[0] === 'patients' && count($route) === 2 && is_numeric($route[1])) {
            $id = (int)$route[1];
            $data = $patient->show($id);
            if ($data) {
                http_response_code(200);
                echo json_encode($data);
                exit;
            }
            http_response_code(404);
            echo json_encode(['message' => 'Not found']);
            exit;
        }
        // Ak nič nesedí
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        exit;

    // --------------------- POST ---------------------
    case 'POST':
        // POST /api.php?route=patients
        if (isset($route[0]) && $route[0] === 'patients' && count($route) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid JSON input']);
                exit;
            }

            // Nastavíme prázdne/potrebné polia na null, ak sú ''
            foreach ($data as $key => $value) {
                if (!isset($data[$key]) || $data[$key] === '') {
                    $data[$key] = null;
                }
            }

            // Spojíme first_name a last_name do fullname
            $first_name = $data['first_name'] ?? '';
            $last_name  = $data['last_name']  ?? '';
            $fullname   = trim($first_name . ' ' . $last_name);

            // Ostatné údaje
            $diagnose    = $data['diagnose']    ?? '';
            $birth_year  = $data['birth_year']  ?? '';
            $appointment = $data['appointment'] ?? null;

            // Vložíme nového pacienta
            $newID = $patient->store($fullname, $diagnose, $birth_year, $appointment);

            if (!is_numeric($newID)) {
                http_response_code(400);
                echo json_encode([
                    'message' => "Bad request",
                    'error'   => $newID  // Môže byť text s chybou z DB
                ]);
                exit;
            }

            // Načítame práve vytvoreného pacienta
            $new_patient = $patient->show($newID);
            http_response_code(201);
            echo json_encode([
                'message' => "Created successfully",
                'data'    => $new_patient
            ]);
            exit;
        }
        // Ak route nesedí
        http_response_code(400);
        echo json_encode(['message' => 'Bad request']);
        exit;

    // --------------------- DELETE ---------------------
    case 'DELETE':

        // DELETE /api.php?route=patients/5/appointment/7
        // => vymaže ZÁZNAM v pivot tabuľke (patient_appointment)
        if (isset($route[0]) && $route[0] === 'patients'
            && count($route) === 4
            && is_numeric($route[1])
            && $route[2] === 'appointment'
            && is_numeric($route[3])
        ) {
            $patientId     = (int)$route[1];
            $appointmentId = (int)$route[3];

            // Skontrolujeme, či pacient existuje (voliteľne aj appointment)
            $exist = $patient->show($patientId);
            if (!$exist) {
                http_response_code(404);
                echo json_encode(['message' => 'Patient not found']);
                exit;
            }

            // Zavoláme metódu, ktorá vymaže záznam z pivot tabuľky
            $status = $patient->destroyAppointment($patientId, $appointmentId);
            if ($status === 0) {
                // Môžeme vrátiť 200 OK alebo 204 No Content
                http_response_code(200);
                echo json_encode(['message' => "Appointment deleted successfully"]);
            } else {
                // Môže ísť o to, že záznam neexistuje (pacient/appointment nie je spárovaný)
                http_response_code(404);
                echo json_encode(['message' => $status]); 
            }
            exit;
        }

        // DELETE /api.php?route=patients/5
        // => vymaže CELÉHO pacienta (ak to tak chcete)
        if (isset($route[0]) && $route[0] === 'patients' && count($route) === 2 && is_numeric($route[1])) {
            $id = (int)$route[1];
            $exist = $patient->show($id);

            if (!$exist) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                exit;
            }

            // Tu by ste volali $patient->destroy($id) ak to tak máte implementované
            $status = $patient->destroy($id);
            if ($status !== 0) {
                http_response_code(400);
                echo json_encode([
                    'message' => "Bad request",
                    'error'   => $status
                ]);
                exit;
            }

            http_response_code(200);
            echo json_encode(['message' => "Patient deleted successfully"]);
            exit;
        }

        // Inak 404
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        exit;

    // --------------------- Ostatné metódy ---------------------
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
}
