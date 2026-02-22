<?php
session_start();

// Пользователи: логин => [пароль, роль]
$USERS = [
    'admin' => ['', ''],     // Полный доступ
    'user' => ['', '']       // Только просмотр
];

// Проверка авторизации
$loggedIn = isset($_SESSION['user']);
$isAdmin = $loggedIn && $_SESSION['role'] === 'admin';

// Выход
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: /");
    exit;
}
// Automation actions
if (isset($_GET["page"]) && $_GET["page"] === "automation" && $isAdmin) {
    $pdo_early = new PDO("mysql:host=localhost;dbname=wegabox;charset=utf8mb4", "wegabox", "Tem2006!@");
    if(isset($_GET["delete"])){$pdo_early->prepare("DELETE FROM automation WHERE id = ?")->execute([intval($_GET["delete"])]);header("Location: ?page=automation&device=" . ($_GET["device"] ?? "wegabox"));exit;}
    if(isset($_GET["toggle"])){$pdo_early->query("UPDATE automation SET enabled = NOT enabled WHERE id = " . intval($_GET["toggle"]));header("Location: ?page=automation&device=" . ($_GET["device"] ?? "wegabox"));exit;}
}

// Вход
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $login = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (isset($USERS[$login]) && $USERS[$login][0] === $pass) {
        $_SESSION['user'] = $login;
        $_SESSION['role'] = $USERS[$login][1];
        header('Location: /');
        exit;
    }
    $loginError = "Неверный логин или пароль";
}

// Страница входа
if (!$loggedIn):
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RDWC Box - Вход</title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,sans-serif;background:#0d1117;color:#c9d1d9;min-height:100vh;display:flex;align-items:center;justify-content:center}.login-box{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:40px;width:100%;max-width:420px;text-align:center}.logo-img{width:120px;height:120px;border-radius:50%;margin-bottom:20px;border:3px solid #58a6ff}h1{color:#58a6ff;margin-bottom:5px}h2{color:#8b949e;font-weight:normal;font-size:14px;margin-bottom:30px}.form-group{margin-bottom:15px;text-align:left}label{display:block;color:#8b949e;margin-bottom:5px}input{width:100%;padding:12px;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#c9d1d9;font-size:16px}input:focus{border-color:#58a6ff;outline:none}.btn{width:100%;padding:12px;border:none;border-radius:6px;cursor:pointer;font-size:16px;background:#238636;color:#fff;margin-top:10px}.btn:hover{background:#2ea043}.error{background:rgba(248,81,73,0.1);border:1px solid #f85149;color:#f85149;padding:10px;border-radius:6px;margin-bottom:15px}</style>
</head><body>
<div class="login-box">
<img src="/logo.png" class="logo-img" alt="RDWC Box">
<h1>RDWC Box</h1>
<h2>Система мониторинга гидропоники</h2>
<?php if(isset($loginError)):?><div class="error"><?=$loginError?></div><?php endif;?>
<form method="POST">
<div class="form-group"><label>Логин</label><input type="text" name="username" required></div>
<div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>
<button type="submit" name="login" class="btn">Войти</button>
</form>
</div>
</body></html>
<?php exit; endif;

$DB_HOST = 'localhost';
$DB_NAME = 'wegabox';
$DB_USER = 'wegabox';
$DB_PASS = 'Tem2006!@';
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Ошибка БД"); }

// AJAX переключение насосов
if (isset($_GET['action']) && $_GET['action'] === 'toggle_pump') {
    header('Content-Type: application/json');
    if (!$isAdmin) { echo json_encode(['ok' => false, 'error' => 'Access denied']); exit; }
    $pump = intval($_GET['pump'] ?? 0);
    $state = intval($_GET['state'] ?? 0);
    if ($pump >= 1 && $pump <= 4) {
        $pdo->prepare("UPDATE pumps SET pump$pump = ? WHERE device_id = '$currentDevice'")->execute([$state]);
        echo json_encode(['ok' => true]);
    } else { echo json_encode(['ok' => false]); }
    exit;
}

