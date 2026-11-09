<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

function ngoc_json_response(array $payload): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ngoc_shop_filter_sql(): string {
    return "icon_spec IN (0, 1, 9, 160)";
}

function ngoc_fetch_item_rows(PDO $pdo, int $tab_db_id): array {
    $stmt = $pdo->prepare("SELECT * FROM item_shop WHERE tab_id = ? AND " . ngoc_shop_filter_sql() . " ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$tab_db_id]);
    return $stmt->fetchAll();
}

function ngoc_fetch_options(PDO $pdo, int $item_shop_id): array {
    $stmt = $pdo->prepare("SELECT option_id, param FROM item_shop_option WHERE item_shop_id = ? ORDER BY id ASC");
    $stmt->execute([$item_shop_id]);
    $options = [];
    while ($row = $stmt->fetch()) {
        $options[] = ['id' => (int)$row['option_id'], 'param' => (int)$row['param']];
    }
    return $options;
}

function ngoc_get_items_json(PDO $pdo, int $tab_db_id): array {
    $items = [];
    foreach (ngoc_fetch_item_rows($pdo, $tab_db_id) as $row) {
        $items[] = [
            'item_shop_id' => (int)$row['id'],
            'temp_id' => (int)$row['temp_id'],
            'cost' => (int)$row['cost'],
            'type_sell' => (int)$row['type_sell'],
            'item_spec' => (int)$row['icon_spec'],
            'is_new' => false,
            'is_sell' => true,
            'options' => ngoc_fetch_options($pdo, (int)$row['id'])
        ];
    }
    return $items;
}

function ngoc_parse_options_raw(string $opt_raw): array {
    $options = [];
    if ($opt_raw === '') {
        return $options;
    }
    foreach (explode('|', $opt_raw) as $p) {
        $parts = explode(',', $p);
        if (count($parts) === 2) {
            $id = (int)trim($parts[0]);
            $param = (int)trim($parts[1]);
            if ($id >= 0) {
                $options[] = ['id' => $id, 'param' => $param];
            }
        }
    }
    return $options;
}

function ngoc_save_options(PDO $pdo, int $item_shop_id, array $options): void {
    $pdo->prepare("DELETE FROM item_shop_option WHERE item_shop_id = ?")->execute([$item_shop_id]);
    $stmt = $pdo->prepare("INSERT INTO item_shop_option (item_shop_id, option_id, param) VALUES (?, ?, ?)");
    foreach ($options as $option) {
        $stmt->execute([$item_shop_id, (int)$option['id'], (int)$option['param']]);
    }
}

function ngoc_reorder_items(PDO $pdo, int $tab_db_id, int $item_shop_id, int $target_index): void {
    $rows = ngoc_fetch_item_rows($pdo, $tab_db_id);
    $ids = array_map(fn($row) => (int)$row['id'], $rows);
    $ids = array_values(array_filter($ids, fn($id) => $id !== $item_shop_id));
    $target_index = max(0, min($target_index, count($ids)));
    array_splice($ids, $target_index, 0, [$item_shop_id]);
    $stmt = $pdo->prepare("UPDATE item_shop SET sort_order = ? WHERE id = ?");
    foreach ($ids as $idx => $id) {
        $stmt->execute([$idx + 1, $id]);
    }
}

function ngoc_delete_by_index(PDO $pdo, int $tab_db_id, int $index): bool {
    $rows = ngoc_fetch_item_rows($pdo, $tab_db_id);
    if (!isset($rows[$index])) {
        return false;
    }
    $id = (int)$rows[$index]['id'];
    $pdo->prepare("DELETE FROM item_shop_option WHERE item_shop_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM item_shop WHERE id = ?")->execute([$id]);
    return true;
}

function ngoc_save_by_index(PDO $pdo, int $tab_db_id, int $index, int $target_index, array $item, array $options): int {
    $rows = ngoc_fetch_item_rows($pdo, $tab_db_id);
    if ($index >= 0 && isset($rows[$index])) {
        $item_shop_id = (int)$rows[$index]['id'];
        $stmt = $pdo->prepare("UPDATE item_shop SET temp_id = ?, cost = ?, type_sell = ?, icon_spec = 0 WHERE id = ?");
        $stmt->execute([(int)$item['temp_id'], (int)$item['cost'], (int)$item['type_sell'], $item_shop_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO item_shop (temp_id, cost, type_sell, tab_id, icon_spec, sort_order) VALUES (?, ?, ?, ?, 0, 999999)");
        $stmt->execute([(int)$item['temp_id'], (int)$item['cost'], (int)$item['type_sell'], $tab_db_id]);
        $item_shop_id = (int)$pdo->lastInsertId();
    }
    ngoc_save_options($pdo, $item_shop_id, $options);
    ngoc_reorder_items($pdo, $tab_db_id, $item_shop_id, $target_index);
    return $item_shop_id;
}

function render_ngoc_tab_inner_html(PDO $pdo, int $tab_db_id): string {
    $item_templates = $item_names = [];
    $stmt = $pdo->query("SELECT id, NAME, icon_id FROM item_template ORDER BY id ASC");
    while ($t_row = $stmt->fetch()) {
        $item_templates[(int)$t_row['id']] = (int)$t_row['icon_id'];
        $item_names[(int)$t_row['id']]     = $t_row['NAME'];
    }

    $option_names = [];
    try {
        $stmt_opt = $pdo->query("SELECT id, name FROM item_option_template ORDER BY id ASC");
    } catch (PDOException $e) {
        $stmt_opt = $pdo->query("SELECT id, str AS name FROM item_option_template ORDER BY id ASC");
    }
    while ($o_row = $stmt_opt->fetch()) {
        $option_names[(int)$o_row['id']] = $o_row['name'];
    }

    $items_json = ngoc_get_items_json($pdo, $tab_db_id);

    ob_start();
    ?>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:12px;">
                    <button type="button" class="btn bteal btn-sm" onclick="updateShop(this)">UPDATE SHOP</button>
                    <button type="button" class="btn bsuccess btn-sm" onclick="openAdd(<?= $tab_db_id ?>)">+ THÊM VẬT PHẨM</button>
                </div>

                <?php if (is_array($items_json) && count($items_json)): ?>
                <div class="items-grid">
                    <?php foreach ($items_json as $index => $item):
                        $stt           = $index + 1;
                        $temp_id       = (int)($item['temp_id'] ?? 0);
                        $icon_id       = $item_templates[$temp_id] ?? 0;
                        $i_name        = $item_names[$temp_id] ?? 'Unknown';
                        $type_sell     = (int)($item['type_sell'] ?? 0);
                        $cost          = (int)($item['cost'] ?? 0);
                        $is_gold       = ($type_sell == 0);
                        $price_color   = $is_gold ? '#b45309' : '#0284c7';
                        $currency_icon = $is_gold ? 930 : 932;

                        $display_options = '';
                        $opt_raw_string  = '';
                        if (isset($item['options']) && is_array($item['options'])) {
                            $raw_parts = [];
                            foreach ($item['options'] as $option) {
                                $opt_id = (int)($option['id'] ?? 0);
                                $param  = (int)($option['param'] ?? 0);
                                $raw_parts[]      = $opt_id . ',' . $param;
                                $name_tpl         = $option_names[$opt_id] ?? 'ID ' . $opt_id;
                                $text             = str_replace('#', (string)$param, $name_tpl);
                                $display_options .= "<div>• " . htmlspecialchars($text) . "</div>";
                            }
                            $opt_raw_string = implode('|', $raw_parts);
                        }
                        $name_js = htmlspecialchars(json_encode($i_name, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        $opts_js = htmlspecialchars(json_encode($opt_raw_string, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="item-card"
                         ondblclick="openEdit(<?= $tab_db_id ?>, <?= $index ?>, <?= $temp_id ?>, <?= $name_js ?>, <?= $icon_id ?>, <?= $cost ?>, <?= $type_sell ?>, <?= $opts_js ?>, <?= $stt ?>)">
                        <div class="item-index-badge">#<?= $stt ?></div>
                        <div class="btn-delete-trigger"
                             onclick="event.stopPropagation(); triggerDelete(<?= $tab_db_id ?>, <?= $index ?>, <?= $name_js ?>)">&times;</div>
                        <div style="display:flex; gap:11px; margin-top:5px;">
                            <img src="/icon/<?= $icon_id ?>.png" style="width:38px; height:38px; object-fit:contain; image-rendering:pixelated;" onerror="this.src='/icon/0.png'">
                            <div style="flex:1">
                                <div style="font-size:.74rem; font-weight:800; color:var(--tx); margin-bottom:3px;"><?= htmlspecialchars($i_name) ?></div>
                                <div class="price-tag" style="color:<?= $price_color ?>">
                                    <?= number_format($cost) ?>
                                    <img src="/icon/<?= $currency_icon ?>.png" class="currency-img">
                                </div>
                            </div>
                        </div>
                        <div class="item-options-display">
                            <?= $display_options ?: "<span style='color:var(--tx3); font-style:italic;'>Trắng thông tin</span>" ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-tab">Tab này chưa có vật phẩm nào. Nhấn "+ THÊM VẬT PHẨM" để bắt đầu.</div>
                <?php endif; ?>
    <?php
    return ob_get_clean();
}

if (empty($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$account_id = (int)$_SESSION['account_id'];

$stmt = $pdo->prepare("SELECT id, is_admin FROM account WHERE id = ? LIMIT 1");
$stmt->execute([$account_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: /sv1/trangchu");
    exit;
}

if ((int)$user['is_admin'] !== 1) {
    echo "Không có quyền!";
    exit;
}

$csrfToken = ensure_csrf_token();

// ===== XỬ LÝ HÀNH ĐỘNG =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    if ($_POST['action'] === 'update_shop') {
        if (append_panel_command('LS')) {
            ngoc_json_response(['status' => 'success', 'message' => 'Đã gửi lệnh update shop sang game.']);
        }
        ngoc_json_response(['status' => 'error', 'message' => 'Không thể ghi lệnh update shop vào file điều khiển game.']);
    }

    $tab_db_id = (int)$_POST['tab_id'];
    $action = $_POST['action'];

    if ($action == 'delete') {
        $index = (int)$_POST['index'];
        ngoc_delete_by_index($pdo, $tab_db_id, $index);
    } elseif ($action == 'save') {
        $index   = isset($_POST['index']) ? (int)$_POST['index'] : -1;
        $new_stt = isset($_POST['stt'])   ? (int)$_POST['stt'] - 1 : $index;

        $newItem = [
            'temp_id'   => (int)$_POST['temp_id'],
            'cost'      => (int)$_POST['cost'],
            'type_sell' => (int)$_POST['type_sell'],
            'item_spec' => 0
        ];

        $options = ngoc_parse_options_raw($_POST['options_raw'] ?? '');
        if ($new_stt < 0) $new_stt = count(ngoc_fetch_item_rows($pdo, $tab_db_id));
        ngoc_save_by_index($pdo, $tab_db_id, $index, $new_stt, $newItem, $options);
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        ngoc_json_response([
            'status' => 'success',
            'tab_id' => $tab_db_id,
            'html' => render_ngoc_tab_inner_html($pdo, $tab_db_id)
        ]);
    }
    header("Location: " . current_public_path() . "?tab_id=" . $tab_db_id);
    exit;
}

// ===== LẤY DỮ LIỆU =====
$items_list = $item_templates = $item_names = [];
$stmt = $pdo->query("SELECT id, NAME, icon_id FROM item_template ORDER BY id ASC");
while ($t_row = $stmt->fetch()) {
    $items_list[]                 = $t_row;
    $item_templates[$t_row['id']] = $t_row['icon_id'];
    $item_names[$t_row['id']]     = $t_row['NAME'];
}

$option_list = $option_names = [];
try {
    $stmt_opt = $pdo->query("SELECT id, name FROM item_option_template ORDER BY id ASC");
} catch (PDOException $e) {
    $stmt_opt = $pdo->query("SELECT id, str AS name FROM item_option_template ORDER BY id ASC");
}
while ($o_row = $stmt_opt->fetch()) {
    $option_list[]               = $o_row;
    $option_names[$o_row['id']]  = $o_row['name'];
}

// ===== LẤY SHOP DATA =====
$sql = "SELECT ts.*, n.NAME AS npc_name, IF(n.avatar = 0, n.head, n.avatar) AS npc_icon 
        FROM tab_shop ts 
        LEFT JOIN shop s ON ts.shop_id = s.id 
        LEFT JOIN npc_template n ON s.npc_id = n.id 
        WHERE (ts.id BETWEEN 0 AND 23 OR ts.id = 25) 
        ORDER BY ts.shop_id ASC, ts.id ASC";
$result = $pdo->query($sql);
$grouped_shops = [];
while ($row = $result->fetch()) {
    $sid = $row['shop_id'];
    $row['tab_name'] = $row['NAME'] ?? ('Tab ' . $row['id']);
    $row['items'] = json_encode(ngoc_get_items_json($pdo, (int)$row['id']), JSON_UNESCAPED_UNICODE);
    if (!isset($grouped_shops[$sid])) {
        $grouped_shops[$sid] = [
            'npc_name' => $row['npc_name'] ?? 'Shop ' . $sid,
            'npc_icon' => $row['npc_icon'] ?? 0,
            'tabs'     => []
        ];
    }
    $grouped_shops[$sid]['tabs'][] = $row;
}

$url_tab_id = isset($_GET['tab_id']) ? (int)$_GET['tab_id'] : null;
$active_shop_id = null;
$active_tab_id  = null;
foreach ($grouped_shops as $sid => $data) {
    foreach ($data['tabs'] as $tab) {
        if ($url_tab_id !== null && $tab['id'] == $url_tab_id) {
            $active_shop_id = $sid;
            $active_tab_id  = $tab['id'];
        }
    }
}
if ($active_shop_id === null && !empty($grouped_shops)) {
    $active_shop_id = array_key_first($grouped_shops);
    $active_tab_id  = $grouped_shops[$active_shop_id]['tabs'][0]['id'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Quản Lý Shop Thường</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ── TOKENS ─────────────────────────────────── */
:root {
    --bg:     #ddeeff;
    --surf:   #ffffff;
    --surf2:  #f0f7ff;
    --surf3:  #e4f0fb;
    --bd:     #9bbedd;
    --bd2:    #7aaacf;
    --tx:     #09131f;
    --tx2:    #1a3a5c;
    --tx3:    #4a7a9b;
    --blue:   #0d5fa3;
    --blue2:  #1272c2;
    --indigo: #6c3fc7;
    --sky:    #1e8fd4;
    --teal:   #0a8a85;
    --success:#0a8a5a;
    --danger: #d92b3a;
    --warn:   #c47a00;
    --blu-lt: #cce3f8;
    --ind-lt: #e6deff;
    --sky-lt: #dbeeff;
    --glow:   rgba(13,95,163,.15);
    --r:  13px; --rs: 8px;
    --sh:  0 4px 24px rgba(13,95,163,.13);
    --sh2: 0 1px 8px rgba(13,95,163,.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    width: 100%;
    height: 100%;
    overflow-x: hidden;
    background: var(--bg);
    font-family: 'Plus Jakarta Sans', sans-serif;
    -webkit-font-smoothing: antialiased;
}

/* ── SCALE ROOT ────────── */
#scale-root {
    width: 1500px;
    transform-origin: top left;
    font-size: 14px;
    line-height: 1.6;
    color: var(--tx);
    background: var(--bg);
    min-height: 100vh;
    position: relative;
}

#scale-root::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background-image: radial-gradient(circle, var(--bd) 1px, transparent 1px);
    background-size: 22px 22px; opacity: .45;
}

.W {
    position: relative; z-index: 1;
    width: 1500px;
    padding: 14px 18px 28px;
}

/* ── TOPBAR ──────────────────────────────────── */
.top {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: var(--surf); border: 1px solid var(--bd2);
    border-radius: var(--r); padding: 16px 20px;
    margin-bottom: 12px; box-shadow: var(--sh);
    gap: 14px;
}

.title-icon-wrap {
    display: flex; align-items: center; gap: 16px; justify-content: center;
}
.currency-icon-frame {
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--blu-lt), var(--ind-lt));
    border: 2px solid var(--bd2); border-radius: var(--rs);
    padding: 8px 14px; gap: 8px; box-shadow: var(--sh2);
}
.currency-icon-frame img {
    width: 32px; height: 32px; object-fit: contain; image-rendering: pixelated;
}
.title-sep {
    font-size: 1.5rem; font-weight: 800; color: var(--bd2); line-height: 1;
}

.tr2 { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center; }

/* ── BUTTONS ─────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 15px; border: none; border-radius: var(--rs);
    font-family: inherit; font-size: .82rem; font-weight: 700;
    cursor: pointer; text-decoration: none; white-space: nowrap;
    transition: all .15s; letter-spacing: .2px; color: #fff;
    min-width: 110px; justify-content: center;
}
.btn:hover  { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.15); }
.btn:active { transform: scale(.96); }
.bblue   { background: linear-gradient(135deg, var(--blue2), var(--indigo)); box-shadow: 0 3px 12px var(--glow); }
.bteal   { background: linear-gradient(135deg, var(--teal), #0d9488); }
.bdanger { background: linear-gradient(135deg, var(--danger), #b91c1c); }
.bslate  { background: linear-gradient(135deg, var(--tx3), var(--tx2)); }
.bsuccess{ background: linear-gradient(135deg, var(--success), #059669); }

/* Nút nhỏ bên trong modal / grid — không ép min-width */
.btn-sm {
    min-width: unset !important;
}
button[onclick^="openAdd"] {
    font-size: .74rem;
    padding: 7px 14px;
    min-width: 99px !important;
}

/* ── TOPBAR BUTTONS CỐ ĐỊNH CHIỀU RỘNG ──────── */
.btn-top {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

/* ── OUTER FRAME ─────────────────────────────── */
.shop-frame {
    background: var(--surf); border: 2px solid var(--bd2);
    border-radius: var(--r); box-shadow: var(--sh);
    overflow: hidden;
}
.shop-frame-title {
    background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt));
    border-bottom: 2px solid var(--bd2);
    padding: 10px 18px;
    display: flex; align-items: center; gap: 10px;
    font-weight: 800; font-size: .95rem; color: var(--tx2);
}
.shop-frame-title .sf-icon { font-size: 1.1rem; }

/* ── NPC ROW ─────────────────────────────────── */
.npc-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    padding: 10px 14px;
    background: var(--surf2);
    border-bottom: 1.5px solid var(--bd2);
}

.npc-btn {
    background: var(--surf); border: 1.5px solid var(--bd);
    border-radius: var(--rs); padding: 8px 10px;
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: all .15s; font-family: inherit; text-align: left;
    min-width: 0;
}
.npc-btn:hover  { border-color: var(--blue2); background: var(--sky-lt); transform: translateY(-1px); box-shadow: var(--sh2); }
.npc-btn.active { border-color: var(--blue2); background: linear-gradient(135deg, var(--blu-lt), var(--ind-lt)); box-shadow: var(--sh2); }
.npc-btn img    { width: 30px; height: 30px; object-fit: contain; image-rendering: pixelated; flex-shrink: 0; }
.npc-btn-info   { min-width: 0; overflow: hidden; }
.npc-btn-id     { font-size: .6rem; font-weight: 800; color: var(--tx3); font-family: 'JetBrains Mono', monospace; }
.npc-btn-name   { font-size: .72rem; font-weight: 700; color: var(--tx2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── SHOP BODY ───────────────────────────────── */
.shop-body { padding: 14px 14px 24px; }
.shop-container { display: none; }
.shop-container.active { display: block; animation: fadeIn .2s ease; }

/* ── SUB-TAB BAR ─────────────────────────────── */
.subtab-bar {
    display: flex; gap: 6px; flex-wrap: wrap;
    margin-bottom: 14px;
    padding: 10px 14px;
    background: var(--surf3); border-radius: var(--rs);
    border: 1px solid var(--bd2);
}
.subtab-btn {
    padding: 6px 16px; border: 1.5px solid var(--bd2);
    border-radius: 999px; background: var(--surf);
    font-family: inherit; font-size: .78rem; font-weight: 700;
    color: var(--tx2); cursor: pointer; transition: all .15s;
    white-space: nowrap;
}
.subtab-btn:hover  { border-color: var(--blue2); color: var(--blue); background: var(--blu-lt); }
.subtab-btn.active {
    background: linear-gradient(135deg, var(--blue2), var(--indigo));
    border-color: transparent; color: #fff;
    box-shadow: 0 3px 10px var(--glow);
}

.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeIn .2s ease; }

/* ── ITEMS GRID ─────────────────────────────── */
.items-grid {
    display: grid;
    grid-template-columns: repeat(7, 200px);
    gap: calc((100% - 7 * 200px) / 6);
    width: 100%;
}

/* ── ITEM CARD ───────────────────────────────── */
.item-card {
    width: 200px;
    border: 1.5px solid var(--bd); border-radius: var(--rs); padding: 13px;
    background: var(--surf2); position: relative; cursor: pointer;
    transition: all .15s; min-height: 145px; display: flex; flex-direction: column;
    user-select: none;
}
.item-card:hover { border-color: var(--blue2); transform: translateY(-3px); box-shadow: var(--sh); }

.item-index-badge {
    position: absolute; top: 5px; left: 5px;
    background: linear-gradient(135deg, var(--indigo), var(--blue));
    color: #fff; font-size: .62rem; font-weight: 800;
    padding: 2px 7px; border-radius: 4px; z-index: 5;
    font-family: 'JetBrains Mono', monospace;
}
.btn-delete-trigger {
    position: absolute; top: -8px; right: -8px;
    background: linear-gradient(135deg, var(--danger), #b91c1c);
    color: white; width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: bold; border: 2px solid white; cursor: pointer;
    z-index: 10; box-shadow: 0 2px 8px rgba(217,43,58,.35);
    transition: transform .15s;
}
.btn-delete-trigger:hover { transform: scale(1.15); }
.price-tag { font-weight: 700; display: flex; align-items: center; gap: 4px; font-size: .86rem; }
.currency-img { width: 15px; height: 15px; object-fit: contain; }
.item-options-display {
    font-size: .71rem; color: var(--danger); font-weight: 600;
    margin-top: 8px; border-top: 1px dashed var(--bd);
    padding-top: 7px; line-height: 1.5;
}
#demo_options_list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 12px;
    row-gap: 2px;
}
#demo_options_list > * {
    min-width: 0;
    overflow-wrap: anywhere;
}
#demo_options_list > span:only-child { grid-column: 1 / -1; }
.empty-tab {
    text-align: center; padding: 32px; color: var(--tx3);
    font-style: italic; font-size: .88rem;
    background: var(--surf2); border-radius: var(--rs);
    border: 1.5px dashed var(--bd2);
}

/* ── PREVIEW BOX ─────────────────────────────── */
.preview-demo-box {
    background: var(--surf2); border: 2px dashed var(--blue2);
    border-radius: var(--r); padding: 15px; margin-bottom: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    position: relative; min-height: 240px;
}
.preview-label {
    position: absolute; top: -10px; left: 20px;
    background: linear-gradient(135deg, var(--blue2), var(--indigo));
    color: white; font-size: .66rem; padding: 2px 12px;
    border-radius: 10px; font-weight: 800; letter-spacing: .5px;
}
.demo-scale-stage {
    width: 100%;
    min-height: 212px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 1px;
}
.game-demo-card {
    width: 90%;
    max-width: none;
    pointer-events: none;
    border-color: var(--blue2) !important;
    box-shadow: var(--sh);
    transform: none;
}

/* ══════════════════════════════════════════════════
   OVERLAY CHUNG — dùng cho tất cả các modal
   ══════════════════════════════════════════════════ */
.overlay-blur {
    display: none;
    position: absolute;
    top: var(--edit-modal-top, 0px);
    left: 0;
    width: 1500px;
    height: var(--edit-modal-vh, 1000px);
    background: rgba(9,19,31,.65); z-index: 1000;
    backdrop-filter: blur(5px);
}

/* ══════════════════════════════════════════════════
   EDIT MODAL — 80vw × 90vh
   ══════════════════════════════════════════════════ */
#editModal {
    display: none;
    position: absolute;
    top: calc(var(--edit-modal-top, 0px) + (var(--edit-modal-vh, 1000px) - var(--edit-modal-h, 720px)) / 2);
    left: 50%;
    width: var(--edit-modal-w, 1224px);
    height: var(--edit-modal-h, 720px);
    transform: translateX(-50%);
    background: var(--surf);
    border-radius: var(--r);
    z-index: 1001;
    border: 1px solid var(--bd2);
    overflow: hidden;
    box-shadow: var(--sh);
    animation: zoomIn .25s cubic-bezier(.34,1.56,.64,1);
    flex-direction: column;
}
#editModal.open {
    display: flex;
}

.modal-header {
    background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt));
    border-bottom: 2px solid var(--bd2);
    padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.modal-header h3 { margin: 0; font-weight: 800; color: var(--tx); font-size: 1.1rem; letter-spacing: .4px; }
.modal-close-btn {
    background: transparent; border: none; color: var(--tx3);
    font-size: 1.55rem; cursor: pointer; transition: color .15s; line-height: 1;
}
.modal-close-btn:hover { color: var(--danger); }

.modal-body {
    padding: 18px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    font-size: .99em;
}
.modal-footer {
    padding: 11px 18px; background: var(--surf2);
    border-top: 1px solid var(--bd2); display: flex; gap: 10px; justify-content: flex-end;
    flex-shrink: 0;
}

/* ── FORM ─────────────────────────────────────── */
.edit-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
    align-items: stretch;
    margin-bottom: 8px;
}
.edit-fields-grid {
    display: grid;
    grid-template-columns: 1fr;
    grid-template-areas:
        "item item"
        "price pos"
        "currency currency";
    gap: 5px 10px;
}
.edit-fields-grid .form-row { display: contents; }
.edit-fields-grid .form-group { margin-bottom: 0; }
.edit-fields-grid .form-row:nth-of-type(1) .form-group:nth-child(1) { grid-area: item; }
.edit-fields-grid .form-row:nth-of-type(2) .form-group:nth-child(1) { grid-area: price; }
.edit-fields-grid .form-row:nth-of-type(1) .form-group:nth-child(2) { grid-area: pos; }
.edit-fields-grid .form-row:nth-of-type(2) .form-group:nth-child(2) { grid-area: currency; }
.form-row { display: grid; grid-template-columns: 1.5fr 1fr; gap: 16px; }
.form-group { margin-bottom: 0; }
.form-group label {
    display: block; font-size: .76rem; font-weight: 800; color: var(--tx3);
    margin-bottom: 2px; text-transform: uppercase; letter-spacing: .5px;
}
.custom-input {
    width: 100%; padding: 8px 11px;
    border: 1.5px solid var(--bd2); border-radius: var(--rs);
    font-size: .86rem; font-weight: 600; outline: none;
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--surf2); color: var(--tx);
    transition: border-color .15s, box-shadow .15s;
}
.custom-input:focus { border-color: var(--blue2); background: #fff; box-shadow: 0 0 0 3px rgba(13,95,163,.12); }
.select-trigger {
    background: var(--surf2); border: 2px dashed var(--bd2);
    padding: 10px; border-radius: var(--rs);
    cursor: pointer; text-align: center;
    font-weight: 700; color: var(--blue); transition: all .15s; font-size: .86rem;
}
.select-trigger:hover { border-color: var(--blue2); background: var(--blu-lt); }
.currency-selector-wrapper {
    display: flex; align-items: center; gap: 10px;
    background: var(--surf2); padding: 4px 11px;
    border-radius: var(--rs); border: 1.5px solid var(--bd2);
}
.currency-selector-wrapper img { width: 20px; height: 20px; object-fit: contain; }
.currency-selector-wrapper select {
    border: none; background: transparent; flex: 1;
    font-weight: 700; outline: none; padding: 6px 0; cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif; color: var(--tx); font-size: .86rem;
}

/* ── OPTION CARDS ─────────────────────────────── */
.opt-container-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 5px; border-bottom: 2px solid var(--blu-lt); padding-bottom: 4px;
}
.opt-container-header label { font-size: .84rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; }
.opt-card {
    display: flex; align-items: center; gap: 10px;
    background: var(--surf2); padding: 9px 13px;
    border-radius: var(--rs); border: 1px solid var(--bd);
    margin-bottom: 0; min-width: 0; transition: border-color .15s;
}
#editModal #option_container {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px 10px;
}
.opt-card:hover { border-color: var(--blue2); }
.opt-card .opt-name { flex: 1; min-width: 0; font-size: .86rem; font-weight: 600; color: var(--tx); }
.opt-card .opt-name small { color: var(--tx3); display: block; }
.opt-card input {
    width: 88px; border: 1.5px solid var(--bd2); border-radius: 6px;
    padding: 5px; text-align: center; font-weight: 800;
    font-size: .9rem; outline: none; font-family: 'JetBrains Mono', monospace; background: var(--surf);
}
.opt-card input:focus { border-color: var(--blue2); box-shadow: 0 0 0 2px rgba(13,95,163,.1); }
.opt-card input.is-special-id { color: var(--danger); }
.opt-card input:not(.is-special-id) { color: var(--tx); }
.opt-remove-btn {
    background: none; border: none; color: var(--danger);
    font-size: 1.3rem; cursor: pointer; transition: transform .15s; line-height: 1;
}
.opt-remove-btn:hover { transform: scale(1.2); }


