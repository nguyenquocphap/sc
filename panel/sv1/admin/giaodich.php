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
$user = $stmt->fetch();

// ❌ Session fake hoặc user không tồn tại
if (!$user) {
    session_destroy();
    header("Location: /sv1/trangchu");
    exit;
}

// ❌ Không phải admin
if ((int)$user['is_admin'] !== 1) {
    echo "Không có quyền!";
    exit;
}

$item_per_page = 100;
$current_page  = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($current_page - 1) * $item_per_page;
$total_row          = $pdo->query("SELECT COUNT(*) as total FROM history_transaction")->fetch();
$total_transactions = $total_row['total'];
$total_pages        = max(1, ceil($total_transactions / $item_per_page));

$itemIcons = [];
$tplStmt   = $pdo->query("SELECT name, icon_id FROM item_template");
while ($t = $tplStmt->fetch()) {
    $itemIcons[trim($t['name'])] = $t['icon_id'];
}

$ALLOW_ITEMS = [
    'Thỏi vàng',
    'Ngọc rồng 3 sao',
    'Ngọc rồng 6 sao',
    'Ngọc rồng 7 sao',
    'Đá AZ'
];
define('MAX_INVENTORY_SLOTS', 5);
function renderItems(string $s, array $map, bool $twoCol = false, bool $invGrid = false, array $newItems = []): string {
    global $ALLOW_ITEMS;

    $s = trim($s);
    $isEmpty = ($s === '' || $s === 'Gold: 0, ');

    if ($invGrid) {
        $items = [];
        if (!$isEmpty) {
            foreach (explode(',', $s) as $item) {
                $item = trim($item);
                if ($item === '') continue;
                $cleanName = preg_replace('/ \(x\d+\)/', '', $item);
                $allowed = false;
                foreach ($ALLOW_ITEMS as $ai) {
                    if (mb_stripos($cleanName, $ai) !== false) { $allowed = true; break; }
                }
                if (!$allowed) continue;
                $qty = '';
                if (preg_match('/\(x(\d+)\)/', $item, $qm))
                    $qty = number_format((int)$qm[1], 0, ',', '.');
                $icon = null;
                if (stripos($item, 'Gold') !== false) {
                    $icon = 930;
                } else {
                    if (isset($map[$cleanName])) $icon = $map[$cleanName];
                }
                $fmt      = preg_replace_callback('/\d+/', fn($m) => number_format($m[0], 0, ',', '.'), $item);
                $baseName = preg_replace('/ \(x\d+\)/', '', $fmt);
                $isNew = false;
                foreach ($newItems as $ni) {
                    $niClean = preg_replace('/ \(x\d+\)/', '', trim($ni));
                    if (mb_stripos($cleanName, $niClean) !== false || mb_stripos($niClean, $cleanName) !== false) {
                        $isNew = true; break;
                    }
                }
                $items[] = ['icon'=>$icon,'qty'=>$qty,'name'=>$baseName,'new'=>$isNew];
            }
        }
        $filled = count($items);
        $cols   = 5;
        $SLOTS  = max($filled, MAX_INVENTORY_SLOTS);
        $total  = (int)ceil($SLOTS / $cols) * $cols;
        $out = "<div class='inv-grid'>";
        for ($i = 0; $i < $total; $i++) {
            if ($i < $filled) {
                $it  = $items[$i];
                $cls = 'inv-slot filled' . ($it['new'] ? ' inv-new' : '');
                $out .= "<div class='{$cls}' title='" . htmlspecialchars($it['name']) . "'>";
                if ($it['new']) $out .= "<span class='inv-glow'></span>";
                if ($it['icon'])
                    $out .= "<img src='/icon/{$it['icon']}.png' onerror=\"this.src='/icon/1.png'\">";
                else
                    $out .= "<span class='inv-noicon'>?</span>";
                if ($it['qty'])
                    $out .= "<span class='inv-qty'>{$it['qty']}</span>";
                $out .= "</div>";
            } else {
                $out .= "<div class='inv-slot empty'></div>";
            }
        }
        $out .= "</div>";
        return $out;
    }

    if ($isEmpty) return "<span class='empty-state'>Trống</span>";
    $items = [];
    foreach (explode(',', $s) as $item) {
        $item = trim($item);
        if ($item === '') continue;
        $qty = '';
        if (preg_match('/\(x(\d+)\)/', $item, $qm))
            $qty = number_format((int)$qm[1], 0, ',', '.');
        $icon = null;
        if (stripos($item, 'Gold') !== false) {
            $icon = 930;
        } else {
            $clean = preg_replace('/ \(x\d+\)/', '', $item);
            if (isset($map[$clean])) $icon = $map[$clean];
        }
        $fmt = preg_replace_callback('/\d+/', fn($m) => number_format($m[0], 0, ',', '.'), $item);
        $baseName = preg_replace('/ \(x\d+\)/', '', $fmt);
        $html  = "<div class='ir'>";
        if ($icon) $html .= "<img src='/icon/{$icon}.png' class='ii' onerror=\"this.src='/icon/1.png'\">";
        $html .= "<span class='in'>" . htmlspecialchars($baseName) . "</span>";
        if ($qty) $html .= "<span class='iq'>×{$qty}</span>";
        $html .= "</div>";
        $items[] = $html;
    }
    if (empty($items)) return "<span class='empty-state'>Trống</span>";
    return implode('', $items);
}

