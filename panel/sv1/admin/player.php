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

// ========== HÀM XỬ LÝ JSON AN TOÀN ==========
function safeJsonDecode($str) {
    if (is_array($str)) return $str;
    if (!is_string($str) || trim($str) === '') return [];
    $data = json_decode($str, true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    header('Content-Type: application/json');

    // Lưu toàn bộ chỉnh sửa (power + items)
    if ($_POST['action'] === 'save_full') {
        $pid         = (int)$_POST['player_id'];
        $sm_su       = (int)$_POST['sm_su'];
        $sm_de       = (int)$_POST['sm_de'];
        $tn_de       = (int)$_POST['tn_de'];
        $nv_task     = max(0, (int)($_POST['nv_task'] ?? 0));
        $items_bag   = $_POST['items_bag'];
        $items_box   = $_POST['items_box'];

        try {
            // cập nhật data_point (sm sư)
            $r = $pdo->query("SELECT data_point, pet, data_task FROM player WHERE id = $pid")->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $dp = safeJsonDecode($r['data_point']);
                $dp[1] = $sm_su;
                $pdo->prepare("UPDATE player SET data_point = ? WHERE id = ?")->execute([json_encode($dp), $pid]);

                $task = safeJsonDecode($r['data_task']);
                for ($i = 0; $i < 4; $i++) {
                    if (!isset($task[$i])) $task[$i] = 0;
                }
                $task[0] = $nv_task;
                $pdo->prepare("UPDATE player SET data_task = ? WHERE id = ?")->execute([json_encode(array_values($task)), $pid]);

                $pet_outer = safeJsonDecode($r['pet']);
                if (isset($pet_outer[1])) {
                    $inner = is_string($pet_outer[1]) ? safeJsonDecode($pet_outer[1]) : $pet_outer[1];
                    if (!is_array($inner)) $inner = [];
                    $inner[1] = $sm_de;
                    $inner[2] = $tn_de;
                    $pet_outer[1] = json_encode($inner);
                    $pdo->prepare("UPDATE player SET pet = ? WHERE id = ?")->execute([json_encode($pet_outer), $pid]);
                }

                // cập nhật items_bag và items_box
                $pdo->prepare("UPDATE player SET items_bag = ?, items_box = ? WHERE id = ?")->execute([$items_bag, $items_box, $pid]);

                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy nhân vật']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_player') {
        $pid = (int)$_POST['player_id'];
        $pdo->prepare("DELETE FROM player WHERE id = ?")->execute([$pid]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    exit;
}

function getPower($data_point) {
    $arr = safeJsonDecode($data_point);
    return $arr[1] ?? 0;
}

function getPetInfo($pet) {
    $pet = safeJsonDecode($pet);
    if (isset($pet[1])) {
        $info = is_string($pet[1]) ? safeJsonDecode($pet[1]) : $pet[1];
        return [
            'power'    => $info[1] ?? 0,
            'tiemnang' => $info[2] ?? 0,
        ];
    }
    return ['power' => 0, 'tiemnang' => 0];
}

function formatK($num) {
    $num = (int)$num;
    if ($num >= 1000000000) return round($num / 1000000000, 1) . " Tỷ";
    if ($num >= 1000000)    return round($num / 1000000, 1)    . " Tr";
    if ($num >= 1000)       return round($num / 1000, 1)       . " K";
    return number_format($num);
}

function countItemInBag($itemsArray, $itemId) {
    $total = 0;
    foreach ($itemsArray as $itemStr) {
        $it = is_string($itemStr) ? json_decode($itemStr, true) : $itemStr;
        if (($it[0] ?? -1) == $itemId) {
            $total += (int)($it[1] ?? 1);
        }
    }
    return $total;
}

function renderItems($itemsArray, $templates, $limit = false) {
    $cls   = $limit ? 'limit-3-rows' : 'full-grid';
    $html  = "<div class=\"item-grid $cls\">";
    foreach ($itemsArray as $idx => $itemStr) {
        if ($limit && $idx >= 30) break;
        $it  = is_string($itemStr) ? json_decode($itemStr, true) : $itemStr;
        $id  = $it[0] ?? -1;
        $qty = $it[1] ?? 0;
        $html .= '<div class="item-slot" data-idx="'.$idx.'">';
        if ($id != -1 && isset($templates[$id])) {
            $icon = $templates[$id]['icon_id'];
            $name = htmlspecialchars($templates[$id]['name']);
            $html .= "<img src='/icon/{$icon}.png' onerror=\"this.src='/icon/1.png'\" title='{$name}'>";
            if ($qty > 1) $html .= "<span class='qty'>$qty</span>";
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

// === LOAD ITEM TEMPLATES ===
$item_temp = [];
$t_res = $pdo->query("SELECT id, name, icon_id FROM item_template");
while ($r = $t_res->fetch(PDO::FETCH_ASSOC)) {
    $item_temp[$r['id']] = $r;
}

// === THỐNG KÊ ===
$task_main_templates = [];
$task_res = $pdo->query("SELECT id, NAME FROM task_main_template WHERE id > 0 ORDER BY id ASC");
while ($r = $task_res->fetch(PDO::FETCH_ASSOC)) {
    $task_main_templates[(int)$r['id']] = $r['NAME'];
}

$total_players   = (int)$pdo->query("SELECT COUNT(*) FROM player")->fetchColumn();
$total_gender0   = (int)$pdo->query("SELECT COUNT(*) FROM player WHERE gender = 0")->fetchColumn();
$total_gender1   = (int)$pdo->query("SELECT COUNT(*) FROM player WHERE gender = 1")->fetchColumn();
$total_gender2   = (int)$pdo->query("SELECT COUNT(*) FROM player WHERE gender = 2")->fetchColumn();

// === PHÂN TRANG ===
$item_per_page = 100;
$total_pages   = ceil($total_players / $item_per_page);
$current_page  = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($current_page - 1) * $item_per_page;

$sql = "SELECT p.*, a.username, ha.avatar_id
        FROM player p
        LEFT JOIN account a ON p.account_id = a.id
        LEFT JOIN head_avatar ha ON p.head = ha.head_id
        ORDER BY p.id ASC
        LIMIT $item_per_page OFFSET $offset";
$raw_list = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($raw_list as &$r) {
    // Chuẩn hóa các trường JSON thành mảng
    $r['data_inventory'] = safeJsonDecode($r['data_inventory']);
    $r['data_point']     = safeJsonDecode($r['data_point']);
    $r['data_task']      = safeJsonDecode($r['data_task']);
    $r['pet']            = safeJsonDecode($r['pet']);
    $r['items_bag']      = safeJsonDecode($r['items_bag']);
    $r['items_box']      = safeJsonDecode($r['items_box']);
    $r['tv_count']       = countItemInBag($r['items_bag'], 457);
}
unset($r);

usort($raw_list, function($a, $b) {
    if ($b['tv_count'] !== $a['tv_count']) return $b['tv_count'] - $a['tv_count'];
    return $a['id'] - $b['id'];
});

$display_list = $raw_list;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản Lý Player</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
/* ══════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════ */
:root {
  --bg:      #ddeeff;
  --surf:    #ffffff;
  --surf2:   #f0f7ff;
  --surf3:   #e4f0fb;
  --bd:      #9bbedd;
  --bd2:     #7aaacf;
  --tx:      #09131f;
  --tx2:     #1a3a5c;
  --tx3:     #4a7a9b;
  --blue:    #0d5fa3;
  --blue2:   #1272c2;
  --indigo:  #6c3fc7;
  --sky:     #1e8fd4;
  --teal:    #0a8a85;
  --success: #0a8a5a;
  --danger:  #d92b3a;
  --warn:    #c47a00;
  --blu-lt:  #cce3f8;
  --ind-lt:  #e6deff;
  --sky-lt:  #dbeeff;
  --glow:    rgba(13,95,163,.15);

  --r:  13px;
  --rs: 8px;
  --sh:  0 4px 24px rgba(13,95,163,.13);
  --sh2: 0 1px 8px  rgba(13,95,163,.08);

  --page-w: 1500px;
}

/* ══════════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; scroll-behavior: smooth; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14.3px;
  line-height: 1.6;
  background: var(--bg);
  color: var(--tx);
  min-height: 100dvh;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  pointer-events: none; z-index: 0;
  background-image: radial-gradient(circle, var(--bd) 1px, transparent 1px);
  background-size: 22px 22px;
  opacity: .45;
}

/* ══════════════════════════════════════════════════
   SCALER WRAPPER
══════════════════════════════════════════════════ */
#scaler {
  position: relative;
  z-index: 1;
  width: var(--page-w);
  transform-origin: top left;
}

.W {
  width: 100%;
  padding: 14px 12px 28px;
}

/* ══════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════ */
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


/* ══════════════════════════════════════════════════
   STATS
══════════════════════════════════════════════════ */
.stats-row {
  display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;
  margin-bottom: 11px;
}
.stat-card {
  background: var(--surf); border: 1px solid var(--bd2);
  border-radius: var(--r); padding: 10px 18px;
  display: flex; align-items: center; gap: 10px;
  box-shadow: var(--sh2); min-width: 110px;
}
.stat-img  { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 2px solid var(--bd2); }
.stat-val  { font-size: 1.15rem; font-weight: 800; color: var(--blue2); font-family: 'JetBrains Mono', monospace; }
.stat-lbl  { font-size: .75rem; color: var(--tx3); font-weight: 600; }

/* ══════════════════════════════════════════════════
   PAGINATION
══════════════════════════════════════════════════ */
.pag-wrap {
  display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;
  margin-bottom: 11px;
}
.page-link {
  padding: 5px 11px; border-radius: var(--rs);
  text-decoration: none; font-weight: 700; font-size: .82rem;
  border: 1.5px solid var(--bd2);
  background: var(--surf); color: var(--tx2);
  transition: all .15s;
}
.page-link:hover  { background: var(--blu-lt); border-color: var(--blue2); }
.page-link.active { background: linear-gradient(135deg, var(--blue2), var(--indigo)); color: #fff; border-color: transparent; }

/* ══════════════════════════════════════════════════
   TABLE CONTAINER
══════════════════════════════════════════════════ */
.tc  { background: var(--surf); border: 1px solid var(--bd2); border-radius: var(--r); overflow: hidden; box-shadow: var(--sh); }
.tsc { overflow-x: auto; -webkit-overflow-scrolling: touch; }

table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
}

.col-name  { width: 10%;  }
.col-power { width: 16.5%; }
.col-fin   { width: 7.5%;  }
.col-bag   { width: 30%;   }
.col-box   { width: 30%;   }
.col-act   { width: 6%;    }

thead tr {
  background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt));
  border-bottom: 2px solid var(--bd2);
}
th {
  padding: 12px 8px 9px;
  font-size: .85rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .5px;
  color: #09131f;
  text-align: center;
  word-break: break-word;
}
tbody tr { border-bottom: 1px solid var(--bd); transition: background .12s; }
tbody tr:nth-child(even) { background: rgba(13,95,163,.03); }
tbody tr:hover { background: var(--sky-lt); }
td {
  padding: 9px 6px;
  vertical-align: middle;
  text-align: center;
  word-break: break-word;
}

/* ── Name cell ── */
.nv-cell {
  display: flex; flex-direction: column; align-items: center;
  gap: 4px; width: 100%;
}
.nv-cell img { width: 44px; height: 44px; object-fit: contain; border-radius: 8px; border: 2px solid var(--bd); }
/* ĐỔI MÀU TÊN PLAYER THÀNH XANH LÁ */
.nv-name {
  font-weight: 800;
  font-size: .8rem;
  color: var(--success);
  word-break: break-word;
  max-width: 100%;
}
.nv-acc  { font-size: .7rem; color: var(--tx3); }
.nv-meta { font-size: .75rem; color: var(--tx3); font-family: 'JetBrains Mono', monospace; font-weight: 700; }
.task-box-meta {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 8px;
  min-height: 24px;
}
.nv-task-picker {
  display: none;
  width: min(100%, 620px);
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
  padding: 5px;
  border-radius: 6px;
  border: 1.5px solid var(--bd2);
  background: var(--surf2);
  color: var(--tx);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: .98rem;
  font-weight: 700;
  max-height: 118px;
  overflow-y: auto;
}
.task-modal-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  width: 100%;
  max-height: 62vh;
  overflow-y: auto;
}
#taskModal .modal-content {
  width: 900px;
  max-width: none;
  padding: 34px;
}
#scaler #taskModal {
  position: absolute;
  inset: 0;
  width: var(--page-w);
  min-height: 100%;
  height: 100%;
  z-index: 500;
}
#scaler #itemModal {
  position: absolute;
  inset: 0;
  width: var(--page-w);
  min-height: 100%;
  height: 100%;
  z-index: 500;
}
#scaler #taskModal .modal-content,
#scaler #itemModal .modal-content {
  margin: auto;
}
.nv-task-option {
  border: 1px solid var(--bd2);
  background: #fff;
  color: var(--tx);
  border-radius: 5px;
  padding: 4px 6px;
  text-align: left;
  font: inherit;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  touch-action: manipulation;
  user-select: none;
}
@media (max-width: 560px) {
  .nv-task-option {
    font-size: .9rem;
  }
}
.nv-task-option:hover {
  background: var(--blu-lt);
  border-color: var(--blue2);
}
.nv-task-option.selected {
  background: linear-gradient(135deg, var(--blue2), var(--indigo));
  border-color: transparent;
  color: #fff;
}
.nv-task-option.confirming {
  background: #fff7cc;
  border-color: #f4c542;
  color: var(--tx);
}
.nv-task-display {
  display: inline-block;
}
.task-box-meta.editable .nv-task-display {
  cursor: pointer;
  padding: 3px 8px;
  border: 1.5px solid var(--bd2);
  border-radius: 6px;
  background: var(--surf2);
}
.task-box-meta.editable .nv-task-display:hover {
  border-color: var(--blue2);
  background: var(--blu-lt);
}
.nv-task-danger {
  color: var(--danger);
  font-weight: 900;
}
.nv-task-option.task-danger {
  color: var(--danger);
  font-weight: 800;
}
.nv-task-option.task-danger.selected {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff;
}
.name-search {
  width: 95%; max-width: 130px;
  padding: 5px 8px;
  background: var(--surf2); border: 1.5px solid var(--bd2);
  border-radius: var(--rs); color: var(--tx);
  font-weight: 600; font-size: .75rem;
}

