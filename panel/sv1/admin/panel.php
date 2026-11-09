<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

if (!isset($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$account_id = $_SESSION['account_id'];

$stmt = $pdo->prepare("SELECT * FROM account WHERE id = :id LIMIT 1");
$stmt->bindParam(":id", $account_id);
$stmt->execute();
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    session_destroy();
    header("Location: /sv1/trangchu");
    exit;
}

$stmt2 = $pdo->prepare("SELECT * FROM player WHERE account_id = :aid LIMIT 1");
$stmt2->bindParam(":aid", $account_id);
$stmt2->execute();
$player = $stmt2->fetch(PDO::FETCH_ASSOC);

$power = 0;
if (!empty($player['data_point'])) {
    $clean = trim($player['data_point'], "[]");
    $arr = explode(',', $clean);
    $power = isset($arr[1]) ? (int)$arr[1] : 0;
}

$gender = $player['gender'] ?? 0;
$genderIcon = '/img/24.jpg';
if ($gender == 1) { $genderIcon = '/img/25.jpg'; }
elseif ($gender == 2) { $genderIcon = '/img/26.jpg'; }

$playerName = $player['name'] ?? 'Vô Danh';
function format_fish_amount($vnd): string {
    return number_format((float)$vnd, 0, ',', '.');
}

$vnd = format_fish_amount($account['vnd'] ?? 0);
$active = $account['active'] ?? 0;
$is_admin = (int)($account['is_admin'] ?? 0);
$csrfToken = ensure_csrf_token();

function get_current_exp_server(): int {
    $statusFile = panel_exp_status_file();
    if (is_file($statusFile)) {
        $value = (int)trim((string)@file_get_contents($statusFile));
        if ($value > 0) return $value;
    }
    $configFile = panel_config_file();
    if (is_file($configFile)) {
        foreach (@file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*server\.expserver\s*=\s*(\d+)/', $line, $m)) {
                $value = (int)$m[1];
                if ($value > 0) return $value;
            }
        }
    }
    return 10;
}

function get_current_drop_server(): int {
    $statusFile = panel_drop_status_file();
    if (is_file($statusFile)) {
        $value = (int)trim((string)@file_get_contents($statusFile));
        if ($value > 0) return $value;
    }
    $configFile = panel_config_file();
    if (is_file($configFile)) {
        foreach (@file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*server\.dropserver\s*=\s*(\d+)/', $line, $m)) {
                $value = (int)$m[1];
                if ($value > 0) return $value;
            }
        }
    }
    return 5;
}

function get_current_admin_mode(): bool {
    $statusFile = panel_admin_mode_status_file();
    if (is_file($statusFile)) {
        return trim((string)@file_get_contents($statusFile)) === '1';
    }
    return false;
}

function get_current_xnxx_mode(): bool {
    $statusFile = panel_xnxx_status_file();
    if (is_file($statusFile)) {
        return trim((string)@file_get_contents($statusFile)) === '1';
    }
    return false;
}

function get_current_boss_persist_mode(): bool {
    $statusFile = panel_boss_persist_status_file();
    if (is_file($statusFile)) {
        return trim((string)@file_get_contents($statusFile)) === '1';
    }
    return false;
}
function get_panel_bosses(): array {
    global $pdo;
    $statusFile = panel_boss_status_file();
    if (!is_file($statusFile)) {
        return [];
    }

    $raw = @file_get_contents($statusFile);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['bosses']) || !is_array($data['bosses'])) {
        return [];
    }

    $bosses = [];
    foreach ($data['bosses'] as $boss) {
        if (!is_array($boss) || !isset($boss['id'])) {
            continue;
        }
        $bosses[] = [
            'id' => (int)$boss['id'],
            'name' => (string)($boss['name'] ?? ('Boss ' . (int)$boss['id'])),
            'state' => (string)($boss['state'] ?? ''),
            'status' => (string)($boss['status'] ?? ''),
            'mapName' => (string)($boss['mapName'] ?? ''),
            'mapId' => (int)($boss['mapId'] ?? -1),
            'zoneId' => (int)($boss['zoneId'] ?? -1),
            'hp' => (int)($boss['hp'] ?? 0),
            'maxHp' => (int)($boss['maxHp'] ?? 0),
            'outfit0' => (int)($boss['outfit0'] ?? 0),
        ];
    }

    $headIds = array_values(array_unique(array_filter(array_column($bosses, 'outfit0'))));
    $avatars = [];
    if (!empty($headIds)) {
        $placeholders = implode(',', array_fill(0, count($headIds), '?'));
        $stmt = $pdo->prepare("SELECT head_id, avatar_id FROM head_avatar WHERE head_id IN ($placeholders)");
        $stmt->execute($headIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $avatars[(int)$row['head_id']] = (int)$row['avatar_id'];
        }
    }
    foreach ($bosses as &$boss) {
        $boss['avatarId'] = panel_boss_avatar_id($boss, $avatars);
    }
    unset($boss);

    return $bosses;
}

function panel_boss_avatar_id(array $boss, array $avatars): int {
    $head = (int)($boss['outfit0'] ?? 0);
    $name = (string)($boss['name'] ?? '');
    $overrides = [
        291 => 2893,
        294 => 2924,
        1501 => 13488,
    ];

    if (stripos($name, 'super broly') !== false) {
        return 2924;
    }
    if (stripos($name, 'broly') !== false) {
        return 2893;
    }
    return $overrides[$head] ?? ($avatars[$head] ?? 1);
}

function panel_boss_short_map(array $boss): string {
    $mapName = (string)($boss['mapName'] ?? '');
    $zoneId = (int)($boss['zoneId'] ?? -1);
    if (preg_match('/^(.*?)\s*\(khu\s+\d+\)\s*$/u', $mapName, $m)) {
        $mapName = trim($m[1]);
    }
    if ($mapName === '') {
        $mapName = 'Chua xuat hien';
    }
    return $zoneId >= 0 ? $mapName . ' - ' . $zoneId : $mapName;
}

// ── ĐIỂM DANH LOGIC ──
$diemDanhMsg    = '';
$diemDanhResult = '';
$todayStr       = date('Y-m-d');

$stmtCheckLast = $pdo->prepare("SELECT last_check FROM account WHERE id = :id LIMIT 1");
$stmtCheckLast->bindParam(":id", $account_id);
$stmtCheckLast->execute();
$lastCheckRow = $stmtCheckLast->fetch(PDO::FETCH_ASSOC);
$lastCheckRaw = $lastCheckRow['last_check'] ?? null;

if ($lastCheckRaw === null || $lastCheckRaw === '0000-00-00 00:00:00' || $lastCheckRaw === '') {
    $canDiemDanh = true;
} else {
    $lastCheckDate = date('Y-m-d', strtotime($lastCheckRaw));
    $canDiemDanh   = ($lastCheckDate < $todayStr);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'diem_danh') {
    require_csrf_token();
    $stmtCheck = $pdo->prepare("SELECT last_check, vnd, tongnap FROM account WHERE id = :id LIMIT 1");
    $stmtCheck->bindParam(":id", $account_id);
    $stmtCheck->execute();
    $freshAccount  = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    $freshLastCheck = $freshAccount['last_check'] ?? null;

    $alreadyDone = false;
    if ($freshLastCheck && $freshLastCheck !== '0000-00-00 00:00:00') {
        $alreadyDone = (date('Y-m-d', strtotime($freshLastCheck)) === $todayStr);
    }

    if ($alreadyDone) {
        header("Location: " . current_public_path() . "?dd=already");
        exit;
    } else {
        $reward   = rand(1, 3000);
        $newvnd  = ($freshAccount['vnd']  ?? 0) + $reward;
        $newtongnap = ($freshAccount['tongnap'] ?? 0) + $reward;
        $stmtUpd  = $pdo->prepare("UPDATE account SET vnd = :vnd, tongnap = :tongnap, last_check = NOW() WHERE id = :id");
        $stmtUpd->bindParam(":vnd",  $newvnd,    PDO::PARAM_INT);
        $stmtUpd->bindParam(":tongnap", $newtongnap,   PDO::PARAM_INT);
        $stmtUpd->bindParam(":id",    $account_id, PDO::PARAM_INT);
        $stmtUpd->execute();
        header("Location: " . current_public_path() . "?dd=ok&reward=" . $reward);
        exit;
    }
}

if (isset($_GET['dd'])) {
    if ($_GET['dd'] === 'ok' && isset($_GET['reward'])) {
        $reward         = (int)$_GET['reward'];
        $diemDanhResult = 'success';
        $diemDanhMsg    = '🎉 Điểm danh thành công! Nhận được +' . format_fish_amount($reward) . ' cá!';
        $canDiemDanh    = false;
        $stmtvnd = $pdo->prepare("SELECT vnd FROM account WHERE id = :id LIMIT 1");
        $stmtvnd->bindParam(":id", $account_id);
        $stmtvnd->execute();
        $freshvnd       = $stmtvnd->fetch(PDO::FETCH_ASSOC);
        $vnd            = format_fish_amount($freshvnd['vnd'] ?? 0);
        $account['vnd'] = $freshvnd['vnd'] ?? 0;
    } elseif ($_GET['dd'] === 'already') {
        $diemDanhResult = 'already';
        $diemDanhMsg    = '⚠️ Hôm nay bạn đã điểm danh rồi! Quay lại vào ngày mai nhé!';
        $canDiemDanh    = false;
    }
}

// ── BUFF VND LOGIC ──
$buffMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buff_vnd' && isset($_POST['char_name']) && isset($_POST['amount'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $char_name = trim($_POST['char_name']);
    $amount = (int)str_replace('.', '', (string)$_POST['amount']);
    if ($amount <= 0) {
        $buffMsg = 'Số Cá phải > 0!';
    } elseif ($char_name === '') {
        $buffMsg = 'Tên nhân vật không được để trống!';
    } else {
        $command = sprintf('BUFF_VND|%s|%d', str_replace(["\\r", "\\n"], '', $char_name), $amount);
        if (append_panel_command($command)) {
            $buffMsg = "Đã Buff Cá " . number_format($amount, 0, ',', '.') . " cho nhân vật " . $char_name;
        } else {
            $buffMsg = 'Không thể ghi lệnh buff vào file điều khiển game.';
        }
    }
}

// ── KICK PLAYER LOGIC ──
$kickMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kick_player' && isset($_POST['char_name'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $char_name = trim($_POST['char_name']);
    if ($char_name === '') {
        $kickMsg = 'Tên nhân vật không được để trống!';
    } else {
        $command = sprintf('KICK_PLAYER|%s|300', str_replace(["\r", "\n"], '', $char_name));
        if (append_panel_command($command)) {
            $kickMsg = "Đã gửi lệnh kick nhân vật " . $char_name . ". Người chơi sẽ bị khóa đăng nhập 5 phút.";
        } else {
            $kickMsg = 'Không thể ghi lệnh kick vào file điều khiển game.';
        }
    }
}

// ── ADMIN MODE LOGIC ──
$adminModeMsg = '';
$currentAdminMode = get_current_admin_mode();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_mode') {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $enabled = (($_POST['enabled'] ?? '0') === '1');
    $command = 'ADMIN_MODE|' . ($enabled ? '1' : '0');
    if (append_panel_command($command)) {
        @file_put_contents(panel_admin_mode_status_file(), $enabled ? '1' : '0', LOCK_EX);
        $currentAdminMode = $enabled;
        $adminModeMsg = $enabled
            ? 'Đã bật chế độ admin. Server chỉ cho tài khoản admin đăng nhập.'
            : 'Đã tắt chế độ admin. Người chơi thường có thể đăng nhập lại.';
    } else {
        $adminModeMsg = 'Không thể ghi lệnh chế độ admin vào file điều khiển game.';
    }
}

// ── XNXX BOT MODE LOGIC ──
$xnxxModeMsg = '';
$currentXnxxMode = get_current_xnxx_mode();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'xnxx_mode') {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $enabled = (($_POST['enabled'] ?? '0') === '1');
    $command = 'BOT_PEM|' . ($enabled ? '1' : '0');
    if (append_panel_command($command)) {
        @file_put_contents(panel_xnxx_status_file(), $enabled ? '1' : '0', LOCK_EX);
        $currentXnxxMode = $enabled;
        $xnxxModeMsg = $enabled
            ? 'Đã bật XNXX. Bot pem quái sẽ được bật giống lệnh chat bot.'
            : 'Đã tắt XNXX. Bot pem quái sẽ được tắt giống lệnh chat bot.';
    } else {
        $xnxxModeMsg = 'Không thể ghi lệnh XNXX vào file điều khiển game.';
    }
}

