<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sv1/config.php';
header('Content-Type: application/json; charset=utf-8');

function registration_response(string $status, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    registration_response('error', 'Phương thức không hợp lệ.', 405);
}

$user = trim($_POST['username'] ?? '');
$pass = $_POST['password'] ?? '';
$phone = normalize_phone($_POST['phone'] ?? ($_POST['email'] ?? ''));
$ip = security_client_ip();

if ($user === '' || $pass === '' || $phone === '') {
    registration_response('error', 'Vui lòng nhập đầy đủ thông tin!');
}

if (strlen($pass) < 6) {
    registration_response('error', 'Mật khẩu phải từ 6 ký tự trở lên!');
}

if (!is_valid_phone($phone)) {
    registration_response('error', 'Số điện thoại không hợp lệ!');
}

try {
    $pdo->beginTransaction();

    // Mỗi IP có một dòng khóa theo ngày. SELECT ... FOR UPDATE giúp hai request
    // chạy đồng thời không thể cùng vượt qua giới hạn.
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO registration_ip_limits
            (ip_address, registration_date, account_count, last_registered_at)
        VALUES (?, CURRENT_DATE(), 0, NULL)
    ");
    $stmt->execute([$ip]);

    $stmt = $pdo->prepare("
        SELECT account_count,
               CASE
                   WHEN last_registered_at IS NULL THEN NULL
                   ELSE TIMESTAMPDIFF(SECOND, last_registered_at, NOW())
               END AS seconds_since_last
        FROM registration_ip_limits
        WHERE ip_address = ? AND registration_date = CURRENT_DATE()
        FOR UPDATE
    ");
    $stmt->execute([$ip]);
    $limit = $stmt->fetch();

    if (!$limit) {
        throw new RuntimeException('Không thể khởi tạo giới hạn đăng ký.');
    }

    if ((int)$limit['account_count'] >= 10) {
        $pdo->rollBack();
        registration_response('error', 'IP này đã đăng ký tối đa 10 tài khoản trong hôm nay. Vui lòng thử lại vào ngày mai.', 429);
    }

    if ($limit['seconds_since_last'] !== null && (int)$limit['seconds_since_last'] < 30) {
        $waitSeconds = 30 - (int)$limit['seconds_since_last'];
        $pdo->rollBack();
        registration_response('error', "Vui lòng chờ {$waitSeconds} giây trước khi đăng ký tài khoản tiếp theo.", 429);
    }

    $stmt = $pdo->prepare('SELECT username FROM account WHERE username = ? LIMIT 1');
    $stmt->execute([$user]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        registration_response('error', "Tài khoản {$user} đã tồn tại!");
    }

    $stmt = $pdo->prepare('SELECT phone FROM account WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        registration_response('error', "Số điện thoại {$phone} đã tồn tại!");
    }

    $stmt = $pdo->prepare('INSERT INTO account (username, password, phone) VALUES (?, ?, ?)');
    $stmt->execute([$user, $pass, $phone]);

    $stmt = $pdo->prepare("
        UPDATE registration_ip_limits
        SET account_count = account_count + 1,
            last_registered_at = NOW()
        WHERE ip_address = ? AND registration_date = CURRENT_DATE()
    ");
    $stmt->execute([$ip]);

    $pdo->commit();
    registration_response('success', 'Đăng ký thành công!');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Registration error: ' . $e->getMessage());
    registration_response('error', 'Lỗi hệ thống, vui lòng thử lại!', 500);
}
