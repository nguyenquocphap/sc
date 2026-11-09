<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";

if (empty($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$account_id = (int)$_SESSION['account_id'];

$stmt = $pdo->prepare("SELECT id, is_admin FROM account WHERE id = ? LIMIT 1");
$stmt->execute([$account_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: /sv1/trangchu");
    exit;
}

if ((int)$user['is_admin'] !== 1) {
    echo json_encode(["status" => "error", "msg" => "Khong co quyen!"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input || !isset($input['items']) || !is_array($input['items'])) {
        echo json_encode(["status" => "error", "msg" => "Du lieu khong hop le"]);
        exit;
    }

    $data = [];
    foreach ($input['items'] as $item) {
        if (!is_array($item)) {
            echo json_encode(["status" => "error", "msg" => "Du lieu vat pham khong hop le"]);
            exit;
        }
        $itemId = isset($item['id']) ? (int)$item['id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        if ($itemId <= 0 || $quantity <= 0) {
            echo json_encode(["status" => "error", "msg" => "ID hoac so luong vat pham khong hop le"]);
            exit;
        }
        $data[] = [
            'id' => $itemId,
            'quantity' => $quantity,
            'options' => isset($item['options']) && is_array($item['options']) ? $item['options'] : [],
        ];
    }
    $type = isset($input['type']) ? (int)$input['type'] : 0; // 0: normal, 1: VIP, 2: share
    if (!in_array($type, [0, 1, 2], true)) {
        echo json_encode(["status" => "error", "msg" => "Loai code khong hop le"]);
        exit;
    }

    $batchCount = $type === 2 ? 300 : 1;
    $countLeft  = $type === 2 ? 1 : 9999;
    $detail     = json_encode($data, JSON_UNESCAPED_UNICODE);
    $expired    = '2030-01-01 00:00:00';

    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $makeCode = function () use ($characters): string {
        $randomCode = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 8; $i++) {
            $randomCode .= $characters[random_int(0, $max)];
        }
        return $randomCode;
    };

    $codes = [];
    $ids = [];

    try {
        $pdo->beginTransaction();

        // Bang giftcode khong co AUTO_INCREMENT nen tu tinh id = MAX(id) + 1.
        $maxId = $pdo->query("SELECT MAX(id) FROM giftcode")->fetchColumn();
        $newId = ((int)$maxId) + 1;

        $ck = $pdo->prepare("SELECT id FROM giftcode WHERE code = ? LIMIT 1");
        $stmt = $pdo->prepare("INSERT INTO giftcode (id, code, count_left, detail, expired, type) VALUES (?, ?, ?, ?, ?, ?)");

        for ($n = 0; $n < $batchCount; $n++) {
            do {
                $randomCode = $makeCode();
                $ck->execute([$randomCode]);
                $exists = (bool)$ck->fetch();
            } while ($exists || in_array($randomCode, $codes, true));

            $stmt->execute([$newId, $randomCode, $countLeft, $detail, $expired, $type]);
            $codes[] = $randomCode;
            $ids[] = $newId;
            $newId++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(["status" => "error", "msg" => "Khong the tao giftcode"]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "code" => $codes[0] ?? '',
        "codes" => $codes,
        "id" => $ids[0] ?? 0,
        "ids" => $ids,
        "type" => $type,
        "count_left" => $countLeft,
        "created" => count($codes)
    ]);
    exit;
}
?>