/* DELETE CONFIRM MODAL
   ══════════════════════════════════════════════════ */
#deleteConfirmModal {
    display: none; position: fixed; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: var(--surf); width: 380px; border-radius: var(--r);
    z-index: 5000; padding: 28px; text-align: center;
    box-shadow: var(--sh); border-top: 5px solid var(--danger);
    border: 1px solid var(--bd2);
}
#deleteConfirmModal .del-icon { font-size: 44px; margin-bottom: 10px; }
#deleteConfirmModal h3 { margin: 0; color: var(--tx); font-size: 1.1rem; }
#deleteConfirmModal p { color: var(--tx3); margin: 10px 0; font-size: .92rem; }
#deleteConfirmModal .del-btns { display: flex; gap: 10px; margin-top: 16px; }
#deleteConfirmModal .del-btns .btn { flex: 1; justify-content: center; }

/* ══════════════════════════════════════════════════
   PICKER MODALS — Hiển thị ALL item, scroll dọc
   ══════════════════════════════════════════════════ */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(9,19,31,.75); z-index: 2000;
    justify-content: center; align-items: center;
    backdrop-filter: blur(6px);
}
.modal-overlay.open { display: flex; }

.picker-scale-root {
    width: 1500px;
    max-height: 950px;
    transform-origin: center center;
    background: var(--surf);
    border-radius: var(--r);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(9,19,31,.55);
    border: 2px solid var(--bd2);
    animation: pickerZoom .22s cubic-bezier(.34,1.4,.64,1);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 16.8px;
    color: var(--tx);
    line-height: 1.6;
    position: relative;
    flex-shrink: 0;
}