/* ── Power cell ── */
.power-edit { display: flex; flex-direction: column; gap: 5px; align-items: stretch; width: 100%; }
.power-row  { display: flex; align-items: center; gap: 6px; font-size: .75rem; font-weight: 700; flex-wrap: wrap; }
.power-row label { width: 62px; text-align: right; color: var(--tx3); flex-shrink: 0; }
.power-col  { flex: 1; min-width: 70px; }
.power-input {
  width: 100%;
  background: var(--surf2); border: 1.5px solid var(--bd2);
  border-radius: var(--rs); color: var(--tx);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: .8rem; font-weight: 700;
  padding: 4px 6px; outline: none;
}
.power-input:disabled {
  background: #eef4fa;
  color: #7a8ea8;
  cursor: not-allowed;
}
.power-hint { font-size: .65rem; color: var(--tx3); display: block; text-align: left; }

/* ── Finance cell ── */
.finance-cell {
  display: flex; flex-direction: column; gap: 5px;
  align-items: center;
  justify-content: center;
  width: 100%;
  text-align: center;
}
.fin-row {
  display: flex; align-items: center; gap: 4px; font-size: .78rem; font-weight: 700;
  justify-content: center;
}
.fin-row img { width: 18px; height: 18px; object-fit: contain; }
.fin-gold  { color: var(--warn);   }
.fin-gem   { color: var(--blue2);  }
.fin-ruby  { color: var(--danger); }
.fin-tv    { color: var(--indigo); }

