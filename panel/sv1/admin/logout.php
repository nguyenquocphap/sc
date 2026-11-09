<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

// Xóa toàn bộ session
$_SESSION = [];
session_unset();
session_destroy();

// Xóa cookie session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Nếu gọi bằng fetch → trả JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(["status" => "ok"]);
    exit;
}

// Nếu truy cập trực tiếp → redirect
header("Location: /sv1/trangchu");
exit;
