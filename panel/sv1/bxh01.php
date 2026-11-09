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

function getPower($data_point){
    $arr = json_decode($data_point, true);
    return $arr[1] ?? 0;
}

function getPetPower($pet){
    $pet = json_decode($pet, true);
    if(isset($pet[1])){
        $info = json_decode($pet[1], true);
        return $info[1] ?? 0;
    }
    return 0;
}

function getTask($data_task){
    $task = json_decode($data_task, true);
    return [
        "main" => $task[0] ?? 0,
        "sub" => $task[1] ?? 0
    ];
}

function formatK($num){
    if($num >= 1000000000) return round($num/1000000000, 1) . " Tỷ";
    if($num >= 1000000) return round($num/1000000, 1) . " Tr";
    if($num >= 1000) return round($num/1000, 1) . " K";
    return number_format($num);
}

// DATA
$topSM = [];
$res = $pdo->query("SELECT name, gender, data_point, pet FROM player");
while($row = $res->fetch()){
    $sm = getPower($row['data_point']);
    $pet = getPetPower($row['pet']);
    $topSM[] = [
        "name"=>$row['name'],
        "gender"=>$row['gender'],
        "total"=>$sm + $pet,
        "sm"=>$sm,
        "pet"=>$pet
    ];
}
usort($topSM, fn($a,$b)=>$b['total'] <=> $a['total']);
$topSM = array_slice($topSM,0,20);

$topTask = [];
$res = $pdo->query("SELECT name, gender, data_task FROM player");
while($row = $res->fetch()){
    $task = getTask($row['data_task']);
    $topTask[] = [
        "name"=>$row['name'],
        "gender"=>$row['gender'],
        "main"=>$task['main'],
        "sub"=>$task['sub']
    ];
}
usort($topTask, function($a, $b){
    if ($b['main'] === $a['main']) {
        return $b['sub'] <=> $a['sub'];
    }
    return $b['main'] <=> $a['main'];
});
$topTask = array_slice($topTask,0,20);

$topNap = [];
$res = $pdo->query("SELECT p.name, p.gender, a.tongnap FROM player p JOIN account a ON p.account_id = a.id");
while($row = $res->fetch()){
    $topNap[] = $row;
}
usort($topNap, fn($a,$b)=>$b['tongnap'] <=> $a['tongnap']);
$topNap = array_slice($topNap,0,20);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bảng Xếp Hạng</title>
<link rel="icon" type="image/jpg" href="../img/icon.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* --- PHẦN SỬA ĐỂ GIỐNG PANEL --- */
body {
    margin: 0;
    font-family: 'Segoe UI', Arial;
    /* Background gradient xanh từ panel */
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
    /* Khung tối bo góc từ panel */
    background: linear-gradient(160deg, #4a4a4a, #2b2b2b 65%, #1b1b1b);
    border-radius: 28px;
    padding: 22px;
    box-shadow: 0 25px 60px rgba(0,0,0,.5), inset 0 2px 8px rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,0.15);
    color: white; /* Đổi màu chữ mặc định trong khung thành trắng */
    margin: 0 12px;
}
/* ---------------------------------- */

/* --- TẤT CẢ PHẦN CÒN LẠI GIỮ NGUYÊN --- */
* { box-sizing: border-box; }

.topbar { text-align: center; margin-bottom: 20px; }

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: 30px;
    background: linear-gradient(135deg,#3b82f6,#2563eb);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 110%;
    box-shadow: 0 6px 15px rgba(0,0,0,.3);
    transition: .2s;
}

.back-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 10px 25px rgba(0,0,0,.4);
}

@media(min-width:769px){
    body{font-size:115%;}
}

.tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
}

.tabs button {
    flex: 1;
    padding: 15px;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    color: #fff;
    font-size: 125%;
    cursor: pointer;
    transition: opacity 0.2s;
}

.tabs button:hover { opacity: 0.9; }