/* ── Item grid ── */
.item-grid  { display: grid; gap: 3px; width: 100%; }
table .limit-3-rows { grid-template-columns: repeat(10, minmax(0, 1fr)); }

.item-slot {
  aspect-ratio: 1 / 1; width: 100%;
  background: var(--surf2); border: 1px solid var(--bd);
  border-radius: 4px;
  position: relative;
  display: flex; align-items: center; justify-content: center;
  transition: border-color .15s; cursor: pointer;
}
.item-slot:hover { border-color: var(--blue2); }
.item-slot img { width: 70%; height: 70%; object-fit: contain; image-rendering: pixelated; }
.item-slot .qty {
  position: absolute; bottom: 1px; right: 1px;
  font-size: 7px; color: var(--blue2); font-weight: 800;
  background: rgba(255,255,255,.85); border-radius: 2px; padding: 0 2px;
}

/* ── Action cell ── */
.action-cell { display: flex; flex-direction: column; gap: 5px; align-items: stretch; width: 100%; }
.btn-sm {
  display: inline-flex; align-items: center; justify-content: center;
  width: 100%; padding: 5px 0;
  font-size: .72rem; font-weight: 700;
  border-radius: var(--rs); border: 1.5px solid; cursor: pointer;
  transition: all .14s; font-family: inherit;
}
.btn-edit-p { background: #e0f2fe; color: var(--blue2); border-color: #7ab7d4; }
.btn-edit-p:hover { background: #cae8fc; }
.btn-save-p { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.btn-save-p:hover { background: #b8edda; }
.btn-cancel-p { background: #fde8ea; color: var(--danger); border-color: #f5a3aa; }
.btn-cancel-p:hover { background: #fbcfd4; }
.btn-del-p  { background: #fde8ea; color: var(--danger);  border-color: #f5a3aa; }
.btn-del-p:hover  { background: #fbcfd4; }

/* ══════════════════════════════════════════════════
   TOAST - LỚN GẤP 1.5 LẦN (giống account.php)
══════════════════════════════════════════════════ */
#toast {
  position: fixed;
  bottom: 32px;
  right: 32px;
  z-index: 9999;
  font-size: 1.5rem;
  padding: 21px 33px;
  border-radius: 18px;
  background: linear-gradient(135deg, var(--blue), var(--blue2));
  color: #fff;
  font-weight: 800;
  box-shadow: 0 6px 24px rgba(13,95,163,.35);
  border: 2px solid rgba(255,255,255,.25);
  display: none;
  animation: pop .22s ease;
  max-width: 480px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  line-height: 1.2;
}
#toast.error {
  background: linear-gradient(135deg, var(--danger), #b02030);
}
@keyframes pop {
  from { transform: scale(.88); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

/* ══════════════════════════════════════════════════
   MODAL
══════════════════════════════════════════════════ */
.modal {
  display: none; position: fixed; z-index: 200;
  left: 0; top: 0; width: 100%; height: 100%;
  background: rgba(9,19,31,.65);
  align-items: center; justify-content: center; padding: 20px;
}
.modal-content {
  background: var(--surf); border: 1.5px solid var(--bd2);
  padding: 34px;
  border-radius: var(--r);
  width: 100%; max-width: 960px;
  position: relative;
  animation: pop .22s ease; max-height: 90vh; overflow-y: auto;
  box-shadow: var(--sh);
  font-size: 1.1rem;
}
.modal-title {
  font-size: 1.3rem; font-weight: 800; color: var(--blue2);
  margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--bd);
}
.close-modal {
  position: absolute; top: 14px; right: 14px;
  background: var(--danger); color: #fff; border: none; border-radius: 50%;
  width: 36px; height: 36px; font-size: 1.1rem; font-weight: 900; cursor: pointer;
  transition: all .2s; display: flex; align-items: center; justify-content: center;
}
.close-modal:hover { transform: scale(1.15) rotate(90deg); }

.full-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)) !important;
  gap: 10px;
}
.full-grid .item-slot { width: 100%; aspect-ratio: 1/1; cursor: default; position: relative; }
.full-grid .item-slot img { width: 70%; height: 70%; }
.full-grid .item-slot .remove-btn {
  display: flex; position: absolute; top: -6px; right: -6px;
  width: 20px; height: 20px; background: var(--danger); border: none; border-radius: 50%;
  color: #fff; font-size: 10px; font-weight: 900; cursor: pointer;
  align-items: center; justify-content: center; z-index: 5;
}
.full-grid .item-slot:hover .remove-btn { display: flex; }
.confirm-box {
  position: relative;
}
.close-x {
  position: absolute;
  top: 12px;
  right: 18px;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
  color: var(--tx3);
  background: rgba(0, 0, 0, 0.05);
  border-radius: 50%;
  transition: all 0.2s;
  line-height: 1;
}
.close-x:hover {
  background: var(--danger);
  color: #fff;
  transform: scale(1.05);
}
/* CONFIRM MODAL - XUỐNG DÒNG ĐẸP + MÀU SẮC THEO YÊU CẦU */
.confirm-modal {
  display: none; position: fixed; z-index: 300;
  left: 0; top: 0; width: 100%; height: 100%;
  background: rgba(9,19,31,.65);
  align-items: center; justify-content: center;
}
#confirmModal .confirm-box {
  background: var(--surf); border: 1.5px solid var(--bd2);
  border-radius: var(--r); padding: 2rem;
  max-width: 550px; width: 90%; text-align: center;
  animation: pop .2s ease; box-shadow: var(--sh);
  font-size: 1.6rem;
}
#confirmModal .confirm-box h3 {
  color: #f4c542;  /* Màu vàng cho tiêu đề */
  font-size: 1.4em; margin-bottom: 12px;
}
.confirm-msg-main {
  color: #2ecc71;  /* Màu xanh lá */
  font-weight: 700;
}
.confirm-msg-warning {
  color: #e74c3c;  /* Màu đỏ */
  font-weight: 600;
  display: block;
  margin-top: 8px;
}
#confirmModal .confirm-box p {
  color: var(--tx3); margin-bottom: 24px;
  font-size: 1em;
  word-break: break-word;
  white-space: normal;
  line-height: 1.4;
}
#confirmModal .confirm-btns .btn-sm {
  font-size: 1rem; padding: 8px 16px; width: auto;
}

/* SCROLLBAR */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track  { background: var(--surf2); }
::-webkit-scrollbar-thumb  { background: var(--bd2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--tx3); }
</style>
<script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>

<div id="scaler">
<div class="W">

<!-- TOPBAR -->
<div class="top">
  <div class="ttit">
    <div class="tx-box">
      <svg class="tx-ic" viewBox="0 0 24 24" width="16" height="16" fill="none">
        <circle cx="9" cy="7" r="4" stroke="white" stroke-width="1.8"/>
        <circle cx="17" cy="9" r="3" stroke="white" stroke-width="1.6"/>
        <path d="M2 21c0-4 3.1-7 7-7s7 3 7 7" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M22 21c0-2.8-2-5-4.5-5.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      Tổng Player:
      <span style="font-family:'JetBrains Mono',monospace;font-size:.95em">
        <?= number_format($total_players) ?>
      </span>
    </div>
  </div>
  <div class="tr2">
    <a href="/sv1/admin/panel" class="btn bblue">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M3 10.5L12 3L21 10.5V20A1 1 0 0 1 20 21H15V14H9V21H4A1 1 0 0 1 3 20V10.5Z"
              stroke="white" stroke-width="2" stroke-linejoin="round"/>
      </svg>
      <span>Panel</span>
    </a>
    <button class="btn bteal" onclick="location.reload()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M20 12A8 8 0 1 1 17.3 6M20 4V12H12"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Làm Mới</span>
    </button>
  </div>
</div>

<!-- STATS -->
<div class="stats-row">
  <div class="stat-card">
    <img src="/img/25.jpg" class="stat-img" onerror="this.style.display='none'" title="Trái Đất">
    <div><div class="stat-val"><?= number_format($total_gender0) ?></div></div>
  </div>
  <div class="stat-card">
    <img src="/img/24.jpg" class="stat-img" onerror="this.style.display='none'" title="Namek">
    <div><div class="stat-val"><?= number_format($total_gender1) ?></div></div>
  </div>
  <div class="stat-card">
    <img src="/img/26.jpg" class="stat-img" onerror="this.style.display='none'" title="Xayda">
    <div><div class="stat-val"><?= number_format($total_gender2) ?></div></div>
  </div>
</div>

<!-- PAGINATION -->
<?php
$start_loop = max(1, $current_page - 4);
$end_loop   = min($total_pages, $current_page + 5);
?>
<div class="pag-wrap">
  <?php for ($i = $start_loop; $i <= $end_loop; $i++): ?>
    <a href="?page=<?= $i ?>" class="page-link <?= ($i == $current_page) ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>

<!-- TABLE -->
<div class="tc"><div class="tsc">
<table>
<colgroup>
  <col class="col-name">
  <col class="col-power">
  <col class="col-fin">
  <col class="col-bag">
  <col class="col-box">
  <col class="col-act">
</colgroup>
<thead>

  <th>
    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
      <span>Name</span>
      <input type="text" id="searchPlayer" class="name-search" placeholder="🔍 Tìm...">
    </div>
  </th>
  <th>Power</th>
  <th>Tài Sản</th>
  <th>Hành Trang</th>
  <th>Rương Đồ</th>
  <th>VIP</th>
</tr>
</thead>
<tbody id="playerTableBody">
<?php foreach ($display_list as $row):
  $inv = $row['data_inventory']; // đã là mảng
  $sm_su  = $row['data_point'][1] ?? 0;
  $nv_task = (int)($row['data_task'][0] ?? 0);
  $pet    = getPetInfo(json_encode($row['pet'])); // tạm dùng hàm cũ để lấy power, tiềm năng
  $tv_count = $row['tv_count'];
  // Chuẩn bị dữ liệu gốc dưới dạng JSON string để lưu vào data attribute
  $original_bag_json = json_encode($row['items_bag']);
  $original_box_json = json_encode($row['items_box']);
?>
<tr class="player-row" data-name="<?= mb_strtolower(htmlspecialchars($row['name'])) ?>" data-id="<?= $row['id'] ?>"
    data-original-power='{"sm_su":<?=$sm_su?>,"sm_de":<?=$pet['power']?>,"tn_de":<?=$pet['tiemnang']?>}'
    data-original-task="<?= $nv_task ?>"
    data-original-bag='<?= htmlspecialchars($original_bag_json, ENT_QUOTES) ?>'
    data-original-box='<?= htmlspecialchars($original_box_json, ENT_QUOTES) ?>'
    data-editing="false">

  <!-- NAME -->
  <td>
    <div class="nv-cell">
      <img src="/icon/<?= $row['avatar_id'] ?? 1 ?>.png" onerror="this.src='/icon/1.png'">
      <div class="nv-name"><?= htmlspecialchars($row['name']) ?></div>
      <div class="nv-acc">@<?= htmlspecialchars($row['username'] ?? '') ?></div>
    </div>
  </td>

  <!-- POWER -->
  <td>
    <div class="power-edit">
      <div class="power-row">
        <label>⚔️ SM Sư:</label>
        <div class="power-col">
          <input type="number" class="power-input inp-sm-su" value="<?= (int)$sm_su ?>" min="0" disabled>
          <span class="power-hint">≈ <?= formatK($sm_su) ?></span>
        </div>
      </div>
      <div class="power-row">
        <label>⚡ SM Đệ:</label>
        <div class="power-col">
          <input type="number" class="power-input inp-sm-de" value="<?= (int)$pet['power'] ?>" min="0" disabled>
          <span class="power-hint">≈ <?= formatK($pet['power']) ?></span>
        </div>
      </div>
      <div class="power-row">
        <label>✨ TN Đệ:</label>
        <div class="power-col">
          <input type="number" class="power-input inp-tn-de" value="<?= (int)$pet['tiemnang'] ?>" min="0" disabled>
          <span class="power-hint">≈ <?= formatK($pet['tiemnang']) ?></span>
        </div>
      </div>
    </div>
  </td>

  <!-- FINANCE -->
  <td>
    <div class="finance-cell">
      <div class="fin-row fin-gold">
        <img src="/img/930.png" onerror="this.style.display='none'">
        <?= formatK($inv[0] ?? 0) ?>
      </div>
      <div class="fin-row fin-gem">
        <img src="/img/932.png" onerror="this.style.display='none'">
        <?= formatK($inv[1] ?? 0) ?>
      </div>
      <div class="fin-row fin-ruby">
        <img src="/img/7743.png" onerror="this.style.display='none'">
        <?= formatK($inv[2] ?? 0) ?>
      </div>
      <div class="fin-row fin-tv">
        <img src="/icon/4028.png" onerror="this.src='/icon/1.png'" style="width:16px;height:16px;object-fit:contain;">
        <?= number_format($tv_count) ?>
      </div>
    </div>
  </td>

  <!-- BAG -->
  <td class="bag-cell" data-col="bag" style="cursor:pointer;">
    <div class="bag-grid-container">
      <?= renderItems($row['items_bag'], $item_temp, true) ?>
    </div>
  </td>

  <!-- BOX -->
  <td class="box-cell" data-col="box" style="cursor:pointer;">
    <div class="nv-meta task-box-meta">
      <span class="nv-task-display <?= $nv_task === 22 ? 'nv-task-danger' : '' ?>">Nhiệm Vụ: <?= $nv_task ?></span>
      <input type="hidden" class="nv-task-value" value="<?= $nv_task ?>">
      <div class="nv-task-picker">
        <?php foreach ($task_main_templates as $taskId => $taskName): ?>
          <button type="button"
                  class="nv-task-option <?= $taskId === 22 ? 'task-danger' : '' ?> <?= $taskId === $nv_task ? 'selected' : '' ?>"
                  data-task-id="<?= $taskId ?>"
                  title="<?= $taskId ?> - <?= htmlspecialchars($taskName) ?>">
            <?= $taskId ?> - <?= htmlspecialchars($taskName) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="box-grid-container">
      <?= renderItems($row['items_box'], $item_temp, true) ?>
    </div>
  </td>

  <!-- ACTION -->
  <td>
    <div class="action-cell">
      <button class="btn-sm btn-edit-p" onclick="enableEditMode(this)">✏️ Sửa</button>
      <button class="btn-sm btn-del-p"  onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', event)">🗑️ Xóa</button>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

</div><!-- /W -->

<div id="taskModal" class="modal">
  <div class="modal-content" onclick="event.stopPropagation()">
    <button class="close-modal" onclick="closeTaskModal()">✕</button>
    <div class="modal-title">📜 Chọn Nhiệm Vụ - Ấn 2 Lần</div>
    <div id="taskModalBody" class="task-modal-grid"></div>
  </div>
</div>
<!-- MODALS -->
<div id="itemModal" class="modal">
  <div class="modal-content" onclick="event.stopPropagation()">
    <button class="close-modal" onclick="$('#itemModal').hide()">✕</button>
    <div class="modal-title" id="modalTitle">📦 Chi Tiết Vật Phẩm</div>
    <div id="modalBody" class="item-grid full-grid"></div>
  </div>
</div>

</div><!-- /scaler -->

<div id="confirmModal" class="confirm-modal">
  <div class="confirm-box">
    <span class="close-x">&times;</span>
    <h3>⚠️ Xác Nhận Xóa</h3>
    <p id="confirmMsg">Bạn có chắc muốn xóa?</p>
    <div class="confirm-btns">
      <button class="btn-sm btn-save-p" id="confirmCancelBtn">✖️ Hủy</button>
      <button class="btn-sm btn-del-p" id="confirmYesBtn">🗑️ Xóa</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const itemTemplates = <?= json_encode($item_temp) ?>;

// Helper: show toast
function showToast(msg, isError) {
    const t = $('#toast');
    t.text(msg).removeClass('error');
    if (isError) t.addClass('error');
    t.stop(true).fadeIn(200).delay(2400).fadeOut(400);
}

// Scale logic
(function () {
  var PAGE_W  = 1500;
  var FILL    = 0.98;
  var scaler  = document.getElementById('scaler');

  function applyScale() {
    var vw    = window.innerWidth;
    var avail = vw * FILL;
    var ratio = avail / PAGE_W;
    var leftOffset = (vw - avail) / 2;

    scaler.style.width           = PAGE_W + 'px';
    scaler.style.transform       = 'scale(' + ratio + ')';
    scaler.style.transformOrigin = 'top left';
    scaler.style.marginLeft      = leftOffset + 'px';
    scaler.style.marginRight     = '0';
    document.body.style.minHeight = (scaler.offsetHeight * ratio) + 'px';
    syncModalCanvas();
  }

  applyScale();
  window.addEventListener('resize', applyScale);
})();

// Search filter
$('#searchPlayer').on('keyup', function () {
  const val = $(this).val().toLowerCase().trim();
  $('#playerTableBody tr').each(function () {
    const name = $(this).attr('data-name') || '';
    $(this).toggle(name.includes(val));
  });
});

// ========== EDIT MODE LOGIC ==========
let editCache = {};
let currentTaskRow = null;

function syncModalCanvas() {
  const scaler = document.getElementById('scaler');
  if (!scaler) return;
  ['taskModal', 'itemModal'].forEach(function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.height = scaler.offsetHeight + 'px';
  });
}