.modal-content-header {
    padding: 14px 18px; border-bottom: 2px solid var(--bd2);
    display: flex; gap: 12px; align-items: center;
    background: linear-gradient(90deg, var(--blu-lt), var(--surf2));
    flex-shrink: 0;
}
.modal-content-header b { font-size: 1.14rem; font-weight: 800; color: var(--tx); white-space: nowrap; }
.modal-content-header input {
    flex: 1; padding: 9px 14px; border-radius: var(--rs);
    border: 1.5px solid var(--bd2); outline: none;
    font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.06rem;
    background: var(--surf); color: var(--tx); transition: border-color .15s;
}
.modal-content-header input:focus { border-color: var(--blue2); box-shadow: 0 0 0 3px rgba(13,95,163,.12); }

.icon-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 7px;
    padding: 12px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    min-height: 0;
    max-height: 820px;
    background: var(--surf2);
}

.icon-box {
    border: 1.5px solid var(--bd); padding: 10px 8px;
    text-align: center; cursor: pointer; border-radius: var(--rs);
    background: var(--surf); transition: all .15s;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 5px; overflow: hidden; min-height: 90px; min-width: 0;
    user-select: none;
}
.icon-box:hover {
    border-color: var(--blue2); background: var(--sky-lt);
    transform: translateY(-2px); box-shadow: var(--sh2);
    z-index: 2; position: relative;
}
.icon-box.pending-select {
    border-color: var(--warn);
    background: #fff8e1;
    box-shadow: 0 0 0 3px rgba(196,122,0,.25);
}
.icon-box img {
    width: 43px; height: 43px; object-fit: contain;
    image-rendering: pixelated; flex-shrink: 0;
}
.icon-box .ib-name {
    font-size: .77rem; font-weight: 700; color: var(--tx);
    overflow: hidden; line-height: 1.3; text-align: center;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    word-break: break-word;
}
.icon-box code {
    font-size: .66rem; color: var(--indigo);
    font-family: 'JetBrains Mono', monospace; white-space: nowrap;
}

