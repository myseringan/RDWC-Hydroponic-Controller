<?php
$DB_HOST = 'localhost';
$DB_NAME = 'wegabox';
$DB_USER = 'wegabox';
$DB_PASS = '';
$API_KEY = '';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB error']));
}

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
$api_key = $_GET['api_key'] ?? '';
if ($api_key !== $API_KEY) { echo json_encode(['error' => 'Invalid API key']); exit; }

if ($action === 'log') {
    $device_id = $_GET['device_id'] ?? 'wegabox';
    $level = $_GET['level'] ?? 'INFO';
    $message = $_GET['msg'] ?? $_GET['message'] ?? '';
    if ($message) {
        $stmt = $pdo->prepare("INSERT INTO logs (device_id, level, message) VALUES (?, ?, ?)");
        $stmt->execute([$device_id, $level, $message]);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'logs') {
    $device_id = $_GET['device_id'] ?? 'wegabox';
    $limit = min(intval($_GET['limit'] ?? 100), 500);
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE device_id = ? ORDER BY created_at DESC LIMIT " . $limit);
    $stmt->execute([$device_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'data') {
    $device_id = $_GET['device_id'] ?? 'wegabox';
    $root_temp = isset($_GET['RootTemp']) ? floatval($_GET['RootTemp']) : null;
    $air_temp = isset($_GET['AirTemp']) ? floatval($_GET['AirTemp']) : null;
    $cpu_temp = isset($_GET['CPUTemp']) ? floatval($_GET['CPUTemp']) : null;
    $air_hum = isset($_GET['AirHum']) ? floatval($_GET['AirHum']) : null;
    $air_press = isset($_GET['AirPress']) ? floatval($_GET['AirPress']) : null;
    $ph_mv = isset($_GET['pHmV']) ? floatval($_GET['pHmV']) : null;
    $co2 = isset($_GET['CO2']) ? intval($_GET['CO2']) : null;
    $tvoc = isset($_GET['tVOC']) ? intval($_GET['tVOC']) : null;
    $distance = isset($_GET['Dist']) ? floatval($_GET['Dist']) : null;
    $light = isset($_GET['PR']) ? intval($_GET['PR']) : null;
    $ntc = isset($_GET['NTC']) ? intval($_GET['NTC']) : null;
    $hall = isset($_GET['hall']) ? intval($_GET['hall']) : null;
    $rssi = isset($_GET['RSSI']) ? intval($_GET['RSSI']) : null;
    $uptime = isset($_GET['uptime']) ? intval($_GET['uptime']) : null;
    $heap = isset($_GET['heap']) ? intval($_GET['heap']) : null;
    $wph = isset($_GET['wpH']) ? floatval($_GET['wpH']) : null;
    $wec = isset($_GET['wEC']) ? floatval($_GET['wEC']) : null;

    $sql = "INSERT INTO readings (device_id, root_temp, air_temp, cpu_temp, air_hum, air_press, ph, ph_mv, ec, co2, tvoc, distance, light, ntc, hall, rssi, heap, uptime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$device_id, $root_temp, $air_temp, $cpu_temp, $air_hum, $air_press, $wph, $ph_mv, $wec, $co2, $tvoc, $distance, $light, $ntc, $hall, $rssi, $heap, $uptime]);

    $stmt = $pdo->prepare("SELECT * FROM calibration WHERE device_id = ? OR device_id = 'wegabox' LIMIT 1");
    $stmt->execute([$device_id]);
    $calib = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM pumps WHERE device_id = ? OR device_id = 'wegabox' LIMIT 1");
    $stmt->execute([$device_id]);
    $pumps = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = ['status' => 'ok'];
    if ($calib) {
        $response['tR_DAC'] = 4095;
        $response['tR_B'] = floatval($calib['ntc_b'] ?? 3950);
        $response['EC_R1'] = floatval($calib['ec_r1'] ?? 1000);
        $response['EC_val_p1'] = floatval($calib['ec_val_p1'] ?? 1.413);
        $response['EC_val_p2'] = floatval($calib['ec_val_p2'] ?? 2.76);
        $response['EC_R2_p1'] = floatval($calib['ec_r2_p1'] ?? 1000);
        $response['EC_R2_p2'] = floatval($calib['ec_r2_p2'] ?? 500);
        $response['EC_val_korr'] = floatval($calib['ec_val_korr'] ?? 0);
        $response['EC_kT'] = floatval($calib['ec_kt'] ?? 0.02);
        $response['Dr'] = 4095;
        $response['EC_Rx1'] = 0;
        $response['EC_Rx2'] = 0;
        $response['pH_val_p1'] = floatval($calib['ph_val_p1'] ?? 4.0);
        $response['pH_val_p2'] = floatval($calib['ph_val_p2'] ?? 7.0);
        $response['pH_val_p3'] = floatval($calib['ph_val_p3'] ?? 10.0);
        $response['pH_raw_p1'] = floatval($calib['ph_raw_p1'] ?? 0);
        $response['pH_raw_p2'] = floatval($calib['ph_raw_p2'] ?? 0);
        $response['pH_raw_p3'] = floatval($calib['ph_raw_p3'] ?? 0);
        $response['pH_lkorr'] = 0;
    }
    if ($pumps) {
        $response['pumps'] = ['pump1'=>(bool)$pumps['pump1'],'pump2'=>(bool)$pumps['pump2'],'pump3'=>(bool)$pumps['pump3'],'pump4'=>(bool)$pumps['pump4']];
    }
    echo json_encode($response);
    exit;
}

if ($action === 'pumps') {
    $device_id = $_GET['device_id'] ?? 'wegabox';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stmt = $pdo->prepare("UPDATE pumps SET pump1=?, pump2=?, pump3=?, pump4=? WHERE device_id=?");
        $stmt->execute([$data['pump1']??0, $data['pump2']??0, $data['pump3']??0, $data['pump4']??0, $device_id]);
        echo json_encode(['status' => 'ok']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pumps WHERE device_id = ? LIMIT 1");
        $stmt->execute([$device_id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['error'=>'not found']);
    }
    exit;
}

if ($action === 'last') {
    $device_id = $_GET['device_id'] ?? 'wegabox';
    $stmt = $pdo->prepare("SELECT * FROM readings WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$device_id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['error'=>'no data']);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
