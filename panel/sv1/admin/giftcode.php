<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

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

$csrfToken = ensure_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM giftcode WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'load_giftcode') {
        if (append_panel_command('LOAD_GIFTCODE')) {
            echo json_encode(['status' => 'success', 'message' => 'Đã Update Giftcode.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể Update Giftcode.']);
        }
        exit;
    }

    if ($_POST['action'] === 'update') {
        $id      = intval($_POST['id']);
        $code    = trim($_POST['code']       ?? '');
        $count   = intval($_POST['count_left'] ?? 0);
        $type    = intval($_POST['type']     ?? 0);
        try {
            $stmt = $pdo->prepare("
                UPDATE giftcode 
                SET code=:code, count_left=:count, type=:type 
                WHERE id=:id
            ");
            $stmt->execute([
                ':code'  => $code,
                ':count' => $count,
                ':type'  => $type,
                ':id'    => $id
            ]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

try {
    $stmt = $pdo->query("SELECT id, NAME, icon_id FROM item_template");
    $itemTemplates = [];
    foreach ($stmt->fetchAll() as $row) $itemTemplates[$row['id']] = $row;
} catch (Exception $e) { $itemTemplates = []; }

try {
    $stmt = $pdo->query("SELECT id, NAME FROM item_option_template");
    $optionTemplates = [];
    foreach ($stmt->fetchAll() as $row) $optionTemplates[$row['id']] = $row['NAME'];
} catch (Exception $e) { $optionTemplates = []; }

function parseDetail($detail, $itemTemplates, $optionTemplates) {
    if (empty($detail)) return '<span class="no-detail">—</span>';
    $data = json_decode($detail, true);
    if (!$data) return '<span class="raw-detail">' . htmlspecialchars($detail) . '</span>';
    $items = isset($data[0]) ? $data : [$data];
    $html  = '<div class="item-grid">';
    foreach ($items as $item) {
        $itemId  = $item['id'] ?? null;
        $count   = $item['quantity'] ?? ($item['count'] ?? 1);
        $options = $item['options'] ?? [];
        $name    = '?'; $iconId = null;
        if ($itemId && isset($itemTemplates[$itemId])) {
            $name   = htmlspecialchars($itemTemplates[$itemId]['NAME']);
            $iconId = $itemTemplates[$itemId]['icon_id'];
        }
        $iconPath = $iconId ? "/icon/{$iconId}.png" : "/icon/default.png";
        $optStr = '';
        if (!empty($options)) {
            $parts = [];
            foreach ($options as $opt) {
                $optId = isset($opt['id']) ? (int)$opt['id'] : null;
                $param = $opt['param'] ?? '';
                if ($optId !== null && isset($optionTemplates[$optId])) {
                    $optName = $optionTemplates[$optId];
                } else {
                    $optName = "Option#{$optId}";
                }
                if (strpos($optName, '#') !== false) {
                    $optName = str_replace('#', '<b>' . htmlspecialchars($param) . '</b>', $optName);
                    $parts[] = '+ ' . $optName;
                } else {
                    $parts[] = '+ ' . htmlspecialchars($optName);
                }
            }
            $optStr = '<div class="item-opts">' . implode('<br>', $parts) . '</div>';
        }
        $html .= '<div class="item-card">
            <img src="' . $iconPath . '" alt="icon" class="item-icon" onerror="this.src=\'/icon/default.png\'">
            <div class="item-info">
                <div class="item-name-count">
                    <span class="item-name">' . $name . '</span>
                    <span class="item-count">x' . intval($count) . '</span>
                </div>
                ' . $optStr . '
            </div>
        </div>';
    }
    return $html . '</div>';
}

function renderShareSubRows(array $rows, string $groupId): void {
    if (empty($rows)) return;
    echo '<tr class="share-sub-row is-hidden" data-share-sub-group="' . htmlspecialchars($groupId, ENT_QUOTES) . '"><td colspan="6"><div class="share-sub-grid">';
    foreach ($rows as $row) {
        $usedClass = ((int)$row['count_left'] <= 0) ? ' is-used-up' : '';
        $code = (string)$row['code'];
        $codeAttr = htmlspecialchars($code, ENT_QUOTES);
        echo '<div class="share-sub-item' . $usedClass . '">';
        echo '<span class="share-sub-line">' . (int)$row['id'] . ' - ' . htmlspecialchars($code, ENT_QUOTES) . ' - ' . (int)$row['count_left'] . '</span>';
        echo '<button type="button" class="copy-code-btn" data-copy-code="' . $codeAttr . '" onclick="copyGiftcode(this)" title="Copy code" aria-label="Copy code ' . $codeAttr . '">';
        echo '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>';
        echo '</button>';
        echo '</div>';
    }
    echo '</div></td></tr>';
}

$activeTab = ($_GET['tab'] ?? 'player') === 'share' ? 'share' : 'player';
$typeWhere = $activeTab === 'share' ? 'type = 2' : 'type IN (0, 1)';
$perPage = 500;
$page = max(1, (int)($_GET['page'] ?? 1));

try {
    $totalCodes = (int)$pdo->query("SELECT COUNT(*) FROM giftcode WHERE {$typeWhere}")->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCodes / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $orderBy = $activeTab === 'share' ? 'detail ASC, id ASC' : 'id ASC';
    $stmt = $pdo->query("SELECT * FROM giftcode WHERE {$typeWhere} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
    $giftcodes = $stmt->fetchAll();
} catch (Exception $e) {
    $giftcodes = [];
    $totalCodes = 0;
    $totalPages = 1;
    $page = 1;
}

function giftcode_page_url(string $tab, int $page): string {
    return '?tab=' . urlencode($tab) . '&page=' . max(1, $page);
}

$currentUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Quản Lý Giftcode</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
/* ══════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════
   SCALER — cố định 1500px, scale theo viewport
══════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════ */
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
  text-decoration: none;
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

.code-tabs {
  display: flex; justify-content: center; align-items: center; gap: 10px;
  margin: 8px 0 10px; flex-wrap: wrap;
}
.code-tab {
  min-width: 170px; padding: 11px 18px; border-radius: var(--rs);
  border: 1.5px solid var(--bd2); background: var(--surf);
  color: var(--tx2); text-decoration: none; font-weight: 800;
  text-align: center; box-shadow: var(--sh2);
}
.code-tab.active {
  color: #fff; border-color: transparent;
  background: linear-gradient(135deg, #2563eb, #6d3fd1);
  box-shadow: 0 7px 18px rgba(79,70,229,.26);
}
.code-tab-btn {
  cursor: pointer;
  font-family: inherit;
}
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

/* ══════════════════════════════════════════════
   TABLE CONTAINER
══════════════════════════════════════════════ */
.tc  { background: var(--surf); border: 1px solid var(--bd2); border-radius: var(--r); overflow: hidden; box-shadow: var(--sh); margin-bottom: 11px; }
.tsc { overflow-x: auto; -webkit-overflow-scrolling: touch; }

table { width: 100%; table-layout: fixed; border-collapse: collapse; }

colgroup col:nth-child(1) { width: 7.5%; }
colgroup col:nth-child(2) { width: 15%;  }
colgroup col:nth-child(3) { width: 7.5%; }
colgroup col:nth-child(4) { width: 54%;  }
colgroup col:nth-child(5) { width: 10%;  }
colgroup col:nth-child(6) { width: 6%;   }

thead tr { background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt)); border-bottom: 2px solid var(--bd2); }
th {
  padding: 12px 10px 9px;
  font-size: .9rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .7px;
  color: #09131f; text-align: center;
}
tbody tr { border-bottom: 1px solid var(--bd); transition: background .12s; }
tbody tr:nth-child(even) { background: rgba(13,95,163,.03); }
tbody tr:hover { background: var(--sky-lt); }
tbody tr.row-editing { background: rgba(200,160,0,.07) !important; }
tbody tr.row-used-up {
  background: #eef1f4 !important;
  color: #7b8794;
  opacity: .72;
}
tbody tr.row-used-up:hover { background: #e2e7ec !important; }
tbody tr.row-used-up .code-badge,
tbody tr.row-used-up .badge-count,
tbody tr.row-used-up .badge-tv,
tbody tr.row-used-up .cell-id,
tbody tr.row-used-up .item-card {
  filter: grayscale(1);
  opacity: .72;
}
td { padding: 9px 8px; vertical-align: middle; text-align: center; color: var(--tx); }

.cell-id {
  color: var(--indigo); font-weight: 800;
  font-family: 'JetBrains Mono', monospace; font-size: .82rem;
  background: var(--ind-lt); padding: 3px 9px; border-radius: 6px;
  border: 1px solid #c4b5fd; display: inline-block;
}
.code-badge {
  display: inline-block;
  font-family: 'JetBrains Mono', monospace;
  font-size: .84rem; font-weight: 700;
  color: var(--blue2);
  background: var(--blu-lt);
  border: 1.5px solid var(--bd2);
  border-radius: 8px; padding: 4px 11px;
  letter-spacing: .3px; word-break: break-all;
}
.code-copy-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  max-width: 100%;
}
.copy-code-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  padding: 0;
  border: 1.5px solid var(--bd2);
  border-radius: 7px;
  background: var(--surf);
  color: var(--blue2);
  cursor: pointer;
  transition: all .14s;
}
.copy-code-btn:hover {
  color: #fff;
  border-color: var(--blue2);
  background: var(--blue2);
  transform: translateY(-1px);
}
.copy-code-btn:active { transform: scale(.92); }
.copy-code-btn.is-copied {
  color: #fff;
  border-color: var(--success);
  background: var(--success);
}
.copy-code-btn svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.badge-count {
  display: inline-block;
  background: var(--blu-lt); color: var(--blue2);
  border: 1.5px solid var(--bd2);
  border-radius: 20px; padding: 4px 16px;
  font-size: .84rem; font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
}
.badge-count.zero { background: #fde8ea; color: var(--danger); border-color: #f5a3aa; }

.badge-tv {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: 20px;
  font-size: .82rem; font-weight: 700; border: 1.5px solid;
}
.badge-tv.no-need { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.badge-tv.need    { background: #fde8ea; color: var(--danger);  border-color: #f5a3aa; }
.badge-tv .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.badge-tv.no-need .dot { background: var(--success); }
.badge-tv.need    .dot { background: var(--danger); }

.editable-input {
  background: var(--surf2); border: 1.5px solid var(--bd2);
  color: var(--tx); border-radius: var(--rs);
  padding: 5px 9px; width: 100%;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: .84rem;
  outline: none; transition: border-color .18s, box-shadow .18s;
}
.editable-input:focus {
  border-color: var(--blue2);
  box-shadow: 0 0 0 3px rgba(13,95,163,.13);
  background: var(--surf);
}
select.editable-input {
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234a7a9b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 9px center;
  padding-right: 26px; appearance: none;
}
.input-error {
  border-color: var(--danger) !important;
  box-shadow: 0 0 0 3px rgba(217,43,58,.14) !important;
  animation: shake .25s ease;
}
@keyframes shake {
  0%   { transform: translateX(0); }
  25%  { transform: translateX(-3px); }
  50%  { transform: translateX(3px); }
  75%  { transform: translateX(-2px); }
  100% { transform: translateX(0); }
}

.btn-sm {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 10px; font-size: .79rem; font-weight: 700;
  border-radius: var(--rs); border: 1.5px solid; cursor: pointer;
  transition: all .14s; font-family: inherit; white-space: nowrap;
}
.btn-edit   { background: var(--blu-lt); color: var(--blue2); border-color: var(--bd2); }
.btn-edit:hover   { background: var(--sky-lt); border-color: var(--blue2); }
.btn-save   { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.btn-save:hover   { background: #b8edda; }
.btn-save:disabled { opacity: .5; cursor: not-allowed; }
.btn-cancel { background: var(--surf2); color: var(--tx3); border-color: var(--bd); }
.btn-cancel:hover { background: var(--surf3); color: var(--tx2); }
.btn-del    { background: #fde8ea; color: var(--danger); border-color: #f5a3aa; }
.btn-del:hover    { background: #fbcfd4; }

.action-group { display: flex; flex-direction: column; gap: 5px; align-items: stretch; }
.action-group .btn-sm { justify-content: center; width: 100%; }
.action-group.edit-mode {
  flex-direction: row;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
}
.action-group.edit-mode .btn-sm {
  width: auto;
  flex: 1 1 auto;
  justify-content: center;
}

/* ══════════════════════════════════════════════
   ITEM GRID – CĂN GIỮA VẬT PHẨM
══════════════════════════════════════════════ */
.item-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  justify-items: center;
}
.item-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 6px;
  background: var(--surf2);
  border-radius: var(--rs);
  padding: 10px 8px;
  border: 1px solid var(--bd);
  transition: border-color .15s;
  width: 100%;
  max-width: 220px;
  text-align: center;
}
.item-card:hover { border-color: var(--blue2); }
.item-icon {
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 6px;
  background: var(--surf3);
  flex-shrink: 0;
  image-rendering: pixelated;
  border: 1px solid var(--bd);
  margin-bottom: 2px;
}
.item-info {
  width: 100%;
}
.item-name-count {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  margin-bottom: 5px;
}
.item-name {
  font-weight: 800;
  color: var(--tx);
  font-size: 0.8rem;
  line-height: 1.2;
}
.item-count {
  font-size: 0.76rem;
  font-weight: 800;
  color: var(--blue2);
  background: var(--blu-lt);
  padding: 2px 6px;
  border-radius: 20px;
  display: inline-block;
}
.item-opts {
  font-size: 0.71rem;
  color: var(--tx3);
  line-height: 1.45;
  text-align: left;
  width: 100%;
  padding-left: 4px;
  margin-top: 4px;
  border-top: 1px dashed var(--bd);
  padding-top: 6px;
}
.item-opts b { color: var(--tx2); font-weight: 700; background: none; padding: 0; }
.no-detail  { color: var(--bd2); font-size: .85rem; }
.raw-detail { font-size: .72rem; color: var(--tx3); word-break: break-all; }
.share-sub-row td {
  background: rgba(13,95,163,.035);
  padding: 10px 12px;
}
.share-sub-row.is-hidden { display: none; }
.share-sub-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px;
}
.share-sub-item {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 10px;
  border: 1.5px solid var(--bd);
  border-radius: var(--rs);
  background: var(--surf);
  box-shadow: var(--sh2);
}
.share-sub-item.is-used-up {
  background: #eef1f4;
  filter: grayscale(1);
  opacity: .72;
}
.share-sub-line {
  display: block;
  min-width: 0;
  flex: 1;
  color: var(--indigo);
  font-family: 'JetBrains Mono', monospace;
  font-weight: 800;
  font-size: .78rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}

/* ══════════════════════════════════════════════
   TOAST — nằm ngoài #scaler, dùng viewport thật
══════════════════════════════════════════════ */
#toast {
  position: fixed;
  bottom: 32px;
  right: 32px;
  z-index: 99999;
  font-size: 1.5rem;
  padding: 21px 33px;
  border-radius: 18px;
  background: linear-gradient(135deg, var(--blue), var(--blue2));
  color: #fff;
  font-weight: 800;
  box-shadow: 0 6px 24px rgba(13,95,163,.4);
  border: 2px solid rgba(255,255,255,.25);
  display: none;
  max-width: 480px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  line-height: 1.2;
  /* QUAN TRỌNG: không bị ảnh hưởng bởi transform của #scaler */
  transform: none !important;
}
#toast.error {
  background: linear-gradient(135deg, var(--danger), #b02030);
}
#toast.show {
  display: block;
  animation: toastPop .22s ease;
}
@keyframes toastPop {
  from { transform: translateY(12px) scale(.92); opacity: 0; }
  to   { transform: translateY(0)    scale(1);   opacity: 1; }
}

/* ══════════════════════════════════════════════
   CONFIRM POPUP — nằm ngoài #scaler
   Dùng viewport thật, không bị ảnh hưởng transform
══════════════════════════════════════════════ */
.confirm-popup {
  position: fixed;
  /* căn giữa viewport thật */
  top: 50vh;
  left: 50vw;
  transform: translate(-50%, -50%);
  z-index: 99999;
  background: var(--surf);
  border: 2px solid var(--bd2);
  border-radius: var(--r);
  padding: 2rem 2.4rem;
  max-width: 460px;
  width: 90vw;
  box-shadow: 0 12px 40px rgba(0,0,0,.35);
  text-align: center;
  /* QUAN TRỌNG: reset lại transform context */
  will-change: auto;
}
.confirm-popup.show {
  display: block !important;
  animation: confirmPop .22s ease;
}
@keyframes confirmPop {
  from { transform: translate(-50%, -48%) scale(.9); opacity: 0; }
  to   { transform: translate(-50%, -50%) scale(1);  opacity: 1; }
}
/* overlay mờ phía sau popup */
#confirmOverlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.35);
  z-index: 99998;
  display: none;
}
#confirmOverlay.show { display: block; }

.confirm-popup .close-x {
  position: absolute;
  top: 12px;
  right: 18px;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
  color: var(--tx3);
  background: rgba(0,0,0,.06);
  border-radius: 50%;
  transition: all 0.2s;
  line-height: 1;
}
.confirm-popup .close-x:hover {
  background: var(--danger);
  color: #fff;
  transform: scale(1.1);
}
.confirm-popup h3 {
  color: #f4c542;
  font-size: 1.4rem;
  margin-bottom: 12px;
}
.confirm-msg-main {
  color: #2ecc71;
  font-weight: 700;
  font-size: 1.05rem;
}
.confirm-msg-warning {
  color: #e74c3c;
  font-weight: 700;
  display: block;
  margin-top: 10px;
  font-size: 0.95rem;
}
.confirm-btns {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 24px;
}
.confirm-btns .btn-sm {
  font-size: 0.9rem;
  padding: 8px 18px;
}
/* ══════════════════════════════════════════════ */
.empty-state { padding: 64px 20px; text-align: center; color: var(--tx3); }
.empty-state svg { width: 50px; height: 50px; margin: 0 auto 12px; display: block; opacity: .25; }
.empty-state p { font-size: 1rem; }

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--surf2); }
::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--tx3); }
</style>
</head>
<body>

