<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
header('Content-Type: application/json; charset=utf-8');

function login_json_error(string $message): void {
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function login_json_success(): void {
    echo json_encode(["status" => "success"], JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_login_security_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_security (
            id INT AUTO_INCREMENT PRIMARY KEY,
            login_key CHAR(64) NOT NULL UNIQUE,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            failed_count INT NOT NULL DEFAULT 0,
            first_failed_at DATETIME NULL,
            locked_until DATETIME NULL,
            lock_level INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_locked_until (locked_until),
            INDEX idx_username (username),
            INDEX idx_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function login_lock_minutes(int $level): int {
    $durations = [5, 10, 30, 60, 120, 240, 480, 1440];
    $index = max(0, min($level - 1, count($durations) - 1));
    return $durations[$index];
}

function login_remaining_text(int $seconds): string {
    $minutes = max(1, (int)ceil($seconds / 60));
    return $minutes . ' phút';
}

function load_login_security(PDO $pdo, string $loginKey): ?array {
    $stmt = $pdo->prepare("SELECT * FROM login_security WHERE login_key = ? LIMIT 1");
    $stmt->execute([$loginKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function clear_login_security(PDO $pdo, string $loginKey): void {
    $stmt = $pdo->prepare("DELETE FROM login_security WHERE login_key = ?");
    $stmt->execute([$loginKey]);
}

function record_login_failure(PDO $pdo, string $loginKey, string $username, string $ip): string {
    $now = time();
    $row = load_login_security($pdo, $loginKey);

    $failedCount = 1;
    $firstFailedAt = date('Y-m-d H:i:s', $now);
    $lockLevel = (int)($row['lock_level'] ?? 0);

    if ($row && !empty($row['first_failed_at'])) {
        $firstTs = strtotime($row['first_failed_at']);
        if ($firstTs !== false && ($now - $firstTs) <= 120) {
            $failedCount = ((int)$row['failed_count']) + 1;
            $firstFailedAt = $row['first_failed_at'];
        }
    }

    if ($failedCount >= 5) {
        $lockLevel++;
        $minutes = login_lock_minutes($lockLevel);
        $lockedUntil = date('Y-m-d H:i:s', $now + ($minutes * 60));
        $stmt = $pdo->prepare("
            INSERT INTO login_security (login_key, username, ip_address, failed_count, first_failed_at, locked_until, lock_level)
            VALUES (?, ?, ?, 0, ?, ?, ?)
            ON DUPLICATE KEY UPDATE username=VALUES(username), ip_address=VALUES(ip_address), failed_count=0,
                first_failed_at=VALUES(first_failed_at), locked_until=VALUES(locked_until), lock_level=VALUES(lock_level)
        ");
        $stmt->execute([$loginKey, $username, $ip, date('Y-m-d H:i:s', $now), $lockedUntil, $lockLevel]);
        return "Bạn nhập sai quá nhiều lần. Tài khoản/IP này bị khóa đăng nhập $minutes phút.";
    }

    $stmt = $pdo->prepare("
        INSERT INTO login_security (login_key, username, ip_address, failed_count, first_failed_at, locked_until, lock_level)
        VALUES (?, ?, ?, ?, ?, NULL, ?)
        ON DUPLICATE KEY UPDATE username=VALUES(username), ip_address=VALUES(ip_address), failed_count=VALUES(failed_count),
            first_failed_at=VALUES(first_failed_at), locked_until=NULL, lock_level=VALUES(lock_level)
    ");
    $stmt->execute([$loginKey, $username, $ip, $failedCount, $firstFailedAt, $lockLevel]);

    $left = max(0, 5 - $failedCount);
    return "Sai tài khoản hoặc mật khẩu. Còn $left lần thử trong 2 phút trước khi bị khóa.";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    login_json_error("Invalid request");
}

$user = trim($_POST['username'] ?? '');
$pass = trim($_POST['password'] ?? '');

if (!$user || !$pass) {
    login_json_error("Thiếu tài khoản hoặc mật khẩu");
}

try {
    ensure_login_security_table($pdo);

    $ip = security_client_ip();
    $loginKey = hash('sha256', mb_strtolower($user, 'UTF-8') . '|' . $ip);
    $lock = load_login_security($pdo, $loginKey);

    if ($lock && !empty($lock['locked_until'])) {
        $untilTs = strtotime($lock['locked_until']);
        if ($untilTs !== false && $untilTs > time()) {
            login_json_error("Đăng nhập đang bị khóa. Vui lòng thử lại sau " . login_remaining_text($untilTs - time()) . ".");
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM account WHERE username = :user AND password = :pass LIMIT 1");
    $stmt->bindParam(":user", $user);
    $stmt->bindParam(":pass", $pass);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        clear_login_security($pdo, $loginKey);
        session_regenerate_id(true);
        $_SESSION['account_id'] = $data['id'];
        $_SESSION['login_at'] = time();
        $_SESSION['last_activity'] = time();
        ensure_csrf_token();
        login_json_success();
    }

    login_json_error(record_login_failure($pdo, $loginKey, $user, $ip));
} catch (Exception $e) {
    login_json_error("Lỗi server");
}
