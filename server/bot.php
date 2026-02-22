<?php
$BOT_TOKEN = "";
$CHAT_IDS = ["7918577049", "1273160896"];

$pdo = new PDO("mysql:host=localhost;dbname=wegabox;charset=utf8mb4", "wegabox", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function getSelectedDevice($pdo, $chatId) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_key = ?");
    $stmt->execute(["device_$chatId"]);
    return $stmt->fetchColumn() ?: "wegabox";
}

function setSelectedDevice($pdo, $chatId, $device) {
    $pdo->prepare("REPLACE INTO settings (setting_key, value) VALUES (?, ?)")->execute(["device_$chatId", $device]);
}

function getDevices($pdo) {
    return $pdo->query("SELECT DISTINCT device_id FROM readings ORDER BY device_id")->fetchAll(PDO::FETCH_COLUMN);
}

function getDeviceNames($pdo) {
    return $pdo->query("SELECT device_id, name FROM device_names")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

function getLastData($pdo, $device_id) {
    $stmt = $pdo->prepare("SELECT * FROM readings WHERE device_id = ? AND root_temp IS NOT NULL ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$device_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPumps($pdo, $device_id) {
    $stmt = $pdo->prepare("SELECT * FROM pumps WHERE device_id = ? LIMIT 1");
    $stmt->execute([$device_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: $pdo->query("SELECT * FROM pumps LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getLogs($pdo, $device_id) {
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE device_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$device_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatStatus($data, $deviceName) {
    if (!$data) return "❌ Нет данных";
    $time = date('d.m.Y H:i:s', strtotime($data['created_at']));
    $isOnline = (time() - strtotime($data['created_at'])) < 120;
    $status = $isOnline ? "🟢 Online" : "🔴 Offline";
    
    $msg = "📟 <b>$deviceName</b>\n📅 $time | $status\n\n";
    $msg .= "💧 <b>Раствор:</b>\n";
    $msg .= "EC: " . ($data['ec'] > 0 ? number_format($data['ec'], 2) . " мС/см" : '--') . "\n";
    $msg .= "pH: " . ($data['ph'] > 0 ? number_format($data['ph'], 2) : '--') . "\n\n";
    $msg .= "🌡 <b>Температуры:</b>\n";
    $msg .= "Корни: " . ($data['root_temp'] > 0 ? number_format($data['root_temp'], 1) . "°C" : '--') . "\n";
    $msg .= "Воздух: " . ($data['air_temp'] > 0 ? number_format($data['air_temp'], 1) . "°C" : '--') . "\n\n";
    $msg .= "💨 Влажность: " . ($data['air_hum'] > 0 ? number_format($data['air_hum'], 1) . "%" : '--') . "\n";
    $msg .= "📶 RSSI: " . ($data['rssi'] ?? '--') . " dBm";
    return $msg;
}

function formatPumps($pumps, $deviceName) {
    $msg = "💧 <b>Насосы - $deviceName:</b>\n\n";
    for ($i = 1; $i <= 4; $i++) {
        $name = $pumps["pump{$i}_name"] ?? "Насос $i";
        $state = ($pumps["pump$i"] ?? 0) ? "🟢 ВКЛ" : "⚪ ВЫКЛ";
        $msg .= "$name: $state\n";
    }
    return $msg;
}

function formatLogs($logs, $deviceName) {
    $msg = "📋 <b>Логи - $deviceName:</b>\n\n";
    if (empty($logs)) return $msg . "Логов нет";
    foreach ($logs as $log) {
        $emoji = $log['level'] === 'ERROR' ? '🔴' : ($log['level'] === 'WARN' ? '🟡' : '🔵');
        $msg .= "$emoji " . substr($log['created_at'], 11, 8) . " {$log['message']}\n";
    }
    return $msg;
}

function formatDevicesList($pdo, $currentDevice) {
    $devices = getDevices($pdo);
    $names = getDeviceNames($pdo);
    $msg = "📟 <b>Выберите устройство:</b>\n\n";
    foreach ($devices as $d) {
        $name = $names[$d] ?? $d;
        $data = getLastData($pdo, $d);
        $status = ($data && (time() - strtotime($data['created_at'])) < 120) ? "🟢" : "🔴";
        $selected = ($d === $currentDevice) ? " ✅" : "";
        $msg .= "$status <b>$name</b>$selected\n";
    }
    return $msg;
}

function sendTelegram($token, $chatId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function getMainKeyboard($pdo, $currentDevice) {
    $names = getDeviceNames($pdo);
    $deviceName = $names[$currentDevice] ?? $currentDevice;
    return ['keyboard' => [
        [['text' => '📊 Статус'], ['text' => '💧 Насосы']],
        [['text' => '📋 Логи'], ['text' => '⚙️ Веб']],
        [['text' => "📟 $deviceName ▼"]]
    ], 'resize_keyboard' => true];
}

function getDevicesKeyboard($pdo) {
    $devices = getDevices($pdo);
    $names = getDeviceNames($pdo);
    $keyboard = [];
    foreach ($devices as $d) {
        $keyboard[] = [['text' => "🔘 " . ($names[$d] ?? $d)]];
    }
    $keyboard[] = [['text' => '◀️ Назад']];
    return ['keyboard' => $keyboard, 'resize_keyboard' => true];
}

// Webhook
if (($_SERVER['REQUEST_METHOD'] ?? 'CLI') === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true);
    if (!isset($update['message'])) exit;
    
    $chatId = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    
    if (!in_array($chatId, array_map('intval', $CHAT_IDS))) {
        sendTelegram($BOT_TOKEN, $chatId, "⛔ Доступ запрещён");
        exit;
    }
    
    $devices = getDevices($pdo);
    $names = getDeviceNames($pdo);
    $currentDevice = getSelectedDevice($pdo, $chatId);
    $deviceName = $names[$currentDevice] ?? $currentDevice;
    
    // Выбор устройства
    if (preg_match('/^🔘\s*(.+)$/', $text, $m)) {
        $selectedName = trim($m[1]);
        foreach ($devices as $d) {
            if (($names[$d] ?? $d) === $selectedName) {
                setSelectedDevice($pdo, $chatId, $d);
                $msg = "✅ Выбрано: <b>" . ($names[$d] ?? $d) . "</b>";
                sendTelegram($BOT_TOKEN, $chatId, $msg, getMainKeyboard($pdo, $d));
                exit;
            }
        }
    }
    
    if (preg_match('/^📟/', $text)) {
        sendTelegram($BOT_TOKEN, $chatId, formatDevicesList($pdo, $currentDevice), getDevicesKeyboard($pdo));
        exit;
    }
    
    if ($text === '◀️ Назад' || $text === '/start' || $text === '📊 Статус') {
        $msg = formatStatus(getLastData($pdo, $currentDevice), $deviceName);
    } elseif ($text === '💧 Насосы') {
        $msg = formatPumps(getPumps($pdo, $currentDevice), $deviceName);
    } elseif ($text === '📋 Логи') {
        $msg = formatLogs(getLogs($pdo, $currentDevice), $deviceName);
    } elseif ($text === '⚙️ Веб') {
        $msg = "🌐 https://a204831.fvds.ru/?device=$currentDevice";
    } else {
        $msg = "❓ Используйте меню\n📟 Устройство: <b>$deviceName</b>";
    }
    
    sendTelegram($BOT_TOKEN, $chatId, $msg, getMainKeyboard($pdo, $currentDevice));
    exit;
}

// CLI
$names = getDeviceNames($pdo);
foreach ($CHAT_IDS as $chatId) {
    $dev = getSelectedDevice($pdo, $chatId);
    $result = sendTelegram($BOT_TOKEN, $chatId, formatStatus(getLastData($pdo, $dev), $names[$dev] ?? $dev), getMainKeyboard($pdo, $dev));
    echo "Sent to $chatId: " . ($result['ok'] ? 'OK' : 'FAIL') . "\n";
}
