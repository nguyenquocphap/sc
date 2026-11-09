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

$csrfToken = ensure_csrf_token();

// ========== XỬ LÝ POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    header('Content-Type: application/json');

    if ($_POST['action'] === 'save_intrinsic') {
        $id           = (int)$_POST['intrinsic_id'];
        $name         = trim($_POST['name']);
        $param_from_1 = (int)$_POST['param_from_1'];
        $param_to_1   = (int)$_POST['param_to_1'];
        $param_from_2 = (int)$_POST['param_from_2'];
        $param_to_2   = (int)$_POST['param_to_2'];
        $icon         = (int)$_POST['icon'];

        try {
            $pdo->prepare(
                "UPDATE intrinsic SET NAME=?, param_from_1=?, param_to_1=?, param_from_2=?, param_to_2=?, icon=? WHERE id=?"
            )->execute([$name, $param_from_1, $param_to_1, $param_from_2, $param_to_2, $icon, $id]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    exit;
}

// === LOAD DATA - không phân trang, bỏ id = 0 ===
$list = $pdo->query(
    "SELECT id, NAME, param_from_1, param_to_1, param_from_2, param_to_2, icon, gender
     FROM intrinsic WHERE id > 0 ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($list); // tổng số sau khi lọc
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Quản Lý Nội Tại</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
:root {
  --bg:      #ddeeff;
  --surf:    #ffffff;
  --surf2:   #f0f7ff;
  --bd:      #9bbedd;
  --bd2:     #7aaacf;
  --tx:      #09131f;
  --tx2:     #1a3a5c;
  --tx3:     #4a7a9b;
  --blue:    #0d5fa3;
  --blue2:   #1272c2;
  --indigo:  #6c3fc7;
  --teal:    #0a8a85;
  --success: #0a8a5a;
  --danger:  #d92b3a;
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
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; scroll-behavior: smooth; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14.3px; line-height: 1.6;
  background: var(--bg); color: var(--tx);
  min-height: 100dvh; -webkit-font-smoothing: antialiased; overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0;
  pointer-events: none; z-index: 0;
  background-image: radial-gradient(circle, var(--bd) 1px, transparent 1px);
  background-size: 22px 22px; opacity: .45;
}
#scaler { position: relative; z-index: 1; width: var(--page-w); transform-origin: top left; }
.W { width: 100%; padding: 14px 12px 28px; }

/* TOPBAR */
.top {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; background: var(--surf); border: 1px solid var(--bd2);
  border-radius: var(--r); padding: 16px 22px 14px;
  margin-bottom: 10px; box-shadow: var(--sh);
  position: relative; overflow: hidden; text-align: center;
}
.tx-box {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 11px 26px; border-radius: 999px;
  background: linear-gradient(135deg, #a78bfa, #c4b5fd);
  color: #fff; font-weight: 800; font-size: 1.05rem;
  box-shadow: 0 6px 18px rgba(167,139,250,.4);
  border: 1px solid rgba(255,255,255,.3);
  letter-spacing: .3px; cursor: pointer;
  transition: all .15s; line-height: 1.3;
}
.tx-box:hover { filter: brightness(1.07); transform: translateY(-1px); }
.tr2 { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center; }
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 11px 20px; border: none; border-radius: var(--rs);
  font-family: inherit; font-size: .9rem; font-weight: 700;
  cursor: pointer; text-decoration: none; white-space: nowrap;
  transition: all .15s; letter-spacing: .2px; line-height: 1;
}
.btn:hover  { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.15); }
.btn:active { transform: scale(.96); }
.bblue { background: linear-gradient(135deg, var(--blue2), var(--indigo)); color: #fff; box-shadow: 0 3px 12px var(--glow); }
.bteal { background: linear-gradient(135deg, var(--teal), #0d9488); color: #fff; }

/* TABLE */
.tc  { background: var(--surf); border: 1px solid var(--bd2); border-radius: var(--r); overflow: hidden; box-shadow: var(--sh); }
.tsc { overflow-x: auto; -webkit-overflow-scrolling: touch; }
table { width: 100%; table-layout: fixed; border-collapse: collapse; }

/* Độ rộng cột (sau khi ẩn Param 2) */
.col-id     { width: 8%;  }
.col-name   { width: 48%; }
.col-p1     { width: 18%; }
.col-icon   { width: 12%; }
.col-gender { width: 10%; }
.col-act    { width: 8%; }

thead tr { background: linear-gradient(90deg, var(--blu-lt), var(--ind-lt)); border-bottom: 2px solid var(--bd2); }
th {
  padding: 12px 8px 9px; font-size: .85rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .5px;
  color: #09131f; text-align: center; word-break: break-word;
}
tbody tr { border-bottom: 1px solid var(--bd); transition: background .12s; }
tbody tr:nth-child(even) { background: rgba(13,95,163,.03); }
tbody tr:hover { background: var(--sky-lt); }
td { padding: 9px 6px; vertical-align: middle; text-align: center; word-break: break-word; }

/* Textarea for name */
.cell-textarea {
  width: 100%; min-height: 52px;
  background: transparent; border: 1.5px solid transparent;
  border-radius: var(--rs); color: var(--success);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: .84rem; font-weight: 800;
  padding: 2px; outline: none; resize: none;
  transition: border-color .15s, background .15s;
  white-space: pre-wrap; word-break: break-word;
  line-height: 1.45;
}
.cell-textarea:not(:disabled) {
  background: var(--surf2); border-color: var(--bd2);
  padding: 5px 7px; resize: vertical; cursor: text;
}
.cell-textarea:not(:disabled):focus { border-color: var(--blue2); background: #fff; }

/* Param pair (chỉ còn param 1) */
.param-block { display: flex; flex-direction: column; gap: 4px; width: 100%; }
.param-row   { display: flex; align-items: center; gap: 4px; }
.param-row label { font-size: .67rem; font-weight: 700; color: var(--tx3); width: 30px; text-align: right; flex-shrink: 0; }
.param-input {
  flex: 1; background: transparent; border: 1.5px solid transparent;
  border-radius: var(--rs); color: var(--tx2);
  font-family: 'JetBrains Mono', monospace;
  font-size: .82rem; font-weight: 800;
  padding: 2px 4px; outline: none; text-align: center;
  transition: border-color .15s, background .15s;
}
.param-input:not(:disabled) { background: var(--surf2); border-color: var(--bd2); }
.param-input:not(:disabled):focus { border-color: var(--blue2); background: #fff; }

/* Icon cell */
.icon-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.icon-img  { width: 48px; height: 48px; object-fit: contain; border-radius: 8px; border: 2px solid var(--bd); image-rendering: pixelated; }
.icon-num-input {
  width: 72px; text-align: center;
  background: transparent; border: 1.5px solid transparent;
  border-radius: var(--rs); color: var(--tx2);
  font-family: 'JetBrains Mono', monospace;
  font-size: .78rem; font-weight: 800;
  padding: 2px 4px; outline: none;
  transition: border-color .15s, background .15s;
}
.icon-num-input:not(:disabled) { background: var(--surf2); border-color: var(--bd2); }
.icon-num-input:not(:disabled):focus { border-color: var(--blue2); background: #fff; }

/* Gender */
.gender-img   { width: 46px; height: 46px; object-fit: cover; border-radius: 8px; border: 2px solid var(--bd2); }
.gender-label { font-size: .67rem; color: var(--tx3); font-weight: 600; margin-top: 2px; }

/* Buttons */
.action-cell { display: flex; flex-direction: column; gap: 5px; align-items: stretch; }
.btn-sm {
  display: inline-flex; align-items: center; justify-content: center;
  width: 100%; padding: 6px 0; font-size: .75rem; font-weight: 700;
  border-radius: var(--rs); border: 1.5px solid; cursor: pointer;
  transition: all .14s; font-family: inherit;
}
.btn-edit-p   { background: #e0f2fe; color: var(--blue2);  border-color: #7ab7d4; }
.btn-edit-p:hover   { background: #cae8fc; }
.btn-save-p   { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.btn-save-p:hover   { background: #b8edda; }
.btn-cancel-p { background: #fde8ea; color: var(--danger);  border-color: #f5a3aa; }
.btn-cancel-p:hover { background: #fbcfd4; }

.name-search {
  width: 95%; max-width: 180px; padding: 5px 8px;
  background: var(--surf2); border: 1.5px solid var(--bd2);
  border-radius: var(--rs); color: var(--tx);
  font-weight: 600; font-size: .75rem; margin-top: 4px;
}

/* TOAST */
#toast {
  position: fixed; bottom: 32px; right: 32px; z-index: 9999;
  font-size: 1.5rem; padding: 21px 33px; border-radius: 18px;
  background: linear-gradient(135deg, var(--blue), var(--blue2));
  color: #fff; font-weight: 800;
  box-shadow: 0 6px 24px rgba(13,95,163,.35);
  border: 2px solid rgba(255,255,255,.25);
  display: none; animation: pop .22s ease;
  max-width: 480px; font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1.2;
}
#toast.error { background: linear-gradient(135deg, var(--danger), #b02030); }
@keyframes pop {
  from { transform: scale(.88); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--surf2); }
::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--tx3); }
</style>
</head>
<body>
<div id="scaler">
<div class="W">

<!-- TOPBAR -->
<div class="top">
  <div class="tx-box">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
      <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/>
      <path d="M2 17l10 5 10-5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M2 12l10 5 10-5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Quản Lý Nội Tại —
    <span style="font-family:'JetBrains Mono',monospace;font-size:.95em"><?= number_format($total) ?></span>
  </div>
  <div class="tr2">
    <a href="/sv1/admin/panel" class="btn bblue">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M3 10.5L12 3L21 10.5V20A1 1 0 0 1 20 21H15V14H9V21H4A1 1 0 0 1 3 20V10.5Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
      </svg>
      Panel
    </a>
    <button class="btn bteal" onclick="location.reload()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
        <path d="M20 12A8 8 0 1 1 17.3 6M20 4V12H12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Làm Mới
    </button>
  </div>
</div>

<!-- TABLE (không phân trang) -->
<div class="tc"><div class="tsc">
<table>
<colgroup>
  <col class="col-id">
  <col class="col-name">
  <col class="col-p1">
  <col class="col-icon">
  <col class="col-gender">
  <col class="col-act">
</colgroup>
<thead>
<tr>
  <th>ID</th>
  <th>
    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
      <span>Tên Nội Tại</span>
      <input type="text" id="searchNT" class="name-search" placeholder="🔍 Tìm tên...">
    </div>
  </th>
  <th>Chỉ Số<br><small style="font-weight:600;font-size:.72rem;opacity:.7">Min / Max</small></th>
  <th>Icon</th>
  <th>Gender</th>
  <th>Hành Động</th>
</tr>
</thead>
<tbody id="ntTableBody">
<?php foreach ($list as $row):
  $g = (int)$row['gender'];
  $genderSrc = match($g) {
    0 => '/img/td.jpg',   // Trái Đất
    1 => '/img/nm.jpg',   // Namek
    2 => '/img/xd.jpg',   // Xayda
    3 => '/img/v1.png',   // Tất cả
    default => '/img/v1.png',
  };
  $genderLabel = match($g) {
    0 => 'Trái Đất',
    1 => 'Namek',
    2 => 'Xayda',
    3 => 'Tất cả',
    default => "Gender $g",
  };
  $nameEsc = htmlspecialchars($row['NAME'], ENT_QUOTES);
?>
<tr class="nt-row"
    data-id="<?= $row['id'] ?>"
    data-name="<?= mb_strtolower(htmlspecialchars($row['NAME'])) ?>"
    data-editing="false"
    data-orig-name="<?= $nameEsc ?>"
    data-orig-pf1="<?= (int)$row['param_from_1'] ?>"
    data-orig-pt1="<?= (int)$row['param_to_1'] ?>"
    data-orig-pf2="<?= (int)$row['param_from_2'] ?>"
    data-orig-pt2="<?= (int)$row['param_to_2'] ?>"
    data-orig-icon="<?= (int)$row['icon'] ?>">

  <!-- ID -->
  <td>
    <span style="font-family:'JetBrains Mono',monospace;font-size:.78rem;color:var(--tx3);font-weight:700;">#<?= $row['id'] ?></span>
  </td>

  <!-- NAME -->
  <td>
    <textarea class="cell-textarea" disabled><?= $nameEsc ?></textarea>
  </td>

  <!-- PARAM 1 -->
  <td>
    <div class="param-block">
      <div class="param-row">
        <label>From</label>
        <input type="number" class="param-input inp-pf1" value="<?= (int)$row['param_from_1'] ?>" min="0" disabled>
      </div>
      <div class="param-row">
        <label>To</label>
        <input type="number" class="param-input inp-pt1" value="<?= (int)$row['param_to_1'] ?>" min="0" disabled>
      </div>
    </div>
  </td>

  <!-- ICON -->
  <td>
    <div class="icon-wrap">
      <img class="icon-img icon-preview"
           src="/icon/<?= (int)$row['icon'] ?>.png"
           onerror="this.src='/icon/1.png'"
           title="Icon #<?= (int)$row['icon'] ?>">
      <input type="number" class="icon-num-input inp-icon"
             value="<?= (int)$row['icon'] ?>" min="0" disabled>
    </div>
  </td>

  <!-- GENDER -->
  <td>
    <div style="display:flex;flex-direction:column;align-items:center;gap:3px;">
      <img class="gender-img" src="<?= $genderSrc ?>" onerror="this.style.display='none'" title="<?= $genderLabel ?>">
      <span class="gender-label"><?= $genderLabel ?></span>
    </div>
  </td>

  <!-- ACTION -->
  <td>
    <div class="action-cell">
      <button class="btn-sm btn-edit-p" onclick="enableEdit(this)">✏️ Sửa</button>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

</div><!-- /W -->
</div><!-- /scaler -->

<div id="toast"></div>

<script>
(function () {
  var PAGE_W = 1500, FILL = 0.98;
  var scaler = document.getElementById('scaler');
  function applyScale() {
    var vw    = window.innerWidth;
    var avail = vw * FILL;
    var ratio = avail / PAGE_W;
    scaler.style.width           = PAGE_W + 'px';
    scaler.style.transform       = 'scale(' + ratio + ')';
    scaler.style.transformOrigin = 'top left';
    scaler.style.marginLeft      = ((vw - avail) / 2) + 'px';
    document.body.style.minHeight = (scaler.offsetHeight * ratio) + 'px';
  }
  applyScale();
  window.addEventListener('resize', applyScale);
})();

function showToast(msg, isError) {
  const t = $('#toast');
  t.text(msg).removeClass('error');
  if (isError) t.addClass('error');
  t.stop(true).fadeIn(200).delay(2400).fadeOut(400);
}

$('#searchNT').on('keyup', function () {
  const val = $(this).val().toLowerCase().trim();
  $('#ntTableBody tr').each(function () {
    $(this).toggle(($(this).attr('data-name') || '').includes(val));
  });
});

function enableEdit(btn) {
  const $row = $(btn).closest('tr');
  if ($row.attr('data-editing') === 'true') return;
  $row.attr('data-editing', 'true');

  $row.find('.cell-textarea').prop('disabled', false);
  $row.find('.inp-pf1, .inp-pt1, .inp-icon').prop('disabled', false);

  const $ac = $row.find('.action-cell');
  $ac.find('.btn-edit-p').hide();
  $ac.append('<button class="btn-sm btn-save-p"   onclick="saveEdit(this)">💾 Lưu</button>');
  $ac.append('<button class="btn-sm btn-cancel-p" onclick="cancelEdit(this)">✖️ Hủy</button>');

  $row.find('.inp-icon').off('input').on('input', function () {
    const v = parseInt($(this).val()) || 0;
    $row.find('.icon-preview').attr('src', '/icon/' + v + '.png');
  });
}

function cancelEdit(btn) {
  const $row = $(btn).closest('tr');
  $row.find('.cell-textarea').val($row.attr('data-orig-name')).prop('disabled', true);
  $row.find('.inp-pf1').val($row.attr('data-orig-pf1')).prop('disabled', true);
  $row.find('.inp-pt1').val($row.attr('data-orig-pt1')).prop('disabled', true);
  $row.find('.inp-icon').val($row.attr('data-orig-icon')).prop('disabled', true);
  $row.find('.icon-preview').attr('src', '/icon/' + $row.attr('data-orig-icon') + '.png');
  $row.find('.btn-save-p, .btn-cancel-p').remove();
  $row.find('.btn-edit-p').show();
  $row.attr('data-editing', 'false');
}

async function saveEdit(btn) {
  const $row = $(btn).closest('tr');
  const id          = $row.data('id');
  const name        = $row.find('.cell-textarea').val().trim();
  const param_from_1 = parseInt($row.find('.inp-pf1').val()) || 0;
  const param_to_1   = parseInt($row.find('.inp-pt1').val()) || 0;
  // Giữ nguyên param2 từ data cũ
  const param_from_2 = parseInt($row.attr('data-orig-pf2')) || 0;
  const param_to_2   = parseInt($row.attr('data-orig-pt2')) || 0;
  const icon         = parseInt($row.find('.inp-icon').val()) || 0;

  try {
    const res = await $.post('', {
      csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '',
      action: 'save_intrinsic',
      intrinsic_id: id,
      name, param_from_1, param_to_1, param_from_2, param_to_2, icon
    });
    if (res.ok) {
      showToast('✅ Đã lưu nội tại #' + id + '!');
      $row.attr('data-orig-name', name);
      $row.attr('data-orig-pf1',  param_from_1);
      $row.attr('data-orig-pt1',  param_to_1);
      $row.attr('data-orig-icon', icon);
      $row.attr('data-name', name.toLowerCase());
      $row.find('.cell-textarea').prop('disabled', true);
      $row.find('.inp-pf1, .inp-pt1, .inp-icon').prop('disabled', true);
      $row.find('.btn-save-p, .btn-cancel-p').remove();
      $row.find('.btn-edit-p').show();
      $row.attr('data-editing', 'false');
    } else {
      showToast('❌ Lỗi: ' + (res.msg || 'Không rõ'), true);
    }
  } catch (e) {
    showToast('❌ Lỗi kết nối', true);
  }
}
</script>
</body>
</html>