// Получаем список устройств с именами
$deviceNames = $pdo->query("SELECT device_id, name FROM device_names")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
$devices = $pdo->query("SELECT DISTINCT device_id FROM readings ORDER BY device_id")->fetchAll(PDO::FETCH_COLUMN);
$currentDevice = $_GET["device"] ?? "wegabox";
$stmt = $pdo->prepare("SELECT * FROM readings WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$currentDevice]);
$last = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$stmt = $pdo->query("SELECT * FROM calibration WHERE device_id = '" . $currentDevice . "' LIMIT 1");
$calib = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$stmt = $pdo->query("SELECT * FROM pumps WHERE device_id = '" . $currentDevice . "' LIMIT 1");
$pumps = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
// Создаём записи для нового устройства если нет
if(empty($calib)){$pdo->prepare("INSERT INTO calibration (device_id) VALUES (?)")->execute([$currentDevice]);$calib=["device_id"=>$currentDevice];}
if(empty($pumps)){$pdo->prepare("INSERT INTO pumps (device_id,pump1_name,pump2_name,pump3_name,pump4_name) VALUES (?,?,?,?,?)")->execute([$currentDevice,"Насос 1","Насос 2","Насос 3","Насос 4"]);$pumps=["device_id"=>$currentDevice,"pump1"=>0,"pump2"=>0,"pump3"=>0,"pump4"=>0,"pump1_name"=>"Насос 1","pump2_name"=>"Насос 2","pump3_name"=>"Насос 3","pump4_name"=>"Насос 4"];}
$page = $_GET['page'] ?? 'dashboard';

$period = $_GET['period'] ?? 'day';
$periodHours = ['hour' => 1, 'day' => 24, 'week' => 168, 'month' => 720];
$hours = $periodHours[$period] ?? 24;

$chartData = []; $stats = [];
if ($page === 'dashboard' || $page === 'charts') {
    $stmt = $pdo->prepare("SELECT root_temp, air_temp, air_hum, ph, ec, distance, created_at FROM readings WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) AND (root_temp IS NOT NULL OR air_temp IS NOT NULL OR ph IS NOT NULL OR ec IS NOT NULL) ORDER BY created_at ASC");
    $stmt->execute([$currentDevice, $hours]);
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT MIN(root_temp) as min_root, MAX(root_temp) as max_root, AVG(root_temp) as avg_root, MIN(air_temp) as min_air, MAX(air_temp) as max_air, AVG(air_temp) as avg_air, MIN(air_hum) as min_hum, MAX(air_hum) as max_hum, AVG(air_hum) as avg_hum, MIN(ph) as min_ph, MAX(ph) as max_ph, AVG(ph) as avg_ph, MIN(ec) as min_ec, MAX(ec) as max_ec, AVG(ec) as avg_ec, MIN(distance) as min_dist, MAX(distance) as max_dist, AVG(distance) as avg_dist FROM readings WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)");
    $stmt->execute([$currentDevice, $hours]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RDWC Box - Гидропоника</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,sans-serif;background:#0d1117;color:#c9d1d9;min-height:100vh}.container{max-width:1400px;margin:0 auto;padding:20px}header{background:#161b22;border-bottom:1px solid #30363d;padding:12px 0;margin-bottom:30px}header .container{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}.logo{display:flex;align-items:center;gap:12px;font-size:22px;font-weight:bold;color:#58a6ff;text-decoration:none}.logo img{width:40px;height:40px;border-radius:50%}.logo span{color:#8b949e;font-weight:normal;font-size:12px}nav{display:flex;gap:5px;flex-wrap:wrap}nav a{color:#8b949e;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:14px}nav a:hover{background:#21262d;color:#c9d1d9}nav a.active{background:#238636;color:#fff}.user-info{display:flex;align-items:center;gap:10px;color:#8b949e;font-size:13px}.user-info .role{background:#238636;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px}.user-info .role.viewer{background:#6e7681}.user-info a{color:#f85149;text-decoration:none}.device-selector select{background:#238636;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:14px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:30px}.card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:20px}.card-title{color:#8b949e;font-size:12px;text-transform:uppercase;margin-bottom:8px}.card-value{font-size:28px;font-weight:bold;color:#58a6ff}.card-unit{color:#8b949e;font-size:14px}.card-stats{margin-top:10px;font-size:11px;color:#8b949e}.section{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:20px;margin-bottom:20px}.section-title{font-size:18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #30363d}th{color:#8b949e}.form-group{margin-bottom:15px}label{display:block;color:#8b949e;margin-bottom:5px}input,select{width:100%;padding:10px;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#c9d1d9}input:focus{border-color:#58a6ff;outline:none}input:disabled{opacity:0.5}.btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer}.btn-primary{background:#238636;color:#fff}.btn:disabled{opacity:0.5}.pump-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}.pump-card{background:#0d1117;border:2px solid #30363d;border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all 0.2s}.pump-card.active{border-color:#3fb950;background:rgba(63,185,80,0.1)}.pump-card.disabled{opacity:0.6;cursor:not-allowed}.pump-icon{font-size:40px;margin-bottom:10px}.pump-status{color:#8b949e;font-size:12px;margin-top:5px}.pump-card.active .pump-status{color:#3fb950}.status{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px}.status.online{background:#3fb950;box-shadow:0 0 10px #3fb950}.status.offline{background:#f85149}.alert{padding:15px;border-radius:6px;margin-bottom:20px}.alert-info{background:rgba(88,166,255,0.1);border:1px solid #58a6ff;color:#58a6ff}.alert-warning{background:rgba(210,153,34,0.1);border:1px solid #d29922;color:#d29922}.alert-success{background:rgba(63,185,80,0.1);border:1px solid #3fb950;color:#3fb950}.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}.chart-container{position:relative;height:300px}.period-selector{display:flex;gap:5px}.period-selector a{padding:6px 14px;border-radius:4px;color:#8b949e;text-decoration:none;font-size:13px;border:1px solid #30363d}.period-selector a:hover{background:#21262d}.period-selector a.active{background:#238636;color:#fff;border-color:#238636}.stat-box{display:inline-block;background:#0d1117;padding:4px 8px;border-radius:4px;margin:2px;font-size:11px}.stat-box.min{color:#58a6ff}.stat-box.max{color:#f85149}.stat-box.avg{color:#3fb950}@media(max-width:768px){.two-col{grid-template-columns:1fr}.chart-container{height:250px}.grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}header .container{flex-direction:column;text-align:center}}</style>
</head><body>
<header><div class="container">
<a href="/" class="logo"><img src="/logo.png" alt="RDWC">RDWC Box <span>v2.5</span></a>
<nav>
<a href="?page=dashboard&device=<?=$currentDevice?>" class="<?=$page==='dashboard'?'active':''?>">Главная</a>
<a href="?page=charts&device=<?=$currentDevice?>&period=<?=$period?>" class="<?=$page==='charts'?'active':''?>">Графики</a>
<a href="?page=sensors&device=<?=$currentDevice?>" class="<?=$page==='sensors'?'active':''?>">Датчики</a>
<?php if($isAdmin):?>
<a href="?page=calibration&device=<?=$currentDevice?>" class="<?=$page==='calibration'?'active':''?>">Калибровка</a>
<a href="?page=pumps&device=<?=$currentDevice?>" class="<?=$page==='pumps'?'active':''?>">Насосы</a>
<a href="?page=automation&device=<?=$currentDevice?>" class="<?=$page==='automation'?'active':''?>">🤖 Авто</a>
<a href="?page=devices&device=<?=$currentDevice?>" class="<?=$page==='devices'?'active':''?>">📟 Устройства</a>
<?php endif;?>
<a href="?page=history&device=<?=$currentDevice?>" class="<?=$page==='history'?'active':''?>">История</a>
<a href="?page=logs&device=<?=$currentDevice?>" class="<?=$page==='logs'?'active':''?>">Логи</a>
</nav>
<div class="device-selector"><select onchange="location.href='?page=<?=$page?>&device='+this.value"><?php foreach($devices as $d):?><option value="<?=$d?>" <?=$d===$currentDevice?"selected":""?>><?=$deviceNames[$d] ?? $d?></option><?php endforeach;?></select></div>
<div class="user-info">👤 <?=$_SESSION['user']?> <span class="role <?=$isAdmin?'':'viewer'?>"><?=$isAdmin?'Админ':'Просмотр'?></span> <a href="?logout">Выйти</a></div>
</div></header>
<div class="container">

<?php if($page==='dashboard'): $isOnline=(time()-strtotime($last['created_at']??'2000-01-01'))<120; ?>
<div style="display:flex;align-items:center;margin-bottom:20px;justify-content:space-between;flex-wrap:wrap;gap:10px">
<div><span class="status <?=$isOnline?'online':'offline'?>"></span>Обновлено: <?=$last['created_at']??'Нет данных'?></div>
<div class="period-selector">
<a href="?page=dashboard&device=<?=$currentDevice?>&period=hour" class="<?=$period==='hour'?'active':''?>">Час</a>
<a href="?page=dashboard&device=<?=$currentDevice?>&period=day" class="<?=$period==='day'?'active':''?>">День</a>
<a href="?page=dashboard&device=<?=$currentDevice?>&period=week" class="<?=$period==='week'?'active':''?>">Неделя</a>
<a href="?page=dashboard&device=<?=$currentDevice?>&period=month" class="<?=$period==='month'?'active':''?>">Месяц</a>
</div></div>
<div class="grid">
<div class="card"><div class="card-title">🌡️ Т Корней</div><div class="card-value"><?=isset($last['root_temp'])?number_format($last['root_temp'],1):'--'?><span class="card-unit">°C</span></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_root'])?number_format($stats['min_root'],1):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_root'])?number_format($stats['avg_root'],1):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_root'])?number_format($stats['max_root'],1):'-'?></span></div></div>
<div class="card"><div class="card-title">🌡️ Т Воздуха</div><div class="card-value"><?=isset($last['air_temp'])?number_format($last['air_temp'],1):'--'?><span class="card-unit">°C</span></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_air'])?number_format($stats['min_air'],1):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_air'])?number_format($stats['avg_air'],1):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_air'])?number_format($stats['max_air'],1):'-'?></span></div></div>
<div class="card"><div class="card-title">💧 Влажность</div><div class="card-value"><?=isset($last['air_hum'])?number_format($last['air_hum'],1):'--'?><span class="card-unit">%</span></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_hum'])?number_format($stats['min_hum'],1):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_hum'])?number_format($stats['avg_hum'],1):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_hum'])?number_format($stats['max_hum'],1):'-'?></span></div></div>
<div class="card"><div class="card-title">⚗️ pH</div><div class="card-value"><?=isset($last['ph'])?number_format($last['ph'],2):'--'?></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_ph'])?number_format($stats['min_ph'],2):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_ph'])?number_format($stats['avg_ph'],2):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_ph'])?number_format($stats['max_ph'],2):'-'?></span></div></div>
<div class="card"><div class="card-title">⚡ EC</div><div class="card-value"><?=isset($last['ec'])?number_format($last['ec'],2):'--'?><span class="card-unit">мС</span></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_ec'])?number_format($stats['min_ec'],2):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_ec'])?number_format($stats['avg_ec'],2):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_ec'])?number_format($stats['max_ec'],2):'-'?></span></div></div>
<div class="card"><div class="card-title">📏 Уровень</div><div class="card-value"><?=isset($last['distance'])?number_format($last['distance'],1):'--'?><span class="card-unit">см</span></div><div class="card-stats"><span class="stat-box min">↓<?=isset($stats['min_dist'])?number_format($stats['min_dist'],1):'-'?></span><span class="stat-box avg">~<?=isset($stats['avg_dist'])?number_format($stats['avg_dist'],1):'-'?></span><span class="stat-box max">↑<?=isset($stats['max_dist'])?number_format($stats['max_dist'],1):'-'?></span></div></div>
<div class="card"><div class="card-title">☀️ Свет</div><div class="card-value"><?=$last['light']??'--'?></div></div>
<div class="card"><div class="card-title">📶 Сигнал</div><div class="card-value"><?=$last['rssi']??'--'?><span class="card-unit">дБм</span></div></div>
</div>
<div class="two-col">
<div class="section"><div class="section-title">🌡️ Температура</div><div class="chart-container"><canvas id="tempChart"></canvas></div></div>
<div class="section"><div class="section-title">⚗️ pH и EC</div><div class="chart-container"><canvas id="phEcChart"></canvas></div></div>
</div>
<div class="section"><div class="section-title">💧 Насосы</div>
<?php if(!$isAdmin):?><div class="alert alert-warning">🔒 Управление доступно только администратору</div><?php endif;?>
<div class="pump-grid">
<?php for($i=1;$i<=4;$i++):?><div class="pump-card <?=($pumps["pump$i"]??0)?'active':''?> <?=$isAdmin?'':'disabled'?>" id="pump<?=$i?>" <?=$isAdmin?"onclick=\"togglePump($i)\"":''?>><div class="pump-icon">💧</div><div><?=$pumps["pump{$i}_name"]??"Насос $i"?></div><div class="pump-status" id="pump<?=$i?>_status"><?=($pumps["pump$i"]??0)?'ВКЛ':'ВЫКЛ'?></div></div><?php endfor;?>
</div></div>
<?php if($isAdmin):?><script>function togglePump(n){const c=document.getElementById('pump'+n),s=document.getElementById('pump'+n+'_status'),st=c.classList.contains('active')?0:1;fetch('?action=toggle_pump&pump='+n+'&state='+st).then(r=>r.json()).then(d=>{if(d.ok){c.classList.toggle('active');s.textContent=st?'ВКЛ':'ВЫКЛ';}});}</script><?php endif;?>

