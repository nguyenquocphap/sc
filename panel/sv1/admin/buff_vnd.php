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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['char_name']) && isset($_POST['amount'])) {
    require_csrf_token();
    $char_name = trim($_POST['char_name']);
    $amount = (int)$_POST['amount'];
    if ($amount <= 0) {
        $message = 'Số tiền phải > 0!';
    } else if ($char_name === '') {
        $message = 'Tên nhân vật không được để trống!';
    } else {
        $command = sprintf('BUFF_VND|%s|%d', str_replace(["\r", "\n"], '', $char_name), $amount);
        if (append_panel_command($command)) {
            $message = "Đã gửi lệnh buff cá cho nhân vật '$char_name' với số cá " . number_format($amount) . ".";
        } else {
            $message = 'Không thể ghi lệnh buff vào file điều khiển game.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buff Cá - Admin</title>
<link rel="icon" type="image/jpeg" href="../../img/icon.jpg">
<style>
body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
.container { max-width: 400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
h1 { text-align: center; color: #333; }
form { display: flex; flex-direction: column; }
label { margin-bottom: 5px; font-weight: bold; }
input { padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
button { padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #218838; }
.message { margin-top: 15px; padding: 10px; border-radius: 4px; text-align: center; }
.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.back { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #007bff; }
.back:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h1>Buff Cá cho Nhân Vật</h1>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <label for="char_name">Tên Nhân Vật:</label>
        <input type="text" id="char_name" name="char_name" required>

        <label for="amount">Số Cá:</label>
        <input type="number" id="amount" name="amount" min="1" required>

        <button type="submit">Buff Cá</button>
    </form>
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'thành công') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <a href="panel" class="back">← Quay lại Panel</a>
</div>
</body>
</html>
