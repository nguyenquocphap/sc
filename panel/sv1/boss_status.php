<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin/game_bridge.php';

$bossStatusPath = panel_boss_status_file();
if (!file_exists($bossStatusPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Không tìm thấy file dữ liệu boss."], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = @file_get_contents($bossStatusPath);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(["error" => "Không thể đọc file dữ liệu boss."], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($raw, true);
if ($data === null) {
    http_response_code(500);
    echo json_encode(["error" => "Dữ liệu boss không hợp lệ."], JSON_UNESCAPED_UNICODE);
    exit;
}

$headIds = [];
foreach ($data['bosses'] as $boss) {
    if (isset($boss['outfit0'])) {
        $headIds[] = (int)$boss['outfit0'];
    }
}
$headIds = array_values(array_unique($headIds));
$avatars = [];
if (!empty($headIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($headIds), '?'));
        $stmt = $pdo->prepare("SELECT head_id, avatar_id FROM head_avatar WHERE head_id IN ($placeholders)");
        $stmt->execute($headIds);
        while ($row = $stmt->fetch()) {
            $avatars[(int)$row['head_id']] = (int)$row['avatar_id'];
        }
    } catch (Exception $e) {
        // Không cần dừng; vẫn trả dữ liệu boss mà không có avatar.
    }
}

$avatarOverrides = [
    291 => 2893,   // Broly
    294 => 2924,   // Super Broly
    1501 => 13488, // Mat Troi
];

foreach ($data['bosses'] as &$boss) {
    $head = isset($boss['outfit0']) ? (int)$boss['outfit0'] : 0;
    $name = (string)($boss['name'] ?? '');

    if (strcasecmp(trim($name), 'Boss Test Dame') === 0 && (int)($boss['avatar_id'] ?? 0) === 18144) {
        $boss['avatar_id'] = 18144;
    } elseif (stripos($name, 'super broly') !== false) {
        $boss['avatar_id'] = 2924;
    } elseif (stripos($name, 'broly') !== false) {
        $boss['avatar_id'] = 2893;
    } elseif (isset($avatarOverrides[$head])) {
        $boss['avatar_id'] = $avatarOverrides[$head];
    } elseif (isset($avatars[$head])) {
        $boss['avatar_id'] = $avatars[$head];
    } else {
        $boss['avatar_id'] = 1;
    }
}
unset($boss);

echo json_encode($data, JSON_UNESCAPED_UNICODE);
