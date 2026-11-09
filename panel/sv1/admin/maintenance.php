<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

if (!isset($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$account_id = $_SESSION['account_id'];

$stmt = $pdo->prepare("SELECT is_admin FROM account WHERE id = :id LIMIT 1");
$stmt->bindParam(":id", $account_id);
$stmt->execute();
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$acc || $acc['is_admin'] != 1) {
    header("Location: /sv1/trangchu");
    exit;
}

$message = '';
$csrfToken = ensure_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_maintenance'])) {
    require_csrf_token();
    $command = 'MAINTENANCE_NOW';
    if (append_panel_command($command)) {
        $message = 'Lệnh bảo trì đã được gửi, server sẽ thực hiện bảo trì trong game.';
    } else {
        $message = 'Không thể ghi lệnh bảo trì vào file điều khiển game.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bảo Trì - Admin</title>
<link rel="icon" type="image/jpeg" href="../../img/icon.jpg">
<style>
body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
.container { max-width: 400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
h1 { text-align: center; color: #333; }
form { display: flex; flex-direction: column; }
button { padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
button:hover { background: #c82333; }
.message { margin-top: 15px; padding: 10px; border-radius: 4px; text-align: center; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.back { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #007bff; }
.back:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h1>Bảo Trì Server</h1>
    <p style="text-align: center; color: #666;">Nhấn nút dưới để bắt đầu chế độ bảo trì. Tất cả người chơi sẽ bị kick và server sẽ offline để bảo trì.</p>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <button type="submit" name="start_maintenance">Bắt Đầu Bảo Trì</button>
    </form>
    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <a href="panel" class="back">← Quay lại Panel</a>
</div>
</body>
</html>