#optPickerModal .icon-box {
    text-align: left;
    align-items: flex-start;
    padding: 10px 12px;
    gap: 4px;
}
#optPickerModal .icon-box b {
    font-size: .72rem; color: var(--indigo);
    font-family: 'JetBrains Mono', monospace; display: block; line-height: 1.2;
}
#optPickerModal .icon-box span {
    font-size: .86rem; font-weight: 700; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;
    overflow: hidden; word-break: break-word;
}
#optPickerModal .icon-box.special-opt b {
    color: var(--danger);
}
#optPickerModal .icon-box.special-opt {
    border-color: rgba(217,43,58,.35);
    background: #fff5f5;
}
#optPickerModal .icon-box.special-opt:hover {
    background: #ffe4e4;
    border-color: var(--danger);
}

/* ── SCROLLBAR ───────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--surf2); }
::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--tx3); }

/* ── TOAST ───────────────────────────────────── */
#toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    font-size: 1.4rem; padding: 16px 26px; border-radius: var(--r);
    background: linear-gradient(135deg, var(--blue2), var(--indigo));
    color: #fff; font-weight: 800;
    box-shadow: 0 10px 30px rgba(13,95,163,.35);
    border: 2px solid rgba(255,255,255,.25);
    opacity: 0; transform: translateY(80px) scale(.9);
    transition: all .25s ease; pointer-events: none;
}
#toast.show { transform: translateY(0) scale(1.08); opacity: 1; }

