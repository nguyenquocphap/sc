<?php
// SRC_4 icon proxy: /icon/{id}.png  (vd /icon/4028.png)
// Nguon chuan: storage/Download/SRC_4.zip/SRC/data/icon/x4/{id}.png (ten file = id icon).
// Tool nro4.py muc [9]/[10] > sync icon se copy TOAN BO x4/*.png vao thu muc /icon/ nay.
// Thu tu tim: /icon/{id}.png -> /img/{id}.png -> /icon/1.png -> /img/account.gif
$id = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['id']) : '1';
if ($id === '' || $id === '0') $id = '1';
$candidates = [
    __DIR__ . '/icon/' . $id . '.png',
    __DIR__ . '/img/' . $id . '.png',
    __DIR__ . '/icon/1.png',
    __DIR__ . '/img/account.gif',
];
$f = null;
foreach ($candidates as $c) { if (is_file($c)) { $f = $c; break; } }
if ($f === null) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : ($ext === 'gif' ? 'image/gif' : 'image/png')));
header('Cache-Control: public, max-age=86400');
header('X-Icon-Id: ' . $id);
readfile($f);