<?php elseif($page==='charts'):?>
<div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
<h2>📊 Аналитика</h2>
<div class="period-selector">
<a href="?page=charts&device=<?=$currentDevice?>&period=hour" class="<?=$period==='hour'?'active':''?>">Час</a>
<a href="?page=charts&device=<?=$currentDevice?>&period=day" class="<?=$period==='day'?'active':''?>">День</a>
<a href="?page=charts&device=<?=$currentDevice?>&period=week" class="<?=$period==='week'?'active':''?>">Неделя</a>
<a href="?page=charts&device=<?=$currentDevice?>&period=month" class="<?=$period==='month'?'active':''?>">Месяц</a>
</div></div>
<div class="section"><div class="section-title">🌡️ Температура</div><div class="chart-container" style="height:350px"><canvas id="tempChartFull"></canvas></div></div>
<div class="section"><div class="section-title">💧 Влажность</div><div class="chart-container" style="height:350px"><canvas id="humChart"></canvas></div></div>
<div class="section"><div class="section-title">⚗️ pH</div><div class="chart-container" style="height:350px"><canvas id="phChart"></canvas></div></div>
<div class="section"><div class="section-title">⚡ EC</div><div class="chart-container" style="height:350px"><canvas id="ecChart"></canvas></div></div>

<?php elseif($page==='sensors'):?>
<div class="section"><div class="section-title">📊 Все датчики</div>
<?php if(empty($last)):?><div class="alert alert-info">Нет данных</div><?php else:?>
<table><tr><th>Датчик</th><th>Значение</th><th>Ед.</th></tr>
<tr><td>Температура корней</td><td><?=$last['root_temp']??'-'?></td><td>°C</td></tr>
<tr><td>Температура воздуха</td><td><?=$last['air_temp']??'-'?></td><td>°C</td></tr>
<tr><td>Температура CPU</td><td><?=$last['cpu_temp']??'-'?></td><td>°C</td></tr>
<tr><td>Влажность воздуха</td><td><?=$last['air_hum']??'-'?></td><td>%</td></tr>
<tr><td>pH</td><td><?=$last['ph']??'-'?></td><td>pH</td></tr>
<tr><td>pH (мВ)</td><td><?=$last['ph_mv']??'-'?></td><td>мВ</td></tr>
<tr><td>EC</td><td><?=$last['ec']??'-'?></td><td>мС/см</td></tr>
<tr><td>Уровень</td><td><?=$last['distance']??'-'?></td><td>см</td></tr>
<tr><td>Свет</td><td><?=$last['light']??'-'?></td><td></td></tr>
<tr><td>WiFi</td><td><?=$last['rssi']??'-'?></td><td>дБм</td></tr>
</table><?php endif;?></div>