function updateTaskDisplay($row, taskId) {
  const taskNum = parseInt(taskId) || 0;
  $row.find('.nv-task-value').val(taskNum);
  $row.find('.nv-task-option').removeClass('selected');
  $row.find('.nv-task-option[data-task-id="' + taskNum + '"]').addClass('selected');
  $row.find('.nv-task-display')
    .text('Nhiệm Vụ: ' + taskNum)
    .toggleClass('nv-task-danger', taskNum === 22);
}

function openTaskModal($row) {
  currentTaskRow = $row;
  syncModalCanvas();
  const $grid = $('<div class="task-modal-grid"></div>');
  $row.find('.nv-task-picker .nv-task-option').each(function() {
    $grid.append($(this).clone());
  });
  $('#taskModalBody').empty().append($grid.children());
  $('#taskModalBody .nv-task-option').on('pointerdown', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const now = Date.now();
    const taskId = $(this).data('task-id');
    const lastTask = this.dataset.lastTaskConfirm || '';
    const lastTime = parseInt(this.dataset.lastTaskConfirmAt || '0', 10);

    if (lastTask === String(taskId) && now - lastTime <= 1000) {
      updateTaskDisplay(currentTaskRow, taskId);
      closeTaskModal();
      return;
    }

    $('#taskModalBody .nv-task-option').removeClass('confirming').each(function() {
      this.dataset.lastTaskConfirm = '';
      this.dataset.lastTaskConfirmAt = '0';
    });
    this.dataset.lastTaskConfirm = String(taskId);
    this.dataset.lastTaskConfirmAt = String(now);
    $(this).addClass('confirming');
  });
  $('#taskModal').css('display', 'flex');
}

