<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";

function getAvatar($gender) {
    return match((int)$gender){
        0 => "../img/25.jpg",
        1 => "../img/24.jpg",
        2 => "../img/26.jpg",
        default => "../img/25.jpg"
    };
}

function formatK($num){
    if($num >= 1000000000) return round($num/1000000000, 1)." Tỷ";
    if($num >= 1000000) return round($num/1000000, 1)." Tr";
    if($num >= 1000) return round($num/1000, 1)." K";
    return number_format($num);
}

// Gộp items_bag và items_box để lấy tổng số lượng item
function getItemQuantityCombined($items_bag, $items_box, $id){
    $total = 0;
    foreach([$items_bag, $items_box] as $itemsText){
        $items = json_decode($itemsText, true);
        if(!$items) continue;
        foreach($items as $it){
            $it = json_decode($it, true);
            if(isset($it[0]) && $it[0] == $id){
                $total += $it[1] ?? 0;
            }
        }
    }
    return $total;
}

function getInventory($data_inventory){
    $inv = json_decode($data_inventory, true);
    return [
        "vang_chim" => $inv[0] ?? 0,
        "ngoc_xanh" => $inv[1] ?? 0,
        "hong_ngoc" => $inv[2] ?? 0
    ];
}

function getStat($items_bag, $items_box, $data_point){
    $arr = json_decode($data_point, true);
    return [
        "sd" => $arr[7] ?? 0,
        "hp" => getItemQuantityCombined($items_bag, $items_box, 16),
        "ki" => getItemQuantityCombined($items_bag, $items_box, 20)
    ];
}

$res = $pdo->query("SELECT name, gender, data_inventory, items_bag, items_box, data_point FROM player");

$vangTuoi = $vangChim = $ngocXanh = $hongNgoc = [];
$sdBXH = $hpBXH = $kiBXH = [];

while($row = $res->fetch()){
    $inv = getInventory($row['data_inventory']);
    $stat = getStat($row['items_bag'], $row['items_box'], $row['data_point']);
    $vn = getItemQuantityCombined($row['items_bag'], $row['items_box'], 457);

    $base = [
        "name"=>$row['name'],
        "gender"=>$row['gender']
    ];

    $vangTuoi[] = $base + ["value"=>$vn];
    $vangChim[] = $base + ["value"=>$inv['vang_chim']];
    $ngocXanh[] = $base + ["value"=>$inv['ngoc_xanh']];
    $hongNgoc[] = $base + ["value"=>$inv['hong_ngoc']];

    $sdBXH[] = $base + ["value"=>$stat['sd']];
    $hpBXH[] = $base + ["value"=>$stat['hp']];
    $kiBXH[] = $base + ["value"=>$stat['ki']];
}

function top10(&$arr){
    usort($arr, fn($a,$b)=>$b['value'] <=> $a['value']);
    $arr = array_slice($arr,0,10);
}

top10($vangTuoi);
top10($vangChim);
top10($ngocXanh);
top10($hongNgoc);
top10($sdBXH);
top10($hpBXH);
top10($kiBXH);

function renderTable($title, $data, $icon){
    echo "<div class='title'>$title</div>";
    echo "<table class='table'>";
    echo "<tr><th>TOP</th><th>Thông Tin Hiện Tại</th><th></th></tr>";

    foreach($data as $i=>$p){
        echo "<tr class='top$i'>
        <td class='name'>".($i+1).". {$p['name']}</td>
        <td class='value'>
            <div class='value-box'>
                <img src='$icon'>
                ".formatK($p['value'])."
            </div>
        </td>
        <td><div class='avatar'><img src='".getAvatar($p['gender'])."'></div></td>
        </tr>";
    }
    echo "</table><br>";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bảng Xếp Hạng Tài Nguyên</title>
<link rel="icon" type="image/jpeg" href="../img/icon.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<meta http-equiv="refresh" content="30">

<style>
/* --- PHẦN BACK GROUND ĐỒNG BỘ PANEL --- */
body {
    margin: 0;
    font-family: 'Segoe UI', Arial;
    background: linear-gradient(135deg, #bae6fd, #60a5fa);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    padding: 20px 0;
    font-size: 90%;
}

.wrapper {
    max-width: 720px;
    width: 100%;
    background: linear-gradient(160deg, #4a4a4a, #2b2b2b 65%, #1b1b1b);
    border-radius: 28px;
    padding: 22px;
    box-shadow: 0 25px 60px rgba(0,0,0,.5), inset 0 2px 8px rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,0.15);
    color: white;
    margin: 0 12px;
}
/* -------------------------------------- */

* { box-sizing: border-box; }

.topbar {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
}

.home-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 26px;
    border-radius: 30px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    text-decoration: none;
    font-weight: 800;
    box-shadow: 0 8px 20px rgba(0,0,0,.4);
    transition: .2s;
}

.home-btn:hover { transform: scale(1.08); }

.tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
}