<!-- TOAST & CONFIRM MODAL nằm ngoài #scaler để tránh bị ảnh hưởng transform -->
<div id="toast"></div>
<div id="confirmOverlay"></div>
<div id="confirmModal" class="confirm-popup" style="display:none;">
  <span class="close-x">&times;</span>
  <h3>⚠️ Xác Nhận Xóa</h3>
  <p id="confirmMsg">
    <span class="confirm-msg-main">Xóa Giftcode: "<span id="confirmCodeName"></span>"?</span>
    <span class="confirm-msg-warning">Không thể hoàn tác!</span>
  </p>
  <div class="confirm-btns">
    <button class="btn-sm btn-save" id="confirmCancelBtn">✖️ Hủy</button>
    <button class="btn-sm btn-del" id="confirmDeleteBtn">🗑️ Xóa</button>
  </div>
</div>

<div id="scaler">
<div class="W">

<!-- TOPBAR -->
<div class="top">
  <div class="ttit">
    <a class="tx-box" href="/sv1/admin/taocode">
      <svg class="tx-ic" viewBox="0 0 24 24" width="16" height="16" fill="none">
        <path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6z"
              stroke="white" stroke-width="1.6" stroke-linejoin="round"/>
      </svg>
      Tạo GiftCode Nhanh:
      <span style="font-family:'JetBrains Mono',monospace;font-size:.95em">
        <?= number_format($totalCodes) ?>
      </span>
    </a>
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

