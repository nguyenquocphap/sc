<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";
require_once __DIR__ . "/game_bridge.php";

if (empty($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu");
    exit;
}

$account_id = (int)$_SESSION['account_id'];
$stmt = $pdo->prepare("SELECT is_admin FROM account WHERE id = ? LIMIT 1");
$stmt->execute([$account_id]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_admin'] !== 1) {
    echo "Không có quyền!";
    exit;
}

if (isset($_GET['ajax_items'])) {
    header('Content-Type: application/json; charset=utf-8');
    $keyword = trim((string)($_GET['q'] ?? ''));

    if ($keyword === '') {
        $stmt = $pdo->prepare("SELECT id, NAME, icon_id FROM item_template ORDER BY id ASC");
        $stmt->execute();
    } elseif (ctype_digit($keyword)) {
        $stmt = $pdo->prepare("SELECT id, NAME, icon_id FROM item_template WHERE id = :id OR NAME LIKE :keyword ORDER BY id ASC");
        $stmt->execute([
            ':id' => (int)$keyword,
            ':keyword' => '%' . $keyword . '%',
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT id, NAME, icon_id FROM item_template WHERE NAME LIKE :keyword ORDER BY id ASC");
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
    }

    $rows = $stmt->fetchAll();

    echo json_encode([
        'items' => $rows,
        'has_more' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

$options = $pdo->query("SELECT id, NAME FROM item_option_template ORDER BY id ASC")->fetchAll();
$csrfToken = ensure_csrf_token();

$buffItemMsg = '';
$msgClass = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buff_item') {
    require_csrf_token();
    $char_name = trim($_POST['char_name'] ?? '');
    $items_json = $_POST['items_json'] ?? '[]';
    $playerItems = json_decode($items_json, true);

    if ($char_name === '') {
        $buffItemMsg = 'Tên nhân vật không được để trống!';
        $msgClass = 'error';
    } elseif (!is_array($playerItems) || empty($playerItems)) {
        $buffItemMsg = 'Bạn cần chọn ít nhất 1 vật phẩm để tặng.';
        $msgClass = 'error';
    } else {
        $valid = true;
        foreach ($playerItems as $index => $item) {
            if (!isset($item['temp_id'], $item['quantity'])) {
                $valid = false;
                break;
            }
            if ((int)$item['temp_id'] <= 0 || (int)$item['quantity'] <= 0) {
                $valid = false;
                break;
            }
            if (!isset($item['options']) || !is_array($item['options'])) {
                $playerItems[$index]['options'] = [];
            } else {
                foreach ($item['options'] as $opt) {
                    if (!isset($opt['id'], $opt['param']) || (int)$opt['id'] < 0 || !is_numeric($opt['param'])) {
                        $valid = false;
                        break 2;
                    }
                }
            }
        }

        if (!$valid) {
            $buffItemMsg = 'Dữ liệu vật phẩm không hợp lệ. Vui lòng kiểm tra lại.';
            $msgClass = 'error';
        } else {
            $payload = json_encode($playerItems, JSON_UNESCAPED_UNICODE);
            $command = sprintf('BUFF_ITEM|%s|%s', str_replace(["\r", "\n"], '', $char_name), str_replace(["\r", "\n"], '', $payload));

            if (append_panel_command($command)) {
                $buffItemMsg = "Đã tặng thành công cho nhân vật $char_name.";
                $msgClass = 'success';
            } else {
                $buffItemMsg = 'Không thể ghi lệnh tặng vào file điều khiển game.';
                $msgClass = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Tặng Vật Phẩm - Admin</title>
<link rel="icon" type="image/jpeg" href="../../img/icon.jpg">
<style>
:root { --bg:#f4f7fb; --surf:#ffffff; --surf2:#edf2f7; --bd:#cbd5e1; --bd2:#94a3b8; --tx:#102a43; --tx2:#334e68; --success:#0f766e; --danger:#b91c1c; --primary:#1d4ed8; --r:14px; }
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;}
.page{max-width:1200px;margin:0 auto;padding:22px;}
.header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px;}
.header-panel{background:var(--surf);border:1px solid var(--bd);border-radius:var(--r);box-shadow:0 10px 25px rgba(15,23,42,.05);padding:22px;margin-bottom:20px;}
.header-panel .header{margin-bottom:0;}
.header-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.btn-f5{background:var(--primary);color:#fff;}
.item-form-layout{display:flex;flex-direction:column;gap:14px;}
.item-left{width:100%;}
.item-right{width:100%;border-top:2px solid #f1f5f9;padding-top:14px;}
.icon-box{border:1px solid #e2e8f0;padding:1.5px;text-align:center;cursor:pointer;border-radius:8px;transition:0.2s;background:white;display:flex;flex-direction:column;align-items:center;justify-content:space-between;min-height:80px;}
.icon-box:hover{border-color:var(--primary);background:#eff6ff;transform:translateY(-2px);}
.icon-box img{width:36px;height:36px;object-fit:contain;margin-bottom:5px;}
.icon-box .it-name{font-size:12px;font-weight:700;color:#1e293b;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height:32px;line-height:1.3;}
.opt-modal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;padding:15px;}
.opt-modal-card{border:2px solid #e2e8f0;border-radius:10px;padding:12px 15px;cursor:pointer;background:#fff;transition:.18s;display:flex;align-items:center;gap:12px;}
.opt-modal-card:hover{border-color:var(--success);background:#f0fdf4;transform:translateY(-2px);box-shadow:0 4px 12px rgba(15,118,110,.12);}
.opt-modal-card.is-important{border-color:#fca5a5;background:#fff7f7;}
.opt-modal-card.is-important:hover{border-color:var(--danger);background:#fef2f2;box-shadow:0 4px 12px rgba(185,28,28,.12);}
.opt-modal-badge{width:36px;height:36px;border-radius:8px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:var(--primary);flex-shrink:0;}
.opt-modal-card.is-important .opt-modal-badge{background:#fee2e2;color:var(--danger);}
.opt-modal-info{flex:1;min-width:0;}
.opt-modal-name{font-weight:700;font-size:.88rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.opt-modal-card.is-important .opt-modal-name{color:var(--danger);}
.opt-modal-sub{font-size:.75rem;color:#94a3b8;margin-top:2px;}
.opt-modal-arrow{color:#cbd5e1;font-size:1.1rem;}
.opt-modal-card:hover .opt-modal-arrow{color:var(--success);}
.header h1{font-size:1.75rem;color:var(--primary);}
.btn{border:none;border-radius:12px;padding:12px 18px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;}
.btn-back{background:#94a3b8;color:#fff;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-success{background:var(--success);color:#fff;}
.btn-danger{background:var(--danger);color:#fff;}
.card{background:var(--surf);border:1px solid var(--bd);border-radius:var(--r);box-shadow:0 10px 25px rgba(15,23,42,.05);padding:22px;margin-bottom:20px;}
.field{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.field label{font-size:0.95rem;font-weight:700;color:var(--tx2);}
.field input{border:1px solid var(--bd);border-radius:12px;padding:12px;font-size:1rem;outline:none;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
.buff-compose{display:grid;grid-template-columns:minmax(0,65fr) minmax(0,35fr);gap:18px;align-items:start;}
.demo-panel{border:1.5px solid var(--bd2);border-radius:16px;background:#f8fafc;padding:18px;position:sticky;top:16px;min-height:260px;}
.demo-panel h3{font-size:1rem;color:var(--tx2);margin-bottom:14px;}
.demo-empty{border:2px dashed var(--bd);border-radius:14px;min-height:190px;display:flex;align-items:center;justify-content:center;text-align:center;color:#64748b;font-weight:800;padding:18px;}
.demo-item-head{display:flex;gap:14px;align-items:center;border-bottom:1px solid #e2e8f0;padding-bottom:14px;margin-bottom:14px;}
.demo-item-head img{width:64px;height:64px;object-fit:contain;border:1px solid #dbe4ef;border-radius:12px;background:#fff;padding:6px;flex-shrink:0;}
.demo-item-name{font-weight:900;color:#0f172a;font-size:1.05rem;line-height:1.25;}
.demo-item-meta{font-size:.85rem;color:#64748b;font-weight:800;margin-top:5px;}
.demo-options{display:flex;flex-direction:column;gap:8px;}
.demo-option{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px 12px;font-weight:800;color:#1e293b;}
.demo-option-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.demo-option.important .demo-option-name{color:var(--danger);}
.item-card{border:1.5px solid var(--bd2);border-radius:16px;padding:16px;position:relative;background:#fff;}
.item-card h3{font-size:1rem;margin-bottom:12px;color:var(--tx2);}
.item-pick-row{display:grid;grid-template-columns:4fr 1fr;gap:12px;align-items:end;}
.item-pick-row .field{margin-bottom:0;}
.input_quantity{width:100%;}
.buff-popup{position:fixed;inset:0;z-index:1200;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:18px;}
.buff-popup.show{display:flex;}
.buff-popup-box{width:min(440px,100%);background:#fff;border-radius:18px;padding:24px;border:1px solid rgba(15,118,110,.25);box-shadow:0 24px 70px rgba(15,23,42,.22);text-align:center;}
.buff-popup-icon{width:52px;height:52px;border-radius:50%;background:rgba(15,118,110,.12);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 12px;font-weight:900;}
.buff-popup-message{font-weight:800;color:var(--tx);font-size:1.05rem;margin-bottom:18px;}
.buff-popup .btn{min-width:120px;}
.item-card .remove-btn{position:absolute;top:16px;right:16px;border:none;background:transparent;color:var(--danger);font-size:1.1rem;cursor:pointer;}
.option-row{display:grid;grid-template-columns:1fr 100px 50px;gap:10px;align-items:center;margin-bottom:10px;padding:10px;border:1px solid rgba(148,163,184,.4);border-radius:10px;background:#f8fafc;}
.option-row input{padding:10px;border:1px solid var(--bd);border-radius:10px;}
.option-row .opt-label{font-size:0.85rem;color:var(--tx2);font-weight:700;}
.select-trigger{border:2px dashed var(--bd2);border-radius:14px;padding:18px;text-align:center;color:var(--bd2);cursor:pointer;min-height:70px;display:flex;align-items:center;justify-content:center;}
.select-trigger:hover{border-color:var(--primary);color:var(--primary);}
.notice{padding:14px 18px;border-radius:14px;border:1px solid;}
.notice.success{background:rgba(15,118,110,.1);border-color:rgba(15,118,110,.35);color:var(--success);}
.notice.error{background:rgba(185,28,28,.1);border-color:rgba(185,28,28,.35);color:var(--danger);}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;justify-content:center;align-items:center;backdrop-filter:blur(3px);}
.modal-content{background:white;width:95%;max-width:1400px;height:90vh;border-radius:15px;overflow:hidden;display:flex;flex-direction:column;border:3px solid var(--primary);}
.modal-box{background:white;width:95%;max-width:1400px;height:90vh;border-radius:15px;overflow:hidden;display:flex;flex-direction:column;border:3px solid var(--primary);}
.icon-grid{display:grid;grid-template-columns:repeat(9,1fr);gap:10px;padding:15px;}
#optionModal{z-index:1100;}
#optionModal .modal-content{max-width:1400px;border-color:var(--success);transform:scale(1.1);transform-origin:center;height:82vh;}
#optionModal .modal-box{max-width:1400px;border-color:var(--success);transform:scale(1.1);transform-origin:center;height:82vh;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:2px solid #eee;background:#fff;gap:15px;}
.modal-body{overflow-y:auto;flex:1;background:#f8fafc;padding:0;}
.icon-card{border:1px solid var(--bd);border-radius:14px;padding:14px;display:flex;flex-direction:column;align-items:center;gap:10px;cursor:pointer;transition:transform .18s;background:#f8fafc;}
.icon-card:hover{transform:translateY(-2px);border-color:var(--primary);box-shadow:0 8px 24px rgba(29,78,216,.12);}
.icon-card img{width:52px;height:52px;object-fit:contain;}
.icon-card .name{font-size:0.9rem;font-weight:700;text-align:center;color:var(--tx2);}
.search-box{margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;}
.search-box input{flex:1;min-width:220px;border:1px solid var(--bd);border-radius:12px;padding:12px;}
@media (max-width:760px){.header{flex-direction:column;align-items:flex-start;}.grid{grid-template-columns:1fr;}.buff-compose{grid-template-columns:1fr;}.demo-panel{position:static;}.item-form-layout{flex-direction:column;}.item-left{width:100%;}.item-right{width:100%;border-top:2px solid #f1f5f9;padding-top:14px;}.header-actions{width:100%;}.header-actions .btn{flex:1;}}
</style>
</head>
<body>
<div class="page">
    <div class="header-panel">
        <div class="header">
            <div>
                <h1>Tặng Vật Phẩm cho Player</h1>
                <p style="color:var(--tx2);margin-top:6px;max-width:720px;font-weight:700;">Lưu Ý: Vật Phẩm Sẽ + Thẳng Vào Hành Trang (Vui lòng để trống đủ Slot)</p>
            </div>
            <div class="header-actions">
                <a href="/sv1/admin/panel" class="btn btn-back">← Trở về Panel</a>
                <button type="button" class="btn btn-f5" onclick="location.reload()">🔄 F5</button>
            </div>
        </div>
    </div>

    <?php if ($buffItemMsg): ?>
        <div class="notice <?= $msgClass ?>" style="margin-bottom:20px;"><?= htmlspecialchars($buffItemMsg) ?></div>
    <?php endif; ?>

    <div class="card">
        <form id="buffItemForm" method="POST" onsubmit="submitBuffItem(event)">
            <input type="hidden" name="action" value="buff_item">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="hidden" name="items_json" id="items_json">

            <div class="field">
                <label for="char_name">Tên nhân vật</label>
                <input type="text" id="char_name" name="char_name" placeholder="Nhập tên nhân vật..." required>
            </div>

            <div class="field" style="margin-bottom:0;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div style="font-size:0.95rem;font-weight:700;color:var(--tx2);margin-bottom:8px;">Danh sách vật phẩm</div>
                    <button type="button" class="btn btn-success" onclick="addItem()">+ Thêm vật phẩm</button>
                </div>
            </div>

            <div class="buff-compose">
                <div id="itemList"></div>
                <aside class="demo-panel">
                    <h3>Demo vật phẩm sẽ tặng</h3>
                    <div id="itemDemo" class="demo-empty">Chọn vật phẩm và nhập chỉ số để xem trước.</div>
                </aside>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:flex-end;align-items:center;margin-top:16px;">
                <button type="button" class="btn btn-back" onclick="window.location.href='/sv1/admin/panel'">Hủy</button>
                <button type="submit" class="btn btn-primary">Gửi lệnh tặng</button>
            </div>
        </form>
    </div>
</div>

<div id="buffPopup" class="buff-popup">
    <div class="buff-popup-box">
        <div class="buff-popup-icon">✓</div>
        <div id="buffPopupMessage" class="buff-popup-message"></div>
        <button type="button" class="btn btn-success" onclick="closeBuffPopup()">OK</button>
    </div>
</div>
<div class="modal-overlay" id="itemModal">
    <div class="modal-content">
        <div style="padding:15px 20px; border-bottom:2px solid #eee; display:flex; gap:15px; align-items:center; background:#fff;">
            <b style="font-size:1.1rem; white-space:nowrap;">CHỌN VẬT PHẨM</b>
            <input type="text" id="modalSearch" placeholder="Tìm tên hoặc ID..." onkeyup="filterItems()" style="flex:1; padding:10px 20px; border-radius:20px; border:2px solid var(--primary); outline:none;">
            <button class="btn btn-back" onclick="closeModal()" style="padding:8px 15px; font-size:13px;">ĐÓNG</button>
        </div>
        <div style="overflow-y:auto; flex:1; background:#f8fafc;">
            <div class="icon-grid" id="modalGrid"></div>
            <div id="itemModalHint" style="padding:0 15px 15px;color:#64748b;font-weight:700;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="optionModal">
    <div class="modal-content">
        <div style="padding:15px 20px; border-bottom:2px solid #e2e8f0; display:flex; gap:15px; align-items:center; background:#fff;">
            <b style="font-size:1.1rem; white-space:nowrap; color:var(--success);">⚡ CHỌN CHỈ SỐ</b>
            <input type="text" id="optModalSearch" placeholder="Tìm tên hoặc ID chỉ số..." oninput="filterOptionModal()" style="flex:1; padding:10px 20px; border-radius:20px; border:2px solid var(--success); outline:none;">
            <button class="btn btn-back" onclick="closeOptionModal()" style="padding:8px 15px; font-size:13px;">ĐÓNG</button>
        </div>
        <div style="overflow-y:auto; flex:1; background:#f8fafc;">
            <div class="opt-modal-grid" id="optModalGrid">
                
                <?php foreach ($options as $opt):
                    $isImp = in_array($opt['id'], [0,6,7,10,11,12,13,14,210,160,156,30,50,77,101,103,106]);
                ?>
                    <div class="opt-modal-card <?= $isImp ? 'is-important' : '' ?>"
                         data-opt-name="<?= strtolower($opt['NAME']) ?>"
                         data-opt-id="<?= $opt['id'] ?>"
                         onclick="confirmSelectOption(<?= $opt['id'] ?>, '<?= addslashes($opt['NAME']) ?>')">
                        <div class="opt-modal-badge"><?= $opt['id'] ?></div>
                        <div class="opt-modal-info">
                            <div class="opt-modal-name"><?= htmlspecialchars($opt['NAME']) ?></div>
                            <div class="opt-modal-sub">ID: <?= $opt['id'] ?></div>
                        </div>
                        <div class="opt-modal-arrow">›</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentActiveCard = null;
let currentOptionTarget = null;
const optionData = <?= json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const optionNameMap = Object.fromEntries(optionData.map(opt => [String(opt.id), opt.NAME]));
const importantIds = [0,6,7,10,11,12,13,14,210,160,156,30,50,77,101,103,106];
let itemSearchTimer = null;
let itemSearchSeq = 0;

function addItem() {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    const html = `
        <div class="item-card" id="card_${id}">
            <button type="button" class="remove-btn" onclick="removeItem(${id})">XÓA ✕</button>
            <div class="item-form-layout">
                <div class="item-left">
                    <div class="item-pick-row">
                        <div class="field">
                            <label>Vật phẩm mục tiêu</label>
                            <div class="select-trigger" id="trigger_${id}" onclick="openModal('${id}')">
                                <span style="color:#64748b;font-weight:700;">[ NHẤP ĐỂ CHỌN ]</span>
                            </div>
                            <input type="hidden" class="input_id">
                            <input type="hidden" class="input_name">
                            <input type="hidden" class="input_icon">
                        </div>
                        <div class="field">
                            <label>Số lượng</label>
                            <input type="number" class="input_quantity" min="1" value="1" oninput="updateDemoPreview(this.closest('.item-card'))">
                        </div>
                    </div>
                </div>
                <div class="item-right">
                    <label>Chỉ số</label>
                    <div class="opt_list" id="opt_list_${id}"></div>
                    <button type="button" class="btn btn-back" style="font-size:12px;padding:6px 12px;margin-top:8px" onclick="openOptionModal('${id}')">+ Thêm chỉ số</button>
                </div>
            </div>
        </div>`;
    document.getElementById('itemList').insertAdjacentHTML('beforeend', html);
}
function removeItem(id) {
    const card = document.getElementById(`card_${id}`);
    if (card) card.remove();
    updateDemoPreview();
}

function openModal(cardId) {
    currentActiveCard = cardId;
    document.getElementById('modalSearch').value = '';
    document.getElementById('itemModal').style.display = 'flex';
    filterItems();
}

function closeModal() {
    document.getElementById('itemModal').style.display = 'none';
}

function openOptionModal(cardId) {
    currentOptionTarget = { cardId };
    document.getElementById('optModalSearch').value = '';
    filterOptionModal();
    document.getElementById('optionModal').style.display = 'flex';
}

function closeOptionModal() {
    document.getElementById('optionModal').style.display = 'none';
    currentOptionTarget = null;
}

function confirmSelect(id, name, icon) {
    const trigger = document.getElementById(`trigger_${currentActiveCard}`);
    const card = trigger.closest('.item-card');
    card.querySelector('.input_id').value = id;
    card.querySelector('.input_name').value = name;
    card.querySelector('.input_icon').value = icon;
    trigger.innerHTML = `<div style="display:flex;align-items:center;gap:10px;"><img src='/icon/${icon}.png' width='30' height='30' style='border-radius:8px;object-fit:contain;' onerror='this.style.display="none"'><strong style='color:var(--tx2);'>${escapeHtml(name)}</strong></div>`;
    closeModal();
    updateDemoPreview(card);
}

function confirmSelectOption(optId, optName) {
    if (!currentOptionTarget) return;
    const cardId = currentOptionTarget.cardId;
    const list = document.getElementById(`opt_list_${cardId}`);
    const isImp = importantIds.includes(parseInt(optId));
    const nameStyle = isImp ? 'color:var(--danger);font-weight:bold;' : 'color:#1e293b;font-weight:600;';
    const safeOptName = escapeHtml(optName);
    const html = `
        <div class="option-row">
            <div style="display:flex;align-items:center;gap:8px;padding:6px 4px;">
                <span style="background:${isImp ? '#fee2e2' : '#e0f2fe'};color:${isImp ? 'var(--danger)' : 'var(--primary)'};font-size:11px;font-weight:900;padding:3px 7px;border-radius:6px;flex-shrink:0;">${optId}</span>
                <span style="${nameStyle}font-size:0.88rem;">${safeOptName}</span>
                <input type="hidden" class="opt_id" value="${optId}">
                <input type="hidden" class="opt_template" value="${safeOptName}">
            </div>
            <input type="number" class="opt_param" placeholder="Giá trị" oninput="updateDemoPreview(this.closest('.item-card'))">
            <button type="button" style="border:none;background:none;color:var(--danger);cursor:pointer;font-weight:bold;font-size:1.2rem;" onclick="const card=this.closest('.item-card'); this.parentElement.remove(); updateDemoPreview(card);">✕</button>
        </div>`;
    list.insertAdjacentHTML('beforeend', html);
    closeOptionModal();
    updateDemoPreview(document.getElementById(`card_${cardId}`));
}
function filterItems() {
    const keyword = document.getElementById('modalSearch').value.toLowerCase().trim();
    clearTimeout(itemSearchTimer);
    itemSearchTimer = setTimeout(() => fetchItemResults(keyword), 120);
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function fetchItemResults(keyword) {
    const grid = document.getElementById('modalGrid');
    const hint = document.getElementById('itemModalHint');
    const seq = ++itemSearchSeq;

    grid.textContent = '';
    hint.textContent = 'Đang tải vật phẩm...';

    fetch(`${window.location.pathname}?ajax_items=1&q=${encodeURIComponent(keyword)}`, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            if (!response.ok) throw new Error('item_search_failed');
            return response.json();
        })
        .then(payload => {
            if (seq !== itemSearchSeq) return;
            renderItemResults(payload.items || [], Boolean(payload.has_more));
        })
        .catch(() => {
            if (seq !== itemSearchSeq) return;
            hint.textContent = 'Không thể tải danh sách vật phẩm.';
        });
}

function renderItemResults(items, hasMore) {
    const grid = document.getElementById('modalGrid');
    const hint = document.getElementById('itemModalHint');
    const fragment = document.createDocumentFragment();
    let rendered = 0;

    grid.textContent = '';

    items.forEach(item => {
        const id = String(item.id || '');
        const name = String(item.NAME || '');

        const card = document.createElement('div');
        card.className = 'icon-box';
        card.onclick = () => confirmSelect(Number(item.id), name, Number(item.icon_id || 0));

        const img = document.createElement('img');
        img.src = `/icon/${Number(item.icon_id || 0)}.png`;
        img.alt = name;
        img.loading = 'lazy';
        img.onerror = () => { img.style.display = 'none'; };

        const itemName = document.createElement('div');
        itemName.className = 'it-name';
        itemName.textContent = name;

        const itemId = document.createElement('div');
        itemId.style.fontSize = '0.8rem';
        itemId.style.color = 'var(--bd2)';
        itemId.textContent = `ID: ${id}`;

        card.append(img, itemName, itemId);
        fragment.appendChild(card);
        rendered++;
    });

    grid.appendChild(fragment);
    hint.textContent = `${rendered} vật phẩm`;
}

function getPreviewCard(preferredCard = null) {
    if (preferredCard && preferredCard.querySelector('.input_id')?.value) {
        return preferredCard;
    }
    return Array.from(document.querySelectorAll('.item-card')).find(card => card.querySelector('.input_id')?.value) || null;
}

function formatOptionText(id, fallbackName, param) {
    const template = optionNameMap[String(id)] || fallbackName || `ID ${id}`;
    const value = param === '' ? '0' : String(param);
    return template.includes('#') ? template.split('#').join(value) : `${template}: ${value}`;
}

function updateDemoPreview(preferredCard = null) {
    const demo = document.getElementById('itemDemo');
    if (!demo) return;

    const card = getPreviewCard(preferredCard);
    if (!card) {
        demo.className = 'demo-empty';
        demo.textContent = 'Chọn vật phẩm và nhập chỉ số để xem trước.';
        return;
    }

    const itemName = card.querySelector('.input_name').value || 'Vật phẩm chưa đặt tên';
    const itemId = card.querySelector('.input_id').value || '';
    const icon = card.querySelector('.input_icon').value || '0';
    const quantity = card.querySelector('.input_quantity').value || '1';
    const options = Array.from(card.querySelectorAll('.option-row')).map(row => {
        const id = row.querySelector('.opt_id').value;
        const name = row.querySelector('.opt_template').value;
        const param = row.querySelector('.opt_param').value;
        return { id, name, param };
    }).filter(opt => opt.name);

    demo.className = '';
    demo.innerHTML = `
        <div class="demo-item-head">
            <img src="/icon/${Number(icon)}.png" alt="${escapeHtml(itemName)}" onerror="this.style.display='none'">
            <div>
                <div class="demo-item-name">${escapeHtml(itemName)}</div>
                <div class="demo-item-meta">ID: ${escapeHtml(itemId)} · SL: ${escapeHtml(quantity)}</div>
            </div>
        </div>
        <div class="demo-options">
            ${options.length ? options.map(opt => {
                const isImp = importantIds.includes(parseInt(opt.id));
                const text = formatOptionText(opt.id, opt.name, opt.param);
                return `<div class="demo-option ${isImp ? 'important' : ''}">
                    <span class="demo-option-name">${escapeHtml(text)}</span>
                </div>`;
            }).join('') : '<div class="demo-empty" style="min-height:110px;">Chưa thêm chỉ số.</div>'}
        </div>`;
}

function filterOptionModal() {
    const keyword = document.getElementById('optModalSearch').value.toLowerCase().trim();
    document.querySelectorAll('#optModalGrid .opt-modal-card').forEach(card => {
        const name = card.dataset.optName || '';
        const id = card.dataset.optId || '';
        card.style.display = name.includes(keyword) || id.includes(keyword) ? 'flex' : 'none';
    });
}

function gatherItems() {
    const cards = document.querySelectorAll('.item-card');
    const data = [];

    cards.forEach(card => {
        const itemId = card.querySelector('.input_id').value;
        const qty = card.querySelector('.input_quantity').value;
        const options = [];

        card.querySelectorAll('.option-row').forEach(row => {
            const optId = row.querySelector('.opt_id').value;
            const optParam = row.querySelector('.opt_param').value;
            if (optId !== '' && optParam !== '') {
                options.push({ id: Number(optId), param: Number(optParam) });
            }
        });

        if (itemId && qty) {
            data.push({ temp_id: Number(itemId), quantity: Number(qty), options });
        }
    });

    return data;
}

function submitBuffItem(event) {
    event.preventDefault();
    const form = document.getElementById('buffItemForm');
    const charName = document.getElementById('char_name').value.trim();
    const items = gatherItems();

    if (!charName) { return alert('Tên nhân vật không được để trống.'); }
    if (items.length === 0) { return alert('Vui lòng thêm ít nhất 1 vật phẩm.'); }

    document.getElementById('items_json').value = JSON.stringify(items);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerText;
    submitButton.innerText = 'Đang gửi...';
    submitButton.disabled = true;

    fetch('', { method: 'POST', headers: { 'X-CSRF-TOKEN': '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>' }, body: new FormData(form) })
        .then(response => {
            if (!response.ok) throw new Error('request_failed');
            return response.text();
        })
        .then(() => {
            submitButton.innerText = originalText;
            submitButton.disabled = false;
            showBuffPopup(`Đã tặng thành công cho nhân vật ${charName}`);
        })
        .catch(() => {
            submitButton.innerText = originalText;
            submitButton.disabled = false;
            alert('Không thể gửi lệnh tặng.');
        });
}

function showBuffPopup(message) {
    document.getElementById('buffPopupMessage').textContent = message;
    document.getElementById('buffPopup').classList.add('show');
}

function closeBuffPopup() {
    document.getElementById('buffPopup').classList.remove('show');
}


window.onload = function() {
    addItem();
    updateDemoPreview();
};
</script>
</body>
</html>

