function closeTaskModal() {
  $('#taskModal').hide();
  $('#taskModalBody').empty();
  currentTaskRow = null;
}

function enableEditMode(btn) {
  const $row = $(btn).closest('tr');
  if ($row.attr('data-editing') === 'true') return;
  const playerId = $row.data('id');

  // Lưu original data vào cache
  editCache[playerId] = {
    power: {
      sm_su: parseInt($row.find('.inp-sm-su').val()),
      sm_de: parseInt($row.find('.inp-sm-de').val()),
      tn_de: parseInt($row.find('.inp-tn-de').val())
    },
    task: parseInt($row.find('.nv-task-value').val()) || 0,
    bag: JSON.parse($row.attr('data-original-bag')),
    box: JSON.parse($row.attr('data-original-box'))
  };

  $row.attr('data-editing', 'true');
  $row.find('.power-input').prop('disabled', false);
  $row.find('.task-box-meta').addClass('editable');
  $row.find('.task-box-meta').off('click').on('click', function(e) {
    e.stopPropagation();
    openTaskModal($row);
  });

  const $actionDiv = $row.find('.action-cell');
  $actionDiv.find('.btn-edit-p').hide();
  $actionDiv.find('.btn-del-p').hide();
  $actionDiv.append('<button class="btn-sm btn-save-p" onclick="saveFullEdit(this)">💾 Lưu</button>');
  $actionDiv.append('<button class="btn-sm btn-cancel-p" onclick="cancelEdit(this)">✖️ Hủy</button>');

  // Gắn sự kiện click cho bag/box để mở modal có nút xóa
  $row.find('.bag-cell, .box-cell').off('click').on('click', function(e) {
    e.stopPropagation();
    const col = $(this).data('col');
    const currentData = (col === 'bag') ? editCache[playerId].bag : editCache[playerId].box;
    openItemModalWithDelete(playerId, col, currentData, $row);
  });
}