$SRC4_EVENT_NAMES = [0=>'Khong su kien',1=>'Tet Nguyen Dan',2=>'Trung Thu',3=>'Halloween',4=>'Noel / Giang Sinh',5=>'Hung Vuong 10/3',6=>'8/3 - 20/10',7=>'He / Quoc Khanh 2/9',8=>'Sinh Nhat Server'];
function get_current_src4_event(): array {
    global $SRC4_EVENT_NAMES;
    $cur = ['event_id' => 0, 'enabled' => false, 'source' => 'default'];
    $stFile = game_file_path('panel_event_status.json');
    if (is_file($stFile)) {
        $d = json_decode((string)@file_get_contents($stFile), true);
        if (is_array($d) && isset($d['event_id'])) {
            $cur = ['event_id' => (int)$d['event_id'], 'enabled' => !empty($d['enabled']), 'source' => 'panel_status'];
            return $cur;
        }
    }
    // Fallback: doc truc tiep config cua SRC_4 (key chuan la server.event)
    $cfgCandidates = [
        game_file_path('data/config/config.properties'),
        game_file_path('data/config/config.ini'),
        game_file_path('config/server.properties'),
        game_file_path('resources/config/server.properties'),
        game_file_path('Config.properties'),
        panel_config_file(),
    ];
    foreach ($cfgCandidates as $cf) {
        if (!is_file($cf)) continue;
        foreach (@file($cf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*(?:server\.)?event\s*=\s*(\d+)/i', $line, $m)) {
                $eid = (int)$m[1];
                return ['event_id' => $eid, 'enabled' => $eid > 0, 'source' => basename($cf)];
            }
        }
    }
    return $cur;
}
function write_src4_event_config(int $eid): string {
    // Ghi dung key server.event cho SRC_4 (ant) + giu tuong thich event= cho ban new03. Tra ve chuoi mo ta file da ghi.
    $done = [];
    $targets = [
        game_file_path('data/config/config.properties'),
        game_file_path('data/config/config.ini'),
        game_file_path('config/server.properties'),
        game_file_path('resources/config/server.properties'),
        game_file_path('Config.properties'),
    ];
    foreach ($targets as $cfgFile) {
        $dir = dirname($cfgFile);
        if (!is_dir($dir)) continue;
        $txt = is_file($cfgFile) ? (string)@file_get_contents($cfgFile) : '';
        $orig = $txt;
        if (preg_match('/^\s*server\.event\s*=.*$/mi', $txt)) {
            $txt = preg_replace('/^\s*server\.event\s*=.*$/mi', 'server.event=' . $eid, $txt);
        } else {
            $txt .= (strlen($txt) && substr($txt, -1) !== "\n" ? "\n" : '') . 'server.event=' . $eid . "\n";
        }
        // Giu tuong thich nguoc: dong bo them key event= (ban new03/TeamV2)
        if (preg_match('/^\s*event\s*=.*$/mi', $txt)) {
            $txt = preg_replace('/^\s*event\s*=.*$/mi', 'event=' . $eid, $txt);
        } else {
            $txt .= 'event=' . $eid . "\n";
        }
        if ($txt !== $orig && is_writable($dir)) {
            if (@file_put_contents($cfgFile, $txt, LOCK_EX) !== false) $done[] = $cfgFile;
        } elseif ($txt === $orig) {
            $done[] = $cfgFile . ' (da dung)';
        }
    }
    // Luon dam bao it nhat config mac dinh ton tai de lan restart sau giu event
    if (empty($done)) {
        $fallback = panel_config_file();
        $dir = dirname($fallback);
        if (is_dir($dir) && is_writable($dir)) {
            $txt = is_file($fallback) ? (string)@file_get_contents($fallback) : '';
            if (preg_match('/^\s*server\.event\s*=.*$/mi', $txt)) $txt = preg_replace('/^\s*server\.event\s*=.*$/mi', 'server.event=' . $eid, $txt);
            else $txt .= "\nserver.event=" . $eid . "\n";
            if (@file_put_contents($fallback, $txt, LOCK_EX) !== false) $done[] = $fallback;
        }
    }
    return implode(', ', $done);
}
$changeEventMsg = '';
$currentSrc4Event = get_current_src4_event();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_event') {
    require_csrf_token();
    if ($is_admin !== 1) { http_response_code(403); exit('Forbidden'); }
    $eid = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $en  = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 1;
    if ($eid < 0) $eid = 0;
    if (!$en) $eid = 0;
    $evName = $SRC4_EVENT_NAMES[$eid] ?? ('Event '.$eid);
    $cmd = sprintf('SET_EVENT|%d|%d', $eid, $en ? 1 : 0);
    $cmdOk = append_panel_command($cmd);
    @file_put_contents(game_file_path('panel_event_status.json'), json_encode(['event_id'=>$eid,'enabled'=>$en?true:false,'at'=>date('c')]), LOCK_EX);
    $cfgWrote = write_src4_event_config($eid);
    $currentSrc4Event = ['event_id' => $eid, 'enabled' => (bool)$en, 'source' => 'vua doi'];
    if ($cmdOk || $cfgWrote !== '') {
        $changeEventMsg = ($en ? 'Da BAT su kien ' : 'Da TAT su kien ') . $evName . ' (ID ' . $eid . '). '
            . 'Config server.event da luu' . ($cfgWrote !== '' ? (' (' . $cfgWrote . ')') : '') . '. '
            . 'De giao dien game doi ngay: restart Game Server (menu [8] > Stop > Start) roi vao lai game. '
            . 'Lenh live SET_EVENT da gui qua panel_cmd.txt cho watcher (neu co cai).';
    } else {
        $changeEventMsg = 'Khong the ghi lenh su kien (game_root chua tro dung toi SRC_4 hoac chua writable). Kiem tra GAME_ROOT trong game_bridge.php.';
    }
}
// SRC4-EVENT-BACKEND-OK (server.event + panel_event_status.json + SET_EVENT live)

// ── MAINTENANCE LOGIC ──
$maintenanceMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'maintenance') {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $command = 'MAINTENANCE_NOW';
    if (append_panel_command($command)) {
        $maintenanceMsg = 'Lệnh bảo trì đã được gửi, server sẽ thực hiện bảo trì trong 30 giây.';
    } else {
        $maintenanceMsg = 'Không thể ghi lệnh bảo trì vào file điều khiển game.';
    }
}


// ── EXP SERVER LOGIC ──
$expMsg = '';
$currentExpServer = get_current_exp_server();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_exp' && isset($_POST['exp_rate'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $expRate = (int)str_replace('.', '', (string)$_POST['exp_rate']);
    if ($expRate <= 0) {
        $expMsg = 'EXP server phải > 0!';
    } else {
        $command = sprintf('SET_EXP|%d', $expRate);
        if (append_panel_command($command)) {
            $expMsg = 'Đã tăng EXP server thành x' . number_format($expRate, 0, ',', '.');
            @file_put_contents(panel_exp_status_file(), (string)$expRate, LOCK_EX);
            $currentExpServer = $expRate;
        } else {
            $expMsg = 'Không thể ghi lệnh EXP vào file điều khiển game.';
        }
    }
}
// ── DROP SERVER LOGIC ──
$dropMsg = '';
$currentDropServer = get_current_drop_server();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_drop' && isset($_POST['drop_rate'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $dropRate = (int)str_replace('.', '', (string)$_POST['drop_rate']);
    if ($dropRate <= 0) {
        $dropMsg = 'Tỉ lệ rơi đồ phải > 0!';
    } else {
        $command = sprintf('SET_DROP|%d', $dropRate);
        if (append_panel_command($command)) {
            $dropMsg = 'Đã chỉnh tỉ lệ rơi đồ server thành x' . number_format($dropRate, 0, ',', '.');
            @file_put_contents(panel_drop_status_file(), (string)$dropRate, LOCK_EX);
            $currentDropServer = $dropRate;
        } else {
            $dropMsg = 'Không thể ghi lệnh rơi đồ vào file điều khiển game.';
        }
    }
}