<div class="code-tabs">
  <a class="code-tab <?= $activeTab === 'player' ? 'active' : '' ?>" href="<?= giftcode_page_url('player', 1) ?>">CODE _ PLAYER</a>
  <a class="code-tab <?= $activeTab === 'share' ? 'active' : '' ?>" href="<?= giftcode_page_url('share', 1) ?>">CODE _ SHARE</a>
  <button type="button" class="code-tab code-tab-btn" onclick="loadGiftcode()">UPDATE_CODE</button>
</div>

<!-- TABLE -->
<div class="tc"><div class="tsc">
<table style="border-collapse:collapse;width:100%;table-layout:fixed">
<colgroup>
  <col style="width:5%">   <!-- ID -->
  <col style="width:12.5%">    <!-- Giftcode -->
  <col style="width:7.5%">   <!-- Lượt -->
  <col style="width:59%">    <!-- Nội Dung -->
  <col style="width:10%">    <!-- Mở TV -->
  <col style="width:6%">     <!-- Hành Động -->
</colgroup>
<thead>
  <tr>
    <th>ID</th>
    <th>Giftcode</th>
    <th>Lượt</th>
    <th>Nội Dung</th>
    <th>Type</th>
    <th>Hành Động</th>
  </tr>
</thead>
<tbody id="tbody">
  <?php if (empty($giftcodes)): ?>
  <tr><td colspan="6">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7"/>
        <path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/>
        <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/>
        <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/>
      </svg>
      <p>Chưa có Giftcode nào</p>
    </div>
  </td></tr>
  <?php else: ?>
  <?php $seenShareDetails = []; ?>
  <?php $shareSubRows = []; ?>
  <?php $shareGroupIndex = 0; $currentShareGroup = ''; ?>
  <?php foreach ($giftcodes as $gc):
    $countLeft  = intval($gc['count_left'] ?? 0);
    $typeVal    = intval($gc['type']       ?? 0);
    $badgeCls   = $countLeft <= 0 ? 'badge-count zero' : 'badge-count';
    $tvCls      = $typeVal === 0 ? 'badge-tv no-need' : 'badge-tv need';
    $tvLabel    = $typeVal === 2 ? '2 - Share' : ($typeVal === 1 ? '1 - VIP' : '0 - Player');
    $detailRaw  = (string)($gc['detail'] ?? '');
    $detailKey  = hash('sha256', $detailRaw);
    $hideDetail = $activeTab === 'share' && isset($seenShareDetails[$detailKey]);
    if ($activeTab === 'share') {
        $seenShareDetails[$detailKey] = true;
    }
    $compactShareRow = $hideDetail;
    $detailHtml = $compactShareRow ? '' : parseDetail($detailRaw, $itemTemplates, $optionTemplates);
    $codeVal    = htmlspecialchars($gc['code'] ?? '');
    $rowClasses = [];
    if ($countLeft <= 0) $rowClasses[] = 'row-used-up';
    if ($compactShareRow) $rowClasses[] = 'row-share-compact';
    if (!$compactShareRow && !empty($shareSubRows)) {
        renderShareSubRows($shareSubRows, $currentShareGroup);
        $shareSubRows = [];
    }
    if ($activeTab === 'share' && !$compactShareRow) {
        $currentShareGroup = 'share-group-' . (++$shareGroupIndex);
    }
    if ($compactShareRow) {
        $shareSubRows[] = [
            'id' => (int)($gc['id'] ?? 0),
            'code' => (string)($gc['code'] ?? ''),
            'count_left' => $countLeft,
        ];
        continue;
    }
  ?>
  <tr class="<?= htmlspecialchars(implode(' ', $rowClasses), ENT_QUOTES) ?>" data-id="<?= intval($gc['id']) ?>" data-code="<?= $codeVal ?>">

    <td><span class="cell-id"><?= intval($gc['id']) ?></span></td>

    <td>
      <span class="view-mode">
        <?php if ($activeTab === 'share'): ?>
        <span class="code-copy-wrap">
          <span class="code-badge"><?= $codeVal ?></span>
          <button type="button" class="copy-code-btn" data-copy-code="<?= $codeVal ?>" onclick="copyGiftcode(this)" title="Copy code" aria-label="Copy code <?= $codeVal ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="8" y="8" width="11" height="11" rx="2"></rect>
              <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
            </svg>
          </button>
        </span>
        <?php else: ?>
        <span class="code-badge"><?= $codeVal ?></span>
        <?php endif; ?>
      </span>
      <input class="editable-input edit-mode" style="display:none" name="code" value="<?= $codeVal ?>">
    </td>

    <td>
      <span class="view-mode"><span class="<?= $badgeCls ?>"><?= $countLeft ?></span></span>
      <input class="editable-input edit-mode" style="display:none" name="count_left" type="number" min="0" value="<?= $countLeft ?>">
    </td>

    <td style="text-align:left"><?= $detailHtml ?></td>

    <td>
      <?php if (!$compactShareRow): ?>
      <span class="view-mode">
        <span class="<?= $tvCls ?>">
          <span class="dot"></span><?= $tvLabel ?>
        </span>
      </span>
      <select class="editable-input edit-mode" style="display:none" name="type">
        <option value="0" <?= $typeVal === 0 ? 'selected' : '' ?>>0 - Player</option>
        <option value="1" <?= $typeVal === 1 ? 'selected' : '' ?>>1 - VIP</option>
        <option value="2" <?= $typeVal === 2 ? 'selected' : '' ?>>2 - Share</option>
      </select>
      <?php endif; ?>
    </td>

    <td>
      <?php if (!$compactShareRow): ?>
      <?php if ($activeTab === 'share'): ?>
      <div class="action-group view-mode">
        <button class="btn-sm btn-save" data-share-toggle="<?= htmlspecialchars($currentShareGroup, ENT_QUOTES) ?>" onclick="toggleShareGroup(this)">Hiện</button>
      </div>
      <?php else: ?>
      <!-- VIEW MODE -->
      <div class="action-group view-mode">
        <button class="btn-sm btn-edit" onclick="startEdit(this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>Sửa
        </button>
        <button class="btn-sm btn-del" onclick="deleteRow(this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
          </svg>Xóa
        </button>
      </div>
      <!-- EDIT MODE -->
      <div class="action-group edit-mode" style="display:none">
        <button class="btn-sm btn-save"   onclick="saveRow(this)">✔ Lưu</button>
        <button class="btn-sm btn-cancel" onclick="cancelEdit(this)">✕ Hủy</button>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </td>

  </tr>
  <?php endforeach; ?>
  <?php if (!empty($shareSubRows)) renderShareSubRows($shareSubRows, $currentShareGroup); ?>
  <?php endif; ?>