function cancelEdit(btn) {
  const $row = $(btn).closest('tr');
  const playerId = $row.data('id');
  const orig = editCache[playerId];
  if (!orig) return;

  // Khôi phục power
  $row.find('.inp-sm-su').val(orig.power.sm_su);
  $row.find('.inp-sm-de').val(orig.power.sm_de);
  $row.find('.inp-tn-de').val(orig.power.tn_de);
  updateTaskDisplay($row, orig.task);
  // Khôi phục lại grid
  const origBagHtml = renderItemsGrid(JSON.parse($row.attr('data-original-bag')), true);
  const origBoxHtml = renderItemsGrid(JSON.parse($row.attr('data-original-box')), true);
  $row.find('.bag-cell .bag-grid-container').html(origBagHtml);
  $row.find('.box-cell .box-grid-container').html(origBoxHtml);

  finishEditMode($row);
  delete editCache[playerId];
}

function finishEditMode($row) {
  $row.attr('data-editing', 'false');
  $row.find('.power-input').prop('disabled', true);
  $row.find('.task-box-meta').removeClass('editable').off('click');
  const $actionDiv = $row.find('.action-cell');
  $actionDiv.find('.btn-save-p').remove();
  $actionDiv.find('.btn-cancel-p').remove();
  $actionDiv.find('.btn-edit-p').show();
  $actionDiv.find('.btn-del-p').show();

  // Khôi phục sự kiện xem thường
  $row.find('.bag-cell, .box-cell').off('click').on('click', function(e) {
    e.stopPropagation();
    const col = $(this).data('col');
    const dataAttr = (col === 'bag') ? $row.attr('data-original-bag') : $row.attr('data-original-box');
    const items = JSON.parse(dataAttr);
    openItemModalViewOnly(items);
  });
}