$sql = "SELECT t.*, h1.avatar_id AS avt1, h2.avatar_id AS avt2
        FROM history_transaction t
        LEFT JOIN player p1 ON SUBSTRING_INDEX(TRIM(t.player_1),' (',1) = p1.name
        LEFT JOIN head_avatar h1 ON p1.head = h1.head_id
        LEFT JOIN player p2 ON SUBSTRING_INDEX(TRIM(t.player_2),' (',1) = p2.name
        LEFT JOIN head_avatar h2 ON p2.head = h2.head_id
        ORDER BY t.time_tran DESC
        LIMIT $item_per_page OFFSET $offset";
$rows = $pdo->query($sql)->fetchAll();

function pgHTML(int $cur, int $total): string {
    $range = 2; $start = max(1,$cur-$range); $end = min($total,$cur+$range);
    $h = '<div class="pg">';
    if ($cur>1) $h.="<a href='?page=".($cur-1)."' class='pb'>‹</a>";
    if ($start>1){ $h.="<a href='?page=1' class='pb'>1</a>"; if($start>2) $h.="<span class='pd'>…</span>"; }
    for($i=$start;$i<=$end;$i++) $h.="<a href='?page=$i' class='pb".($i==$cur?' on':'')."'>$i</a>";
    if($end<$total){ if($end<$total-1) $h.="<span class='pd'>…</span>"; $h.="<a href='?page=$total' class='pb'>$total</a>"; }
    if($cur<$total) $h.="<a href='?page=".($cur+1)."' class='pb'>›</a>";
    return $h.'</div>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<!-- KHÔNG dùng width=device-width — để JS tự tính scale -->
<meta name="viewport" content="width=1500">
<title>Quản Lý Lịch Sử Giao Dịch</title>
<link rel="icon" type="image/png" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   SCALE WRAPPER — toàn bộ trang sống trong #app
   #app luôn rộng 1500px, được scale + căn giữa
   bằng JS để vừa khít 98vw của thiết bị.
   Không có media-query nào; mọi kích thước là
   giá trị tuyệt đối tính cho base 1500px.
   ══════════════════════════════════════════════ */

/* Reset */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{
  width:100%; height:100%;
  overflow-x:hidden;          /* ẩn thanh ngang của body */
  background:#ddeeff;
  font-family:'Plus Jakarta Sans',sans-serif;
  -webkit-font-smoothing:antialiased;
}

/* Dot pattern trên nền body */
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:radial-gradient(circle,#9bbedd 1px,transparent 1px);
  background-size:22px 22px;opacity:.45;
}

/* ── SCALE CONTAINER ─────────────────────────── */
#app{
  /* Kích thước thiết kế cố định */
  width:1500px;
  /* transform-origin góc trên-trái để JS scale đúng */
  transform-origin:top left;
  /* margin sẽ được JS set để căn giữa */
  position:relative;
  z-index:1;
}