</tbody>
</table>
</div></div>

</div><!-- /W -->
</div><!-- /scaler -->

<script>
/* ══════════════════════════════════════════════
   URL gốc để POST — tránh redirect
══════════════════════════════════════════════ */
const ACTION_URL = <?= json_encode($currentUrl) ?>;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const CSRF_HEADERS = { 'X-CSRF-TOKEN': CSRF_TOKEN };

/* ══════════════════════════════════════════════
   VIEWPORT SCALER — 1500px cố định, chiếm 98vw
══════════════════════════════════════════════ */
(function () {
  var PAGE_W = 1500;
  var FILL   = 0.98;
  var scaler = document.getElementById('scaler');

  function applyScale() {
    var vw        = window.innerWidth;
    var avail     = vw * FILL;
    var ratio     = avail / PAGE_W;
    var leftOff   = (vw - avail) / 2;

    scaler.style.width           = PAGE_W + 'px';
    scaler.style.transform       = 'scale(' + ratio + ')';
    scaler.style.transformOrigin = 'top left';
    scaler.style.marginLeft      = leftOff + 'px';
    scaler.style.marginRight     = '0';

    document.body.style.minHeight = (scaler.offsetHeight * ratio) + 'px';
  }

  applyScale();
  window.addEventListener('resize', applyScale);
})();