async function saveFullEdit(btn) {
  const $row = $(btn).closest('tr');
  const playerId = $row.data('id');
  const sm_su = $row.find('.inp-sm-su').val();
  const sm_de = $row.find('.inp-sm-de').val();
  const tn_de = $row.find('.inp-tn-de').val();
  const nv_task = $row.find('.nv-task-value').val();
  const items_bag = JSON.stringify(editCache[playerId].bag);
  const items_box = JSON.stringify(editCache[playerId].box);

  try {
    const res = await $.post('', {
      csrf_token: CSRF_TOKEN,
      action: 'save_full',
      player_id: playerId,
      sm_su, sm_de, tn_de,
      nv_task,
      items_bag, items_box
    });
    if (res.ok) {
    const playerName = $row.find('.nv-name').text().trim();
showToast('✅ Đã lưu Player: ' + playerName);
      // Cập nhật lại data-original để đồng bộ
      $row.attr('data-original-bag', items_bag);
      $row.attr('data-original-box', items_box);
      $row.attr('data-original-power', JSON.stringify({sm_su, sm_de, tn_de}));
      $row.attr('data-original-task', nv_task);
      updateTaskDisplay($row, nv_task);
      finishEditMode($row);
      delete editCache[playerId];
      // Reload sau 1.5 giây để người dùng kịp thấy toast
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('❌ Lỗi: ' + (res.msg || 'Không rõ'), true);
    }
  } catch(e) {
    showToast('❌ Lỗi kết nối', true);
  }
}

function renderItemsGrid(itemsArray, limit = true) {
  let html = '<div class="item-grid limit-3-rows">';
  const maxIdx = limit ? Math.min(itemsArray.length, 30) : itemsArray.length;
  for (let i = 0; i < maxIdx; i++) {
    let it = typeof itemsArray[i] === 'string' ? JSON.parse(itemsArray[i]) : itemsArray[i];
    let id = it[0] ?? -1;
    let qty = it[1] ?? 0;
    html += '<div class="item-slot" data-idx="'+i+'">';
    if (id !== -1 && itemTemplates[id]) {
      let icon = itemTemplates[id].icon_id;
      let name = itemTemplates[id].name || '';
      html += `<img src="/icon/${icon}.png" onerror="this.src='/icon/1.png'" title="${name.replace(/"/g, '&quot;')}">`;
      if (qty > 1) html += `<span class="qty">${qty}</span>`;
    }
    html += '</div>';
  }
  html += '</div>';
  return html;
}

// ----------------- XÓA ITEM VỚI XÁC NHẬN -----------------
let pendingItemRemoval = null; // { playerId, col, slotIdx, $row, itemName }

function showConfirmForItem(itemName, onConfirm) {
  // Cập nhật nội dung confirm modal theo yêu cầu
  $('#confirmMsg').html(`
    <span class="confirm-msg-main">Xóa Vật Phẩm: "${escapeHtml(itemName)}"?</span>
    <span class="confirm-msg-warning">Không thể hoàn tác!</span>
  `);
  // Lưu callback
  pendingItemRemoval = onConfirm;
  $('#confirmModal').css('display', 'flex');
}

