<?php
$DB_HOST = "127.0.0.1";
$DB_NAME = "team2026";
$DB_USER = "root";
$DB_PASS = "";
$DB_CHARSET = "utf8mb4";
try {
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Lỗi kết nối Database: " . $e->getMessage());
}

function ensure_account_phone_column(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM account LIKE 'phone'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE account ADD COLUMN phone VARCHAR(20) NULL DEFAULT NULL AFTER password");
        }
    } catch (PDOException $e) {
        // Account table may not exist during import/setup; callers will surface real DB errors.
    }
}

function normalize_phone(string $phone): string {
    return preg_replace('/[\s\-.]+/', '', trim($phone)) ?? '';
}

function is_valid_phone(string $phone): bool {
    return (bool)preg_match('/^0[0-9]{9,14}$/', $phone);
}

ensure_account_phone_column($pdo);

function ensure_panel_account_columns(PDO $pdo): void {
    // FIX-SRC4 team2026: panel can last_check/vdn/tongnap nhung schema goc khong co -> tu tao.
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM account")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[strtolower($r['Field'])] = true;
        }
        if (!isset($cols['last_check'])) {
            $pdo->exec("ALTER TABLE account ADD COLUMN last_check DATETIME NULL DEFAULT NULL");
        }
        if (!isset($cols['vnd'])) {
            $pdo->exec("ALTER TABLE account ADD COLUMN vnd INT NOT NULL DEFAULT 0");
        }
        if (!isset($cols['tongnap'])) {
            $pdo->exec("ALTER TABLE account ADD COLUMN tongnap BIGINT NOT NULL DEFAULT 0");
        }
    } catch (PDOException $e) {
        // Khong block page neu khong co quyen ALTER; trang se fallback.
    }
}
ensure_panel_account_columns($pdo);

function ensure_panel_extra_tables(PDO $pdo): void {
    // FIX-SRC4 team2026: giftcode thieu cot type; power_limit chua co bang.
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM giftcode")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[strtolower($r['Field'])] = true;
        }
        if (!isset($cols['type'])) {
            $pdo->exec("ALTER TABLE giftcode ADD COLUMN type INT NOT NULL DEFAULT 0");
        }
    } catch (PDOException $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS power_limit ("
            . "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
            . "power BIGINT NOT NULL DEFAULT 0, hp BIGINT NOT NULL DEFAULT 0,"
            . "mp BIGINT NOT NULL DEFAULT 0, damage BIGINT NOT NULL DEFAULT 0,"
            . "defense BIGINT NOT NULL DEFAULT 0, critical INT NOT NULL DEFAULT 0"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $n = (int)$pdo->query("SELECT COUNT(*) FROM power_limit")->fetchColumn();
        if ($n === 0) {
            $pdo->exec("INSERT INTO power_limit (power, hp, mp, damage, defense, critical)"
                . " VALUES (200000000000, 200000000000, 200000000000, 2000000000, 2000000, 70)");
        }
    } catch (PDOException $e) {}
}
ensure_panel_extra_tables($pdo);

function security_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function ensure_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_token(): string {
    return ensure_csrf_token();
}

function clear_login_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function request_wants_json(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return strpos($accept, 'application/json') !== false || $requestedWith === 'xmlhttprequest';
}

function enforce_login_session_timeout(int $timeoutSeconds = 1200): void {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['account_id'])) {
        return;
    }

    $now = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? ($_SESSION['login_at'] ?? $now));

    if ($lastActivity > 0 && ($now - $lastActivity) > $timeoutSeconds) {
        clear_login_session();

        $path = current_public_path();
        $isAdminPath = strpos($path, '/sv1/admin/') === 0;

        if (request_wants_json() || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($isAdminPath) {
            header('Location: /sv1/trangchu?timeout=1');
            exit;
        }

        return;
    }

    $_SESSION['last_activity'] = $now;
    if (empty($_SESSION['login_at'])) {
        $_SESSION['login_at'] = $now;
    }
}

function require_csrf_token(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        http_response_code(403);
        exit('CSRF invalid');
    }
    $sessionToken = $_SESSION['csrf'] ?? '';
    $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($requestToken) || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        exit('CSRF invalid');
    }
}

function enforce_admin_same_origin_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/sv1/admin/') === false) {
        return;
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        http_response_code(403);
        exit('Origin invalid');
    }
    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($source === '') {
        http_response_code(403);
        exit('Origin invalid');
    }
    $sourceHost = strtolower(parse_url($source, PHP_URL_HOST) ?? '');
    $hostOnly = strtolower(explode(':', $host)[0]);
    if ($sourceHost === '' || $sourceHost !== $hostOnly) {
        http_response_code(403);
        exit('Origin invalid');
    }
}

function current_public_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('/\.php$/i', '', $path) ?: $path;
    return $path[0] === '/' ? $path : '/' . $path;
}

enforce_login_session_timeout(1200);
enforce_admin_same_origin_post();
// Cấu hình trang chủ
$SITE_OPEN_TIME = "2026-05-25T20:00:00";
$SITE_TESTFLIGHT_URL = "https://testflight.apple.com/join/SsYJbtr1";
$SITE_ZALO_URL = "https://zalo.me/g/nran3u1pi3hgm9mq5mpc";
$SITE_FACEBOOK_URL = "https://www.facebook.com/share/1JshHuFoyH/";
?>