<?php elseif($page==='calibration' && $isAdmin):
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save'])){
$pdo->prepare("UPDATE calibration SET ntc_b=?,ntc_r25=?,ec_r1=?,ec_kt=?,ec_val_p1=?,ec_val_p2=?,ec_r2_p1=?,ec_r2_p2=?,ec_val_korr=?,ph_val_p1=?,ph_val_p2=?,ph_val_p3=?,ph_raw_p1=?,ph_raw_p2=?,ph_raw_p3=? WHERE device_id='" . $currentDevice . "'")->execute([$_POST['ntc_b'],$_POST['ntc_r25'],$_POST['ec_r1'],$_POST['ec_kt'],$_POST['ec_val_p1'],$_POST['ec_val_p2'],$_POST['ec_r2_p1'],$_POST['ec_r2_p2'],$_POST['ec_val_korr'],$_POST['ph_val_p1'],$_POST['ph_val_p2'],$_POST['ph_val_p3'],$_POST['ph_raw_p1'],$_POST['ph_raw_p2'],$_POST['ph_raw_p3']]);
echo'<div class="alert alert-success">✓ Сохранено!</div>';$calib=$pdo->query("SELECT * FROM calibration WHERE device_id = '" . $currentDevice . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC);}?>
<form method="POST"><div class="two-col">
<div class="section"><div class="section-title">🌡️ NTC</div>
<div class="form-group"><label>Коэфф. B</label><input name="ntc_b" value="<?=$calib['ntc_b']??3950?>"></div>
<div class="form-group"><label>R25 (Ом)</label><input name="ntc_r25" value="<?=$calib['ntc_r25']??10000?>"></div></div>
<div class="section"><div class="section-title">⚡ EC</div>
<div class="form-group"><label>R1 (Ом)</label><input name="ec_r1" value="<?=$calib['ec_r1']??1000?>"></div>
<div class="form-group"><label>kT</label><input name="ec_kt" value="<?=$calib['ec_kt']??0.02?>"></div>
<div class="form-group"><label>EC P1 (мС)</label><input name="ec_val_p1" value="<?=$calib['ec_val_p1']??1.413?>"></div>
<div class="form-group"><label>R2 P1 (Ом)</label><input name="ec_r2_p1" value="<?=$calib['ec_r2_p1']??''?>"></div>
<div class="form-group"><label>EC P2 (мС)</label><input name="ec_val_p2" value="<?=$calib['ec_val_p2']??2.76?>"></div>
<div class="form-group"><label>R2 P2 (Ом)</label><input name="ec_r2_p2" value="<?=$calib['ec_r2_p2']??''?>"></div>
<div class="form-group"><label>Коррекция</label><input name="ec_val_korr" value="<?=$calib['ec_val_korr']??0?>"></div></div></div>
<div class="section"><div class="section-title">⚗️ pH (3 точки)</div><p style="color:#8b949e;margin-bottom:15px">RAW: <b style="color:#58a6ff"><?=$last['ph_mv']??'--'?></b> мВ</p>
<div class="two-col"><div>
<div class="form-group"><label>pH P1</label><input name="ph_val_p1" value="<?=$calib['ph_val_p1']??4?>"></div>
<div class="form-group"><label>RAW P1</label><input name="ph_raw_p1" value="<?=$calib['ph_raw_p1']??''?>"></div>
<div class="form-group"><label>pH P2</label><input name="ph_val_p2" value="<?=$calib['ph_val_p2']??7?>"></div>
<div class="form-group"><label>RAW P2</label><input name="ph_raw_p2" value="<?=$calib['ph_raw_p2']??''?>"></div></div><div>
<div class="form-group"><label>pH P3</label><input name="ph_val_p3" value="<?=$calib['ph_val_p3']??10?>"></div>
<div class="form-group"><label>RAW P3</label><input name="ph_raw_p3" value="<?=$calib['ph_raw_p3']??''?>"></div></div></div></div>
<button type="submit" name="save" class="btn btn-primary">💾 Сохранить</button></form>

