<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user  = trim($_POST['username'] ?? '');
    $phone = normalize_phone($_POST['phone'] ?? ($_POST['email'] ?? ''));

    // ================= VALIDATE =================
    if (!$user || !$phone) {
        echo json_encode([
            "status" => "error",
            "message" => "Vui lòng nhập đầy đủ tài khoản và số điện thoại!"
        ]);
        exit;
    }

    if (!is_valid_phone($phone)) {
        echo json_encode([
            "status" => "error",
            "message" => "Số điện thoại không hợp lệ!"
        ]);
        exit;
    }

    try {

        // ================= CHECK ACCOUNT =================
        $stmt = $pdo->prepare("
            SELECT username, phone
            FROM account 
            WHERE username = ? AND phone = ?
            LIMIT 1
        ");
        $stmt->execute([$user, $phone]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            echo json_encode([
                "status" => "error",
                "message" => "Tài khoản hoặc số điện thoại không đúng!"
            ]);
            exit;
        }

        // ================= SUCCESS =================
        echo json_encode([
            "status" => "success",
            "message" => "Thành công! Hãy liên hệ Admin."
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi hệ thống, vui lòng thử lại!"
        ]);
    }
}
?>