/* ══════════════════════════════════════════════
   TOAST LOGIC — dùng class thay vì jQuery fadeIn
   để tránh bị ảnh hưởng bởi transform context
══════════════════════════════════════════════ */
let toastTimer = null;
function showToast(msg, isError) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.remove('error', 'show');
  if (isError) toast.classList.add('error');

  // force reflow để animation chạy lại
  void toast.offsetWidth;
  toast.classList.add('show');

  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.classList.remove('show');
  }, 2000);
}

/* ══════════════════════════════════════════════
   APP LOGIC
══════════════════════════════════════════════ */
let editModeRows = new Set();

function getRow(btn) { return btn.closest('tr'); }

function startEdit(btn) {
  const row = getRow(btn);
  if (editModeRows.has(row)) return;
  editModeRows.add(row);
  row.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.edit-mode').forEach(el => el.style.display = '');
  row.classList.add('row-editing');
}

function cancelEdit(btn) {
  const row = getRow(btn);
  if (!editModeRows.has(row)) return;
  editModeRows.delete(row);
  row.querySelectorAll('.view-mode').forEach(el => el.style.display = '');
  row.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'none');
  row.classList.remove('row-editing');
}

async function saveRow(btn) {
  btn.disabled    = true;
  btn.textContent = '…';

  const row       = getRow(btn);
  const id        = row.dataset.id;
  const codeInput = row.querySelector('input[name=code]');
  const newCode   = codeInput.value.trim();

  if (newCode.length < 6) {
    markError(codeInput, true);
    showToast('❌ Giftcode phải ít nhất 6 ký tự!', true);
    btn.disabled = false; btn.textContent = '✔ Lưu'; return;
  } else { markError(codeInput, false); }

  if (isDuplicateCode(row, newCode)) {
    markError(codeInput, true);
    showToast('❌ Giftcode đã tồn tại!', true);
    btn.disabled = false; btn.textContent = '✔ Lưu'; return;
  } else { markError(codeInput, false); }

  const count = row.querySelector('input[name=count_left]').value;
  const type  = row.querySelector('select[name=type]')?.value ?? '0';

  const fd = new FormData();
  fd.append('action',     'update');
  fd.append('id',         id);
  fd.append('code',       newCode);
  fd.append('count_left', count);
  fd.append('type',       type);

  try {
    const res  = await fetch(ACTION_URL, { method: 'POST', headers: CSRF_HEADERS, body: fd });
    const data = await res.json();
    if (data.status === 'success') {
      const displayName = newCode.length > 20 ? newCode.substring(0, 17)+'...' : newCode;
      showToast(`✅ Đã lưu Giftcode: ${displayName}`);
      setTimeout(() => location.reload(), 2800);
    } else {
      showToast('❌ ' + (data.message || 'Lỗi cập nhật'), true);
      btn.disabled = false; btn.textContent = '✔ Lưu';
    }
  } catch (e) {
    console.error('Save error:', e);
    showToast('❌ Lỗi kết nối', true);
    btn.disabled = false; btn.textContent = '✔ Lưu';
  }
}

