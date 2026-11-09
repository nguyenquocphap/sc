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

// Xử lý lưu dữ liệu từ AJAX (giống cơ chế của account.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    require_csrf_token();
    header('Content-Type: application/json');
    
    $id       = (int)$_POST['id'];
    $power    = (int)str_replace('.', '', $_POST['power']);
    $hp       = (int)str_replace('.', '', $_POST['hp']);
    $mp       = (int)str_replace('.', '', $_POST['mp']);
    $damage   = (int)str_replace('.', '', $_POST['damage']);
    $defense  = (int)str_replace('.', '', $_POST['defense']);
    $critical = (int)str_replace('.', '', $_POST['critical']);
    
    try {
        $stmt = $pdo->prepare("UPDATE power_limit SET 
            power = ?, hp = ?, mp = ?, damage = ?, defense = ?, critical = ? 
            WHERE id = ?");
        $stmt->execute([$power, $hp, $mp, $damage, $defense, $critical, $id]);
        append_panel_command('LOAD_POWER_LIMIT');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Lấy danh sách các giới hạn chỉ số
$rows = $pdo->query("SELECT id, power, hp, mp, damage, defense, critical FROM power_limit ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalRows = count($rows);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Quản Lý Giới Hạn Chỉ Số</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<style>
/* ========== TOKENS (giống hệt account.php) ========== */
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
  --r:  13px;
  --rs: 8px;
  --sh:  0 4px 24px rgba(13,95,163,.13);
  --sh2: 0 1px 8px rgba(13,95,163,.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  width: 100%;
  height: 100%;
  overflow-x: hidden;
  background: var(--bg);
  font-family: 'Times New Roman', Times, serif;
  -webkit-font-smoothing: antialiased;
}

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

/* TOPBAR */
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

/* STATS nhỏ gọn (tùy chọn, giữ style đồng bộ) */
.stats-row {
  display: flex; gap: 6px; flex-wrap: nowrap;
  justify-content: center; margin-bottom: 9px;
}
.stat-card {
  background: var(--surf); border: 1px solid var(--bd2);
  border-radius: var(--r); padding: 9px 20px;
  display: flex; align-items: center; gap: 8px;
  box-shadow: var(--sh2); min-width: 100px; justify-content: center;
  text-align: center;
}
.stat-val { font-size: 1.1rem; font-weight: 900; font-family: 'Times New Roman', Times, serif; line-height: 1; text-align: center; }
.stat-lbl { font-size: .72rem; color: var(--tx3); font-weight: 700; line-height: 1; text-align: center; }

/* TABLE */
.tc {
  background: var(--surf); border: 1px solid var(--bd2);
  border-radius: var(--r); overflow: hidden; box-shadow: var(--sh);
}
.tsc {
  overflow: visible;
  overflow-x: visible;
  overflow-y: visible;
}

col.c-id   { width: 5%; }
col.c-pow  { width: 20%; }
col.c-hp   { width: 17%; }
col.c-mp   { width: 17%; }
col.c-dmg  { width: 17%; }
col.c-def  { width: 10%; }
col.c-crit { width: 8%; }
col.c-act  { width: 6%; }

table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

thead tr.header-row th {
  background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt));
  color: var(--tx);
  padding: 11px 6px 9px;
  font-size: .79rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .7px;
  border-bottom: 2px solid var(--bd2);
  white-space: nowrap; text-align: center;
}

tbody tr { transition: filter .12s; }
tbody tr:hover td { filter: brightness(.95); }
tbody tr:nth-child(odd)  td { background: #f5faff; }
tbody tr:nth-child(even) td { background: #eaf3fb; }

td {
  padding: 7px 6px;
  text-align: center;
  border-bottom: 1px solid var(--bd);
  vertical-align: middle;
}

.view-value {
  display: inline-block;
  width: 100%;
  font-size: .85rem;
  font-weight: 600;
  font-family: 'Times New Roman', Times, serif;
  background: transparent;
  padding: 4px 6px;
  border-radius: var(--rs);
  color: var(--tx2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.edit-input {
  width: 100%;
  padding: 4px 6px;
  border: 1.5px solid var(--bd2);
  border-radius: var(--rs);
  font-size: .8rem; font-weight: 600;
  background: rgba(255,255,255,.85);
  color: var(--tx); text-align: center;
  font-family: 'Times New Roman', Times, serif;
  outline: none;
  transition: border .15s, background .15s, box-shadow .15s;
}
.edit-input:focus {
  border-color: var(--blue2);
  background: var(--surf);
  box-shadow: 0 0 0 3px rgba(13,95,163,.13);
}

.action-wrap {
  display: flex; gap: 4px; justify-content: center;
  flex-direction: column; align-items: stretch;
}
.btn-sm {
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
  padding: 5px 8px; font-size: .74rem; font-weight: 700;
  border-radius: var(--rs); border: 1.5px solid; cursor: pointer;
  transition: all .14s; font-family: inherit; white-space: nowrap;
}
.btn-edit    { background: #e0f2fe; color: var(--blue2); border-color: #7ab7d4; }
.btn-edit:hover  { background: #cae8fc; }
.btn-save   { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.btn-save:hover  { background: #b8edda; }
.btn-cancel {
  background: #fde8ea; color: var(--danger); border-color: #f5a3aa;
}
.btn-cancel:hover { background: #fbcfd4; }

/* TOAST */
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
  font-family: 'Times New Roman', Times, serif;
}
#toast.error {
  background: linear-gradient(135deg, var(--danger), #b02030);
}
@keyframes pop {
  from { transform: scale(.88); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

#loading {
  position: fixed; inset: 0;
  background: rgba(221,238,255,.6);
  display: none; align-items: center; justify-content: center; z-index: 9998;
}
#loading .spinner {
  width: 44px; height: 44px;
  border: 4px solid var(--bd);
  border-top-color: var(--blue2);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--surf2); }
::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--tx3); }
</style>
</head>
<body>

<div id="loading"><div class="spinner"></div></div>
<div id="toast"></div>

<div id="scale-root">
<div class="W">

<!-- TOPBAR giống hệt account.php -->
<div class="top">
  <div class="ttit">
    <div class="tx-box" style="cursor:default;">
      💪 Giới Hạn Chỉ Số:
      <span style="font-family:'Times New Roman', monospace;font-size:.95em"><?= number_format($totalRows) ?></span>
    </div>
  </div>
  <div class="tr2">
    <a href="/sv1/admin/panel" class="btn bblue">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M3 10.5L12 3L21 10.5V20A1 1 0 0 1 20 21H15V14H9V21H4A1 1 0 0 1 3 20V10.5Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
      </svg>
      <span>Panel</span>
    </a>
    <button class="btn bteal" onclick="location.reload()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M20 12A8 8 0 1 1 17.3 6M20 4V12H12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Làm Mới</span>
    </button>
  </div>
</div>

<!-- Bảng hiển thị giới hạn chỉ số -->
<div class="tc"><div class="tsc">
<table>
<colgroup>
  <col class="c-id">
  <col class="c-pow">
  <col class="c-hp">
  <col class="c-mp">
  <col class="c-dmg">
  <col class="c-def">
  <col class="c-crit">
  <col class="c-act">
</colgroup>
<thead>
  <tr class="header-row">
    <th>ID</th>
    <th>⚡ Power</th>
    <th>❤️ HP</th>
    <th>💧 KI</th>
    <th>⚔️ Damege</th>
    <th>🛡️ Def</th>
    <th>🎯 Crit</th>
    <th>✏️ VIP</th>
  </tr>
</thead>
<tbody>
<?php foreach ($rows as $row): 
    $id = $row['id'];
?>
<tr data-id="<?= $id ?>">
  <td><span class="no-edit view-value"><?= $id ?></span></td>
  <td><span class="view-value" data-field="power" data-original="<?= $row['power'] ?>"><?= number_format($row['power'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="hp" data-original="<?= $row['hp'] ?>"><?= number_format($row['hp'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="mp" data-original="<?= $row['mp'] ?>"><?= number_format($row['mp'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="damage" data-original="<?= $row['damage'] ?>"><?= number_format($row['damage'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="defense" data-original="<?= $row['defense'] ?>"><?= number_format($row['defense'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="critical" data-original="<?= $row['critical'] ?>"><?= number_format($row['critical'], 0, ',', '.') ?></span></td>
  <td>
    <div class="action-wrap">
      <button class="btn-sm btn-edit" onclick="editRow(this)">✏️ Sửa</button>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($rows)): ?>
<tr><td colspan="8" style="padding:32px;color:var(--tx3);font-style:italic">Không có dữ liệu giới hạn chỉ số.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div></div>

</div><!-- /W -->
</div><!-- /scale-root -->

<script>
/* ================= SCALE ================= */
const DESIGN_WIDTH = 1500;
const root = document.getElementById('scale-root');
function applyScale() {
    const vw = window.innerWidth;
    const ratio = (vw * 0.98) / DESIGN_WIDTH;
    root.style.transform = `scale(${ratio})`;
    root.style.transformOrigin = 'top left';
    root.style.marginLeft = '1vw';
    document.body.style.height = (root.offsetHeight * ratio) + 'px';
    document.body.style.width  = '100%';
}
applyScale();
window.addEventListener('resize', applyScale);

/* ================= TOAST / LOADING ================= */
function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = type; t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2200);
}
function showLoading(v) { document.getElementById('loading').style.display = v ? 'flex' : 'none'; }

/* ================= EDIT MODE LOGIC (giống account.php) ================= */
function editRow(btn) {
    const tr = btn.closest('tr');
    if (tr.classList.contains('editing')) return;

    tr.classList.add('editing');

    // Lưu lại giá trị gốc
    const originalValues = {};
    const editableSpans = tr.querySelectorAll('td .view-value[data-field]');
    editableSpans.forEach(span => {
        const field = span.getAttribute('data-field');
        originalValues[field] = span.getAttribute('data-original') || span.innerText;
    });

    // Ẩn nút Sửa gốc (sẽ thay thế tạm thời)
    const actionDiv = tr.querySelector('.action-wrap');
    const editBtn = actionDiv.querySelector('.btn-edit');
    editBtn.style.display = 'none';

    // Tạo input cho từng trường có thể sửa
    editableSpans.forEach(span => {
        const field = span.getAttribute('data-field');
        const currentValue = span.getAttribute('data-original') || span.innerText;
        const td = span.parentNode;
        let inputEl = document.createElement('input');
        inputEl.type = 'text';
        // Hiển thị giá trị thô (không dấu chấm) để dễ chỉnh sửa
        let rawValue = currentValue.toString().replace(/\./g, '');
        inputEl.value = rawValue;
        inputEl.classList.add('edit-input');
        inputEl.setAttribute('data-field', field);
        
        // Xử lý sự kiện nhập: chỉ cho phép số
        inputEl.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^\d]/g, '');
        });
        
        span.style.display = 'none';
        td.appendChild(inputEl);
        inputEl.focus();
    });

    // Thay nút Sửa bằng Lưu + Hủy
    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn-sm btn-save';
    saveBtn.innerHTML = '💾 Lưu';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-sm btn-cancel';
    cancelBtn.innerHTML = '✖️ Hủy';
    
    actionDiv.appendChild(saveBtn);
    actionDiv.appendChild(cancelBtn);
    
    saveBtn.onclick = () => saveEdit(tr, originalValues);
    cancelBtn.onclick = () => cancelEdit(tr, originalValues);
}

async function saveEdit(tr, originalValues) {
    const id = tr.getAttribute('data-id');
    const inputs = tr.querySelectorAll('.edit-input');
    const data = {
        action: 'save',
        id: id,
        power: '',
        hp: '',
        mp: '',
        damage: '',
        defense: '',
        critical: ''
    };
    inputs.forEach(inp => {
        const field = inp.getAttribute('data-field');
        if (field && data.hasOwnProperty(field)) {
            let value = inp.value.replace(/[^\d]/g, '');
            if (value === '') value = '0';
            data[field] = value;
        }
    });

    const fields = ['power','hp','mp','damage','defense','critical'];
    fields.forEach(f => {
        if (!data[f]) data[f] = originalValues[f] || '0';
    });

    showLoading(true);
    try {
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: new URLSearchParams(data)
        });
        const json = await res.json();
        if (json.success) {
            // Xóa input trước
            inputs.forEach(inp => inp.remove());
            // Hiện lại span và cập nhật giá trị mới
            const spans = tr.querySelectorAll('.view-value[data-field]');
            spans.forEach(span => {
                const field = span.getAttribute('data-field');
                const newVal = data[field];
                if (newVal !== undefined) {
                    const formatted = Number(newVal).toLocaleString('vi-VN');
                    span.innerText = formatted;
                    span.setAttribute('data-original', newVal);
                }
                span.style.display = 'inline-block';
            });
            showToast(`✅ Đã lưu giới hạn chỉ số ID ${id}`);
        } else {
            showToast('❌ Lỗi: ' + (json.error || 'Không xác định'), 'error');
            showLoading(false);
            return;
        }
    } catch(e) {
        showToast('❌ Lỗi kết nối!', 'error');
        showLoading(false);
        return;
    }
    showLoading(false);
    finishEdit(tr);
}

function cancelEdit(tr, originalValues) {
    // Xóa input trước, rồi mới hiện span
    const inputs = tr.querySelectorAll('.edit-input');
    inputs.forEach(inp => inp.remove());
    const spans = tr.querySelectorAll('.view-value');
    spans.forEach(span => span.style.display = 'inline-block');
    finishEdit(tr);
}

function finishEdit(tr) {
    tr.classList.remove('editing');
    const actionDiv = tr.querySelector('.action-wrap');
    // Xóa nút Lưu và Hủy nếu có
    const saveBtn = actionDiv.querySelector('.btn-save');
    const cancelBtn = actionDiv.querySelector('.btn-cancel');
    if (saveBtn) saveBtn.remove();
    if (cancelBtn) cancelBtn.remove();
    // Hiện lại nút Sửa
    const editBtn = actionDiv.querySelector('.btn-edit');
    if (editBtn) editBtn.style.display = 'inline-flex';
}
</script>
</body>
</html>
