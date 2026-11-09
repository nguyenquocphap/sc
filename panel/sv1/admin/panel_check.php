<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

if (empty($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$stmt = $pdo->prepare("SELECT is_admin FROM account WHERE id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['account_id']]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account || (int)$account['is_admin'] !== 1) {
    http_response_code(403);
    exit('Forbidden');
}

$root = game_root();
$commandFile = panel_command_file();
$configFile = panel_config_file();
$writeResult = null;

if (isset($_GET['write_test']) && $_GET['write_test'] === '1') {
    $writeResult = append_panel_command('PANEL_TEST|' . date('Y-m-d H:i:s')) ? 'OK' : 'FAILED';
}

function yes_no(bool $value): string {
    return $value ? 'OK' : 'NO';
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Check</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7fb;color:#102a43;padding:24px}
.box{max-width:900px;margin:auto;background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:18px}
table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e2e8f0;padding:10px;text-align:left;vertical-align:top}
.ok{color:#047857;font-weight:700}.bad{color:#b91c1c;font-weight:700}
code{background:#f1f5f9;padding:2px 5px;border-radius:4px}
a.btn{display:inline-block;margin:12px 8px 0 0;padding:10px 14px;border-radius:8px;background:#1d4ed8;color:white;text-decoration:none;font-weight:700}
</style>
</head>
<body>
<div class="box">
  <h2>Panel Check</h2>
  <?php if ($writeResult !== null): ?>
    <p>Write test: <span class="<?= $writeResult === 'OK' ? 'ok' : 'bad' ?>"><?= htmlspecialchars($writeResult) ?></span></p>
  <?php endif; ?>
  <table>
    <tr><th>Mục</th><th>Giá trị</th><th>Trạng thái</th></tr>
    <tr><td>Game root</td><td><code><?= htmlspecialchars($root) ?></code></td><td class="<?= is_dir($root) ? 'ok' : 'bad' ?>"><?= yes_no(is_dir($root)) ?></td></tr>
    <tr><td>Game root writable</td><td><code><?= htmlspecialchars($root) ?></code></td><td class="<?= is_writable($root) ? 'ok' : 'bad' ?>"><?= yes_no(is_writable($root)) ?></td></tr>
    <tr><td>Command file</td><td><code><?= htmlspecialchars($commandFile) ?></code></td><td class="<?= file_exists($commandFile) ? 'ok' : '' ?>"><?= file_exists($commandFile) ? 'EXISTS' : 'NOT EXISTS' ?></td></tr>
    <tr><td>Config file</td><td><code><?= htmlspecialchars($configFile) ?></code></td><td class="<?= is_file($configFile) ? 'ok' : 'bad' ?>"><?= yes_no(is_file($configFile)) ?></td></tr>
    <tr><td>PHP user</td><td><code><?= htmlspecialchars(get_current_user()) ?></code></td><td></td></tr>
    <tr><td>USERPROFILE</td><td><code><?= htmlspecialchars((string)getenv('USERPROFILE')) ?></code></td><td></td></tr>
    <tr><td>GAME_ROOT env</td><td><code><?= htmlspecialchars((string)getenv('GAME_ROOT')) ?></code></td><td></td></tr>
  </table>
  <a class="btn" href="?write_test=1">Ghi lệnh test</a>
  <a class="btn" href="/sv1/admin/panel">Quay lại panel</a>
</div>
</body>
</html>