function isDuplicateCode(currentRow, code) {
  code = (code || '').trim().toLowerCase();
  const rows = document.querySelectorAll('#tbody tr');
  for (const row of rows) {
    if (row === currentRow) continue;
    const input = row.querySelector('input[name=code]');
    if (!input) continue;
    const val = (input.value || '').trim().toLowerCase();
    if (val === code && code !== '') return true;
  }
  return false;
}

function markError(input, isError) {
  if (isError) input.classList.add('input-error');
  else         input.classList.remove('input-error');
}

async function copyGiftcode(btn) {
  const code = btn.dataset.copyCode || '';
  if (!code) return;

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(code);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = code;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      if (!document.execCommand('copy')) throw new Error('Copy command failed');
      textarea.remove();
    }

    btn.classList.add('is-copied');
    showToast(`Đã copy code: ${code}`);
    setTimeout(() => btn.classList.remove('is-copied'), 1200);
  } catch (error) {
    console.error('Copy error:', error);
    showToast('Không thể copy code', true);
  }
}

function toggleShareGroup(btn) {
  const groupId = btn.dataset.shareToggle;
  const rows = document.querySelectorAll(`[data-share-sub-group="${groupId}"]`);
  const shouldShow = Array.from(rows).some(row => row.classList.contains('is-hidden'));
  rows.forEach(row => row.classList.toggle('is-hidden', !shouldShow));
  btn.textContent = shouldShow ? 'Ẩn' : 'Hiện';
  btn.classList.toggle('btn-cancel', shouldShow);
  btn.classList.toggle('btn-save', !shouldShow);
}