/* ── ANIMATIONS ──────────────────────────────── */
@keyframes fadeIn    { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
@keyframes zoomIn    { from { opacity:0; transform:translateX(-50%) scale(.96); } to { opacity:1; transform:translateX(-50%) scale(1); } }
@keyframes pickerZoom{ from { opacity:0; transform:scale(.93); } to { opacity:1; transform:scale(var(--picker-scale,1)); } }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════
     ITEM PICKER MODAL
     CHANGED: hiện tất cả + scroll, bỏ phân trang
     CHANGED: double-click mới chọn (click lần 1 highlight, lần 2 chọn)
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="itemPickerModal">
    <div class="picker-scale-root" id="itemPickerScaleRoot">
        <div class="modal-content-header">
            <b>📦 CHỌN VẬT PHẨM</b>
            <input type="text" id="modalSearch" placeholder="🔍 Tìm theo tên hoặc ID…" oninput="itemPickerFilter()">
            <button class="btn bslate btn-sm" onclick="closePicker('itemPickerModal')">✕ ĐÓNG</button>
        </div>
        <div class="icon-grid" id="modalGrid"></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     OPTION PICKER MODAL
     CHANGED: hiện tất cả + scroll, bỏ phân trang
     CHANGED: double-click mới chọn
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="optPickerModal" style="z-index:3000">
    <div class="picker-scale-root" id="optPickerScaleRoot">
        <div class="modal-content-header">
            <b>⚡ CHỌN CHỈ SỐ THUỘC TÍNH</b>
            <input type="text" id="optSearch" placeholder="🔍 Tìm tên chỉ số…" oninput="optPickerFilter()">
            <button class="btn bslate btn-sm" onclick="closePicker('optPickerModal')">← QUAY LẠI</button>
        </div>
        <div class="icon-grid" id="optGrid"></div>
    </div>
</div>

<!-- ── DELETE CONFIRM ────────────────────────── -->
<div id="deleteConfirmModal">
    <div class="del-icon">🗑️</div>
    <h3>Xác nhận xóa?</h3>
    <p id="deleteMsg">Bạn có chắc muốn xóa vật phẩm này khỏi shop?</p>
    <div class="del-btns">
        <button id="btnConfirmDelete" class="btn bdanger btn-sm">XÓA NGAY</button>
        <button class="btn bslate btn-sm" onclick="closeDeleteModal()">HỦY BỎ</button>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="tab_id" id="del_tab_id">
    <input type="hidden" name="index"  id="del_index">
</form>

<!-- ── OVERLAY BLUR ──────────────────────────── -->


<!-- ── EDIT MODAL — 80vw × 90vh ─────────────── -->
<div id="scale-root">
<!-- SCALE ROOT -->

<div class="overlay-blur"></div>

<div id="editModal">
    <div class="modal-header">
        <h3 id="modalTitle">SỬA VẬT PHẨM</h3>
        <button class="modal-close-btn" onclick="closeModal()">✕</button>
    </div>
    <form method="POST" id="editForm" onsubmit="return handleFormSubmit(event)" style="display:contents;">
        <div class="modal-body">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="tab_id" id="form_tab_id">
            <input type="hidden" name="index" id="form_index">

            <div class="edit-main-grid">
                <div class="preview-demo-box">
                    <span class="preview-label">DEMO HIỂN THỊ TRONG GAME</span>
                    <div class="demo-scale-stage">
                        <div class="item-card game-demo-card">
                            <div class="item-index-badge" id="demo_stt">#1</div>
                            <div style="display:flex; gap:12px; margin-top:5px;">
                                <img id="demo_icon" src="/icon/0.png" style="width:38px; height:38px; object-fit:contain; image-rendering:pixelated;">
                                <div style="flex:1">
                                    <div id="demo_name" style="font-size:.74rem; font-weight:800; color:var(--tx); margin-bottom:3px;">Vui lòng chọn vật phẩm</div>
                                    <div class="price-tag" id="demo_price_tag">
                                        <span id="demo_cost">0</span>
                                        <img id="demo_currency_img" src="/icon/930.png" class="currency-img">
                                    </div>
                                </div>
                            </div>
                            <div class="item-options-display" id="demo_options_list">
                                <span style="color:var(--tx3); font-style:italic;">Trắng thông tin</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="edit-fields-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label>(Vật phẩm)</label>
                            <input type="hidden" name="temp_id" id="form_temp_id" required>
                            <div class="select-trigger" onclick="openPicker('itemPickerModal')" id="item_display"
                                 style="min-height:74px; display:flex; align-items:center; justify-content:center;"
                                 title="Nhấn để chọn vật phẩm">
                                <span style="opacity:.6" id="item_placeholder">⚡ Nhấn để chọn ITEM...</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>(Vị trí)</label>
                            <input type="number" name="stt" id="form_stt" class="custom-input" placeholder="Nhập vị trí" oninput="updateDemo()" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Giá</label>
                            <input type="number" name="cost" id="form_cost" class="custom-input" placeholder="Số tiền" oninput="updateDemo()" required>
                        </div>
                        <div class="form-group">
                            <label>Tiền Tệ</label>
                            <div class="currency-selector-wrapper">
                                <img id="preview_currency" src="/icon/930.png">
                                <select name="type_sell" id="form_type" onchange="updateCurrencyIcon(this.value); updateDemo();">
                                    <option value="0">Vàng</option>
                                    <option value="1">Ngọc</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="opt-container-header">
                <label>Chỉ số</label>
                <button type="button" class="btn bsuccess btn-sm" style="padding:5px 11px; font-size:.77rem;" onclick="openPicker('optPickerModal')" title="Thêm chỉ số">+ THÊM DÒNG</button>
            </div>
            <div id="option_container"
                 style="background:var(--surf2); padding:12px; border-radius:var(--rs); min-height:80px; border:1px solid var(--bd2);">
            </div>
            <input type="hidden" name="options_raw" id="form_options">
        </div>

        <div class="modal-footer">
            <button type="button" class="btn bslate btn-sm" onclick="closeModal()">HỦY BỎ</button>
            <button type="submit" class="btn bblue btn-sm" style="padding-left:26px; padding-right:26px;">💾 LƯU THAY ĐỔI</button>
        </div>
    </form>
</div>

<div class="W">

    <!-- TOPBAR -->
    <div class="top">
        <div class="title-icon-wrap">
            <div class="currency-icon-frame">
                <img src="/icon/930.png" alt="Vàng" onerror="this.src='/icon/0.png'">
                <span style="font-size:.78rem; font-weight:800; color:var(--tx2);">Vàng</span>
            </div>
            <div class="title-sep">&amp;</div>
            <div class="currency-icon-frame">
                <img src="/icon/932.png" alt="Ngọc" onerror="this.src='/icon/0.png'">
                <span style="font-size:.78rem; font-weight:800; color:var(--tx2);">Ngọc</span>
            </div>
        </div>

        <div class="tr2">
            <!-- CHANGED: thêm class btn-top để đồng bộ kích thước -->
            <a href="/sv1/admin/panel" class="btn bblue btn-top">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                    <path d="M3 10.5L12 3L21 10.5V20A1 1 0 0 1 20 21H15V14H9V21H4A1 1 0 0 1 3 20V10.5Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                <span>Panel</span>
            </a>
            <a href="/sv1/admin/itemshop" class="btn bslate btn-top">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                    <path d="M19 12H5M12 19l-7-7 7-7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Quay Lại</span>
            </a>
            <button class="btn bteal btn-top" onclick="location.reload()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                    <path d="M20 12A8 8 0 1 1 17.3 6M20 4V12H12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Làm Mới</span>
            </button>
        </div>
    </div>

    <!-- OUTER FRAME -->
    <div class="shop-frame">
        <div class="shop-frame-title">
            <span class="sf-icon">🏪</span>
            Tìm Shop — Ấn 2 Lần Để Edit
        </div>

        <!-- NPC ROW -->
        <div class="npc-row" id="npcRow">
            <?php foreach ($grouped_shops as $sid => $data): ?>
            <button class="npc-btn <?= $sid == $active_shop_id ? 'active' : '' ?>"
                    onclick="showShop(<?= $sid ?>)"
                    id="npc-btn-<?= $sid ?>">
                <img src="/icon/<?= $data['npc_icon'] ?>.png" onerror="this.src='/icon/0.png'">
                <div class="npc-btn-info">
                    <div class="npc-btn-id">ID: <?= $sid ?></div>
                    <div class="npc-btn-name"><?= htmlspecialchars($data['npc_name']) ?></div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- SHOP CONTAINERS -->
        <?php foreach ($grouped_shops as $sid => $data): ?>
        <div class="shop-body shop-container <?= $sid == $active_shop_id ? 'active' : '' ?>"
             id="shop-group-<?= $sid ?>">

            <?php if (count($data['tabs']) > 1): ?>
            <div class="subtab-bar">
                <?php foreach ($data['tabs'] as $tab):
                    $is_active_tab = ($tab['id'] == $active_tab_id && $sid == $active_shop_id);
                ?>
                <button class="subtab-btn <?= $is_active_tab ? 'active' : '' ?>"
                        onclick="showTab(<?= $sid ?>, <?= $tab['id'] ?>)"
                        id="subtab-<?= $tab['id'] ?>">
                    <?= htmlspecialchars($tab['tab_name']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($data['tabs'] as $tidx => $tab_data):
                $tab_db_id  = $tab_data['id'];
                $items_json = json_decode($tab_data['items'], true);
                $is_first_tab = ($tidx === 0);
                $is_active_tab = ($tab_db_id == $active_tab_id && $sid == $active_shop_id) || ($active_tab_id === null && $is_first_tab && $sid == $active_shop_id);
            ?>
            <div class="tab-content <?= $is_active_tab ? 'active' : '' ?>"
                 id="tab-content-<?= $tab_db_id ?>">

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:12px;">
                    <button type="button" class="btn bteal btn-sm" onclick="updateShop(this)">UPDATE SHOP</button>
                    <button type="button" class="btn bsuccess btn-sm" onclick="openAdd(<?= $tab_db_id ?>)">+ THÊM VẬT PHẨM</button>
                </div>

                <?php if (is_array($items_json) && count($items_json)): ?>
                <div class="items-grid">
                    <?php foreach ($items_json as $index => $item):
                        $stt           = $index + 1;
                        $temp_id       = $item['temp_id'];
                        $icon_id       = $item_templates[$temp_id] ?? 0;
                        $i_name        = $item_names[$temp_id] ?? 'Unknown';
                        $is_gold       = ($item['type_sell'] == 0);
                        $price_color   = $is_gold ? '#b45309' : '#0284c7';
                        $currency_icon = $is_gold ? 930 : 932;

                        $display_options = '';
                        $opt_raw_string  = '';
                        if (isset($item['options']) && is_array($item['options'])) {
                            $raw_parts = [];
                            foreach ($item['options'] as $option) {
                                $raw_parts[]      = $option['id'] . ',' . $option['param'];
                                $name_tpl         = $option_names[$option['id']] ?? 'ID ' . $option['id'];
                                $text             = str_replace('#', $option['param'], $name_tpl);
                                $display_options .= "<div>• {$text}</div>";
                            }
                            $opt_raw_string = implode('|', $raw_parts);
                        }
                    ?>
                    <div class="item-card"
                         ondblclick="openEdit(<?= $tab_db_id ?>, <?= $index ?>, <?= $temp_id ?>, <?= htmlspecialchars(json_encode($i_name, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= $icon_id ?>, <?= $item['cost'] ?>, <?= $item['type_sell'] ?>, <?= htmlspecialchars(json_encode($opt_raw_string, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= $stt ?>)">
                        <div class="item-index-badge">#<?= $stt ?></div>
                        <div class="btn-delete-trigger"
                             onclick="event.stopPropagation(); triggerDelete(<?= $tab_db_id ?>, <?= $index ?>, <?= htmlspecialchars(json_encode($i_name, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">&times;</div>
                        <div style="display:flex; gap:11px; margin-top:5px;">
                            <img src="/icon/<?= $icon_id ?>.png" style="width:38px; height:38px; object-fit:contain; image-rendering:pixelated;" onerror="this.src='/icon/0.png'">
                            <div style="flex:1">
                                <div style="font-size:.74rem; font-weight:800; color:var(--tx); margin-bottom:3px;"><?= htmlspecialchars($i_name) ?></div>
                                <div class="price-tag" style="color:<?= $price_color ?>">
                                    <?= number_format($item['cost']) ?>
                                    <img src="/icon/<?= $currency_icon ?>.png" class="currency-img">
                                </div>
                            </div>
                        </div>
                        <div class="item-options-display">
                            <?= $display_options ?: "<span style='color:var(--tx3); font-style:italic;'>Trắng thông tin</span>" ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-tab">Tab này chưa có vật phẩm nào. Nhấn "+ THÊM VẬT PHẨM" để bắt đầu.</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

    </div><!-- /shop-frame -->

</div><!-- /W -->
</div><!-- /scale-root -->

<div id="toast"></div>

<script>
/* ══════════════════════════════════════════════════
   SPECIAL IDS — màu đỏ trong option picker & form
   ══════════════════════════════════════════════════ */
const SPECIAL_OPT_IDS = new Set([0,6,7,10,11,12,13,14,210,160,156,30,50,77,101,103,106]);

/* ══════════════════════════════════════════════════
   PAGE SCALE ROOT
   ══════════════════════════════════════════════════ */
const DESIGN_WIDTH = 1500;
const root = document.getElementById('scale-root');
let pageScaleRatio = 1;

function syncEditModalCanvas() {
    if (!root || !pageScaleRatio) return;
    const ratio = pageScaleRatio;
    const viewportTop = window.scrollY / ratio;
    const viewportH   = window.innerHeight / ratio;
    const modalW      = (window.innerWidth * 0.80) / ratio;
    const modalH      = (window.innerHeight * 0.80) / ratio;
    const minRootH    = viewportTop + viewportH;

    root.style.setProperty('--edit-modal-top', viewportTop + 'px');
    root.style.setProperty('--edit-modal-vh', viewportH + 'px');
    root.style.setProperty('--edit-modal-w', modalW + 'px');
    root.style.setProperty('--edit-modal-h', modalH + 'px');

    if (root.offsetHeight < minRootH) {
        root.style.minHeight = minRootH + 'px';
    }
    document.body.style.height = (root.offsetHeight * ratio) + 'px';
}

function applyScale() {
    const vw    = window.innerWidth;
    const ratio = (vw * 0.98) / DESIGN_WIDTH;
    pageScaleRatio = ratio;
    root.style.width = DESIGN_WIDTH + 'px';
    const content = root.querySelector('.W');
    const contentH = content ? content.offsetHeight : root.scrollHeight;
    root.style.minHeight = Math.max(contentH, window.innerHeight / ratio) + 'px';
    root.style.transform       = `scale(${ratio})`;
    root.style.transformOrigin = 'top left';
    root.style.marginLeft      = '1vw';
    document.body.style.width  = '100%';
    syncEditModalCanvas();
    document.body.style.height = (root.offsetHeight * ratio) + 'px';
}
applyScale();
window.addEventListener('resize', () => { applyScale(); applyPickerScale(); });
window.addEventListener('scroll', syncEditModalCanvas);

/* ══════════════════════════════════════════════════
   PICKER SCALE
   ══════════════════════════════════════════════════ */
const PICKER_W = 1500;
const PICKER_H = 950;

function applyPickerScale() {
    const vw    = window.innerWidth;
    const vh    = window.innerHeight;
    const ratio = Math.min(vw * 0.80 / PICKER_W, vh * 0.90 / PICKER_H);

    ['itemPickerScaleRoot', 'optPickerScaleRoot'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.transform       = `scale(${ratio})`;
        el.style.transformOrigin = 'center center';
        el.style.setProperty('--picker-scale', ratio);
    });
}
applyPickerScale();

/* ══════════════════════════════════════════════════
   DATA TỪ PHP
   ══════════════════════════════════════════════════ */
const ITEMS_LIST     = <?= json_encode(array_values($items_list), JSON_UNESCAPED_UNICODE) ?>;
const OPTION_LIST    = <?= json_encode(array_values($option_list), JSON_UNESCAPED_UNICODE) ?>;
const OPTION_NAMES_MAP = <?= json_encode($option_names, JSON_UNESCAPED_UNICODE) ?>;

/* ══════════════════════════════════════════════════
   ITEM PICKER
   CHANGED: hiện tất cả, bỏ phân trang
   CHANGED: double-click mới chọn (click 1 → pending, click 2 → confirm)
   ══════════════════════════════════════════════════ */
let itemPickerQuery = '';
let _pendingItemEl  = null;

function itemPickerFilter() {
    itemPickerQuery = document.getElementById('modalSearch').value.toLowerCase().trim();
    _pendingItemEl  = null;
    renderItemPicker();
}

function getFilteredItems() {
    if (!itemPickerQuery) return ITEMS_LIST;
    return ITEMS_LIST.filter(it =>
        it.NAME.toLowerCase().includes(itemPickerQuery) ||
        String(it.id).includes(itemPickerQuery)
    );
}

function renderItemPicker() {
    const filtered = getFilteredItems();
    let html = '';
    filtered.forEach(it => {
        const name = it.NAME.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        html += `<div class="icon-box"
                      data-item-id="${it.id}"
                      data-item-name="${name}"
                      data-item-icon="${it.icon_id}"
                      onclick="itemPickerClick(this, ${it.id}, '${name}', ${it.icon_id})">
                    <img src="/icon/${it.icon_id}.png" loading="lazy" onerror="this.src='/icon/0.png'">
                    <div class="ib-name">${it.NAME}</div>
                    <code>ID: ${it.id}</code>
                 </div>`;
    });
    document.getElementById('modalGrid').innerHTML = html;
}

function itemPickerClick(el, id, name, icon) {
    if (_pendingItemEl && _pendingItemEl !== el) {
        _pendingItemEl.classList.remove('pending-select');
    }
    if (_pendingItemEl === el) {
        confirmSelectItem(id, name, icon);
        _pendingItemEl = null;
    } else {
        el.classList.add('pending-select');
        _pendingItemEl = el;
    }
}

function confirmSelectItem(id, name, icon) {
    document.getElementById('form_temp_id').value = id;
    document.getElementById('item_display').innerHTML = `
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; min-height:64px;">
            <img src="/icon/${icon}.png" width="34" id="selected_item_icon_img" style="image-rendering:pixelated;">
            <span class="selected-item-text" style="font-weight:700; color:var(--tx2);">${name}
                <span style="color:var(--indigo); font-family:'JetBrains Mono',monospace; font-size:.78rem;">(ID: ${id})</span>
            </span>
        </div>`;
    closePicker('itemPickerModal');
    updateDemo();
}

/* ══════════════════════════════════════════════════
   OPTION PICKER
   CHANGED: hiện tất cả, bỏ phân trang
   CHANGED: double-click mới chọn
   CHANGED: ID đặc biệt → class special-opt (viền đỏ)
   ══════════════════════════════════════════════════ */
let optPickerQuery = '';
let _pendingOptEl  = null;

function optPickerFilter() {
    optPickerQuery = document.getElementById('optSearch').value.toLowerCase().trim();
    _pendingOptEl  = null;
    renderOptPicker();
}

function getFilteredOpts() {
    if (!optPickerQuery) return OPTION_LIST;
    return OPTION_LIST.filter(o =>
        o.name.toLowerCase().includes(optPickerQuery) ||
        String(o.id).includes(optPickerQuery)
    );
}

function renderOptPicker() {
    const filtered = getFilteredOpts();
    let html = '';
    filtered.forEach(o => {
        const name      = o.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const isSpecial = SPECIAL_OPT_IDS.has(Number(o.id));
        const specClass = isSpecial ? ' special-opt' : '';
        html += `<div class="icon-box${specClass}"
                      data-opt-id="${o.id}"
                      data-opt-name="${name}"
                      onclick="optPickerClick(this, ${o.id}, '${name}')">
                    <b>ID ${o.id}</b>
                    <span>${o.name}</span>
                 </div>`;
    });
    document.getElementById('optGrid').innerHTML = html;
}

function optPickerClick(el, id, name) {
    if (_pendingOptEl && _pendingOptEl !== el) {
        _pendingOptEl.classList.remove('pending-select');
    }
    if (_pendingOptEl === el) {
        addOptionRow(id, name, 0);
        _pendingOptEl = null;
    } else {
        el.classList.add('pending-select');
        _pendingOptEl = el;
    }
}

/* Render ngay khi trang load */
renderItemPicker();
renderOptPicker();

/* ══════════════════════════════════════════════════
   SHOP / TAB TOGGLE
   ══════════════════════════════════════════════════ */
function showShop(sid) {
    document.querySelectorAll('.shop-container').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.npc-btn').forEach(b => b.classList.remove('active'));
    const shopEl = document.getElementById('shop-group-' + sid);
    const npcBtn = document.getElementById('npc-btn-' + sid);
    if (!shopEl) return;
    shopEl.classList.add('active');
    if (npcBtn) npcBtn.classList.add('active');
    const firstSubTab = shopEl.querySelector('.subtab-btn');
    if (firstSubTab) {
        const tabId = firstSubTab.id.replace('subtab-', '');
        showTab(sid, parseInt(tabId));
        return;
    }
    const firstTabContent = shopEl.querySelector('.tab-content');
    if (firstTabContent) {
        shopEl.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        firstTabContent.classList.add('active');
    }
}

function showTab(sid, tabId) {
    const shopEl = document.getElementById('shop-group-' + sid);
    if (!shopEl) return;
    shopEl.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    shopEl.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
    const tabEl  = document.getElementById('tab-content-' + tabId);
    const tabBtn = document.getElementById('subtab-' + tabId);
    if (tabEl)  tabEl.classList.add('active');
    if (tabBtn) tabBtn.classList.add('active');
}

/* ══════════════════════════════════════════════════
   FORM HELPERS
   ══════════════════════════════════════════════════ */
function updateCurrencyIcon(val) {
    document.getElementById('preview_currency').src = '/icon/' + (val == 0 ? '930' : '932') + '.png';
}

function updateDemo() {
    const stt  = document.getElementById('form_stt').value || '1';
    const cost = document.getElementById('form_cost').value || '0';
    const type = document.getElementById('form_type').value;
    const selIcon = document.getElementById('selected_item_icon_img');
    const selText = document.querySelector('.selected-item-text');
    document.getElementById('demo_stt').innerText  = '#' + stt;
    document.getElementById('demo_cost').innerText = parseInt(cost).toLocaleString();
    document.getElementById('demo_icon').src       = selIcon ? selIcon.src : '/icon/0.png';
    document.getElementById('demo_name').innerText = selText ? selText.innerText.split(' (ID:')[0] : 'Vui lòng chọn vật phẩm';
    document.getElementById('demo_currency_img').src      = (type == 0) ? '/icon/930.png' : '/icon/932.png';
    document.getElementById('demo_price_tag').style.color = (type == 0) ? '#b45309' : '#0284c7';

    let optHtml = '';
    document.querySelectorAll('.opt-card').forEach(row => {
        const id    = row.querySelector('.opt-param').dataset.id;
        const param = row.querySelector('.opt-param').value;
        optHtml += '<div>• ' + (OPTION_NAMES_MAP[id] || 'ID ' + id).replace('#', param) + '</div>';
    });
    document.getElementById('demo_options_list').innerHTML =
        optHtml || "<span style='color:var(--tx3); font-style:italic;'>Trắng thông tin</span>";
}

/* ══════════════════════════════════════════════════
   DELETE HANDLER
   ══════════════════════════════════════════════════ */
function triggerDelete(tabId, index, name) {
    document.getElementById('del_tab_id').value = tabId;
    document.getElementById('del_index').value  = index;
    document.getElementById('deleteMsg').innerHTML =
        'Bạn chắc chắn muốn xóa <b style="color:var(--danger)">[' + name + ']</b> khỏi shop?';
    syncEditModalCanvas();
    document.getElementById('deleteConfirmModal').style.display = 'block';
    document.querySelector('.overlay-blur').style.display = 'block';
    document.getElementById('btnConfirmDelete').onclick = () => submitDeleteAjax();
}
function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    if (!document.getElementById('editModal').classList.contains('open'))
        document.querySelector('.overlay-blur').style.display = 'none';
}