/* ── TOKENS (tất cả px tuyệt đối cho base 1500) ─ */
:root{
  --bg:#ddeeff;
  --surf:#ffffff;
  --surf2:#f0f7ff;
  --surf3:#e4f0fb;
  --bd:#9bbedd;
  --bd2:#7aaacf;
  --tx:#09131f;
  --tx2:#1a3a5c;
  --tx3:#4a7a9b;
  --blue:#0d5fa3;
  --blue2:#1272c2;
  --indigo:#6c3fc7;
  --sky:#1e8fd4;
  --teal:#0a8a85;
  --blu-lt:#cce3f8;
  --ind-lt:#e6deff;
  --sky-lt:#dbeeff;
  --glow:rgba(13,95,163,.15);
  --r:13px; --rs:8px;
  --sh:0 4px 24px rgba(13,95,163,.13);
  --sh2:0 1px 8px rgba(13,95,163,.08);
}

/* ── WRAPPER bên trong #app ─────────────────── */
.W{
  width:1500px;
  padding:14px 20px 28px;
}

/* ── TOP BAR ─────────────────────────────────── */
.top {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px;
  background: var(--surf); border: 1px solid var(--bd2);
  border-radius: var(--r); padding: 16px 22px 14px;
  margin-bottom: 10px; box-shadow: var(--sh);
  position: relative; overflow: hidden; text-align: center;
}
.ttit { font-size: 1.35rem; font-weight: 800; letter-spacing: -.4px; color: var(--tx); }

.tx-box {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 11px 26px;
  border-radius: 999px;
  background: linear-gradient(135deg, #a78bfa, #c4b5fd);
  color: #fff; font-weight: 800; font-size: 1.05rem;
  box-shadow: 0 6px 18px rgba(167,139,250,.4);
  border: 1px solid rgba(255,255,255,.3);
  letter-spacing: .3px; cursor: pointer;
  transition: all .15s;
  line-height: 1.3;
}
.tx-box:hover { filter: brightness(1.07); transform: translateY(-1px); }

.tr2 { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center; }
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 11px 20px; border: none; border-radius: var(--rs);
  font-family: inherit; font-size: .9rem; font-weight: 700;
  cursor: pointer; text-decoration: none; white-space: nowrap;
  transition: all .15s; letter-spacing: .2px;
  line-height: 1;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.15); }