<?php elseif($page==='pumps' && $isAdmin):
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save'])){
$pdo->prepare("UPDATE pumps SET pump1=?,pump2=?,pump3=?,pump4=?,pump1_name=?,pump2_name=?,pump3_name=?,pump4_name=? WHERE device_id='" . $currentDevice . "'")->execute([isset($_POST['pump1'])?1:0,isset($_POST['pump2'])?1:0,isset($_POST['pump3'])?1:0,isset($_POST['pump4'])?1:0,$_POST['pump1_name'],$_POST['pump2_name'],$_POST['pump3_name'],$_POST['pump4_name']]);
echo'<div class="alert alert-success">✓ Сохранено!</div>';$pumps=$pdo->query("SELECT * FROM pumps WHERE device_id = '" . $currentDevice . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC);}?>
<form method="POST"><div class="section"><div class="section-title">💧 Насосы</div>
<div class="pump-grid">
<?php for($i=1;$i<=4;$i++):?><label class="pump-card <?=($pumps["pump$i"]??0)?'active':''?>">
<input type="checkbox" name="pump<?=$i?>" <?=($pumps["pump$i"]??0)?'checked':''?> style="display:none" onchange="this.parentElement.classList.toggle('active');this.parentElement.querySelector('.pump-status').textContent=this.checked?'ВКЛ':'ВЫКЛ'">
<div class="pump-icon">💧</div><input name="pump<?=$i?>_name" value="<?=htmlspecialchars($pumps["pump{$i}_name"]??"Насос $i")?>" style="text-align:center;background:transparent;border:none;color:#c9d1d9;font-weight:bold;width:100%">
<div class="pump-status"><?=($pumps["pump$i"]??0)?'ВКЛ':'ВЫКЛ'?></div></label><?php endfor;?></div></div>
<button type="submit" name="save" class="btn btn-primary">💾 Сохранить</button></form>