/* Màu sắc các nút Tab */
.btn-sm{background:#f43f5e !important;}
.btn-task{background:#2196f3 !important;}
.btn-nap{background:#4caf50 !important;}

.bxh-box {
    padding: 5px;
    border-radius: 16px;
    transition: background 0.3s ease;
}

/* Màu khung đồng bộ với Tab đang active */
.bg-sm{background:#f43f5e;}
.bg-task{background:#2196f3;}
.bg-nap{background:#4caf50;}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 10px;
    color: #333; /* Giữ màu chữ tối cho nội dung bảng */
}

.title {
    text-align: center;
    font-weight: 800;
    color: #fff;
    padding: 8px;
    border-radius: 10px;
    margin-bottom: 8px;
}

#sm .title{background:#f43f5e;}
#task .title{background:#2196f3;}
#nap .title{background:#4caf50;}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 8px;
    text-align: center;
}

.table th {
    background: #f8fafc;
    border-bottom: 2px solid #edf2f7;
    color: #333;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    margin: auto;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.power span{display:block; font-size: 0.9em;}
.name{font-weight:700;}

.top0 td{background:#fff8e1;}
.top1 td{background:#f1f5f9;}
.top2 td{background:#fff5f5;}

.tab { display: none; }
.tab.active { display: block; }

/* Modern leaderboard skin */
body {
    font-family: 'Times New Roman', Times, serif;
    background:
        radial-gradient(circle at 16% 12%, rgba(255,255,255,.75), transparent 28%),
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
    content: "👑 BXH ĐUA TOP";
    display: block;
    text-align: center;
    font-size: 1.75rem;
    font-weight: 900;
    color: #1f5fbf;
    margin: 2px 0 16px;
}
.topbar { margin-bottom: 14px; }
.back-btn {
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
    box-shadow: none;
    opacity: .78;
}
.tabs button.active {
    opacity: 1;
    box-shadow: 0 8px 20px rgba(31,95,191,.22);
    transform: translateY(-1px);
}
.bxh-box {
    padding: 0;
    background: transparent !important;
}
.card {
    background: #f8fbff;
    border: 1px solid rgba(31,95,191,.14);
    border-radius: 16px;
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
}
.title {
    background: linear-gradient(135deg,#1f5fbf,#41a3ff) !important;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 12px;
    letter-spacing: 0;
    box-shadow: 0 8px 22px rgba(31,95,191,.22);
}
.table {
    border-collapse: separate;
    border-spacing: 0 9px;
}
.table th {
    background: transparent;
    color: #5b708c;
    border: 0;
    font-size: .86rem;
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
.power span {
    color: #365877;
    line-height: 1.35;
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
    .wrapper { margin: 0 6px; padding: 10px; border-radius: 14px; }
    .wrapper::before { font-size: 1.35rem; margin-bottom: 10px; }
    .tabs { overflow-x: auto; }
    .tabs button { min-width: 112px; font-size: .9rem; }
    .card { padding: 7px 5px; }
    .table th, .table td { padding: 6px 3px; font-size: .78rem; }
    #sm .table { table-layout: fixed; }
    #sm .table th:nth-child(1) { width: 34%; }
    #sm .table th:nth-child(2) { width: 21%; }
    #sm .table th:nth-child(3) { width: 27%; }
    #sm .table th:nth-child(4) { width: 18%; }
    #sm .name { overflow-wrap: anywhere; line-height: 1.15; }
    #sm .power span { font-size: .92em; white-space: nowrap; }
    .avatar { width: 34px; height: 34px; }
}
@media(max-width:380px){
    .wrapper { margin: 0 4px; padding: 8px; }
    .table th, .table td { padding: 5px 2px; font-size: .72rem; }
    .avatar { width: 30px; height: 30px; }
}
</style>

<script>
function openTab(id, colorClass){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
    
    // Đổi màu khung bxh-box
    const box = document.querySelector('.bxh-box');
    box.className = 'bxh-box ' + colorClass;
}
</script>
</head>

<body>

<div class="wrapper">

<div class="topbar">
    <a href="/sv1/admin/panel" class="back-btn">
        <i class="fa-solid fa-house"></i> Trang Chủ
    </a>
</div>

<div class="tabs">
    <button class="btn-sm active" onclick="openTab('sm', 'bg-sm')">Sức Mạnh</button>
    <button class="btn-task" onclick="openTab('task', 'bg-task')">Nhiệm Vụ</button>
    <button class="btn-nap" onclick="openTab('nap', 'bg-nap')">Cá Cá</button>
</div>

<div class="bxh-box bg-sm"> <div class="card">

<div id="sm" class="tab active">
<div class="title">🏆 BXH Sức Mạnh</div>
<table class="table">
<tr><th>TOP</th><th>Tổng</th><th>Chi tiết</th><th></th></tr>
<?php foreach ($topSM as $i=>$p): ?>
<tr class="<?= 'top'.$i ?>">
<td class="name"><?= ($i+1)."." ?> <?= $p['name'] ?></td>
<td><?= formatK($p['total']) ?></td>
<td class="power">
<span>Sư: <?= formatK($p['sm']) ?></span>
<span>Đệ: <?= formatK($p['pet']) ?></span>
</td>
<td><div class="avatar"><img src="<?= getAvatar($p['gender']) ?>"></div></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div id="task" class="tab">
<div class="title">📜 BXH Nhiệm Vụ</div>
<table class="table">
<tr><th>TOP</th><th>Nhiệm vụ</th><th></th></tr>
<?php foreach ($topTask as $i=>$p): ?>
<tr class="<?= 'top'.$i ?>">
<td class="name"><?= ($i+1)."." ?> <?= $p['name'] ?></td>
<td><?= $p['main'] ?>-<?= $p['sub'] ?></td>
<td><div class="avatar"><img src="<?= getAvatar($p['gender']) ?>"></div></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div id="nap" class="tab">
<div class="title">🐟 BXH Cá Cá</div>
<table class="table">
<tr><th>TOP</th><th>Tổng</th><th></th></tr>
<?php foreach ($topNap as $i=>$p): ?>
<tr class="<?= 'top'.$i ?>">
<td class="name"><?= ($i+1)."." ?> <?= $p['name'] ?></td>
<td><?= number_format($p['tongnap'] / 10) ?></td>
<td><div class="avatar"><img src="<?= getAvatar($p['gender']) ?>"></div></td>
</tr>
<?php endforeach; ?>
</table>
</div>

</div>
</div>

</div>

</body>
</html>