function replaceTabContent(tabId, html) {
    const tabEl = document.getElementById('tab-content-' + tabId);
    if (!tabEl) return;
    tabEl.innerHTML = html;
    tabEl.classList.add('active');
    applyScale();
}

function submitDeleteAjax() {
    const form = document.getElementById('deleteForm');
    const data = new FormData(form);
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            closeDeleteModal();
            replaceTabContent(res.tab_id, res.html);
            toast('Đã xóa vật phẩm!', 'ok');
        } else {
            toast(res.message || 'Lỗi khi xóa!', 'err');
        }
    })
    .catch(() => toast('Lỗi kết nối!', 'err'));
}

function updateShop(btn) {
    const oldHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = 'ĐANG UPDATE...';
    }
    const data = new FormData();
    data.append('action', 'update_shop');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: data
    })
    .then(r => r.json())
    .then(res => {
        toast(res.message || (res.status === 'success' ? 'Đã gửi lệnh update shop!' : 'Update shop thất bại!'), res.status === 'success' ? 'ok' : 'err');
    })
    .catch(() => toast('Không gửi được lệnh update shop!', 'err'))
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    });
}

/* ══════════════════════════════════════════════════
   PICKER OPEN / CLOSE
   ══════════════════════════════════════════════════ */
function openPicker(id) {
    document.getElementById(id).classList.add('open');
    applyPickerScale();
    if (id === 'itemPickerModal') {
        document.getElementById('modalSearch').value = '';
        itemPickerQuery = ''; _pendingItemEl = null;
        renderItemPicker();
    } else if (id === 'optPickerModal') {
        document.getElementById('optSearch').value = '';
        optPickerQuery = ''; _pendingOptEl = null;
        renderOptPicker();
    }
}
function closePicker(id) {
    document.getElementById(id).classList.remove('open');
    _pendingItemEl = null;
    _pendingOptEl  = null;
}