.btn:active { transform: scale(.96); }
.bblue  { background: linear-gradient(135deg, var(--blue2), var(--indigo)); color: #fff; box-shadow: 0 3px 12px var(--glow); }
.bteal  { background: linear-gradient(135deg, var(--teal), #0d9488); color: #fff; }

/* ── PAGINATION ──────────────────────────────── */
.pg{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:5px;margin:9px 0 13px}
.pb{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:37px;height:37px;padding:0 10px;
  background:var(--surf);border:1.5px solid var(--bd2);
  border-radius:var(--rs);color:var(--tx2);
  font-size:12px;font-weight:600;text-decoration:none;
  font-family:inherit;transition:all .14s;
}
.pb:hover:not(.on){border-color:var(--blue2);color:var(--blue);background:var(--blu-lt);transform:translateY(-2px)}
.pb.on{background:linear-gradient(135deg,var(--blue2),var(--indigo));border-color:transparent;color:#fff;font-weight:800;box-shadow:0 4px 12px var(--glow)}
.pd{color:var(--tx3);font-size:12px;padding:0 2px}

/* ── TABLE CONTAINER ─────────────────────────── */
.tc{
  background:var(--surf);border:1px solid var(--bd2);
  border-radius:var(--r);overflow:hidden;
  box-shadow:var(--sh);margin-bottom:11px;
}
.tsc{ overflow-x:hidden; }

table{
  /* Tổng cột: 100+200+150+300+65+300+150+200 = 1465px
     + padding .W (20px*2) = 1505 ≈ khít 1500px         */
  width:100%;
  table-layout:fixed;
  border-collapse:collapse;
}
th,td{
  word-break:break-word;
  vertical-align:top;
}
thead tr{
  background:linear-gradient(90deg,var(--blu-lt),var(--ind-lt));
  border-bottom:2px solid var(--bd2);
}
th{
  padding:12px 8px;
  font-size:12px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.5px;
  color:#09131f;
  text-align:center;
  white-space:normal;
}
th:nth-child(1),td:nth-child(1){border-right:1px solid var(--bd2)}
th:nth-child(8),td:nth-child(8){border-left:1px solid var(--bd2)}

tbody tr{border-bottom:1px solid var(--bd);transition:background .12s;animation:rIn .22s ease backwards}
@keyframes rIn{from{opacity:0;transform:translateY(3px)}}
tbody tr:hover{background:var(--sky-lt)}
tbody tr.hide{display:none}
td{padding:8px 6px;color:var(--tx)}

/* search result row */
.search-cnt-tr td{
  padding:3px 10px 5px;
  background:var(--surf2);
  border-bottom:1px solid var(--bd);
}
.search-cnt-row{
  text-align:center;
  font-size:11px;
  color:var(--tx3);
}

/* ── TIME ────────────────────────────────────── */
.ct{
  font-family:'JetBrains Mono',monospace;
  font-size:11px;
  text-align:center;line-height:1.6;white-space:normal;
}
.ct .d{color:var(--tx2);font-weight:500}
.ct .t{color:var(--blue);font-weight:700;font-size:12px}

/* ── PLAYER ──────────────────────────────────── */
.pc{display:flex;flex-direction:column;align-items:center;gap:4px}
.avt{
  width:52px;height:52px;object-fit:contain;image-rendering:pixelated;
  border-radius:var(--rs);border:2px solid var(--bd2);background:var(--surf2);
  transition:border-color .2s,transform .2s;
}
.avt:hover{border-color:var(--blue2);transform:scale(1.05)}
.pn{font-weight:700;font-size:12px;color:var(--indigo);text-align:center;word-break:break-word;line-height:1.3}

/* ── SEARCH INSIDE HEADER ────────────────────── */
.col-search{
  margin-top:6px;
  position:relative;
  font-weight:400;
  text-transform:none;
  letter-spacing:0;
}
.col-search input{
  width:100%;
  padding:4px 6px 4px 24px;
  background:rgba(255,255,255,.85);
  border:1.5px solid var(--bd2);
  border-radius:6px;
  color:var(--tx);
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:11px;
  font-weight:500;
  outline:none;
  transition:border-color .18s,box-shadow .18s;
}
.col-search input:focus{
  border-color:var(--blue2);
  box-shadow:0 0 0 3px rgba(13,95,163,.15);
  background:#fff;
}
.col-search input::placeholder{color:var(--tx3);font-size:10px}
.col-search .sic{
  position:absolute;left:6px;top:50%;transform:translateY(-50%);
  color:var(--tx3);font-size:10px;pointer-events:none;
}
.col-search .sic svg{width:12px;height:12px}

/* ── ITEM BOX ────────────────────────────────── */
.ib{background:var(--surf2);border:1px solid var(--bd);border-radius:var(--rs);padding:6px 8px;width:100%}
.ir{display:flex;align-items:center;gap:5px;padding:3px 0;border-bottom:1px solid var(--bd);flex-wrap:wrap}
.ir:last-child{border-bottom:none}
.ii{width:24px;height:24px;flex-shrink:0;image-rendering:pixelated;border-radius:3px}
.in{flex:1;color:var(--tx);font-size:12px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:normal;word-break:break-word}
.iq{
  flex-shrink:0;font-family:'JetBrains Mono',monospace;
  font-weight:700;font-size:11px;
  color:var(--blue);background:var(--blu-lt);
  padding:1px 5px;border-radius:4px;white-space:nowrap;
}
.empty-state{color:var(--tx3);font-style:italic;font-size:11px}

/* ── ARROW ───────────────────────────────────── */
.arc{text-align:center;vertical-align:middle !important;padding:0 2px !important;width:65px}
.arw{
  display:inline-flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:50%;
  background:var(--blu-lt);border:1px solid var(--bd2);
  color:var(--blue);font-size:14px;
}

/* ── BAG TD ──────────────────────────────────── */
td.bag-td{padding:5px}
.bp{
  background:var(--surf2);border:1px solid var(--bd);
  border-radius:var(--rs);padding:5px;
  position:relative;overflow:hidden;
}
.bp::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px}
.bs::before{background:linear-gradient(180deg,var(--blue2),var(--sky))}
.br::before{background:linear-gradient(180deg,var(--indigo),var(--teal))}

/* ── INVENTORY GRID ──────────────────────────── */
.inv-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:2px}
.inv-slot{
  aspect-ratio:1/1;border-radius:4px;
  position:relative;display:flex;align-items:center;justify-content:center;
  overflow:hidden;
}
.inv-slot.empty{
  background:rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.07);
}
.inv-slot.filled{
  background:linear-gradient(145deg,var(--surf3),var(--surf2));
  border:1px solid var(--bd2);
  box-shadow:0 1px 3px rgba(13,95,163,.08),inset 0 1px 0 rgba(255,255,255,.6);
  transition:transform .15s,box-shadow .15s;
}
.inv-slot.filled:hover{transform:scale(1.08);box-shadow:0 3px 8px var(--glow);z-index:2}
.inv-slot.filled img{
  width:60%;height:60%;
  object-fit:contain;image-rendering:pixelated;
}
.inv-slot .inv-noicon{font-size:7px;color:var(--tx3);font-weight:700}
.inv-slot .inv-qty{
  position:absolute;
  bottom:2px; right:2px;
  font-family:'JetBrains Mono',monospace;
  font-size:9px;
  font-weight:800;line-height:1;
  color:#09131f;
  text-shadow:0 1px 3px rgba(255,255,255,.8);
  pointer-events:none;z-index:2;
}
.inv-slot.inv-new{
  border-color:#f59e0b !important;
  box-shadow:0 0 0 2px rgba(245,158,11,.35),0 2px 8px rgba(245,158,11,.3) !important;
  animation:newPulse 2s ease-in-out infinite;
}
@keyframes newPulse{
  0%,100%{box-shadow:0 0 0 2px rgba(245,158,11,.35),0 2px 8px rgba(245,158,11,.25)}
  50%{box-shadow:0 0 0 2px rgba(245,158,11,.55),0 2px 13px rgba(245,158,11,.45)}
}
.inv-new::after{
  content:'✦';position:absolute;top:1px;left:2px;
  font-size:6px;color:#f59e0b;
  text-shadow:0 0 4px rgba(245,158,11,.8);
  line-height:1;z-index:3;pointer-events:none;
}
.inv-glow{
  position:absolute;inset:0;border-radius:3px;
  background:radial-gradient(ellipse at 50% 30%,rgba(251,191,36,.18) 0%,transparent 70%);
  pointer-events:none;z-index:0;
  animation:glowPulse 2s ease-in-out infinite;
}
@keyframes glowPulse{0%,100%{opacity:.7}50%{opacity:1}}

.te{text-align:center;padding:66px 22px;color:var(--tx3)}
.te .ei{font-size:48px;margin-bottom:13px;opacity:.4}

/* scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--surf2)}
::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--tx3)}

.tx-box{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:11px 26px;
  border-radius:999px;
  background:linear-gradient(135deg,#a78bfa,#c4b5fd);
  color:#fff;
  font-weight:800;
  font-size:1.05rem;
  line-height:1.3;
  box-shadow:0 6px 18px rgba(167,139,250,.4);
  border:1px solid rgba(255,255,255,.3);
  letter-spacing:.3px;
  cursor:pointer;
  transition:all .15s;
}
.tx-ic{opacity:.95;transition:.2s}
.tx-box:hover{filter:brightness(1.07);transform:translateY(-1px)}
.tx-box:hover .tx-ic{transform:scale(1.1) rotate(5deg)}
.btn svg{flex-shrink:0;transition:transform .2s}
.btn:hover svg{transform:scale(1.12) rotate(4deg)}
</style>
</head>
<body>

<!-- ═══ SCALE CONTAINER ═══════════════════════════
     Toàn bộ nội dung nằm trong #app (1500px).
     JS bên dưới sẽ scale #app để luôn vừa 98vw.
     ════════════════════════════════════════════ -->
<div id="app">
<div class="W">

<!-- TOPBAR -->
<div class="top">
 <div class="ttit">
  <span class="tx-box">
    <svg class="tx-ic" viewBox="0 0 24 24" width="16" height="16" fill="none">
      <path d="M4 7h16M4 12h10M4 17h7"
            stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
    Lịch Sử Giao Dịch: <?= number_format($total_transactions) ?>
  </span>
</div>
  <div class="tr2">
  <a href="/sv1/admin/panel" class="btn bblue">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <path d="M3 10.5L12 3L21 10.5V20A1 1 0 0 1 20 21H15V14H9V21H4A1 1 0 0 1 3 20V10.5Z"
          stroke="white" stroke-width="2" stroke-linejoin="round"/>
  </svg>
  <span>Panel</span>
</a>

<button class="btn bteal" onclick="location.reload()">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <path d="M20 12A8 8 0 1 1 17.3 6M20 4V12H12"
          stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <span>Làm mới</span>
</button>
  </div>
</div>

<!-- PAGINATION -->
<?= pgHTML($current_page, $total_pages) ?>

<!-- TABLE -->
<div class="tc"><div class="tsc">
<table>
  <colgroup>
    <col style="width:100px">   <!-- TIME          -->
    <col style="width:200px">   <!-- Hành Trang 1  -->
    <col style="width:150px">   <!-- Người Gửi     -->
    <col style="width:300px">   <!-- ITEM Gửi      -->
    <col style="width:65px">    <!-- Arrow         -->
    <col style="width:300px">   <!-- ITEM Nhận     -->
    <col style="width:150px">   <!-- Người Nhận    -->
    <col style="width:200px">   <!-- Hành Trang 2  -->
  </colgroup>
  <thead>
    <tr>
      <th>TIME</th>
      <th>Hành Trang</th>
      <th>
        Người Gửi
        <div class="col-search">
         <span class="sic">
  <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"
          stroke="#4a7a9b" stroke-width="2"/>
    <path d="M3 21c0-4.5 4-7 9-7s9 2.5 9 7"
          stroke="#4a7a9b" stroke-width="2" stroke-linecap="round"/>
  </svg>
</span>
          <input type="text" id="s1" placeholder="Tìm người gửi…">
        </div>
      </th>
      <th>ITEM Gửi</th>
      <th></th>
      <th>ITEM Nhận</th>
      <th>
        Người Nhận
        <div class="col-search">
      <span class="sic">
  <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"
          stroke="#4a7a9b" stroke-width="2"/>
    <path d="M3 21c0-4.5 4-7 9-7s9 2.5 9 7"
          stroke="#4a7a9b" stroke-width="2" stroke-linecap="round"/>
  </svg>
</span>
          <input type="text" id="s2" placeholder="Tìm người nhận…">
        </div>
      </th>
      <th>Hành Trang</th>
    </tr>
    <tr class="search-cnt-tr" id="search-cnt-tr" style="display:none">
      <td colspan="8"><div class="search-cnt-row"><span id="sc"></span></div></td>
    </tr>
  </thead>
  <tbody id="tb">
  <?php if(empty($rows)): ?>
    <tr><td colspan="8"><div class="te"><div class="ei">📭</div><p>Chưa có giao dịch nào</p></div></td></tr>
  <?php else: foreach($rows as $i => $row):
    $a1 = $row['avt1'] ?? 1; $a2 = $row['avt2'] ?? 1;
    $n1 = explode(" (", $row['player_1'])[0];
    $n2 = explode(" (", $row['player_2'])[0];
    [$date,$time] = array_pad(explode(" ", $row['time_tran'], 2), 2, '');
  ?>
  <tr style="animation-delay:<?= min($i*6,300) ?>ms"
      data-p1="<?= mb_strtolower(htmlspecialchars($n1)) ?>"
      data-p2="<?= mb_strtolower(htmlspecialchars($n2)) ?>">

    <!-- TIME -->
    <td><div class="ct"><div class="d"><?= htmlspecialchars($date) ?></div><div class="t"><?= htmlspecialchars($time) ?></div></div></td>

    <!-- BAG SENDER -->
    <td class="bag-td">
      <div class="bp bs">
        <?php
          $newFor1 = array_filter(array_map('trim', explode(',', $row['item_player_2'])));
          echo renderItems($row['bag_1_after_tran'], $itemIcons, false, true, $newFor1);
        ?>
      </div>
    </td>

    <!-- SENDER -->
    <td>
      <div class="pc">
        <img src="/icon/<?= $a1 ?>.png" class="avt" onerror="this.src='/icon/1.png'">
        <span class="pn"><?= htmlspecialchars($n1) ?></span>
      </div>
    </td>

    <!-- ITEMS SENT -->
    <td><div class="ib"><?= renderItems($row['item_player_1'], $itemIcons) ?></div></td>

    <!-- ARROW -->
    <td class="arc"><div class="arw">⇄</div></td>

    <!-- ITEMS RECEIVED -->
    <td><div class="ib"><?= renderItems($row['item_player_2'], $itemIcons) ?></div></td>

    <!-- RECEIVER -->
    <td>
      <div class="pc">
        <img src="/icon/<?= $a2 ?>.png" class="avt" onerror="this.src='/icon/1.png'">
        <span class="pn"><?= htmlspecialchars($n2) ?></span>
      </div>
    </td>

    <!-- BAG RECEIVER -->
    <td class="bag-td">
      <div class="bp br">
        <?php
          $newFor2 = array_filter(array_map('trim', explode(',', $row['item_player_1'])));
          echo renderItems($row['bag_2_after_tran'], $itemIcons, false, true, $newFor2);
        ?>
      </div>
    </td>

   </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div></div>

</div><!-- /.W -->
</div><!-- /#app -->

<script>
/* ══════════════════════════════════════════════
   SCALE ENGINE
   - Thiết kế base: 1500px
   - Mục tiêu: #app luôn = 98vw, căn giữa (1% mỗi bên)
   - Cách hoạt động: scale(ratio) + margin-left offset
     → giống hệt Ctrl+/- phóng to / thu nhỏ toàn trang
   ══════════════════════════════════════════════ */
(function(){
  const BASE = 1500;
  const app  = document.getElementById('app');

  function applyScale(){
    const vw    = window.innerWidth;         // px thực của viewport
    const ratio = (vw * 0.98) / BASE;        // tỉ lệ scale để vừa 98vw

    /* scale từ góc trên-trái, sau đó dịch để căn giữa */
    const scaledW  = BASE * ratio;           // chiều rộng sau khi scale
    const leftOver = vw - scaledW;           // khoảng trống hai bên
    const marginL  = leftOver / 2;           /* = 1% vw mỗi bên khi ratio chính xác */

    app.style.transform       = `scale(${ratio})`;
    app.style.transformOrigin = 'top left';
    app.style.marginLeft      = `${marginL}px`;

    /* Chiều cao sau scale nhỏ hơn thực → body phải biết */
    app.style.marginBottom = '0';
    document.body.style.minHeight = (app.offsetHeight * ratio) + 'px';
  }

  applyScale();
  window.addEventListener('resize', applyScale);
})();

/* ── TÌM KIẾM ────────────────────────────────── */
const s1 = document.getElementById('s1'),
      s2 = document.getElementById('s2'),
      sc = document.getElementById('sc'),
      cntRow = document.getElementById('search-cnt-tr');
const trs = document.querySelectorAll('#tb tr[data-p1]');

function flt(){
  const v1 = s1.value.toLowerCase().trim(),
        v2 = s2.value.toLowerCase().trim();
  let n = 0;
  trs.forEach(r => {
    const ok = r.dataset.p1.includes(v1) && r.dataset.p2.includes(v2);
    r.classList.toggle('hide', !ok);
    if(ok) n++;
  });
  if(v1 || v2){
    sc.textContent = `${n} / ${trs.length} kết quả`;
    cntRow.style.display = '';
  } else {
    cntRow.style.display = 'none';
  }
}
s1.addEventListener('input', flt);
s2.addEventListener('input', flt);
</script>
</body>
</html>
