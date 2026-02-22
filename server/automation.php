<?php
/**
 * RDWC Box - Автоматизация для всех устройств
 * Cron: * * * * * php /var/www/wegabox/automation.php
 */

$pdo = new PDO("mysql:host=localhost;dbname=wegabox;charset=utf8mb4", "wegabox", "Tem2006!@");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Получаем все устройства
$devices = $pdo->query("SELECT DISTINCT device_id FROM readings")->fetchAll(PDO::FETCH_COLUMN);

foreach ($devices as $device_id) {
    echo "=== Device: $device_id ===\n";
    
    // Последние данные устройства
    $stmt = $pdo->prepare("SELECT * FROM readings WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$device_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data || (time() - strtotime($data['created_at'])) > 300) {
        echo "No recent data, skipping\n";
        continue;
    }
    
    // Правила для этого устройства (или общие)
    $stmt = $pdo->prepare("SELECT * FROM automation WHERE (device_id = ? OR device_id IS NULL OR device_id = '') AND enabled = 1");
    $stmt->execute([$device_id]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Насосы устройства
    $stmt = $pdo->prepare("SELECT * FROM pumps WHERE device_id = ? LIMIT 1");
    $stmt->execute([$device_id]);
    $pumps = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pumps) {
        // Создаём запись насосов для нового устройства
        $pdo->prepare("INSERT INTO pumps (device_id) VALUES (?)")->execute([$device_id]);
        $pumps = ['pump1' => 0, 'pump2' => 0, 'pump3' => 0, 'pump4' => 0];
    }
    
    $changes = [];
    
    foreach ($rules as $rule) {
        $sensor = $rule['sensor'];
        $condition = $rule['condition'];
        $value = floatval($rule['value']);
        $pump = $rule['pump_num'];
        $action = $rule['action'];
        
        $sensorValue = $data[$sensor] ?? null;
        if ($sensorValue === null) continue;
        
        $triggered = false;
        switch ($condition) {
            case '>': $triggered = $sensorValue > $value; break;
            case '<': $triggered = $sensorValue < $value; break;
            case '>=': $triggered = $sensorValue >= $value; break;
            case '<=': $triggered = $sensorValue <= $value; break;
        }
        
        if ($triggered) {
            $newState = ($action === 'on') ? 1 : 0;
            $currentState = $pumps["pump$pump"] ?? 0;
            
            if ($currentState != $newState) {
                $changes["pump$pump"] = $newState;
                echo "Rule: {$rule['name']} - {$sensor}={$sensorValue} {$condition} {$value} -> Pump{$pump} " . ($newState ? 'ON' : 'OFF') . "\n";
                
                $msg = "Auto[$device_id]: Pump{$pump} " . ($newState ? 'ON' : 'OFF') . " ({$sensor}={$sensorValue})";
                $pdo->prepare("INSERT INTO logs (device_id, level, message) VALUES (?, 'INFO', ?)")->execute([$device_id, $msg]);
            }
        }
    }
    
    if (!empty($changes)) {
        foreach ($changes as $pump => $state) {
            $pdo->prepare("UPDATE pumps SET $pump = ? WHERE device_id = ?")->execute([$state, $device_id]);
        }
        echo "Applied: " . json_encode($changes) . "\n";
    }
}
echo "Done!\n";