/* ══════════════════════════════════════════════════
   OPTION ROW MANAGEMENT
   ══════════════════════════════════════════════════ */
function addOptionRow(id, name, param = 0) {
    const isSpecial   = SPECIAL_OPT_IDS.has(Number(id));
    const inputClass  = isSpecial ? 'opt-param is-special-id' : 'opt-param';
    const div = document.createElement('div');
    div.className = 'opt-card';
    div.innerHTML = `
        <div class="opt-name"><small>ID ${id}</small>${name}</div>
        <input type="number" class="${inputClass}" data-id="${id}" value="${param}" oninput="updateDemo()">
        <button type="button" class="opt-remove-btn" onclick="this.parentElement.remove(); updateDemo();">&times;</button>
    `;
    document.getElementById('option_container').appendChild(div);
    closePicker('optPickerModal');
    updateDemo();
}

function syncOptionsBeforeSubmit() {
    const result = [];
    document.querySelectorAll('.opt-card').forEach(row => {
        result.push(row.querySelector('.opt-param').dataset.id + ',' + row.querySelector('.opt-param').value);
    });
    document.getElementById('form_options').value = result.join('|');
}

/* ══════════════════════════════════════════════════
   FORM SUBMIT (AJAX)
   ══════════════════════════════════════════════════ */