<?php elseif($page==='history'):$limit=intval($_GET['limit']??50);$h=$pdo->query("SELECT * FROM readings ORDER BY created_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);?>
<div class="section"><div class="section-title">📊 История</div>
<form method="GET" style="margin-bottom:20px"><input type="hidden" name="page" value="history">
<select name="limit" onchange="this.form.submit()" style="width:auto"><option value="50" <?=$limit==50?'selected':''?>>50</option><option value="100" <?=$limit==100?'selected':''?>>100</option><option value="500" <?=$limit==500?'selected':''?>>500</option></select></form>
<?php if(empty($h)):?><div class="alert alert-info">Нет данных</div><?php else:?>
<div style="overflow-x:auto"><table><tr><th>Время</th><th>Корни</th><th>Воздух</th><th>Влаж.</th><th>pH</th><th>EC</th><th>Уров.</th><th>WiFi</th></tr>
<?php foreach($h as $r):?><tr><td><?=$r['created_at']?></td><td><?=isset($r['root_temp'])?number_format($r['root_temp'],1):'-'?></td><td><?=isset($r['air_temp'])?number_format($r['air_temp'],1):'-'?></td><td><?=isset($r['air_hum'])?number_format($r['air_hum'],1):'-'?></td><td><?=isset($r['ph'])?number_format($r['ph'],2):'-'?></td><td><?=isset($r['ec'])?number_format($r['ec'],2):'-'?></td><td><?=isset($r['distance'])?number_format($r['distance'],1):'-'?></td><td><?=$r['rssi']??'-'?></td></tr><?php endforeach;?>
</table></div><?php endif;?></div>

<?php elseif($page==='automation' && $isAdmin):
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["add"])){
$pdo->prepare("INSERT INTO automation (device_id, name, sensor, `condition`, value, pump_num, action) VALUES (?,?,?,?,?,?,?)")->execute([$currentDevice, $_POST["name"], $_POST["sensor"], $_POST["cond"], $_POST["value"], $_POST["pump_num"], $_POST["action"]]);
echo '<div class="alert alert-success">✓ Правило добавлено!</div>';}
$rules = $pdo->query("SELECT * FROM automation WHERE device_id = '" . $currentDevice . "' ORDER BY pump_num, id")->fetchAll(PDO::FETCH_ASSOC);
$pumpNames = [$pumps["pump1_name"]??"Насос 1", $pumps["pump2_name"]??"Насос 2", $pumps["pump3_name"]??"Насос 3", $pumps["pump4_name"]??"Насос 4"];
$sensors = ["air_temp" => "Т воздуха", "root_temp" => "Т корней", "air_hum" => "Влажность", "ph" => "pH", "ec" => "EC"];
$conditions = [">" => "больше", "<" => "меньше", ">=" => "≥", "<=" => "≤"];
?>
<div class="section"><div class="section-title">🤖 Автоматизация насосов</div>
<div class="alert alert-info">⏱ Правила проверяются каждую минуту автоматически</div>
<h3 style="margin:20px 0 15px">Активные правила</h3>
<?php if(empty($rules)):?><p style="color:#8b949e">Правил нет</p><?php else:?>
<table><tr><th>Название</th><th>Условие</th><th>Действие</th><th>Статус</th><th></th></tr>
<?php foreach($rules as $r): $sn=$sensors[$r["sensor"]]??"?"; $cn=$conditions[$r["condition"]]??"?"; ?>
<tr style="<?=$r["enabled"]?"":"opacity:0.5"?>"><td><?=htmlspecialchars($r["name"])?></td>
<td><?=$sn?> <?=$cn?> <?=$r["value"]?></td>
<td><?=$pumpNames[$r["pump_num"]-1]?> → <?=$r["action"]==="on"?"🟢 ВКЛ":"⚪ ВЫКЛ"?></td>
<td><a href="?page=automation&device=<?=$currentDevice?>&toggle=<?=$r["id"]?>" style="color:<?=$r["enabled"]?"#3fb950":"#f85149"?>"><?=$r["enabled"]?"✓ Вкл":"✗ Выкл"?></a></td>
<td><a href="?page=automation&device=<?=$currentDevice?>&delete=<?=$r["id"]?>" onclick="return confirm('Удалить?')" style="color:#f85149">🗑</a></td></tr>
<?php endforeach;?></table><?php endif;?>
<h3 style="margin:30px 0 15px">Добавить правило</h3>
<form method="POST"><div class="two-col"><div>
<div class="form-group"><label>Название</label><input name="name" required placeholder="Охлаждение ON"></div>
<div class="form-group"><label>Датчик</label><select name="sensor"><?php foreach($sensors as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Условие</label><select name="cond"><?php foreach($conditions as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
</div><div>
<div class="form-group"><label>Значение</label><input name="value" type="number" step="0.1" required></div>
<div class="form-group"><label>Насос</label><select name="pump_num"><option value="1"><?=$pumpNames[0]?></option><option value="2"><?=$pumpNames[1]?></option><option value="3"><?=$pumpNames[2]?></option><option value="4"><?=$pumpNames[3]?></option></select></div>
<div class="form-group"><label>Действие</label><select name="action"><option value="on">Включить</option><option value="off">Выключить</option></select></div>
</div></div><button type="submit" name="add" class="btn btn-primary" style="margin-top:15px">➕ Добавить</button></form></div>
<?php elseif($page==='logs'):$logsLimit=intval($_GET['limit']??100);$logs=$pdo->query("SELECT * FROM logs WHERE device_id='$currentDevice' ORDER BY created_at DESC LIMIT $logsLimit")->fetchAll(PDO::FETCH_ASSOC);?>
<div class="section">
<div class="section-title" style="flex-wrap:wrap;gap:10px"><span>📋 Логи ESP32</span>
<form method="GET" style="display:flex;gap:10px;align-items:center"><input type="hidden" name="page" value="logs">
<select name="limit" onchange="this.form.submit()" style="width:auto"><option value="50" <?=$logsLimit==50?'selected':''?>>50</option><option value="100" <?=$logsLimit==100?'selected':''?>>100</option><option value="200" <?=$logsLimit==200?'selected':''?>>200</option></select>
<button type="button" onclick="location.reload()" class="btn btn-primary" style="padding:8px 15px">🔄</button></form></div>
<?php if(empty($logs)):?><div class="alert alert-info">Логов нет</div><?php else:?>
<div style="background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:15px;max-height:600px;overflow-y:auto;font-family:monospace;font-size:13px">
<?php foreach($logs as $log):$c=$log['level']==='ERROR'?'#f85149':($log['level']==='WARN'?'#d29922':'#58a6ff');?>
<div style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #21262d"><span style="color:#6e7681"><?=substr($log['created_at'],11)?></span> <span style="color:<?=$c?>;font-weight:bold">[<?=$log['level']?>]</span> <?=htmlspecialchars($log['message'])?></div>
<?php endforeach;?></div><?php endif;?></div>
<?php elseif($page==="devices" && $isAdmin):
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["save_name"])){
$pdo->prepare("REPLACE INTO device_names (device_id, name) VALUES (?, ?)")->execute([$_POST["device_id"], $_POST["name"]]);
$deviceNames[$_POST["device_id"]] = $_POST["name"];
echo '<div class="alert alert-success">✓ Сохранено!</div>';}
if(isset($_GET["delete_device"]) && $_GET["delete_device"] !== "wegabox"){
$d = $_GET["delete_device"];
$pdo->prepare("DELETE FROM readings WHERE device_id = ?")->execute([$d]);
$pdo->prepare("DELETE FROM logs WHERE device_id = ?")->execute([$d]);
$pdo->prepare("DELETE FROM device_names WHERE device_id = ?")->execute([$d]);
header("Location: ?page=devices&device=wegabox");exit;}
?>
<div class="section"><div class="section-title">📟 Управление устройствами</div>
<table><tr><th>ID устройства</th><th>Название</th><th>Последняя активность</th><th>Статус</th><th></th></tr>
<?php foreach($devices as $d):
$stmt=$pdo->prepare("SELECT created_at FROM readings WHERE device_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$d]);$lastAct=$stmt->fetchColumn();
$isOnline=$lastAct && (time()-strtotime($lastAct))<120;
?>
<tr>
<td><code><?=$d?></code></td>
<td><form method="POST" style="display:flex;gap:10px"><input type="hidden" name="device_id" value="<?=$d?>"><input name="name" value="<?=$deviceNames[$d]??$d?>" style="width:150px"><button type="submit" name="save_name" class="btn btn-primary" style="padding:5px 10px">💾</button></form></td>
<td><?=$lastAct?date("d.m.Y H:i",strtotime($lastAct)):"--"?></td>
<td><?=$isOnline?"<span style='color:#3fb950'>🟢 Online</span>":"<span style='color:#f85149'>🔴 Offline</span>"?></td>
<td><?php if($d!=="wegabox"):?><a href="?page=devices&delete_device=<?=$d?>" onclick="return confirm('Удалить устройство <?=$d?> и все его данные?')" style="color:#f85149">🗑 Удалить</a><?php endif;?></td>
</tr>
<?php endforeach;?>
</table>
<div class="alert alert-info" style="margin-top:20px">💡 Новые устройства появляются автоматически при отправке данных на сервер</div>
</div>
</div>
<?php endif;?>

<?php if($page==='dashboard'||$page==='charts'):?>
<script>
const cd=<?=json_encode($chartData)?>,p='<?=$period?>',lb=cd.map(r=>{const d=new Date(r.created_at);return p==='hour'||p==='day'?d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0'):d.getDate()+'.'+(d.getMonth()+1);}),rt=cd.map(r=>r.root_temp),at=cd.map(r=>r.air_temp),ah=cd.map(r=>r.air_hum),ph=cd.map(r=>r.ph),ec=cd.map(r=>r.ec),co={responsive:!0,maintainAspectRatio:!1,spanGaps:!0,plugins:{legend:{labels:{color:'#8b949e'}}},scales:{x:{ticks:{color:'#8b949e',maxTicksLimit:12},grid:{color:'#21262d'}},y:{ticks:{color:'#8b949e'},grid:{color:'#21262d'}}},elements:{line:{tension:0,borderWidth:2},point:{radius:0}}};
<?php if($page==='dashboard'):?>
new Chart(document.getElementById('tempChart'),{type:'line',data:{labels:lb,datasets:[{label:'Корни',data:rt,borderColor:'#f85149',fill:!1},{label:'Воздух',data:at,borderColor:'#58a6ff',fill:!1}]},options:co});
new Chart(document.getElementById('phEcChart'),{type:'line',data:{labels:lb,datasets:[{label:'pH',data:ph,borderColor:'#3fb950',fill:!1,yAxisID:'y'},{label:'EC',data:ec,borderColor:'#d29922',fill:!1,yAxisID:'y1'}]},options:{...co,scales:{x:co.scales.x,y:{type:'linear',position:'left',ticks:{color:'#3fb950'},grid:{color:'#21262d'}},y1:{type:'linear',position:'right',ticks:{color:'#d29922'},grid:{drawOnChartArea:!1}}}}});
<?php endif;if($page==='charts'):?>
new Chart(document.getElementById('tempChartFull'),{type:'line',data:{labels:lb,datasets:[{label:'Корни',data:rt,borderColor:'#f85149',fill:!1},{label:'Воздух',data:at,borderColor:'#58a6ff',fill:!1}]},options:co});
new Chart(document.getElementById('humChart'),{type:'line',data:{labels:lb,datasets:[{label:'Влажность',data:ah,borderColor:'#58a6ff',fill:!1}]},options:co});
new Chart(document.getElementById('phChart'),{type:'line',data:{labels:lb,datasets:[{label:'pH',data:ph,borderColor:'#3fb950',fill:!1}]},options:{...co,scales:{...co.scales,y:{min:3,max:11,ticks:{color:'#8b949e'},grid:{color:'#21262d'}}}}});
new Chart(document.getElementById('ecChart'),{type:'line',data:{labels:lb,datasets:[{label:'EC',data:ec,borderColor:'#d29922',fill:!1}]},options:co});
<?php endif;?>
</script>
<?php endif;if($page==='dashboard'):?><script>setTimeout(()=>location.reload(),30000)</script><?php endif;?>
</body></html>