.tabs button {
    flex: 1;
    padding: 15px;
    font-weight: 800;
    border: none;
    border-radius: 12px;
    color: #fff;
    font-size: 110%;
    cursor: pointer;
}

.tabs button:nth-child(1) { background: #f43f5e; }
.tabs button:nth-child(2) { background: #4caf50; }

.bxh-box {
    padding: 5px;
    border-radius: 16px;
    /* Giữ màu xanh nhẹ bao quanh card nếu muốn, hoặc để trong suốt */
    background: rgba(30, 64, 175, 0.3); 
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 15px;
    color: #333; /* Chữ trong bảng vẫn để màu tối cho dễ đọc */
}

.title {
    text-align: center;
    font-weight: 800;
    color: #fff;
    padding: 8px;
    border-radius: 10px;
    margin: 8px 0;
    background: #2196f3;
    text-transform: uppercase;
    font-size: 95%;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 8px;
    text-align: center;
}

.table th {
    background: #f1f5f9;
    color: #475569;
    font-size: 85%;
    border-bottom: 2px solid #e2e8f0;
}

.value-box {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
}

.value-box img {
    height: 20px;
    width: auto;
    object-fit: contain;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    margin: auto;
    border: 1px solid #ddd;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.name { font-weight: 700; }

/* Styles cho Top 1, 2, 3 */
.top0 td { background: #fff8e1; }
.top1 td { background: #f1f5f9; }
.top2 td { background: #fff5f5; }

.tab { display: none; }
.tab.active { display: block; }

@media(min-width:769px){
    body { font-size: 110%; }
}

/* Modern resource leaderboard skin */
body {
    font-family: 'Times New Roman', Times, serif;
    background:
        radial-gradient(circle at 18% 10%, rgba(255,255,255,.76), transparent 28%),
        linear-gradient(135deg, #dff3ff 0%, #7db7f2 48%, #4f8ed7 100%);
    align-items: flex-start;
}
.wrapper {
    max-width: 980px;
    background: rgba(255,255,255,.92);
    color: #123456;
    border-radius: 18px;
    border: 1.5px solid rgba(37,99,235,.22);
    box-shadow: 0 18px 50px rgba(15,46,89,.28);
}
.wrapper::before {
    content: "💎 BXH TÀI NGUYÊN 💎";
    display: block;
    text-align: center;
    font-size: 1.65rem;
    font-weight: 900;
    color: #1f5fbf;
    margin: 2px 0 16px;
}
.topbar { margin-bottom: 14px; }
.home-btn {
    background: #fff;
    color: #1f5fbf;
    border: 1.5px solid rgba(31,95,191,.24);
    box-shadow: 0 8px 22px rgba(31,95,191,.16);
}
.tabs {
    background: rgba(255,255,255,.72);
    border: 1px solid rgba(31,95,191,.14);
    border-radius: 14px;
    padding: 6px;
    gap: 8px;
}
.tabs button {
    border-radius: 10px;
    padding: 12px 8px;
    font-size: 1rem;
    opacity: .78;
    box-shadow: none;
}
.tabs button.active {
    opacity: 1;
    box-shadow: 0 8px 20px rgba(31,95,191,.22);
    transform: translateY(-1px);
}
.bxh-box {
    padding: 0;
    background: transparent;
}
.card {
    background: #f8fbff;
    border: 1px solid rgba(31,95,191,.14);
    border-radius: 16px;
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
}
.title {
    background: linear-gradient(135deg,#1f5fbf,#41a3ff);
    border-radius: 12px;
    padding: 12px;
    margin: 14px 0 10px;
    box-shadow: 0 8px 22px rgba(31,95,191,.22);
    letter-spacing: 0;
}
.table {
    border-collapse: separate;
    border-spacing: 0 9px;
}
.table th {
    background: transparent;
    color: #5b708c;
    border: 0;
    font-size: .84rem;
    text-transform: uppercase;
}
.table td {
    background: #fff;
    border-top: 1px solid rgba(31,95,191,.12);
    border-bottom: 1px solid rgba(31,95,191,.12);
    color: #123456;
}
.table td:first-child {
    border-left: 1px solid rgba(31,95,191,.12);
    border-radius: 12px 0 0 12px;
}
.table td:last-child {
    border-right: 1px solid rgba(31,95,191,.12);
    border-radius: 0 12px 12px 0;
}
.top0 td { background: linear-gradient(90deg,#fff7d6,#fff); border-color: rgba(217,158,0,.28); }
.top1 td { background: linear-gradient(90deg,#eef4ff,#fff); border-color: rgba(96,120,160,.22); }
.top2 td { background: linear-gradient(90deg,#fff0e8,#fff); border-color: rgba(210,108,50,.22); }
.top0 .name::before { content: "🥇 "; }
.top1 .name::before { content: "🥈 "; }
.top2 .name::before { content: "🥉 "; }
.name {
    color: #0f2f57;
    font-size: 1rem;
}
.value-box {
    background: #eef6ff;
    border: 1px solid rgba(31,95,191,.14);
    border-radius: 999px;
    padding: 5px 10px;
    color: #0f2f57;
}
.value-box img {
    width: 24px;
    height: 24px;
}
.avatar {
    width: 48px;
    height: 48px;
    background: #f1f7ff;
    border: 2px solid rgba(31,95,191,.25);
    box-shadow: 0 6px 14px rgba(31,95,191,.16);
}
@media(max-width:640px){
    body { padding: 10px 0; }
    .wrapper { margin: 0 8px; padding: 14px; border-radius: 14px; }
    .wrapper::before { font-size: 1.28rem; margin-bottom: 10px; }
    .tabs button { font-size: .92rem; }
    .card { padding: 9px; }
    .table th, .table td { padding: 7px 5px; font-size: .86rem; }
    .value-box { padding: 4px 8px; }
    .avatar { width: 42px; height: 42px; }
}
</style>

<script>
function openTab(id){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
}
</script>
</head>

<body>

<div class="wrapper">

    <div class="topbar">
        <a href="/sv1/admin/panel" class="home-btn">
            <i class="fa-solid fa-house"></i> Trang Chủ
        </a>
    </div>

    <div class="tabs">
        <button class="active" onclick="openTab('tn')">💰 Tài Sản</button>
        <button onclick="openTab('cs')">⚡ Chỉ Số</button>
    </div>

    <div class="bxh-box">
        <div class="card">
            <div id="tn" class="tab active">
                <?php
                renderTable("Vàng Nổi",$vangTuoi,"../img/4028.png");
                renderTable("Vàng Chìm",$vangChim,"../img/930.png");
                renderTable("Ngọc Xanh",$ngocXanh,"../img/932.png");
                renderTable("Hồng Ngọc",$hongNgoc,"../img/7743.png");
                ?>
            </div>

            <div id="cs" class="tab">
                <?php
                renderTable("Sức Đánh",$sdBXH,"../img/2754.png");
                renderTable("3 Sao",$hpBXH,"../img/114.png");
                renderTable("7 Sao",$kiBXH,"../img/118.png");
                ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>
