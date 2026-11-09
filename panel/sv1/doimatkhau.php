<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user    = trim($_POST['username'] ?? '');
    $oldpass = trim($_POST['oldpass'] ?? '');
    $newpass = trim($_POST['newpass'] ?? '');
    $phone   = normalize_phone($_POST['phone'] ?? ($_POST['gmail'] ?? ''));

    if (!$user || !$oldpass || !$newpass) {
        echo json_encode(["status"=>"error","message"=>"Thiếu thông tin!"]);
        exit;
    }

    try {

        // ===== lấy thông tin user =====
        $stmt = $pdo->prepare("SELECT password, phone FROM account WHERE username = ?");
        $stmt->execute([$user]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["status"=>"error","message"=>"Tài khoản không tồn tại!"]);
            exit;
        }

        // check mật khẩu cũ
        if ($row['password'] !== $oldpass) {
            echo json_encode(["status"=>"error","message"=>"Mật khẩu cũ không đúng!"]);
            exit;
        }

        // ===== nếu có nhập số điện thoại mới =====
        $newPhone = $row['phone']; // mặc định giữ số điện thoại cũ

        if (!empty($phone)) {

            if (!is_valid_phone($phone)) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Số điện thoại không hợp lệ!"
                ]);
                exit;
            }

            // ===== check trùng số điện thoại =====
            $check = $pdo->prepare("SELECT id FROM account WHERE phone = ? AND username != ? LIMIT 1");
            $check->execute([$phone, $user]);

            if ($check->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Số điện thoại này đã được sử dụng!"
                ]);
                exit;
            }

            $newPhone = $phone;
        }

        // ===== update =====
        $stmt = $pdo->prepare("
            UPDATE account 
            SET password = ?, phone = ?
            WHERE username = ?
        ");

        $ok = $stmt->execute([$newpass, $newPhone, $user]);

        echo json_encode([
            "status" => $ok ? "success" : "error",
            "message" => $ok ? "Đổi thông tin thành công!" : "Lỗi hệ thống!"
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Lỗi hệ thống!"
        ]);
    }
}
?>