/* ══════════════════════════════════════════════
   DELETE CONFIRM
══════════════════════════════════════════════ */
async function loadGiftcode() {
  const fd = new FormData();
  fd.append('action', 'load_giftcode');
  try {
    const res = await fetch(ACTION_URL, { method: 'POST', headers: CSRF_HEADERS, body: fd });
    const data = await res.json();
    if (data.status === 'success') {
      showToast('Đã Update Giftcode');
    } else {
      showToast(data.message || 'Không thể Update Giftcode', true);
    }
  } catch (e) {
    showToast('❌ Lỗi Update Giftcode', true);
  }
}

let deleteTargetRow = null;

function openConfirm() {
  document.getElementById('confirmOverlay').classList.add('show');
  const modal = document.getElementById('confirmModal');
  modal.style.display = 'block';
  modal.classList.add('show');
}

function closeConfirm() {
  document.getElementById('confirmOverlay').classList.remove('show');
  const modal = document.getElementById('confirmModal');
  modal.style.display = 'none';
  modal.classList.remove('show');
  deleteTargetRow = null;
}

function deleteRow(btn) {
  deleteTargetRow = getRow(btn);
  const codeName = deleteTargetRow.dataset.code || '???';
  document.getElementById('confirmCodeName').textContent = codeName;
  openConfirm();
}

document.querySelector('#confirmModal .close-x').addEventListener('click', closeConfirm);
document.getElementById('confirmCancelBtn').addEventListener('click', closeConfirm);
document.getElementById('confirmOverlay').addEventListener('click', closeConfirm);

document.getElementById('confirmDeleteBtn').addEventListener('click', async function () {
  if (!deleteTargetRow) return;
  const id = deleteTargetRow.dataset.id;
  const deletedCodeName = deleteTargetRow.dataset.code || '???';
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  closeConfirm();
  try {
    const res  = await fetch(ACTION_URL, { method: 'POST', headers: CSRF_HEADERS, body: fd });
    const data = await res.json();
    if (data.status === 'success') {
      showToast(`✅ Đã xóa GiftCode: "${deletedCodeName}"`);
      setTimeout(() => location.reload(), 2800);
    } else {
      showToast('❌ ' + (data.message || 'Lỗi xóa'), true);
    }
  } catch (e) {
    console.error('Delete error:', e);
    showToast('❌ Lỗi kết nối', true);
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeConfirm();
});
</script>
</body>
</html>