function openItemModalWithDelete(playerId, col, itemsData, $row) {
  const title = (col === 'bag') ? 'Hành Trang (Chế độ sửa)' : 'Rương Đồ (Chế độ sửa)';
  $('#modalTitle').text('📦 ' + title);
  let html = '';
  itemsData.forEach(function(itemStr, idx) {
    let it = typeof itemStr === 'string' ? JSON.parse(itemStr) : itemStr;
    let id = it[0] ?? -1;
    let qty = it[1] ?? 0;
    let itemName = (id !== -1 && itemTemplates[id]) ? itemTemplates[id].name : '?';
    html += `<div class="item-slot" data-idx="${idx}" data-col="${col}">`;
    if (id !== -1 && itemTemplates[id]) {
      let icon = itemTemplates[id].icon_id;
      let name = itemTemplates[id].name || '';
      html += `<img src="/icon/${icon}.png" onerror="this.src='/icon/1.png'" title="${name.replace(/"/g, '&quot;')}">`;
      if (qty > 1) html += `<span class="qty">${qty}</span>`;
      // Nút xóa gọi hàm xác nhận
      html += `<button class="remove-btn" onclick="requestRemoveItem(${playerId}, '${col}', ${idx}, '${escapeHtml(itemName)}', event)">✕</button>`;
    }
    html += '</div>';
  });
  $('#modalBody').html(html).addClass('full-grid');
  syncModalCanvas();
  $('#itemModal').css('display', 'flex');
  window.currentEditRow = $row;
  window.currentEditCol = col;
  window.currentEditPlayerId = playerId;
}

// Hàm yêu cầu xóa item, hiện confirm trước
function requestRemoveItem(playerId, col, slotIdx, itemName, e) {
  e.stopPropagation();
  showConfirmForItem(itemName, function() {
    // Xóa item thực sự
    const cache = editCache[playerId];
    if (!cache) return;
    let targetArr = (col === 'bag') ? cache.bag : cache.box;
    if (targetArr[slotIdx]) {
      targetArr[slotIdx] = JSON.stringify([-1, 0]);
      const $row = window.currentEditRow;
      const $container = (col === 'bag') ? $row.find('.bag-cell .bag-grid-container') : $row.find('.box-cell .box-grid-container');
      $container.html(renderItemsGrid(targetArr, true));
      // Đóng modal item và mở lại để cập nhật danh sách
      $('#itemModal').hide();
      openItemModalWithDelete(playerId, col, targetArr, $row);
      showToast('🗑️ Đã xóa vật phẩm!');
    }
    pendingItemRemoval = null;
  });
}

function openItemModalViewOnly(itemsData) {
  $('#modalTitle').text('📦 Chi Tiết Vật Phẩm');
  let html = '';
  itemsData.forEach(function(itemStr, idx) {
    let it = typeof itemStr === 'string' ? JSON.parse(itemStr) : itemStr;
    let id = it[0] ?? -1;
    let qty = it[1] ?? 0;
    html += `<div class="item-slot" data-idx="${idx}">`;
    if (id !== -1 && itemTemplates[id]) {
      let icon = itemTemplates[id].icon_id;
      let name = itemTemplates[id].name || '';
      html += `<img src="/icon/${icon}.png" onerror="this.src='/icon/1.png'" title="${name.replace(/"/g, '&quot;')}">`;
      if (qty > 1) html += `<span class="qty">${qty}</span>`;
    }
    html += '</div>';
  });
  $('#modalBody').html(html).addClass('full-grid');
  syncModalCanvas();
  $('#itemModal').css('display', 'flex');
}

// Helper escape html
function escapeHtml(str) {
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

// ========== XÓA PLAYER VỚI MÀU SẮC YÊU CẦU ==========
let _deleteId = null;
function confirmDelete(pid, name, e) {
  e.stopPropagation();
  _deleteId = pid;
  // Định dạng theo yêu cầu: tên player màu xanh lá, "Không thể hoàn tác!" màu đỏ, xuống dòng
  $('#confirmMsg').html(`
    <span class="confirm-msg-main">Xóa thằng chó: "${escapeHtml(name)}"?</span>
    <span class="confirm-msg-warning">Không thể hoàn tác!</span>
  `);
  $('#confirmModal').css('display', 'flex');
}

// Xử lý nút xóa trong confirm modal (cả player và item)
$('#confirmYesBtn').on('click', function () {
  if (_deleteId !== null) {
    // Xóa player
    $.post('', { csrf_token: CSRF_TOKEN, action: 'delete_player', player_id: _deleteId }, function (res) {
      $('#confirmModal').hide();
      if (res.ok) {
        $('tr[data-id="' + _deleteId + '"]').fadeOut(400, function () { $(this).remove(); });
        showToast('🗑️ Đã xóa nhân vật!');
      } else {
        showToast('❌ Xóa thất bại', true);
      }
      _deleteId = null;
    }, 'json').fail(function () { showToast('❌ Kết nối thất bại', true); _deleteId = null; });
  } 
  else if (pendingItemRemoval !== null) {
    // Xóa item: thực thi callback và đóng modal xác nhận
    pendingItemRemoval();
    $('#confirmModal').hide();       // Đóng hộp thoại xác nhận
    pendingItemRemoval = null;       // Reset callback
  }
});

// Đóng modal khi nhấn nút X
$(document).on('click', '#confirmModal .close-x', function() {
  $('#confirmModal').hide();
  _deleteId = null;
  pendingItemRemoval = null;
});
// Đóng modal khi nhấn nút Hủy
$('#confirmCancelBtn').on('click', function() {
  $('#confirmModal').hide();
  _deleteId = null;
  pendingItemRemoval = null;
});
// Đóng modal khi nhấn ESC
$(document).on('keydown', function (e) {
  if (e.key === 'Escape') {
    $('#confirmModal').hide();
    $('#itemModal').hide();
    closeTaskModal();
    _deleteId = null;
    pendingItemRemoval = null;
  }
});

// Gắn sự kiện xem bình thường cho các hàng chưa edit
$(function() {
  $('.player-row').each(function() {
    const $row = $(this);
    $row.find('.bag-cell, .box-cell').on('click', function(e) {
      e.stopPropagation();
      const col = $(this).data('col');
      const dataAttr = (col === 'bag') ? $row.attr('data-original-bag') : $row.attr('data-original-box');
      const items = JSON.parse(dataAttr);
      openItemModalViewOnly(items);
    });
  });
});
</script>
</body>
</html>