function handleFormSubmit(e) {
    e.preventDefault();
    syncOptionsBeforeSubmit();
    const form = document.getElementById('editForm');
    const data = new FormData(form);
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            closeModal();
            replaceTabContent(res.tab_id, res.html);
            toast('Đã lưu thay đổi thành công!', 'ok');
        } else {
            toast(res.message || 'Lỗi khi lưu!', 'err');
        }
    })
    .catch(() => toast('Lỗi kết nối!', 'err'));
    return false;
}

/* ══════════════════════════════════════════════════
   ADD / EDIT MODAL
   ══════════════════════════════════════════════════ */
function openAdd(tabId) {
    document.getElementById('modalTitle').innerText    = 'THÊM VẬT PHẨM MỚI';
    document.getElementById('form_tab_id').value       = tabId;
    document.getElementById('form_index').value        = -1;
    document.getElementById('form_stt').value          = '';
    document.getElementById('form_temp_id').value      = '';
    document.getElementById('item_display').innerHTML  = "<span style='opacity:.6' id='item_placeholder'>⚡ Nhấn để chọn ITEM…</span>";
    document.getElementById('form_cost').value         = '';
    document.getElementById('form_type').value         = 0;
    updateCurrencyIcon(0);
    document.getElementById('option_container').innerHTML = '';
    document.getElementById('editModal').classList.add('open');
    applyScale();
    syncEditModalCanvas();
    document.querySelector('.overlay-blur').style.display = 'block';
    updateDemo();
}

function openEdit(tabId, index, tempId, name, icon, cost, type, opts, stt) {
    document.getElementById('modalTitle').innerText   = 'SỬA VẬT PHẨM';
    document.getElementById('form_tab_id').value      = tabId;
    document.getElementById('form_index').value       = index;
    document.getElementById('form_stt').value         = stt;
    document.getElementById('form_temp_id').value     = tempId;
    document.getElementById('item_display').innerHTML = `
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; min-height:64px;">
            <img src="/icon/${icon}.png" width="34" id="selected_item_icon_img" style="image-rendering:pixelated;">
            <span class="selected-item-text" style="font-weight:700; color:var(--tx2);">${name}
                <span style="color:var(--indigo); font-family:'JetBrains Mono',monospace; font-size:.78rem;">(ID: ${tempId})</span>
            </span>
        </div>`;
    document.getElementById('form_cost').value = cost;
    document.getElementById('form_type').value = type;
    updateCurrencyIcon(type);
    document.getElementById('option_container').innerHTML = '';
    if (opts) {
        opts.split('|').forEach(p => {
            const parts = p.split(',');
            if (parts.length == 2) addOptionRow(parts[0], OPTION_NAMES_MAP[parts[0]] || 'ID ' + parts[0], parts[1]);
        });
    }
    document.getElementById('editModal').classList.add('open');
    applyScale();
    syncEditModalCanvas();
    document.querySelector('.overlay-blur').style.display = 'block';
    updateDemo();
}

function closeModal() {
    document.getElementById('editModal').classList.remove('open');
    document.getElementById('deleteConfirmModal').style.display = 'none';
    document.querySelector('.overlay-blur').style.display       = 'none';
}

/* ══════════════════════════════════════════════════
   KEYBOARD SHORTCUTS
   ══════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const pickerOpen = document.querySelector('.modal-overlay.open');
        if (pickerOpen) {
            pickerOpen.classList.remove('open');
            _pendingItemEl = null;
            _pendingOptEl  = null;
            return;
        }
    }
});

/* ══════════════════════════════════════════════════
   TOAST NOTIFICATION
   ══════════════════════════════════════════════════ */
let _toastTimer;
function toast(msg, type = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className   = 'show ' + type;
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => { el.className = ''; }, 2000);
}
</script>
</body>
</html>
