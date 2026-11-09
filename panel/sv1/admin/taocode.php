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


$username = $user['username'] ?? '';
$csrfToken = ensure_csrf_token();

$items = $pdo->query("SELECT id, NAME, icon_id FROM item_template")->fetchAll();
$options = $pdo->query("SELECT id, NAME FROM item_option_template")->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo Giftcode</title>
    <link rel="icon" type="image/png" href="/img/icon.jpg">
    <style>
        :root { --primary: #1d4ed8; --success: #059669; --danger: #dc2626; --bg: #f1f5f9; --dark: #0f172a; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        .item-card { border: 2px solid #cbd5e1; border-radius: 12px; padding: 20px; margin-bottom: 20px; position: relative; background: #fff; }
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
        .btn { border: none; border-radius: 10px; padding: 12px 24px; font-weight: 800; cursor: pointer; color: white; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; text-transform: uppercase; }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
        .btn-add { background: var(--success); }
        .btn-submit { background: var(--primary); }
        .btn-vip { background: linear-gradient(135deg, #f59e0b, #d97706); } /* nút VIP vàng */
        .btn-share { background: linear-gradient(135deg, #7c3aed, #4f46e5); }
        .btn-back { background: #64748b; }
        .option-row { display: grid; grid-template-columns: 1fr 120px 40px; gap: 12px; margin-bottom: 12px; align-items: center; background: #f8fafc; padding: 8px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .select-trigger { background: #fff; border: 2px solid #94a3b8; border-radius: 8px; padding: 14px; text-align: center; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: 60px; }
        input[type="number"], .opt-search-input { padding: 12px; border: 2px solid #94a3b8; border-radius: 8px; font-weight: 600; width: 100%; }
        label { font-size: 0.9rem; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .demo-section { background: #f8fafc; border: 2px dashed #94a3b8; border-radius: 12px; padding: 20px; margin-top: 20px; }
        .demo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
        .demo-item { background: #fff; border: 1px solid #cbd5e1; padding: 12px; border-radius: 10px; display: flex; gap: 12px; align-items: flex-start; }
        .demo-info { flex: 1; }
        .demo-name { font-weight: 800; color: var(--primary); font-size: 1rem; margin-bottom: 2px; }
        .demo-qty { font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 5px; border-bottom: 1px solid #eee; }
        .demo-opt-line { font-size: 0.85rem; color: #475569; line-height: 1.3; }
        .imp-text { color: var(--danger); font-weight: 700; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .modal-content { background: white; width: 95%; max-width: 1400px; height: 90vh; border-radius: 15px; overflow: hidden; display: flex; flex-direction: column; border: 3px solid var(--primary); }
        .icon-grid { display: grid; grid-template-columns: repeat(9, 1fr); gap: 10px; padding: 15px; }
        .popup-bg { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
        .popup-box { width:90%; max-width:500px; padding:30px; border-radius:18px; text-align:center; background:linear-gradient(145deg,#f8fafc,#e2e8f0); color:#1e293b; box-shadow:0 20px 60px rgba(0,0,0,.4); animation:pop .25s ease; position:relative; }
        .popup-icon { font-size:60px; margin-bottom:10px; }
        .popup-title { font-size:28px; font-weight:800; margin-bottom:10px; color:var(--dark); }
        .popup-content { font-size:18px; font-weight:500; line-height:1.5; }
        @keyframes pop { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
        .close-btn-popup { position:absolute; top:-15px; right:-15px; font-size:25px; font-weight:900; width:45px; height:45px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#ef4444; color:white; cursor:pointer; transition:.2s; border:none; }
        .close-btn-popup:hover { transform:scale(1.1) rotate(90deg); }
        .icon-box { border:1px solid #e2e8f0; padding:1.5px; text-align:center; cursor:pointer; border-radius:8px; transition:0.2s; background:white; display:flex; flex-direction:column; align-items:center; justify-content:space-between; min-height:80px; }
        .icon-box:hover { border-color:var(--primary); background:#eff6ff; transform:translateY(-2px); }
        .icon-box img { width:36px; height:36px; object-fit:contain; margin-bottom:5px; }
        .icon-box .it-name { font-size:12px; font-weight:700; color:#1e293b; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; height:32px; line-height:1.3; }

        /* ===== OPTION MODAL ===== */
        #optionModal { z-index: 1100; }
        #optionModal .modal-content { max-width: 1400px; border-color: var(--success); }
        .opt-modal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; padding: 15px; }
        .opt-modal-card {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            cursor: pointer;
            background: #fff;
            transition: 0.18s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .opt-modal-card:hover { border-color: var(--success); background: #f0fdf4; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.12); }
        .opt-modal-card.is-important { border-color: #fca5a5; background: #fff7f7; }
        .opt-modal-card.is-important:hover { border-color: var(--danger); background: #fef2f2; box-shadow: 0 4px 12px rgba(220,38,38,0.12); }
        .opt-modal-badge {
            width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 900; color: var(--primary); flex-shrink: 0;
        }
        .opt-modal-card.is-important .opt-modal-badge { background: #fee2e2; color: var(--danger); }
        .opt-modal-info { flex: 1; min-width: 0; }
        .opt-modal-name { font-weight: 700; font-size: 0.88rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .opt-modal-card.is-important .opt-modal-name { color: var(--danger); }
        .opt-modal-sub { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .opt-modal-card.is-important .opt-modal-sub { color: #f87171; }
        .opt-modal-arrow { color: #cbd5e1; font-size: 1.1rem; }
        .opt-modal-card:hover .opt-modal-arrow { color: var(--success); }
        .opt-modal-card.is-important:hover .opt-modal-arrow { color: var(--danger); }
        /* tag label */
        .imp-badge { font-size:10px; font-weight:900; background:var(--danger); color:#fff; padding:2px 6px; border-radius:20px; margin-left:6px; vertical-align:middle; }
        #optionModal .modal-content {
            transform: scale(1.1);
            transform-origin: center;
            height: 82vh;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-box">
        <div style="display:flex; gap:10px;">
            <a href="giftcode" class="btn btn-back">⬅ TRỞ VỀ</a>
            <button type="button" class="btn btn-submit" onclick="location.reload();">🔄 F5</button>
        </div>
        <h2 style="margin:0; color:var(--primary); font-weight:900;">TẠO GIFTCODE BẰNG CHIM</h2>
        <div style="width:100px"></div>
    </div>

    <div id="itemList"></div>

    <div class="demo-section">
        <label>🔍 XEM VẬT PHẨM TRONG CODE:</label>
        <div id="demoGrid" class="demo-grid">
            <div style="color:#94a3b8; font-style:italic;">Chưa có vật phẩm...</div>
        </div>
    </div>

    <!-- KHU VỰC NÚT XUẤT ĐÃ SỬA -->
    <div style="margin-top:25px; display:flex; gap:20px;">
        <button type="button" class="btn btn-add" onclick="addItem()" style="flex:1; padding:15px;">+ THÊM VẬT PHẨM</button>
        <button type="button" class="btn btn-submit" onclick="generateJSON(0)" style="flex:1; padding:15px;">📦 XUẤT CODE THƯỜNG</button>
        <button type="button" class="btn btn-vip" onclick="generateJSON(1)" style="flex:1; padding:15px;">👑 XUẤT CODE VIP</button>
        <button type="button" class="btn btn-share" onclick="generateShareCodes()" style="flex:1; padding:15px;">🔗 XUẤT CODE SHARE</button>
    </div>
</div>

<div id="savePopup" class="popup-bg">
    <div class="popup-box">
        <button class="close-btn-popup" onclick="closePopup()">✖</button>
        <div id="savePopupContent"></div>
    </div>
</div>

<!-- ITEM MODAL -->
<div class="modal-overlay" id="itemModal">
    <div class="modal-content">
        <div style="padding:15px 20px; border-bottom:2px solid #eee; display:flex; gap:15px; align-items:center; background:#fff;">
            <b style="font-size:1.1rem; white-space:nowrap;">CHỌN VẬT PHẨM</b>
            <input type="text" id="modalSearch" placeholder="Tìm tên hoặc ID..." onkeyup="filterItems()" style="flex:1; padding:10px 20px; border-radius:20px; border:2px solid var(--primary); outline:none;">
            <button class="btn btn-back" onclick="closeModal()" style="padding:8px 15px; font-size:13px;">ĐÓNG</button>
        </div>
        <div style="overflow-y:auto; flex:1; background:#f8fafc;">
            <div class="icon-grid" id="modalGrid">
                <?php foreach ($items as $it): ?>
                    <div class="icon-box" data-name="<?= mb_strtolower($it['NAME'], 'UTF-8') ?>" data-id="<?= $it['id'] ?>"
                         onclick="confirmSelect(<?= $it['id'] ?>, '<?= addslashes($it['NAME']) ?>', <?= $it['icon_id'] ?>)">
                        <img src="/icon/<?= $it['icon_id'] ?>.png" loading="lazy" onerror="this.src='https://img.icons8.com/color/48/box--v1.png'">
                        <div class="it-name"><?= htmlspecialchars($it['NAME']) ?></div>
                        <code style="font-size:11px; color:var(--primary); font-weight:bold;">ID: <?= $it['id'] ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- OPTION MODAL -->
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
                    $imp = in_array($opt['id'], [0,6,7,10,11,12,13,14,210,160,156,30,50,77,101,103,106]);
                ?>
                    <div class="opt-modal-card <?= $imp ? 'is-important' : '' ?>"
                         data-opt-name="<?= strtolower($opt['NAME']) ?>"
                         data-opt-id="<?= $opt['id'] ?>"
                         onclick="confirmSelectOption(<?= $opt['id'] ?>, '<?= addslashes($opt['NAME']) ?>')">
                        <div class="opt-modal-badge"><?= $opt['id'] ?></div>
                        <div class="opt-modal-info">
                            <div class="opt-modal-name">
                                <?= htmlspecialchars($opt['NAME']) ?>
                            </div>
                        </div>
                        <div class="opt-modal-arrow">›</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    let itemCount = 0;
    let currentActiveCard = null;
    let currentOptionTarget = null;
    const optionData = <?= json_encode($options) ?>;
    const importantIds = [0, 6, 7, 30, 50, 77, 101, 103, 106];

    function addItem() {
        const id = itemCount++;
        const html = `
            <div class="item-card" id="card_${id}">
                <button type="button" style="position:absolute; right:15px; top:15px; color:var(--danger); border:none; background:none; cursor:pointer; font-weight:bold; font-size:1.1rem;" onclick="this.parentElement.remove(); updateDemo();">XÓA ✕</button>
                <div style="display:flex; gap:25px;">
                    <div style="width:280px;">
                        <label>Vật phẩm mục tiêu</label>
                        <input type="hidden" class="input_id">
                        <input type="hidden" class="input_name">
                        <input type="hidden" class="input_icon">
                        <div class="select-trigger" onclick="openModal(${id})" id="trigger_${id}">
                            <span style="color:#64748b; font-weight:600;">[ NHẤP ĐỂ CHỌN ]</span>
                        </div>
                        <label style="margin-top:15px;">Số lượng nhận</label>
                        <input type="number" class="input_quantity" min="1" placeholder="Số lượng..." oninput="updateDemo()">
                    </div>
                    <div style="flex:1; border-left:2px solid #f1f5f9; padding-left:25px;">
                        <label>Chỉ số</label>
                        <div class="opt_list" id="opt_list_${id}"></div>
                        <button type="button" class="btn btn-back" style="font-size:12px; padding:6px 12px; margin-top:8px" onclick="openOptionModal(${id})">+ Thêm chỉ số</button>
                    </div>
                </div>
            </div>`;
        document.getElementById('itemList').insertAdjacentHTML('beforeend', html);
    }

    /* ===== OPTION MODAL LOGIC ===== */
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

    function confirmSelectOption(id, name) {
        if (!currentOptionTarget) return;
        const { cardId } = currentOptionTarget;
        const container = document.getElementById(`opt_list_${cardId}`);

        const isImp = importantIds.includes(parseInt(id));
        const nameStyle = isImp ? 'color:var(--danger); font-weight:bold;' : 'color:#1e293b; font-weight:600;';
        const html = `
            <div class="option-row">
                <div style="display:flex; align-items:center; gap:8px; padding:6px 4px;">
                    <span style="background:${isImp ? '#fee2e2' : '#e0f2fe'}; color:${isImp ? 'var(--danger)' : 'var(--primary)'}; font-size:11px; font-weight:900; padding:3px 7px; border-radius:6px; flex-shrink:0;">${id}</span>
                    <span style="${nameStyle} font-size:0.88rem;">${name}</span>
                    <input type="hidden" class="opt_id" value="${id}">
                    <input type="hidden" class="opt_template" value="${name}">
                </div>
                <input type="number" class="opt_param" oninput="updateDemo()" placeholder="Giá trị">
                <button type="button" style="border:none; background:none; color:var(--danger); cursor:pointer; font-weight:bold; font-size:1.2rem;" onclick="this.parentElement.remove(); updateDemo();">✕</button>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        closeOptionModal();
        updateDemo();
    }

    function filterOptionModal() {
        const val = document.getElementById('optModalSearch').value.toLowerCase();
        document.querySelectorAll('#optModalGrid .opt-modal-card').forEach(card => {
            const match = (card.dataset.optName + card.dataset.optId).includes(val);
            card.style.display = match ? 'flex' : 'none';
        });
    }

    /* ===== CẬP NHẬT DEMO ===== */
    function updateDemo() {
        let html = '';
        document.querySelectorAll('.item-card').forEach(card => {
            const id = card.querySelector('.input_id').value;
            if (!id) return;
            const name = card.querySelector('.input_name').value;
            const qty  = card.querySelector('.input_quantity').value || 0;
            const icon = card.querySelector('.input_icon').value;
            let optHtml = '';
            card.querySelectorAll('.option-row').forEach(row => {
                const oId      = row.querySelector('.opt_id').value;
                const template = row.querySelector('.opt_template').value;
                const param    = row.querySelector('.opt_param').value || '0';
                if (oId) {
                    const text  = template.includes('#') ? template.replace('#', param) : `${template}: ${param}`;
                    const isImp = importantIds.includes(parseInt(oId));
                    optHtml += `<div class="demo-opt-line ${isImp ? 'imp-text' : ''}">• ${text}</div>`;
                }
            });
            if (!optHtml) optHtml = '<i style="color:#94a3b8; font-size:0.8rem">Không có chỉ số</i>';
            html += `
                <div class="demo-item">
                    <img src="/icon/${icon}.png" width="45" height="45" style="object-fit:contain" onerror="this.src='https://img.icons8.com/color/48/box--v1.png'">
                    <div class="demo-info">
                        <div class="demo-name">${name}</div>
                        <div class="demo-qty">Số Lượng: <span style="color:var(--danger)">${qty}</span></div>
                        <div>${optHtml}</div>
                    </div>
                </div>`;
        });
        document.getElementById('demoGrid').innerHTML = html || '<div style="color:#94a3b8; font-style:italic;">Chưa có vật phẩm cấu hình...</div>';
    }

    function showPopup(type, title, content, showBackBtn = false) {
        const box  = document.querySelector('#savePopup .popup-box');
        const html = document.getElementById('savePopupContent');
        box.className = 'popup-box popup-' + type;
        const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : '⚠️');
        const footer = showBackBtn ? `<br><br><a href="giftcode" class="btn btn-submit" style="background:var(--primary); width:100%">QUAY VỀ TRANG GIFTCODE</a>` : '';
        html.innerHTML = `<div class="popup-icon">${icon}</div><div class="popup-title">${title}</div><div class="popup-content">${content}${footer}</div>`;
        document.getElementById('savePopup').style.display = 'flex';
    }

    function closePopup() { document.getElementById('savePopup').style.display = 'none'; }

    // HÀM XUẤT CODE ĐÃ SỬA – nhận type (0: thường, 1: VIP)
    function generateJSON(type) {
        const cards = document.querySelectorAll('.item-card');
        if (cards.length === 0) { showPopup('warning', 'NHẮC NHỞ', 'Bạn chưa thêm vật phẩm nào!'); return; }

        const result = [];
        for (let i = 0; i < cards.length; i++) {
            const card     = cards[i];
            const stt      = i + 1;
            const itemId   = card.querySelector('.input_id').value;
            const qty      = card.querySelector('.input_quantity').value;
            const itemName = card.querySelector('.input_name').value || 'Chưa chọn';

            if (!itemId) { showPopup('error', 'LỖI DỮ LIỆU', `Vật phẩm #${stt} chưa được chọn!`); return; }
            if (!qty || parseInt(qty) <= 0) { showPopup('error', 'LỖI SỐ LƯỢNG', `<b>${itemName}</b> (#${stt}) chưa nhập số lượng!`); return; }

            const optionRows = card.querySelectorAll('.option-row');
            if (optionRows.length === 0) { showPopup('error', 'THIẾU CHỈ SỐ', `<b>${itemName}</b> (#${stt}) chưa có chỉ số nào!`); return; }

            const options = [];
            for (let j = 0; j < optionRows.length; j++) {
                const row  = optionRows[j];
                const oId  = row.querySelector('.opt_id').value;
                const oVal = row.querySelector('.opt_param').value;
                if (!oId || oVal === '') { showPopup('error', 'CHỈ SỐ TRỐNG', `<b>${itemName}</b>: Chỉ số dòng ${j+1} chưa đầy đủ!`); return; }
                options.push({ id: parseInt(oId), param: parseInt(oVal) });
            }

            result.push({ id: parseInt(itemId), quantity: parseInt(qty), options });
        }

        // Gửi kèm type
        fetch('ajax_create_giftcode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>' },
            body: JSON.stringify({ items: result, type: type })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const codeType = type === 1 ? 'VIP' : (type === 2 ? 'Share' : 'Thường');
                if (type === 2) {
                    const codes = Array.isArray(res.codes) ? res.codes : [];
                    showPopup('success', 'THÀNH CÔNG',
                        `Đã tạo <b>${res.created || codes.length}</b> Giftcode ${codeType}.
                        <textarea readonly onclick="this.select()" style="width:100%;height:210px;margin-top:12px;padding:12px;border:2px solid #cbd5e1;border-radius:10px;font-size:15px;font-family:Consolas,monospace;">${codes.join('\n')}</textarea>
                        <span style="font-size:16px; color:#64748b;">Hạn dùng: 2030 | Type: 2 | Lượt: 1</span>`, true);
                    return;
                }
                showPopup('success', 'THÀNH CÔNG',
                    `Đã tạo Giftcode ${codeType}: <b style="color:var(--primary); font-size:40px; display:block; margin:10px 0;">${res.code}</b>
                    <span style="font-size:16px; color:#64748b;">Hạn dùng: 2030 | Lượt: 9999</span>`, true);
            } else {
                showPopup('error', 'LỖI SERVER', res.msg);
            }
        })
        .catch(() => showPopup('error', 'LỖI', 'Không thể kết nối Server!'));
    }

    function generateShareCodes() {
        generateJSON(2);
    }

    function openModal(id)  { currentActiveCard = id; document.getElementById('itemModal').style.display = 'flex'; }
    function closeModal()   { document.getElementById('itemModal').style.display = 'none'; }

    function confirmSelect(id, name, icon) {
        const trigger = document.getElementById(`trigger_${currentActiveCard}`);
        const card    = trigger.closest('.item-card');
        card.querySelector('.input_id').value   = id;
        card.querySelector('.input_name').value = name;
        card.querySelector('.input_icon').value = icon;
        trigger.innerHTML = `<div style="display:flex; align-items:center; gap:10px;"><img src="/icon/${icon}.png" width="30" height="30"><b>${name}</b></div>`;
        closeModal();
        updateDemo();
    }

    function filterItems() {
        const val = document.getElementById('modalSearch').value.toLowerCase().trim();
        document.querySelectorAll('.icon-box').forEach(box => {
            const name = box.dataset.name || '';
            const id   = box.dataset.id || '';
            const match = name.includes(val) || id.includes(val);
            box.style.display = match ? 'flex' : 'none';
        });
    }

    document.addEventListener('click', e => {
        if (!e.target.classList.contains('opt-search-input'))
            document.querySelectorAll('.opt-dropdown').forEach(d => d.style.display = 'none');
    });

    window.onclick = e => {
        if (e.target == document.getElementById('savePopup')) closePopup();
        if (e.target == document.getElementById('itemModal')) closeModal();
        if (e.target == document.getElementById('optionModal')) closeOptionModal();
    };

    window.onload = addItem;
</script>
</body>
</html>