// ── GIFTCODE ──
$bossMsg = '';
$bossEditMsg = '';
$bossPersistMsg = '';
$currentBossPersistMode = get_current_boss_persist_mode();
$panelBosses = get_panel_bosses();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'spawn_boss' && isset($_POST['boss_key'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }

    $parts = explode('|', (string)$_POST['boss_key']);
    $bossId = isset($parts[0]) ? (int)$parts[0] : 0;
    $mapId = isset($parts[1]) ? (int)$parts[1] : -1;
    $zoneId = isset($parts[2]) ? (int)$parts[2] : -1;

    if ($bossId === 0 && trim((string)($parts[0] ?? '')) !== '0') {
        $bossMsg = 'Boss khong hop le!';
    } else {
        $command = sprintf('SPAWN_BOSS|%d|%d|%d', $bossId, $mapId, $zoneId);
        if (append_panel_command($command)) {
            $bossMsg = 'Đã gửi lệnh gọi Boss ID ' . $bossId . '. Boss cũ sẽ bị xóa và tạo ra Boss mới.';
        } else {
            $bossMsg = '❌ Không thể ghi lệnh gọi Boss vào file điều khiển game.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'boss_persist_mode') {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }
    $enabled = (($_POST['enabled'] ?? '0') === '1');
    if (append_panel_command('BOSS_PERSIST_MODE|' . ($enabled ? '1' : '0'))) {
        @file_put_contents(panel_boss_persist_status_file(), $enabled ? '1' : '0', LOCK_EX);
        if (!$enabled) {
            @unlink(panel_boss_overrides_file());
        }
        $currentBossPersistMode = $enabled;
        $bossPersistMsg = $enabled
            ? 'Đã bật lưu HP Boss.'
            : 'Đã tắt lưu HP Boss.';
    } else {
        $bossPersistMsg = 'Khong the ghi lenh bat/tat luu HP boss.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_boss' && isset($_POST['boss_key'])) {
    require_csrf_token();
    if ($is_admin !== 1) {
        http_response_code(403);
        exit('Forbidden');
    }

    $parts = explode('|', (string)$_POST['boss_key']);
    $bossId = isset($parts[0]) ? (int)$parts[0] : 0;
    $mapId = isset($parts[1]) ? (int)$parts[1] : -1;
    $zoneId = isset($parts[2]) ? (int)$parts[2] : -1;
    $bossName = trim((string)($_POST['boss_name'] ?? ''));
    $bossHp = (int)str_replace('.', '', (string)($_POST['boss_hp'] ?? '0'));

    if ($bossId === 0 && trim((string)($parts[0] ?? '')) !== '0') {
        $bossEditMsg = 'Boss khong hop le!';
    } elseif ($mapId < 0 || $zoneId < 0) {
        $bossEditMsg = 'Chi sua duoc boss dang hoat dong trong map.';
    } elseif ($bossName === '') {
        $bossEditMsg = 'Ten boss khong duoc de trong!';
    } elseif ($bossHp <= 0) {
        $bossEditMsg = 'HP boss phai > 0!';
    } elseif ($bossHp > 2000000000) {
        $bossEditMsg = 'HP boss khong duoc vuot qua 2 ty!';
    } else {
        $bossName = str_replace(["\r", "\n", "|"], ' ', $bossName);
        $command = sprintf('EDIT_BOSS|%d|%d|%d|%d|%s', $bossId, $mapId, $zoneId, $bossHp, rawurlencode($bossName));
        if (append_panel_command($command)) {
            $bossEditMsg = 'Đã sửa Boss ' . $bossName . ' HP ' . number_format($bossHp, 0, ',', '.');
            if ($currentBossPersistMode) {
                $overridesFile = panel_boss_overrides_file();
                $lines = is_file($overridesFile) ? (@file($overridesFile, FILE_IGNORE_NEW_LINES) ?: []) : [];
                $nextLines = [];
                foreach ($lines as $line) {
                    if (!preg_match('/^' . preg_quote((string)$bossId, '/') . '\.(hp|name)=/', $line)) {
                        $nextLines[] = $line;
                    }
                }
                $nextLines[] = $bossId . '.hp=' . $bossHp;
                $nextLines[] = $bossId . '.name=' . str_replace(["\r", "\n"], ' ', $bossName);
                @file_put_contents($overridesFile, implode(PHP_EOL, $nextLines) . PHP_EOL, LOCK_EX);
            }
        } else {
            $bossEditMsg = '❌ Không thể sửa Boss.';
        }
    }
}

$giftcodes = [];
try {
    $stmtGc      = $pdo->query("SELECT * FROM giftcode WHERE type = 0 ORDER BY id ASC");
    $rawGiftcodes = $stmtGc->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rawGiftcodes as $gc) {
        $details    = [];
        $detailArr  = json_decode($gc['detail'] ?? '', true);
        if (is_array($detailArr)) {
            foreach ($detailArr as $item) {
                $itemId     = $item['id'] ?? null;
                $qty        = $item['quantity'] ?? 1;
                $optionsRaw = $item['options'] ?? [];
                $iconId     = null;
                $itemName   = '';
                if ($itemId !== null) {
                    $stmtIt = $pdo->prepare("SELECT id, icon_id, name FROM item_template WHERE id = :id LIMIT 1");
                    $stmtIt->bindParam(":id", $itemId);
                    $stmtIt->execute();
                    $itRow = $stmtIt->fetch(PDO::FETCH_ASSOC);
                    if ($itRow) { $iconId = $itRow['icon_id']; $itemName = $itRow['name']; }
                }
                $optionLabels = [];
                if (is_array($optionsRaw)) {
                    foreach ($optionsRaw as $opt) {
                        $optId    = $opt['id']    ?? null;
                        $optParam = $opt['param'] ?? 0;
                        if ($optId !== null) {
                            $stmtOpt = $pdo->prepare("SELECT name FROM item_option_template WHERE id = :id LIMIT 1");
                            $stmtOpt->bindParam(":id", $optId);
                            $stmtOpt->execute();
                            $optRow = $stmtOpt->fetch(PDO::FETCH_ASSOC);
                            if ($optRow) {
                                $name = $optRow['name'];
                                $optionLabels[] = strpos($name, '#') !== false
                                    ? str_replace('#', $optParam, $name)
                                    : $name . ': ' . $optParam;
                            }
                        }
                    }
                }
                $details[] = ['icon_id' => $iconId, 'name' => $itemName, 'quantity' => $qty, 'options' => $optionLabels];
            }
        }
        $giftcodes[] = ['code' => $gc['code'], 'count_left' => $gc['count_left'], 'details' => $details];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Panel Website</title>
<link rel="icon" type="image/jpeg" href="../../img/icon.jpg">
<style>
:root {
  font-size: 18.876px;
  --bg:      #ddeeff;
  --surf:    #ffffff;
  --surf2:   #f0f7ff;
  --bd:      #9bbedd;
  --bd2:     #7aaacf;
  --tx:      #09131f;
  --tx2:     #1a3a5c;
  --tx3:     #4a7a9b;
  --blue:    #0d5fa3;
  --blue2:   #1272c2;
  --success: #0a8a5a;
  --danger:  #d92b3a;
  --warn:    #c47a00;
  --blu-lt:  #cce3f8;
  --glow:    rgba(13,95,163,.15);
  --r:       13px;
  --rs:      8px;
  --sh:      0 4px 24px rgba(13,95,163,.13);
  --sh2:     0 1px 8px rgba(13,95,163,.08);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; scroll-behavior: smooth; }
body {
  font-family: 'Times New Roman', Times, serif;
  font-size: 1rem; line-height: 1.6;
  background: var(--bg); color: var(--tx);
  min-height: 100dvh; -webkit-font-smoothing: antialiased; overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background-image: radial-gradient(circle, var(--bd) 1px, transparent 1px);
  background-size: 22px 22px; opacity: .45;
}

/* LOGOUT BTN */
.logout-circle {
  position: fixed; bottom: 28px; right: 28px; z-index: 1000;
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(135deg, #d92b3a, #b71c2a);
  border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(217,43,58,.4), 0 0 0 2px rgba(255,255,255,.2);
  transition: all 0.3s cubic-bezier(0.68,-0.55,0.265,1.55);
  animation: pulse-glow 2s infinite;
}
.logout-circle:hover { transform: scale(1.15) rotate(5deg); box-shadow: 0 8px 30px rgba(217,43,58,.7), 0 0 0 4px rgba(255,255,255,.4); }
.logout-circle:active { transform: scale(0.92) rotate(-5deg); }
.logout-circle img { width: 100%; height: 100%; object-fit: cover; display: block; }
@keyframes pulse-glow {
  0%,100% { box-shadow: 0 4px 20px rgba(217,43,58,.4), 0 0 0 2px rgba(255,255,255,.2); }
  50%      { box-shadow: 0 4px 25px rgba(217,43,58,.7), 0 0 0 5px rgba(255,255,255,.3); }
}

/* LAYOUT */
.layout { position: relative; z-index: 1; display: grid; grid-template-columns: 248px 1fr; min-height: 100vh; }

/* SIDEBAR */
.sidebar {
  background: var(--surf); border-right: 1.5px solid var(--bd2);
  padding: 1.2rem 0 2rem; display: flex; flex-direction: column; gap: 0; box-shadow: var(--sh2);
}
.char-card {
  padding: 1.2rem 1.2rem 1rem; border-bottom: 1px solid var(--bd);
  display: flex; flex-direction: column; align-items: center; gap: 0.55rem;
}
.panel-boss-grid {
  display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: 12px;
  max-height: calc(90vh - 220px); overflow: auto; padding: 2px 4px 4px 2px; margin: 12px 0 18px;
}
.panel-boss-card {
  border: 1.5px solid var(--bd2); border-radius: 12px; background: var(--surf2);
  padding: 10px; text-align: left; cursor: pointer; font-family: 'Times New Roman', Times, serif;
  display: grid; gap: 7px; min-width: 0;
  transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.panel-boss-card:hover { transform: translateY(-2px); border-color: var(--blue2); box-shadow: 0 7px 18px rgba(13,95,163,.16); }
.panel-boss-card.is-selected {
  border: 2px solid var(--success); background: rgba(10,138,90,.16);
  box-shadow: 0 0 0 3px rgba(10,138,90,.18), 0 12px 26px rgba(10,138,90,.24);
  transform: translateY(-3px);
  animation: panel-boss-picked .28s ease;
}
@keyframes panel-boss-picked {
  0% { transform: scale(.96); }
  70% { transform: scale(1.035); }
  100% { transform: translateY(-3px) scale(1); }
}
.panel-boss-thumb {
  position: relative; height: 92px; border-radius: 9px; background: var(--surf);
  display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.panel-boss-thumb img { max-width: 100%; max-height: 100%; object-fit: contain; }
.panel-boss-state {
  position: absolute; top: 7px; left: 7px; width: 22px; height: 22px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .78rem; font-weight: 700;
}
.panel-boss-state.is-alive { background: var(--success); }
.panel-boss-state.is-dead { background: var(--danger); }
.panel-boss-card.is-selected .panel-boss-state {
  width: 28px; height: 28px; font-size: .95rem;
  box-shadow: 0 0 0 3px rgba(10,138,90,.18);
}
.panel-boss-name { font-size: .9rem; font-weight: 700; color: var(--tx); line-height: 1.2; overflow-wrap: anywhere; }
.panel-boss-meta { font-size: .78rem; color: var(--tx2); line-height: 1.3; overflow-wrap: anywhere; }
.panel-boss-empty { color: var(--tx3); text-align: center; padding: 18px 8px; border: 1px dashed var(--bd2); border-radius: 10px; }
.edit-boss-box {
  width:min(1480px, calc(100vw - 40px)); height:min(90dvh, 820px);
  max-width:none;
  display:flex; flex-direction:column; text-align:left; padding:18px; overflow:hidden;
}
#editBossModal .edit-boss-box { max-width:none; width:min(1480px, calc(100vw - 40px)); }
.edit-boss-title { font-size:1.25rem; font-weight:800; color:var(--blue2); margin-bottom:10px; }
.edit-boss-form-shell { display:flex; flex-direction:column; min-height:0; flex:1; }
.panel-boss-edit-layout { display:grid; grid-template-columns:minmax(0,1fr) minmax(300px,340px); gap:14px; min-height:0; flex:1; align-items:stretch; overflow:hidden; }
.panel-boss-edit-left { display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.panel-boss-edit-toolbar {
  display:flex; justify-content:space-between; align-items:center; gap:12px;
  padding:10px 12px; border:1.5px solid var(--bd2); border-radius:10px; background:var(--surf2); margin-bottom:10px;
}
.panel-boss-edit-toolbar strong { color:var(--tx); font-size:1rem; }
.panel-boss-edit-toolbar span { color:var(--tx3); font-size:.82rem; }
.panel-boss-edit-list {
  display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px;
  flex:1; min-height:0; overflow:auto; padding:2px 4px 4px 2px;
  -webkit-overflow-scrolling:touch;
}
.panel-boss-edit-card {
  border:1.5px solid var(--bd2); border-radius:10px; background:var(--surf2);
  padding:8px; text-align:left; cursor:pointer; font-family:'Times New Roman',Times,serif;
  display:grid; grid-template-columns:1fr; gap:6px; align-content:start;
  min-height:170px; min-width:0;
  transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease,background .16s ease;
}
.panel-boss-edit-card:hover { transform:translateY(-1px); border-color:var(--blue2); box-shadow:0 7px 18px rgba(13,95,163,.14); }
.panel-boss-edit-card.is-selected {
  border:2px solid var(--success); background:rgba(10,138,90,.14);
  box-shadow:0 0 0 3px rgba(10,138,90,.16),0 10px 22px rgba(10,138,90,.18);
}
.panel-boss-edit-avatar {
  position:relative; width:100%; height:78px; border-radius:8px; background:var(--surf);
  display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--bd);
}
#editBossModal .panel-boss-edit-avatar img {
  width:auto; height:auto; max-width:100%; max-height:100%; object-fit:contain;
  border:0; border-radius:0; box-shadow:none; margin:0;
}
.panel-boss-edit-status {
  position:absolute; top:5px; left:5px; min-width:20px; height:20px; border-radius:999px;
  display:inline-flex; align-items:center; justify-content:center; color:#fff; background:var(--success); font-size:.72rem; font-weight:700;
}
.panel-boss-edit-main { min-width:0; display:grid; gap:5px; }
.panel-boss-edit-name { color:var(--tx); font-size:.9rem; font-weight:800; line-height:1.15; overflow-wrap:anywhere; }
.panel-boss-edit-map { color:var(--tx2); font-size:.76rem; line-height:1.22; overflow-wrap:anywhere; }
.panel-boss-edit-stats { display:grid; gap:5px; min-width:0; }
.panel-boss-edit-hp-text { display:block; color:var(--tx2); font-size:.72rem; font-weight:700; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.panel-boss-edit-hp-text span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.panel-boss-edit-hpbar { height:7px; border-radius:999px; background:rgba(217,43,58,.12); overflow:hidden; border:1px solid rgba(217,43,58,.18); }
.panel-boss-edit-hpfill { height:100%; min-width:4px; border-radius:999px; background:linear-gradient(90deg,var(--success),var(--warn)); }
.panel-boss-edit-form { border:1.5px solid var(--bd2); border-radius:10px; background:var(--surf2); padding:14px; align-self:start; position:sticky; top:0; width:100%; }
.panel-boss-edit-form-title { font-size:1rem; font-weight:800; color:var(--tx); margin-bottom:10px; }
.panel-boss-persist-box { display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid var(--bd2); border-radius:8px; padding:10px; margin-bottom:12px; background:var(--surf); }
.panel-boss-persist-box strong { display:block; color:var(--tx); font-size:.92rem; line-height:1.2; }
.panel-boss-persist-box span { display:block; color:var(--tx3); font-size:.78rem; line-height:1.25; margin-top:2px; }
.panel-boss-persist-toggle { min-width:76px; min-height:38px; border-radius:999px; border:1.5px solid var(--bd2); background:var(--surf2); color:var(--tx2); font-weight:800; cursor:pointer; font-family:'Times New Roman',Times,serif; }
.panel-boss-persist-toggle.is-on { border-color:rgba(10,138,90,.45); background:rgba(10,138,90,.12); color:var(--success); }
.panel-boss-edit-form label { display:block; margin-bottom:5px; font-weight:700; color:var(--tx2); }
.panel-boss-edit-form input { width:100%; min-height:44px; padding:10px; margin-bottom:14px; border:1px solid var(--bd2); border-radius:4px; font-size:1.05rem; font-weight:700; font-family:'Times New Roman',Times,serif; }
@media (min-width: 900px) {
  .panel-boss-edit-list { grid-template-columns:repeat(6,minmax(0,1fr)); }
  .panel-boss-edit-form { padding:16px; }
  .panel-boss-edit-form input { min-height:46px; }
}
@media (min-width: 1280px) {
  .edit-boss-box, #editBossModal .edit-boss-box { width:min(1500px, calc(100vw - 56px)); max-width:none; }
  .panel-boss-edit-layout { grid-template-columns:minmax(0,1fr) 350px; gap:18px; }
}
@media (max-width: 768px) {
  #bossModal .modal-box { width: calc(100vw - 20px); padding: 1rem; }
  #editBossModal { align-items:flex-start; overflow:auto; padding:8px 0; }
  #editBossModal .modal-box { width: calc(100vw - 16px); height:auto; min-height:calc(100dvh - 16px); max-height:none; padding:12px; border-radius:12px; }
  .edit-boss-title { font-size:1.12rem; margin-bottom:8px; }
  .panel-boss-grid { grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
  .panel-boss-edit-layout { grid-template-columns:1fr; gap:10px; }
  .panel-boss-edit-layout > div:first-child { order:2; }
  .panel-boss-edit-left { overflow:visible; }
  .panel-boss-edit-form { order:1; }
  .panel-boss-edit-list { max-height:none; grid-template-columns:repeat(2,minmax(0,1fr)); overflow:visible; padding-right:0; }
  .panel-boss-edit-form { position:static; }
  .panel-boss-card { padding: 8px; }
  .panel-boss-thumb { height: 78px; }
}
@media (max-width: 420px) {
  .panel-boss-edit-toolbar { padding:9px 10px; align-items:flex-start; }
  .panel-boss-edit-toolbar strong { font-size:.95rem; }
  .panel-boss-edit-card { min-height:154px; padding:7px; gap:5px; }
  .panel-boss-edit-avatar { height:64px; }
  .panel-boss-edit-name { font-size:.94rem; }
  .panel-boss-edit-map { font-size:.78rem; }
  .panel-boss-edit-form input { font-size:1rem; }
}
.avatar-wrap { position: relative; width: 80px; height: 80px; }
.avatar-wrap img {
  width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
  border: 3px solid var(--blue2);
  box-shadow: 0 0 16px var(--glow), 0 0 5px rgba(13,95,163,.2);
}
.gender-badge {
  position: absolute; bottom: 2px; right: 2px;
  background: var(--blu-lt); border: 2px solid var(--bd2); border-radius: 50%;
  width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
  font-size: 0.7rem; font-weight: 700; color: var(--blue);
}
.char-name-frame {
  background: rgba(13,95,163,.08); border: 1.5px solid rgba(13,95,163,.3);
  border-radius: var(--rs); padding: 0.35rem 0.9rem;
  font-size: 0.9rem; font-weight: 700; color: var(--tx2); text-align: center; margin-top: 0.2rem;
}
.char-status { font-size: 0.78rem; font-weight: 700; padding: 0.18rem 0.75rem; border-radius: 999px; letter-spacing: 0.04em; }
.status-active   { background: rgba(10,138,90,.12); border: 1px solid rgba(10,138,90,.4);  color: var(--success); }
.status-inactive { background: rgba(217,43,58,.1);  border: 1px solid rgba(217,43,58,.35); color: var(--danger);  }
.sidebar-card {
  margin: 0.6rem 0.85rem 0; background: var(--surf2);
  border: 1.5px solid var(--bd2); border-radius: var(--r); padding: 0.9rem 1rem; box-shadow: var(--sh2);
}
.sidebar-card-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; color: var(--blue2); text-transform: uppercase; margin-bottom: 0.5rem; }
.sidebar-btn {
  display: block; width: 100%; margin-top: 6px; padding: 7px 10px;
  border-radius: var(--rs); border: 1.5px solid var(--bd2); background: var(--surf); color: var(--tx2);
  cursor: pointer; font-family: 'Times New Roman', Times, serif; font-weight: 700; font-size: 0.88rem;
  transition: all .15s; text-align: center; text-decoration: none;
}
.sidebar-btn:hover { background: var(--blu-lt); border-color: var(--blue2); }
.btn-danger-sidebar {
  display: block; width: 100%; margin-top: 6px; padding: 9px 10px;
  border-radius: var(--rs); border: 1.5px solid #1e8449;
  background: linear-gradient(135deg,#27ae60,#1e8449);
  color: #fff; text-align: center; font-weight: 700; text-decoration: none;
  font-family: 'Times New Roman', Times, serif; font-size: 0.88rem; transition: all .2s; cursor: pointer;
}
.btn-danger-sidebar:hover { transform: scale(1.02); box-shadow: 0 4px 14px rgba(39,174,96,.35); }
.btn-forum-sidebar {
  border-color: #2d7dd2;
  background: linear-gradient(135deg,#3ba7ff,#1f5fbf);
}
.btn-forum-sidebar:hover { box-shadow: 0 4px 14px rgba(59,167,255,.35); }
.sidebar-nav { display: flex; flex-direction: column; padding: 0.6rem 0; flex: 1; gap: 1px; }

/* MAIN */
.main { position: relative; z-index: 1; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; overflow-y: auto; }

/* STATS */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.stat-card {
  background: var(--surf); border: 1.5px solid var(--bd2); border-radius: var(--r); padding: 1.2rem;
  display: flex; flex-direction: column; gap: 0.5rem; box-shadow: var(--sh2); transition: transform .18s, box-shadow .18s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--sh); }
.stat-card-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em; color: var(--blue2); text-transform: uppercase; }
.stat-card-value {
  font-size: 1.35rem; font-weight: 700; color: var(--warn);
  display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}
.no-topup-box { max-width: 420px; }
.no-topup-icon { font-size:2.8rem; margin-bottom:10px; }
.no-topup-title { font-size:1.25rem; font-weight:700; color:var(--warn); margin-bottom:8px; }
.no-topup-lines { color:var(--tx3); margin-bottom:18px; line-height:1.5; font-size:0.95rem; }
.no-topup-lines span { display:block; margin-top:4px; }
.no-topup-lines .main-line { color:var(--danger); font-weight:800; }
.no-topup-actions { display:flex; gap:10px; }
.no-topup-actions button { flex:1; padding:11px; border-radius:var(--rs); font-weight:700; cursor:pointer; font-family:'Times New Roman',Times,serif; }
.no-topup-actions .danger { border:1.5px solid rgba(217,43,58,.4); background:rgba(217,43,58,.1); color:var(--danger); }
.no-topup-actions .cancel { border:1.5px solid var(--bd2); background:var(--surf2); color:var(--tx2); }

/* SECTION TITLE */
.section-title {
  font-size: 1rem; font-weight: 700; color: var(--blue2); letter-spacing: 0.04em;
  margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;
}
.section-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, var(--bd2), transparent); }

/* BXH */
.bxh-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.9rem; }
.bxh-card {
  background: linear-gradient(135deg,#27ae60,#1e8449); border: 1.5px solid #1e8449;
  border-radius: var(--r); padding: 1.1rem; text-decoration: none;
  display: flex; align-items: center; gap: 0.9rem; transition: all .2s;
  box-shadow: 0 4px 16px rgba(39,174,96,.3);
}
.bxh-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(39,174,96,.5); }
.bxh-icon { font-size: 1.8rem; width: 46px; height: 46px; background: rgba(0,0,0,.12); border-radius: var(--rs); display: flex; align-items: center; justify-content: center; }
.bxh-info strong { display: block; font-size: 0.95rem; color: #fff; font-weight: 700; }
.bxh-info span { font-size: 0.78rem; color: rgba(255,255,255,.82); }

/* ĐIỂM DANH */
.diemdanh-card { background: var(--surf); border: 1.5px solid var(--bd2); border-radius: var(--r); padding: 1rem 1.2rem; box-shadow: var(--sh2); }
.diemdanh-inner { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.diemdanh-icon {
  font-size: 2rem; width: 54px; height: 54px; border-radius: var(--rs);
  background: linear-gradient(135deg, rgba(196,122,0,.12), rgba(196,122,0,.05));
  border: 1px solid rgba(196,122,0,.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.diemdanh-info { flex: 1; min-width: 120px; }
.diemdanh-info h4 { font-size: 0.95rem; font-weight: 700; color: var(--warn); margin-bottom: 2px; }
.diemdanh-info p { font-size: 0.8rem; color: var(--tx3); line-height: 1.4; }
.diemdanh-info .reward-range { font-weight: 700; color: var(--success); }
.btn-diemdanh {
  padding: 0.62rem 1.3rem; border-radius: var(--rs); border: none;
  font-family: 'Times New Roman', Times, serif; font-weight: 700; font-size: 0.95rem;
  cursor: pointer; transition: all .2s; white-space: nowrap; flex-shrink: 0;
}
.btn-diemdanh.can-claim {
  background: linear-gradient(135deg, #f5c842, #e8a020);
  color: #1a1000; box-shadow: 0 4px 14px rgba(196,122,0,.35);
}
.btn-diemdanh.can-claim:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 6px 20px rgba(196,122,0,.5); }
.btn-diemdanh.can-claim:disabled { opacity: .7; cursor: not-allowed; transform: none; }
.btn-diemdanh.claimed { background: var(--surf2); border: 1.5px solid var(--bd2); color: var(--tx3); cursor: not-allowed; }

/* ALERT / THÔNG BÁO */
.alert-banner {
  padding: 0.9rem 1.3rem; border-radius: var(--r);
  display: flex; align-items: center; gap: 0.9rem;
  animation: rewardPop 0.5s cubic-bezier(.34,1.56,.64,1) forwards;
  transition: opacity 0.4s ease;
}
.alert-success { border: 1.5px solid rgba(10,138,90,.4);  background: rgba(10,138,90,.08); }
.alert-warn    { border: 1.5px solid rgba(196,122,0,.4);  background: rgba(196,122,0,.08); }
@keyframes rewardPop {
  0%   { transform: scale(.7) translateY(16px); opacity: 0; }
  60%  { transform: scale(1.06) translateY(-3px); opacity: 1; }
  100% { transform: scale(1) translateY(0); opacity: 1; }
}

/* GIFTCODE */
.gc-quick-list {
  display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; padding: 1rem 1.2rem;
  background: linear-gradient(135deg, #27ae60, #1e8449); border: 1.5px solid #1e8449;
  border-radius: var(--r); box-shadow: 0 4px 16px rgba(39,174,96,.3);
}
.gc-quick-item {
  display: flex; align-items: center; gap: 0.4rem;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
  border-radius: var(--rs); padding: 0.35rem 0.9rem;
  font-size: 1rem; font-weight: 700; color: #fff; letter-spacing: 0.05em; transition: all .2s;
}
.gc-quick-item:hover { transform: translateY(-2px); background: rgba(255,255,255,.25); box-shadow: 0 4px 12px rgba(0,0,0,.2); }
.gc-quick-left { font-size: 0.72rem; font-weight: 700; color: #1e8449; background: rgba(255,255,255,.9); border-radius: 4px; padding: 0.1rem 0.4rem; margin-right: 5px; }
.giftcode-list { display: flex; flex-direction: column; gap: 1rem; }
.gc-card {
  background: linear-gradient(135deg, #d4eaf5, #c0deed); border: 1.5px solid #8fb5cc;
  border-radius: var(--r); padding: 1.1rem 1.3rem;
  display: flex; flex-direction: column; gap: 0.75rem;
  box-shadow: 0 4px 16px rgba(100,130,140,.2); transition: all 0.25s ease;
  position: relative; overflow: hidden;
}
.gc-card::before { content: '🎁'; position: absolute; bottom: -20px; right: -20px; font-size: 80px; opacity: 0.12; pointer-events: none; }
.gc-card:hover { transform: translateX(4px) translateY(-2px); box-shadow: 0 8px 28px rgba(100,130,140,.4); }
.gc-top { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; border-bottom: 1px dashed rgba(50,80,90,.3); padding-bottom: 0.6rem; }
.gc-code { font-size: 1.1rem; font-weight: 800; color: #1a4a5a; letter-spacing: 0.1em; background: rgba(255,255,255,.6); padding: 0.3rem 0.9rem; border-radius: var(--rs); border: 1px solid rgba(50,80,90,.2); }
.gc-left { font-size: 0.85rem; color: #1a4a5a; font-weight: 600; background: rgba(255,255,255,.5); padding: 0.2rem 0.8rem; border-radius: 20px; }
.gc-left span { color: #004d40; font-weight: 800; font-size: 1rem; }
.gc-items { display: flex; flex-wrap: wrap; gap: 0.8rem; margin-top: 0.2rem; }
.gc-item {
  display: flex; flex-direction: column; align-items: center; gap: 0.25rem;
  background: rgba(255,255,255,.35); border: 1.5px solid rgba(50,80,90,.2);
  border-radius: var(--rs); padding: 0.6rem 0.7rem; min-width: 70px; transition: all .2s;
}
.gc-item:hover { transform: scale(1.05); background: rgba(255,255,255,.5); box-shadow: 0 4px 12px rgba(0,0,0,.05); }
.gc-item img { width: 42px; height: 42px; object-fit: contain; border-radius: 6px; image-rendering: pixelated; filter: drop-shadow(0 1px 2px rgba(0,0,0,.1)); }
.gc-item-qty  { font-size: 0.75rem; font-weight: 800; color: #004d40; background: rgba(255,255,255,.7); padding: 0.1rem 0.4rem; border-radius: 12px; }
.gc-item-opts { font-size: 0.65rem; color: #1a4a5a; text-align: center; line-height: 1.3; }
.gc-item-name { font-size: 0.7rem; color: #1a3a4a; text-align: center; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.no-gc { color: var(--tx3); font-size: 0.88rem; padding: 1.5rem; text-align: center; border: 1.5px dashed var(--bd2); border-radius: var(--r); background: var(--surf2); }

/* ADMIN */
.admin-section-stack { display: flex; flex-direction: column; gap: 1rem; }
.admin-menu-card {
  background: var(--surf); border: 1.5px solid var(--bd2); border-radius: var(--r);
  padding: 1rem; box-shadow: var(--sh2);
}
.admin-menu-card.ops {
  border-color: rgba(196,122,0,.45);
  background: linear-gradient(135deg, rgba(255,248,226,.95), rgba(255,255,255,.98));
}
.admin-menu-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.75rem; }
.admin-menu-head strong { display: block; color: var(--tx2); font-size: 0.98rem; }
.admin-menu-head span { display: block; color: var(--tx3); font-size: 0.78rem; margin-top: 1px; }
.admin-menu-card.ops .admin-menu-head strong { color: var(--warn); }
.admin-menu-toggle {
  border: 1.5px solid var(--bd2);
  background: var(--surf2);
  color: var(--blue);
  border-radius: var(--rs);
  padding: 0.42rem 0.75rem;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  font-family:'Times New Roman',Times,serif;
  white-space: nowrap;
}
.admin-menu-toggle:hover { background: var(--blu-lt); border-color: var(--blue2); }
.admin-menu-card.is-collapsible.is-collapsed .admin-grid { display: none; }
.admin-menu-card.is-collapsible.is-collapsed .admin-menu-head { margin-bottom: 0; }
.admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(125px, 1fr)); gap: 0.7rem; }
.admin-btn {
  display: flex; flex-direction: column; align-items: center; gap: 0.38rem;
  padding: 0.85rem 0.5rem; background: var(--surf); border: 1.5px solid var(--bd2);
  border-radius: var(--r); text-decoration: none; color: var(--tx2);
  font-weight: 700; font-family: 'Times New Roman', Times, serif; font-size: 0.88rem;
  transition: all .18s; cursor: pointer; text-align: center;
}
.admin-btn:hover { background: var(--blu-lt); border-color: var(--blue2); transform: translateY(-2px); box-shadow: var(--sh2); color: var(--blue); }
.admin-btn .ab-icon { font-size: 1.55rem; }

/* MODAL */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(9,19,31,.65); backdrop-filter: blur(6px);
  z-index: 9999; align-items: center; justify-content: center;
}
.modal-box {
  background: var(--surf); border: 1.5px solid var(--bd2); border-radius: var(--r);
  padding: 2rem; max-width: 420px; width: 90%; text-align: center;
  box-shadow: var(--sh); animation: popIn .22s ease forwards;
}
.modal-box img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid var(--blue2); box-shadow: var(--sh2); margin-bottom: 14px; }
@keyframes popIn { from { transform: scale(.88); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* SCROLLBAR */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--surf2); }
::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }

/* RESPONSIVE */
@media (max-width: 768px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { border-right: none; border-bottom: 1.5px solid var(--bd2); }
  .char-card { flex-direction: row; align-items: center; gap: 1rem; }
  .main { padding: 1rem; }
  .logout-circle { bottom: 20px; right: 20px; width: 48px; height: 48px; }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .admin-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
</head>
<body>

<button class="logout-circle" onclick="openLogoutModal(event)" title="Đăng xuất">
  <img src="/img/b.png" alt="Đăng xuất">
</button>

<div class="layout">
  <aside class="sidebar">
    <div class="char-card">
      <div class="avatar-wrap">
        <img src="<?= htmlspecialchars($genderIcon) ?>" alt="avatar" onerror="this.src='/img/22.jpg'">
        <div class="gender-badge"><?= $gender == 0 ? '♂' : ($gender == 1 ? '♀' : '⚔') ?></div>
      </div>
      <div class="char-name-frame">Player: <?= htmlspecialchars($playerName) ?></div>
      <?php if ($active == 1): ?>
        <div class="char-status status-active">✔ MTV</div>
      <?php else: ?>
        <div class="char-status status-inactive">✖ MTV</div>
      <?php endif; ?>
    </div>

    <div class="sidebar-card">
      <div class="sidebar-card-label">Làm Quả Lọ ⚙️</div>
      <button onclick="toggleForm()" class="sidebar-btn">➕ Mở / Đóng</button>
      <div id="loaForm" style="display:none;margin-top:10px;">
        <input id="loaInput" type="text" placeholder="Nhập Link or Key..."
          style="width:100%;padding:10px;font-size:0.92rem;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx);outline:none;font-family:'Times New Roman',Times,serif;">
        <button onclick="addLink()"
          style="width:100%;margin-top:6px;padding:6px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;cursor:pointer;font-family:'Times New Roman',Times,serif;">
          ➕ Lưu Link
        </button>
        <div id="linkList" style="margin-top:8px;display:flex;flex-direction:column;gap:5px;"></div>
      </div>
      <div id="loaStatus" style="margin-top:6px;font-size:0.72rem;color:var(--tx3);">Chưa có link</div>
    </div>

    <div class="sidebar-card">
      <div class="sidebar-card-label">MENU ⚠️</div>
      <a href="#" onclick="showNoTopupNotice(event)" class="btn-danger-sidebar">☠️ NẠP TIỀN</a>
      <!-- Đã chuyển Góp ý và Khiếu nại thành BXH -->
      <a href="/sv1/bxh01" class="btn-danger-sidebar">🏆 ĐUA TOP</a>
      <a href="/sv1/bxh02" class="btn-danger-sidebar">🥇 Tài nguyên</a>
      <a href="/sv1/trangchu#communityComments" class="btn-danger-sidebar btn-forum-sidebar">💬 Về Diễn Đàn</a>
    </div>
    <nav class="sidebar-nav"></nav>
  </aside>

  <main class="main">

    <?php if (!empty($diemDanhMsg)): ?>
    <div id="ddAlert" class="alert-banner <?= $diemDanhResult === 'success' ? 'alert-success' : 'alert-warn' ?>">
      <span style="font-size:1.9rem;"><?= $diemDanhResult === 'success' ? '🎉' : '⚠️' ?></span>
      <div style="flex:1;">
        <div style="font-size:1.02rem;font-weight:700;color:<?= $diemDanhResult === 'success' ? 'var(--success)' : 'var(--warn)' ?>;">
          <?= htmlspecialchars($diemDanhMsg) ?>
        </div>
        <?php if ($diemDanhResult === 'success'): ?>
        <div style="font-size:0.8rem;color:var(--tx3);margin-top:2px;">
          Số Cá hiện tại: <strong style="color:var(--warn);"><?= $vnd ?> Cá</strong>
        </div>
        <?php endif; ?>
      </div>
      <!-- Thanh đếm ngược 5 giây -->
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;min-width:40px;">
        <span id="ddCountNum" style="font-size:0.8rem;font-weight:700;color:var(--tx3);">5</span>
        <div style="width:36px;height:5px;background:var(--bd);border-radius:3px;overflow:hidden;">
          <div id="ddCountBar" style="height:100%;width:100%;border-radius:3px;background:<?= $diemDanhResult === 'success' ? 'var(--success)' : 'var(--warn)' ?>;"></div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      const el  = document.getElementById('ddAlert');
      const bar = document.getElementById('ddCountBar');
      const num = document.getElementById('ddCountNum');
      if (!el) return;
      const TOTAL = 5000;
      const start = Date.now();
      const iv = setInterval(function(){
        const elapsed = Date.now() - start;
        const remain  = Math.max(0, TOTAL - elapsed);
        bar.style.width = (remain / TOTAL * 100).toFixed(1) + '%';
        num.textContent  = Math.ceil(remain / 1);
        if (remain <= 0) {
          clearInterval(iv);
          el.style.opacity = '0';
          setTimeout(function(){ el.style.display = 'none'; }, 420);
        }
      }, 50);
    })();
    </script>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-label">⚡ Sức mạnh</div>
        <div class="stat-card-value" style="color:var(--blue2);"><?= number_format($power) ?></div>
      </div>
      <div class="stat-card">
       <div class="stat-card-label">🐟 Số Cá</div>
        <div class="stat-card-value">
          <?= $vnd ?>
        </div>
      </div>
    </div>

    <!-- ĐIỂM DANH -->
    <div>
      <div class="section-title">📅 Điểm danh hàng ngày</div>
      <div class="diemdanh-card">
        <div class="diemdanh-inner">
          <div class="diemdanh-icon"><?= $canDiemDanh ? '🎁' : '✅' ?></div>
          <div class="diemdanh-info">
            <h4><?= $canDiemDanh ? 'Phần thưởng đang chờ bạn!' : 'Hôm nay bạn đã điểm danh' ?></h4>
            <p>
              Điểm danh nhận <span class="reward-range">1 – 3.000 Cá</span> vào tài khoản.<br>
              <?= $canDiemDanh ? 'Nhấn nút bên cạnh để nhận thưởng ngay!' : 'Quay lại vào ngày mai để tiếp tục nhận thưởng!' ?>
            </p>
          </div>
          <?php if ($canDiemDanh): ?>
            <form method="POST" onsubmit="
              const btn = this.querySelector('button');
              btn.disabled = true;
              btn.textContent = '⏳ Đang xử lý...';
            ">
              <input type="hidden" name="action" value="diem_danh">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
              <button type="submit" class="btn-diemdanh can-claim">🎁 Điểm danh!</button>
            </form>
          <?php else: ?>
            <button class="btn-diemdanh claimed" disabled>✅ Đã điểm danh</button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ADMIN -->
    <div>
      <div class="section-title">⚙️ Quản trị hệ thống</div>
      <div class="admin-section-stack">
        <?php
        $operationMenus = [
          ['🐟','Tặng Cá',  'onclick="openBuffModal()"'],
          ['🎒','Tặng Item','/sv1/admin/buff_item'],
          ['🛠️','Bảo trì',    'onclick="openMaintenanceModal()"'],
          ['⚡','Tỉ Lệ EXP',        'onclick="openExpModal()"'],
          ['🍀','Tỉ Lệ Rơi đồ',     'onclick="openDropModal()"'],
          ['⛔','Kick',       'onclick="openKickModal()"'],
          ['🛡️','Chế độ Admin', 'onclick="openAdminModeModal()"'],
          ['🎉','Đổi Sự Kiện', 'onclick="openChangeEventModal()"'],
          ['🔞','XNXX', 'onclick="openXnxxModeModal()"'],
          ['🔄','Reset',      '#reset'],
        ];
        array_splice($operationMenus, 5, 0, [['💀','Gọi Boss', 'onclick="openBossModal()"']]);
        $adminMenus = [
          ['🩸','Chỉnh Boss',  'onclick="openEditBossModal()"'],
          ['👤','Tài khoản',  '/sv1/admin/account'],
          ['🧙','Nhân vật',   '/sv1/admin/player'],
          ['🎁','Giftcode',   '/sv1/admin/giftcode'],
          ['⚔️','Vật phẩm',  '/sv1/admin/item'],
          ['🏪','Cửa hàng',   '/sv1/admin/itemshop'],
          ['🔒','Chỉ số',     '/sv1/admin/gioihan'],
          ['🗺️','Bản đồ',    '/sv1/admin/map'],
          ['📋','Giao dịch',  '/sv1/admin/giaodich'],
          ['🎯','Nội tại',    '/sv1/admin/noitai'],
        ];
        $renderAdminMenu = function ($menus) use ($is_admin) {
          foreach ($menus as $m) {
            if ($is_admin !== 1) {
              echo '<a href="#" class="admin-btn" onclick="adminOnlyNotice(event)"><span class="ab-icon">' . $m[0] . '</span><span>' . htmlspecialchars($m[1]) . '</span></a>';
              continue;
            }
            if ($m[2] === '#reset') {
              echo '<a href="#" class="admin-btn" onclick="resetAll(event)"><span class="ab-icon">' . $m[0] . '</span><span>' . htmlspecialchars($m[1]) . '</span></a>';
            } elseif (strpos($m[2], 'onclick=') === 0) {
              echo '<button class="admin-btn" ' . $m[2] . '><span class="ab-icon">' . $m[0] . '</span><span>' . htmlspecialchars($m[1]) . '</span></button>';
            } else {
              echo '<a href="' . htmlspecialchars($m[2]) . '" class="admin-btn"><span class="ab-icon">' . $m[0] . '</span><span>' . htmlspecialchars($m[1]) . '</span></a>';
            }
          }
        };
        ?>

        <div class="admin-menu-card ops<?= $is_admin !== 1 ? ' is-collapsible is-collapsed' : '' ?>" data-admin-section="ops">
          <div class="admin-menu-head">
            <div>
              <strong>Vận hành</strong>
              <span>Thao tác ảnh hưởng trực tiếp server</span>
            </div>
            <?php if ($is_admin !== 1): ?><button type="button" class="admin-menu-toggle" onclick="toggleAdminMenu(this)">xem thêm</button><?php endif; ?>
          </div>
          <div class="admin-grid">
            <?php $renderAdminMenu($operationMenus); ?>
          </div>
        </div>

        <div class="admin-menu-card<?= $is_admin !== 1 ? ' is-collapsible is-collapsed' : '' ?>" data-admin-section="manage">
          <div class="admin-menu-head">
            <div>
              <strong>Quản trị</strong>
              <span>Quản lý dữ liệu, cấu hình và nội dung game</span>
            </div>
            <?php if ($is_admin !== 1): ?><button type="button" class="admin-menu-toggle" onclick="toggleAdminMenu(this)">xem thêm</button><?php endif; ?>
          </div>
          <div class="admin-grid">
            <?php $renderAdminMenu($adminMenus); ?>
          </div>
        </div>
      </div>
    </div>
    <!-- GIFTCODE -->
    <div>
      <div class="section-title">🎁 Giftcode hiện có</div>
      <?php if (empty($giftcodes)): ?>
        <div class="no-gc">✨ Hiện chưa có giftcode nào. Hãy quay lại sau! ✨</div>
      <?php else: ?>
        <div class="gc-quick-list">
          <?php foreach ($giftcodes as $i => $gc): ?>
            <div class="gc-quick-item">
              <span class="gc-quick-left">#<?= $i + 1 ?></span>
              <?= htmlspecialchars($gc['code']) ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="giftcode-list">
          <?php foreach ($giftcodes as $gc): ?>
          <div class="gc-card">
            <div class="gc-top">
              <div class="gc-code">🎫 <?= htmlspecialchars($gc['code']) ?></div>
              <div class="gc-left">📦 Còn: <span><?= (int)$gc['count_left'] ?></span></div>
            </div>
            <?php if (!empty($gc['details'])): ?>
            <div class="gc-items">
              <?php foreach ($gc['details'] as $di): ?>
              <div class="gc-item">
                <?php if ($di['icon_id'] !== null): ?>
                  <img src="/icon/<?= (int)$di['icon_id'] ?>.png" alt="<?= htmlspecialchars($di['name']) ?>" onerror="this.style.display='none'">
                <?php else: ?>
                  <div style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">🎁</div>
                <?php endif; ?>
                <?php if ($di['name']): ?>
                  <div class="gc-item-name" title="<?= htmlspecialchars($di['name']) ?>"><?= mb_substr(htmlspecialchars($di['name']), 0, 12) ?></div>
                <?php endif; ?>
                <div class="gc-item-qty">x<?= (int)$di['quantity'] ?></div>
                <?php if (!empty($di['options'])): ?>
                  <div class="gc-item-opts"><?= htmlspecialchars(implode(' | ', $di['options'])) ?></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- GÓP Ý & KHIẾU NẠI (ĐƯỢC CHUYỂN XUỐNG CUỐI TRANG SAU GIFTCODE) -->
    <div>
      <div class="section-title">💬 Hỗ trợ</div>
      <div class="bxh-grid">
        <a href="https://zalo.me/84325420834" target="_blank" class="bxh-card" style="background: linear-gradient(135deg,#e67e22,#d35400); border-color:#d35400;">
          <div class="bxh-icon">💡</div>
          <div class="bxh-info"><strong>Góp ý</strong><span>Đóng góp ý kiến</span></div>
        </a>
        <a href="https://zalo.me/84325420834" target="_blank" class="bxh-card" style="background: linear-gradient(135deg,#e74c3c,#c0392b); border-color:#c0392b;">
          <div class="bxh-icon">⚠️</div>
          <div class="bxh-info"><strong>Khiếu nại</strong><span>Báo cáo vấn đề</span></div>
        </a>
      </div>
    </div>

  </main>
</div>

<!-- MODAL BUFF VND -->
<div id="buffModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--blue2);margin-bottom:8px;">Tặng Cá Cho Nhân Vật</div>
    <form method="POST" onsubmit="submitBuffForm(event)">
      <input type="hidden" name="action" value="buff_vnd">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <label for="buff_char_name" style="display:block;margin-bottom:5px;font-weight:700;color:var(--tx2);">Tên Nhân Vật:</label>
      <input type="text" id="buff_char_name" name="char_name" required style="width:100%;padding:10px;margin-bottom:15px;border:1px solid var(--bd2);border-radius:4px;font-size:1.3rem;font-weight:700;font-family:'Times New Roman',Times,serif;">

      <label for="buff_amount" style="display:block;margin-bottom:5px;font-weight:700;color:var(--tx2);">Số Cá:</label>
      <input type="text" id="buff_amount" name="amount" inputmode="numeric" autocomplete="off" required placeholder="Ví dụ: 1.000 Cá" style="width:100%;padding:10px;margin-bottom:20px;border:1px solid var(--bd2);border-radius:4px;font-size:1.3rem;font-weight:700;font-family:'Times New Roman',Times,serif;">

      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">🐟 Tặng Cá</button>
        <button type="button" onclick="document.getElementById('buffModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($buffMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($buffMsg, 'Đã Buff') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($buffMsg, 'Đã Buff') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($buffMsg, 'Đã Buff') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($buffMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL BẢO TRÌ -->
<div id="maintenanceModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--danger);margin-bottom:8px;">Xác Nhận Bảo Trì</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:20px;line-height:1.6;">Bạn có chắc chắn muốn bắt đầu chế độ bảo trì?<br>Bảo trì sau 30s và Tự khởi động lại.</div>
    <form method="POST" onsubmit="submitMaintenanceForm(event)">
      <input type="hidden" name="action" value="maintenance">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(217,43,58,.4);background:rgba(217,43,58,.1);color:var(--danger);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">🛠️ Bảo Trì</button>
        <button type="button" onclick="document.getElementById('maintenanceModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($maintenanceMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($maintenanceMsg, 'Lệnh bảo trì') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($maintenanceMsg, 'Lệnh bảo trì') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($maintenanceMsg, 'Lệnh bảo trì') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($maintenanceMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL EXP SERVER -->
<div id="expModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--blue2);margin-bottom:8px;">Set EXP Server</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:16px;line-height:1.6;">EXP hiện tại: <b id="currentExpRate" style="color:var(--warn);font-size:1.05rem;">x<?= number_format($currentExpServer, 0, ',', '.') ?></b><br>Mặc định là x10.</div>
    <form method="POST" onsubmit="submitExpForm(event)">
      <input type="hidden" name="action" value="set_exp">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <label for="exp_rate" style="display:block;margin-bottom:5px;font-weight:700;color:var(--tx2);">Tỉ Lệ EXP:</label>
      <input type="text" id="exp_rate" name="exp_rate" inputmode="numeric" autocomplete="off" required placeholder="Ví dụ: 10" style="width:100%;padding:10px;margin-bottom:20px;border:1px solid var(--bd2);border-radius:4px;font-size:1.3rem;font-weight:700;font-family:'Times New Roman',Times,serif;">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">⚡ Set EXP</button>
        <button type="button" onclick="document.getElementById('expModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($expMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($expMsg, 'Đã tăng') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($expMsg, 'Đã tăng') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($expMsg, 'Đã tăng') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($expMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<!-- MODAL RƠI ĐỒ SERVER -->
<div id="dropModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--blue2);margin-bottom:8px;">Chỉnh Tỉ Lệ Rơi Đồ</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:16px;line-height:1.6;">Rơi đồ hiện tại: <b id="currentDropRate" style="color:var(--warn);font-size:1.05rem;">x<?= number_format($currentDropServer, 0, ',', '.') ?></b><br>Mặc định là x5.</div>
    <form method="POST" onsubmit="submitDropForm(event)">
      <input type="hidden" name="action" value="set_drop">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <label for="drop_rate" style="display:block;margin-bottom:5px;font-weight:700;color:var(--tx2);">Tỉ Lệ Rơi Đồ:</label>
      <input type="text" id="drop_rate" name="drop_rate" inputmode="numeric" autocomplete="off" required placeholder="Ví dụ: 10" style="width:100%;padding:10px;margin-bottom:20px;border:1px solid var(--bd2);border-radius:4px;font-size:1.3rem;font-weight:700;font-family:'Times New Roman',Times,serif;">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">🍀 Set Tỉ Lệ</button>
        <button type="button" onclick="document.getElementById('dropModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($dropMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($dropMsg, 'Đã chỉnh') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($dropMsg, 'Đã chỉnh') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($dropMsg, 'Đã chỉnh') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($dropMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL ĐĂNG XUẤT -->
<div id="bossModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:1180px;height:90vh;display:flex;flex-direction:column;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--blue2);margin-bottom:8px;">Gọi Boss</div>
    <form method="POST" onsubmit="submitBossForm(event)" style="display:flex;flex-direction:column;min-height:0;flex:1;">
      <input type="hidden" name="action" value="spawn_boss">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <input type="hidden" id="boss_key" name="boss_key" required>
      <?php if (empty($panelBosses)): ?>
        <div class="panel-boss-empty">Chua co du lieu boss_status.json</div>
      <?php else: ?>
        <div class="panel-boss-grid" id="panelBossGrid" style="flex:1;">
          <?php foreach ($panelBosses as $boss): ?>
            <?php
              $bossKey = $boss['id'] . '|' . $boss['mapId'] . '|' . $boss['zoneId'];
              $hpText = $boss['maxHp'] > 0 ? number_format($boss['hp'], 0, ',', '.') . '/' . number_format($boss['maxHp'], 0, ',', '.') : '0';
              $isAlive = $boss['state'] === 'Sống';
            ?>
            <button type="button" class="panel-boss-card" data-boss-key="<?= htmlspecialchars($bossKey, ENT_QUOTES) ?>" onclick="selectPanelBoss(this)">
              <span class="panel-boss-thumb">
                <span class="panel-boss-state <?= $isAlive ? 'is-alive' : 'is-dead' ?>"><?= $isAlive ? '✓' : '×' ?></span>
                <img src="/icon/<?= (int)$boss['avatarId'] ?>.png" alt="<?= htmlspecialchars($boss['name'], ENT_QUOTES) ?>" onerror="this.src='/icon/1.png'">
              </span>
              <span class="panel-boss-name"><?= htmlspecialchars($boss['name']) ?></span>
              <span class="panel-boss-meta"><?= htmlspecialchars($boss['mapName']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div style="display:flex;gap:10px;">
        <button id="spawnBossSubmit" type="submit" disabled style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">💀 Triệu Hồi</button>
        <button type="button" onclick="document.getElementById('bossModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($bossMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($bossMsg, 'Da gui') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($bossMsg, 'Da gui') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($bossMsg, 'Da gui') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($bossMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="editBossModal" class="modal-overlay">
  <div class="modal-box edit-boss-box" onclick="event.stopPropagation()">
    <div class="edit-boss-title">Sửa Boss</div>
    <form method="POST" onsubmit="submitEditBossForm(event)" class="edit-boss-form-shell">
      <input type="hidden" name="action" value="edit_boss">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <input type="hidden" id="edit_boss_key" name="boss_key" required>
      <div class="panel-boss-edit-layout">
        <div class="panel-boss-edit-left">
          <?php
            $activeBosses = array_values(array_filter($panelBosses, function ($boss) {
                return ($boss['state'] ?? '') === 'Sống' && (int)($boss['mapId'] ?? -1) >= 0 && (int)($boss['zoneId'] ?? -1) >= 0;
            }));
          ?>
          <div class="panel-boss-edit-toolbar">
            <div>
              <strong>Boss đang hoạt động</strong>
          
            </div>
            <span id="editBossCount"><?= count($activeBosses) ?> boss</span>
          </div>
          <div class="panel-boss-edit-list" id="editBossGrid">
          <?php if (empty($activeBosses)): ?>
            <div class="panel-boss-empty">Chua co boss dang hoat dong</div>
          <?php else: ?>
            <?php foreach ($activeBosses as $boss): ?>
              <?php
                $bossKey = $boss['id'] . '|' . $boss['mapId'] . '|' . $boss['zoneId'];
                $hpText = number_format(max(0, (int)$boss['hp']), 0, ',', '.') . ' / ' . number_format(max(0, (int)$boss['maxHp']), 0, ',', '.');
              ?>
              <?php
                $hpNow = max(0, (int)$boss['hp']);
                $hpMax = max(1, (int)$boss['maxHp']);
                $hpPercent = max(1, min(100, (int)round($hpNow * 100 / $hpMax)));
                $shortMap = panel_boss_short_map($boss);
              ?>
              <button type="button" class="panel-boss-edit-card" data-boss-key="<?= htmlspecialchars($bossKey, ENT_QUOTES) ?>" data-boss-name="<?= htmlspecialchars($boss['name'], ENT_QUOTES) ?>" data-boss-map="<?= htmlspecialchars($shortMap, ENT_QUOTES) ?>" data-boss-hp="<?= (int)max(0, $boss['hp']) ?>" onclick="selectEditBoss(this)">
                <span class="panel-boss-edit-avatar">
                  <span class="panel-boss-edit-status">✓</span>
                  <img src="/icon/<?= (int)$boss['avatarId'] ?>.png" alt="<?= htmlspecialchars($boss['name'], ENT_QUOTES) ?>" onerror="this.src='/icon/1.png'">
                </span>
                <span class="panel-boss-edit-main">
                  <span class="panel-boss-edit-name"><?= htmlspecialchars($boss['name']) ?></span>
                  <span class="panel-boss-edit-map"><?= htmlspecialchars($shortMap) ?></span>
                </span>
                <span class="panel-boss-edit-stats">
                  <span class="panel-boss-edit-hp-text"><span><?= htmlspecialchars($hpText) ?></span></span>
                  <span class="panel-boss-edit-hpbar"><span class="panel-boss-edit-hpfill" style="width:<?= $hpPercent ?>%"></span></span>
                </span>
              </button>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>
        <div class="panel-boss-edit-form">
          <div class="panel-boss-edit-form-title">Thông tin chỉnh sửa</div>
          <div class="panel-boss-persist-box">
            <div>
              <strong>Lưu HP</strong>
              <span id="bossPersistModeText"><?= $currentBossPersistMode ? 'Đang bật' : 'Đang tắt' ?></span>
            </div>
            <button type="button" id="bossPersistToggle" class="panel-boss-persist-toggle<?= $currentBossPersistMode ? ' is-on' : '' ?>" data-enabled="<?= $currentBossPersistMode ? '1' : '0' ?>" onclick="toggleBossPersistMode(this)"><?= $currentBossPersistMode ? 'Bật' : 'Tắt' ?></button>
          </div>
          <label for="edit_boss_name">Tên Boss:</label>
          <input type="text" id="edit_boss_name" name="boss_name" required>
          <label for="edit_boss_hp">HP Boss:</label>
          <input type="text" id="edit_boss_hp" name="boss_hp" inputmode="numeric" autocomplete="off" required placeholder="Ví dụ: 1.000.000">
          <div style="display:flex;gap:10px;">
            <button id="editBossSubmit" type="submit" disabled style="flex:1;min-height:44px;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.1);color:var(--success);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">💾 Lưu</button>
            <button type="button" onclick="document.getElementById('editBossModal').style.display='none'" style="flex:1;min-height:44px;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
          </div>
        </div>
      </div>
    </form>
    <?php $bossPanelMsg = $bossEditMsg ?: $bossPersistMsg; ?>
    <?php if ($bossPanelMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= (strpos($bossPanelMsg, 'Da gui') !== false || strpos($bossPanelMsg, 'Đã') !== false) ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= (strpos($bossPanelMsg, 'Da gui') !== false || strpos($bossPanelMsg, 'Đã') !== false) ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= (strpos($bossPanelMsg, 'Da gui') !== false || strpos($bossPanelMsg, 'Đã') !== false) ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($bossPanelMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL KICK PLAYER -->
<div id="kickModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--danger);margin-bottom:8px;">Kick Người Chơi</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:16px;line-height:1.6;">Người chơi sẽ bị văng game và khóa đăng nhập lại trong 5 phút.</div>
    <form method="POST" onsubmit="submitKickForm(event)">
      <input type="hidden" name="action" value="kick_player">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <label for="kick_char_name" style="display:block;margin-bottom:5px;font-weight:700;color:var(--tx2);">Tên Nhân Vật:</label>
      <input type="text" id="kick_char_name" name="char_name" required style="width:100%;padding:10px;margin-bottom:20px;border:1px solid var(--bd2);border-radius:4px;font-size:1.3rem;font-weight:700;font-family:'Times New Roman',Times,serif;">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(217,43,58,.4);background:rgba(217,43,58,.1);color:var(--danger);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">⛔ Kick</button>
        <button type="button" onclick="document.getElementById('kickModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($kickMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($kickMsg, 'Đã gửi') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($kickMsg, 'Đã gửi') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($kickMsg, 'Đã gửi') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($kickMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL ĐỔI SỰ KIỆN -->
<div id="changeEventModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:400px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--warn);margin-bottom:8px;">Đổi Sự Kiện</div>
    <div style="font-size:0.9rem;color:var(--tx3);margin-bottom:12px;line-height:1.6;">Chon su kien de BAT, hoac tat het ve Khong su kien.<br><b style="color:var(--warn);">Dang chay: <?= (int)($currentSrc4Event['event_id'] ?? 0) ?> - <?= htmlspecialchars($SRC4_EVENT_NAMES[(int)($currentSrc4Event['event_id'] ?? 0)] ?? 'Event', ENT_QUOTES) ?> (<?= !empty($currentSrc4Event['enabled']) ? 'BAT' : 'TAT' ?>, nguon: <?= htmlspecialchars($currentSrc4Event['source'] ?? '', ENT_QUOTES) ?>)</b><br><span style="font-size:0.8rem;">Doi event can restart Game Server roi vao lai game de thay giao dien (Tet/Trung Thu...).</span></div>
    <form method="POST">
      <input type="hidden" name="action" value="change_event">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <select name="event_id" style="width:100%;padding:10px;border-radius:8px;margin-bottom:8px;">
          <option value="0">0 - Khong su kien</option>
          <option value="1">1 - Tet Nguyen Dan</option>
          <option value="2">2 - Trung Thu</option>
          <option value="3">3 - Halloween</option>
          <option value="4">4 - Noel / Giang Sinh</option>
          <option value="5">5 - Hung Vuong 10/3</option>
          <option value="6">6 - 8/3 - 20/10</option>
          <option value="7">7 - He / Quoc Khanh 2/9</option>
          <option value="8">8 - Sinh Nhat Server</option>
      </select>
      <div style="display:flex;gap:10px;">
        <button type="submit" name="enabled" value="1" style="flex:1;padding:11px;border-radius:8px;border:1.5px solid rgba(10,138,90,.4);background:rgba(10,138,90,.12);font-weight:700;cursor:pointer;">BAT SU KIEN</button>
        <button type="submit" name="enabled" value="0" style="flex:1;padding:11px;border-radius:8px;border:1.5px solid rgba(217,43,58,.4);background:rgba(217,43,58,.1);font-weight:700;cursor:pointer;">TAT SU KIEN</button>
      </div>
    </form>
    <?php if (!empty($changeEventMsg)): ?>
      <div class="panel-action-message" style="margin-top:12px;padding:10px;border-radius:4px;text-align:center;"><?= htmlspecialchars($changeEventMsg) ?></div>
    <?php endif; ?>
    <button type="button" onclick="document.getElementById('changeEventModal').style.display='none'" style="width:100%;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">OK</button>
  </div>
</div>

<!-- MODAL XNXX -->
<div id="xnxxModeModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:420px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--warn);margin-bottom:8px;">XNXX</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:16px;line-height:1.6;">
      Trạng thái hiện tại: <b id="currentXnxxMode" style="color:<?= $currentXnxxMode ? 'var(--success)' : 'var(--danger)' ?>;font-size:1.05rem;"><?= $currentXnxxMode ? 'Đang bật' : 'Đang tắt' ?></b><br>
      Bật/tắt bot pem quái giống lệnh chat <b>bot</b> trong game.
    </div>
    <form method="POST" onsubmit="submitXnxxModeForm(event)">
      <input type="hidden" name="action" value="xnxx_mode">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <input type="hidden" id="xnxx_mode_enabled" name="enabled" value="<?= $currentXnxxMode ? '0' : '1' ?>">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(255,177,66,.45);background:rgba(255,177,66,.12);color:var(--warn);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;"><?= $currentXnxxMode ? 'Tắt XNXX' : 'Bật XNXX' ?></button>
        <button type="button" onclick="document.getElementById('xnxxModeModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($xnxxModeMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($xnxxModeMsg, 'Đã') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($xnxxModeMsg, 'Đã') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($xnxxModeMsg, 'Đã') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($xnxxModeMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<!-- MODAL CHẾ ĐỘ ADMIN -->
<div id="adminModeModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:420px;">
    <div style="font-size:1.3rem;font-weight:700;color:var(--warn);margin-bottom:8px;">Chế Độ Admin</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:16px;line-height:1.6;">
      Trạng thái hiện tại: <b id="currentAdminMode" style="color:<?= $currentAdminMode ? 'var(--danger)' : 'var(--success)' ?>;font-size:1.05rem;"><?= $currentAdminMode ? 'Đang bật' : 'Đang tắt' ?></b><br>
      Khi bật, Server sẽ chặn Acc thường và chỉ Acc <b>Admin</b> mới vào game.
    </div>
    <form method="POST" onsubmit="submitAdminModeForm(event)">
      <input type="hidden" name="action" value="admin_mode">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <input type="hidden" id="admin_mode_enabled" name="enabled" value="<?= $currentAdminMode ? '0' : '1' ?>">
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(255,177,66,.45);background:rgba(255,177,66,.12);color:var(--warn);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;"><?= $currentAdminMode ? 'Tắt Chế Độ Admin' : 'Bật Chế Độ Admin' ?></button>
        <button type="button" onclick="document.getElementById('adminModeModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
      </div>
    </form>
    <?php if ($adminModeMsg): ?>
      <div class="panel-action-message" style="margin-top:15px;padding:10px;border-radius:4px;text-align:center;background:<?= strpos($adminModeMsg, 'Đã') !== false ? 'rgba(10,138,90,.1)' : 'rgba(217,43,58,.1)' ?>;border:1px solid <?= strpos($adminModeMsg, 'Đã') !== false ? 'rgba(10,138,90,.4)' : 'rgba(217,43,58,.4)' ?>;color:<?= strpos($adminModeMsg, 'Đã') !== false ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($adminModeMsg) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="logoutModal" class="modal-overlay" onclick="this.style.display='none'">
  <div class="modal-box" onclick="event.stopPropagation()">
    <img src="/img/b.png" alt="Đăng xuất">
    <div style="font-size:1.3rem;font-weight:700;color:var(--warn);margin-bottom:8px;">Xác Nhận Đăng Xuất</div>
    <div style="font-size:0.88rem;color:var(--tx3);margin-bottom:20px;line-height:1.6;">Thoát toàn bộ phiên đăng nhập tài khoản hiện tại.<br>Bạn sẽ quay về trang diễn đàn.</div>
    <div style="display:flex;gap:10px;">
      <button onclick="doLogout()" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(217,43,58,.4);background:rgba(217,43,58,.1);color:var(--danger);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">🚪 Đăng xuất</button>
      <button onclick="document.getElementById('logoutModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;font-size:0.92rem;cursor:pointer;font-family:'Times New Roman',Times,serif;">❌ Hủy</button>
    </div>
  </div>
</div>

<!-- MODAL KHÔNG NẠP TIỀN -->
<div id="noTopupModal" class="modal-overlay">
  <div class="modal-box no-topup-box" onclick="event.stopPropagation()">
    <div class="no-topup-icon">⚠️</div>
    <div class="no-topup-title">Không Tồn Tại</div>
    <div class="no-topup-lines">
      <span class="main-line">FREE ALL - VUI VẺ</span>
      <span>Tạo Ra Để Học Tập</span>
      <span>Chưa &amp; Không Có Nạp Tiền</span>
    </div>
    <div class="no-topup-actions">
      <button class="danger" onclick="document.getElementById('noTopupModal').style.display='none'">ĐỒNG Ý</button>
      <button class="cancel" onclick="document.getElementById('noTopupModal').style.display='none'">HỦY</button>
    </div>
  </div>
</div>
<!-- MODAL THÔNG BÁO -->
<div id="msgBox" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div id="msgText" style="font-size:1rem;margin-bottom:14px;color:var(--tx);"></div>
    <button onclick="document.getElementById('msgBox').style.display='none'" style="padding:6px 16px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);cursor:pointer;font-weight:700;font-family:'Times New Roman',Times,serif;">OK</button>
  </div>
</div>

<!-- MODAL RESET -->
<div id="resetModal" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div style="font-size:2.8rem;margin-bottom:10px;">⚠️</div>
    <div style="font-size:1.25rem;font-weight:700;color:var(--warn);margin-bottom:8px;">Xác nhận Reset</div>
    <div style="color:var(--tx3);margin-bottom:18px;line-height:1.5;font-size:0.88rem;">
      <b style="color:var(--danger);">XÓA TOÀN BỘ DỮ LIỆU</b>?<br>Xóa ALL: Tài Khoản, Player, Clan ...
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="doReset()" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid rgba(217,43,58,.4);background:rgba(217,43,58,.1);color:var(--danger);font-weight:700;cursor:pointer;font-family:'Times New Roman',Times,serif;">RESET</button>
      <button onclick="document.getElementById('resetModal').style.display='none'" style="flex:1;padding:11px;border-radius:var(--rs);border:1.5px solid var(--bd2);background:var(--surf2);color:var(--tx2);font-weight:700;cursor:pointer;font-family:'Times New Roman',Times,serif;">HỦY</button>
    </div>
  </div>
</div>

<script>
function openLogoutModal(e) { e.preventDefault(); document.getElementById("logoutModal").style.display = "flex"; }
function doLogout() {
  fetch('/sv1/admin/logout', { method: 'POST', credentials: 'same-origin', headers: csrfHeaders() })
    .then(r => r.json())
    .then(() => { window.location.href = '/sv1/trangchu'; })
    .catch(() => { window.location.href = '/sv1/trangchu'; });
}
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
function csrfHeaders(extra = {}) { return Object.assign({}, extra, { 'X-CSRF-TOKEN': CSRF_TOKEN }); }
function showMsg(text) { document.getElementById("msgText").innerText = text; document.getElementById("msgBox").style.display = "flex"; }
function showNoTopupNotice(event) { event.preventDefault(); document.getElementById("noTopupModal").style.display = "flex"; }
function adminOnlyNotice(event) { event.preventDefault(); showMsg("Chức năng chỉ dành cho admin"); }
function resetAll(e) { e.preventDefault(); document.getElementById("resetModal").style.display = "flex"; }
function doReset() {
  fetch('/sv1/admin/reset', { method: 'POST', headers: csrfHeaders() })
    .then(r => r.text())
    .then(d => { document.getElementById("resetModal").style.display = "none"; showMsg("✔ " + d); })
    .catch(() => { document.getElementById("resetModal").style.display = "none"; showMsg("❌ Reset thất bại!"); });
}

function toggleAdminMenu(btn) {
  const card = btn.closest('.admin-menu-card');
  if (!card) return;
  const collapsed = card.classList.toggle('is-collapsed');
  btn.textContent = collapsed ? 'xem thêm' : 'ẩn';
}
function openBuffModal() { document.getElementById("buffModal").style.display = "flex"; }
function openMaintenanceModal() { document.getElementById("maintenanceModal").style.display = "flex"; }
function openExpModal() { document.getElementById("expModal").style.display = "flex"; }
function openDropModal() { document.getElementById("dropModal").style.display = "flex"; }
function openKickModal() { document.getElementById("kickModal").style.display = "flex"; }
function openAdminModeModal() { document.getElementById("adminModeModal").style.display = "flex"; }
function openChangeEventModal() { document.getElementById("changeEventModal").style.display = "flex"; }
function openXnxxModeModal() { document.getElementById("xnxxModeModal").style.display = "flex"; }
function openBossModal() {
  document.getElementById("bossModal").style.display = "flex";
  loadPanelBosses();
}
function openEditBossModal() {
  document.getElementById("editBossModal").style.display = "flex";
  loadActiveBossesForEdit();
}
function selectPanelBoss(card) {
  document.querySelectorAll('.panel-boss-card').forEach(item => item.classList.remove('is-selected'));
  card.classList.add('is-selected');
  const input = document.getElementById('boss_key');
  const submit = document.getElementById('spawnBossSubmit');
  if (input) input.value = card.dataset.bossKey || '';
  if (submit) submit.disabled = !input || !input.value;
}
function escapePanelBossText(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
}
function formatPanelNumber(value) {
  return String(value ?? '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
function shortPanelBossMap(boss) {
  let mapName = String(boss?.mapName || 'Chua xuat hien').replace(/\s*\(khu\s+\d+\)\s*$/i, '').trim();
  if (!mapName) mapName = 'Chua xuat hien';
  const zoneId = Number(boss?.zoneId);
  return Number.isFinite(zoneId) && zoneId >= 0 ? `${mapName} - ${zoneId}` : mapName;
}
function selectEditBoss(card) {
  document.querySelectorAll('#editBossGrid .panel-boss-edit-card').forEach(item => item.classList.remove('is-selected'));
  card.classList.add('is-selected');
  const keyInput = document.getElementById('edit_boss_key');
  const nameInput = document.getElementById('edit_boss_name');
  const hpInput = document.getElementById('edit_boss_hp');
  const submit = document.getElementById('editBossSubmit');
  if (keyInput) keyInput.value = card.dataset.bossKey || '';
  if (nameInput) nameInput.value = card.dataset.bossName || '';
  if (hpInput) hpInput.value = formatPanelNumber(card.dataset.bossHp || '');
  if (submit) submit.disabled = !keyInput || !keyInput.value;
}
function syncBossPersistMode(enabled) {
  const toggle = document.getElementById('bossPersistToggle');
  const text = document.getElementById('bossPersistModeText');
  if (toggle) {
    toggle.dataset.enabled = enabled ? '1' : '0';
    toggle.textContent = enabled ? 'Bật' : 'Tắt';
    toggle.classList.toggle('is-on', enabled);
  }
  if (text) text.textContent = enabled ? 'Đang bật' : 'Đang tắt';
}
function toggleBossPersistMode(btn) {
  const nextEnabled = btn.dataset.enabled !== '1';
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = '...';
  const formData = new FormData();
  formData.set('action', 'boss_persist_mode');
  formData.set('enabled', nextEnabled ? '1' : '0');
  formData.set('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>');
  fetch('', { method: 'POST', headers: csrfHeaders(), body: formData })
    .then(r => r.text())
    .then(html => {
      btn.disabled = false;
      syncBossPersistMode(nextEnabled);
      showMsg(extractPanelMessage(html, 'editBossModal', nextEnabled ? 'Đã bật lưu HP Boss.' : 'Đã tắt lưu HP Boss.'));
    })
    .catch(() => {
      btn.disabled = false;
      btn.textContent = originalText;
      showMsg('Cập nhật chế độ lưu HP boss thất bại!');
    });
}
async function loadPanelBosses() {
  const grid = document.getElementById('panelBossGrid');
  const input = document.getElementById('boss_key');
  const submit = document.getElementById('spawnBossSubmit');
  if (!grid) return;
  grid.innerHTML = '<div class="panel-boss-empty">Dang tai du lieu boss...</div>';
  if (input) input.value = '';
  if (submit) submit.disabled = true;
  try {
    const response = await fetch('/sv1/boss_status', { cache: 'no-store' });
    const data = await response.json();
    if (!Array.isArray(data.bosses) || data.bosses.length === 0) {
      grid.innerHTML = '<div class="panel-boss-empty">Chua co du lieu boss_status.json</div>';
      return;
    }
    grid.innerHTML = data.bosses.map(boss => {
      const bossKey = `${boss.id}|${boss.mapId}|${boss.zoneId}`;
      const isAlive = boss.state === 'Sống';
      return `
        <button type="button" class="panel-boss-card" data-boss-key="${escapePanelBossText(bossKey)}" onclick="selectPanelBoss(this)">
          <span class="panel-boss-thumb">
            <span class="panel-boss-state ${isAlive ? 'is-alive' : 'is-dead'}">${isAlive ? '✓' : '×'}</span>
            <img src="/icon/${Number(boss.avatar_id || 1)}.png" alt="${escapePanelBossText(boss.name || '')}" onerror="this.src='/icon/1.png'">
          </span>
          <span class="panel-boss-name">${escapePanelBossText(boss.name || 'Boss khong xac dinh')}</span>
          <span class="panel-boss-meta">${escapePanelBossText(boss.mapName || 'Chua xuat hien')}</span>
        </button>
      `;
    }).join('');
  } catch (error) {
    grid.innerHTML = '<div class="panel-boss-empty">Khong tai duoc du lieu boss moi nhat.</div>';
  }
}

async function loadActiveBossesForEdit() {
  const grid = document.getElementById('editBossGrid');
  const keyInput = document.getElementById('edit_boss_key');
  const nameInput = document.getElementById('edit_boss_name');
  const hpInput = document.getElementById('edit_boss_hp');
  const submit = document.getElementById('editBossSubmit');
  const count = document.getElementById('editBossCount');
  if (!grid) return;
  grid.innerHTML = '<div class="panel-boss-empty">Dang tai du lieu boss...</div>';
  if (keyInput) keyInput.value = '';
  if (nameInput) nameInput.value = '';
  if (hpInput) hpInput.value = '';
  if (count) count.textContent = '...';
  if (submit) submit.disabled = true;
  try {
    const response = await fetch('/sv1/boss_status', { cache: 'no-store' });
    const data = await response.json();
    const bosses = Array.isArray(data.bosses) ? data.bosses.filter(boss => boss.state === 'Sống' && Number(boss.mapId) >= 0 && Number(boss.zoneId) >= 0) : [];
    if (count) count.textContent = `${bosses.length} boss`;
    if (bosses.length === 0) {
      grid.innerHTML = '<div class="panel-boss-empty">Chua co boss dang hoat dong</div>';
      return;
    }
    grid.innerHTML = bosses.map(boss => {
      const bossKey = `${boss.id}|${boss.mapId}|${boss.zoneId}`;
      const hpValue = Math.max(0, Number(boss.hp || 0));
      const hpNow = Math.max(0, Number(boss.hp || 0));
      const hpMax = Math.max(1, Number(boss.maxHp || 0));
      const hpText = `${formatPanelNumber(hpNow)} / ${formatPanelNumber(hpMax)}`;
      const hpPercent = Math.max(1, Math.min(100, Math.round(hpNow * 100 / hpMax)));
      const shortMap = shortPanelBossMap(boss);
      return `
        <button type="button" class="panel-boss-edit-card" data-boss-key="${escapePanelBossText(bossKey)}" data-boss-name="${escapePanelBossText(boss.name || '')}" data-boss-map="${escapePanelBossText(shortMap)}" data-boss-hp="${hpValue}" onclick="selectEditBoss(this)">
          <span class="panel-boss-edit-avatar">
            <span class="panel-boss-edit-status">✓</span>
            <img src="/icon/${Number(boss.avatar_id || 1)}.png" alt="${escapePanelBossText(boss.name || '')}" onerror="this.src='/icon/1.png'">
          </span>
          <span class="panel-boss-edit-main">
            <span class="panel-boss-edit-name">${escapePanelBossText(boss.name || 'Boss khong xac dinh')}</span>
            <span class="panel-boss-edit-map">${escapePanelBossText(shortMap)}</span>
          </span>
          <span class="panel-boss-edit-stats">
            <span class="panel-boss-edit-hp-text"><span>${hpText}</span></span>
            <span class="panel-boss-edit-hpbar"><span class="panel-boss-edit-hpfill" style="width:${hpPercent}%"></span></span>
          </span>
        </button>
      `;
    }).join('');
  } catch (error) {
    if (count) count.textContent = '0 boss';
    grid.innerHTML = '<div class="panel-boss-empty">Khong tai duoc du lieu boss moi nhat.</div>';
  }
}

function extractPanelMessage(html, modalId, fallback) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const msg = doc.querySelector('#' + modalId + ' .panel-action-message');
  return msg ? msg.textContent.trim() : fallback;
}
function finishPanelAction(modalId, message) {
  const modal = document.getElementById(modalId);
  if (modal) modal.style.display = 'none';
  showMsg(message);
}
function syncCurrentRate(html, sourceId, targetId) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const nextValue = doc.getElementById(sourceId);
  const currentValue = document.getElementById(targetId);
  if (nextValue && currentValue) currentValue.textContent = nextValue.textContent.trim();
}
function formatBuffAmountInput(input) {
  const digits = (input.value || '').replace(/\D/g, '');
  input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
const buffAmountInput = document.getElementById('buff_amount');
if (buffAmountInput) {
  buffAmountInput.addEventListener('input', function(){ formatBuffAmountInput(this); });
}
function submitBuffForm(event) {
  event.preventDefault();
  const form = event.target;
  const amountInput = document.getElementById('buff_amount');
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  const formData = new FormData(form);
  if (amountInput) formData.set('amount', (amountInput.value || '').replace(/\D/g, ''));

  fetch('', { method: 'POST', headers: csrfHeaders(), body: formData })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; finishPanelAction('buffModal', extractPanelMessage(html, 'buffModal', 'Đã tặng Cá thành công.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Tặng Cá thất bại!"); });
}

function submitKickForm(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  fetch('', { method: 'POST', headers: csrfHeaders(), body: new FormData(form) })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; finishPanelAction('kickModal', extractPanelMessage(html, 'kickModal', 'Đã gửi lệnh kick người chơi.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Kick thất bại!"); });
}

function submitAdminModeForm(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  fetch('', { method: 'POST', headers: csrfHeaders(), body: new FormData(form) })
    .then(r => r.text())
    .then(html => {
      btn.innerHTML = originalText;
      btn.disabled = false;
      syncAdminModeState(html);
      finishPanelAction('adminModeModal', extractPanelMessage(html, 'adminModeModal', 'Đã cập nhật chế độ admin.'));
    })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Cập nhật chế độ admin thất bại!"); });
}

function syncAdminModeState(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const nextMode = doc.getElementById('currentAdminMode');
  const currentMode = document.getElementById('currentAdminMode');
  const nextInput = doc.getElementById('admin_mode_enabled');
  const currentInput = document.getElementById('admin_mode_enabled');
  const nextButton = doc.querySelector('#adminModeModal button[type="submit"]');
  const currentButton = document.querySelector('#adminModeModal button[type="submit"]');
  if (nextMode && currentMode) {
    currentMode.textContent = nextMode.textContent.trim();
    currentMode.style.color = nextMode.style.color;
  }
  if (nextInput && currentInput) currentInput.value = nextInput.value;
  if (nextButton && currentButton) currentButton.innerHTML = nextButton.innerHTML;
}

function submitXnxxModeForm(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  fetch('', { method: 'POST', headers: csrfHeaders(), body: new FormData(form) })
    .then(r => r.text())
    .then(html => {
      btn.innerHTML = originalText;
      btn.disabled = false;
      syncXnxxModeState(html);
      finishPanelAction('xnxxModeModal', extractPanelMessage(html, 'xnxxModeModal', 'Đã cập nhật XNXX.'));
    })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Cập nhật XNXX thất bại!"); });
}

function syncXnxxModeState(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const nextMode = doc.getElementById('currentXnxxMode');
  const currentMode = document.getElementById('currentXnxxMode');
  const nextInput = doc.getElementById('xnxx_mode_enabled');
  const currentInput = document.getElementById('xnxx_mode_enabled');
  const nextButton = doc.querySelector('#xnxxModeModal button[type="submit"]');
  const currentButton = document.querySelector('#xnxxModeModal button[type="submit"]');
  if (nextMode && currentMode) {
    currentMode.textContent = nextMode.textContent.trim();
    currentMode.style.color = nextMode.style.color;
  }
  if (nextInput && currentInput) currentInput.value = nextInput.value;
  if (nextButton && currentButton) currentButton.innerHTML = nextButton.innerHTML;
}
function submitExpForm(event) {
  event.preventDefault();
  const form = event.target;
  const input = document.getElementById('exp_rate');
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  const formData = new FormData(form);
  if (input) formData.set('exp_rate', (input.value || '').replace(/\D/g, ''));

  fetch('', { method: 'POST', headers: csrfHeaders(), body: formData })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; syncCurrentRate(html, 'currentExpRate', 'currentExpRate'); finishPanelAction('expModal', extractPanelMessage(html, 'expModal', 'Đã tăng EXP server thành công.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Set EXP thất bại!"); });
}
function submitDropForm(event) {
  event.preventDefault();
  const form = event.target;
  const input = document.getElementById('drop_rate');
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  const formData = new FormData(form);
  if (input) formData.set('drop_rate', (input.value || '').replace(/\D/g, ''));

  fetch('', { method: 'POST', headers: csrfHeaders(), body: formData })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; syncCurrentRate(html, 'currentDropRate', 'currentDropRate'); finishPanelAction('dropModal', extractPanelMessage(html, 'dropModal', 'Đã chỉnh tỉ lệ rơi đồ server thành công.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Set rơi đồ thất bại!"); });
}

function submitBossForm(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = 'Dang xu ly...';
  btn.disabled = true;

  fetch('', { method: 'POST', headers: csrfHeaders(), body: new FormData(form) })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; finishPanelAction('bossModal', extractPanelMessage(html, 'bossModal', 'Đã gửi lệnh gọi Boss.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Gọi Boss thất bại!"); });
}

const editBossHpInput = document.getElementById('edit_boss_hp');
if (editBossHpInput) {
  editBossHpInput.addEventListener('input', function(){ this.value = formatPanelNumber(this.value); });
}
function submitEditBossForm(event) {
  event.preventDefault();
  const form = event.target;
  const hpInput = document.getElementById('edit_boss_hp');
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = 'Dang xu ly...';
  btn.disabled = true;

  const formData = new FormData(form);
  if (hpInput) formData.set('boss_hp', (hpInput.value || '').replace(/\D/g, ''));

  fetch('', { method: 'POST', headers: csrfHeaders(), body: formData })
    .then(r => r.text())
    .then(html => {
      btn.innerHTML = originalText;
      btn.disabled = false;
      finishPanelAction('editBossModal', extractPanelMessage(html, 'editBossModal', 'Đã gửi lệnh sửa Boss.'));
      setTimeout(loadActiveBossesForEdit, 2500);
    })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Sửa Boss thất bại!"); });
}

function submitMaintenanceForm(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Đang xử lý...';
  btn.disabled = true;

  fetch('', { method: 'POST', headers: csrfHeaders(), body: new FormData(form) })
    .then(r => r.text())
    .then(html => { btn.innerHTML = originalText; btn.disabled = false; finishPanelAction('maintenanceModal', extractPanelMessage(html, 'maintenanceModal', 'Lệnh bảo trì đã được gửi.')); })
    .catch(() => { btn.innerHTML = originalText; btn.disabled = false; showMsg("❌ Bảo trì thất bại!"); });
}

// LOA LINKS
let links = JSON.parse(localStorage.getItem("loa_links") || "[]");
function toggleForm() { const f = document.getElementById("loaForm"); f.style.display = f.style.display === "none" ? "block" : "none"; renderLinks(); }
function addLink() {
  const val = document.getElementById("loaInput").value.trim();
  if (!val) return showMsg("⚠️ Nhập Link hoặc Key!");
  links.push(val); localStorage.setItem("loa_links", JSON.stringify(links));
  document.getElementById("loaInput").value = ""; renderLinks();
}
function renderLinks() {
  const box = document.getElementById("linkList");
  box.innerHTML = "";
  if (links.length === 0) { box.innerHTML = "<div style='font-size:11px;color:var(--tx3)'>Chưa có link</div>"; return; }
  links.forEach(function(l, i) {
    const div = document.createElement("div");
    div.style.cssText = "display:flex;justify-content:space-between;align-items:center;padding:8px;border:1px solid var(--bd);border-radius:var(--rs);font-size:0.85rem;";
    div.innerHTML = `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;">${l}</span>
      <div style="display:flex;gap:4px;">
        <button onclick="openGoogle(${i})" style="font-size:11px;cursor:pointer;border:none;background:none;">🔍</button>
        <button onclick="removeLink(${i})" style="font-size:11px;cursor:pointer;border:none;background:none;">❌</button>
      </div>`;
    box.appendChild(div);
  });
  document.getElementById("loaStatus").innerText = `Có ${links.length} link`;
}
function openGoogle(i) { window.open("https://www.google.com/search?q=" + encodeURIComponent(links[i]), "_blank"); }
function removeLink(i) { links.splice(i, 1); localStorage.setItem("loa_links", JSON.stringify(links)); renderLinks(); }

// ESC đóng modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') ['logoutModal','msgBox','resetModal','buffModal','kickModal','maintenanceModal','expModal','dropModal','bossModal'].forEach(function(id){ document.getElementById(id).style.display = 'none'; });
});
</script>
</body>
</html>








