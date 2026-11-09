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

// ── Handle AJAX update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    require_csrf_token();
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $rawId = $_POST['id'] ?? '';
    $id    = intval($rawId);

    if ($id < 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ (nhận: ' . htmlspecialchars($rawId) . ')']);
        exit;
    }

    $type        = trim($_POST['type']          ?? '');
    $gender      = trim($_POST['gender']        ?? '');
    $name        = trim($_POST['name']          ?? '');
    $description = trim($_POST['description']   ?? '');
    $icon_id     = intval($_POST['icon_id']     ?? 0);
    $head        = intval($_POST['head']        ?? 0);
    $body        = intval($_POST['body']        ?? 0);
    $leg         = intval($_POST['leg']         ?? 0);
    $is_up_to_up = intval($_POST['is_up_to_up'] ?? 0);

    try {
        $stmt = $pdo->prepare("UPDATE item_template
            SET TYPE=:type, gender=:gender, NAME=:name, description=:desc,
                icon_id=:icon_id, head=:head, body=:body, leg=:leg, is_up_to_up=:iutu
            WHERE id=:id");
        $stmt->execute([
            ':type'   => $type,   ':gender' => $gender,
            ':name'   => $name,   ':desc'   => $description,
            ':icon_id'=> $icon_id,':head'   => $head,
            ':body'   => $body,   ':leg'    => $leg,
            ':iutu'   => $is_up_to_up, ':id' => $id,
        ]);
        echo json_encode(['status' => 'success', 'id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Pagination & filter ──
$page    = max(1, intval($_GET['page']   ?? 1));
$perPage = 500;
$offset  = ($page - 1) * $perPage;

$filterCol = $_GET['filter_col'] ?? '';
$filterVal = trim($_GET['filter_val'] ?? '');

$allowedCols = ['id','TYPE','gender','NAME','description','icon_id','head','body','leg','is_up_to_up'];

$where  = '';
$params = [];

if ($filterVal !== '') {
    if ($filterCol !== '' && in_array($filterCol, $allowedCols)) {
        $where = "WHERE `$filterCol` LIKE :fv";
        $params[':fv'] = '%' . $filterVal . '%';
    } else {
        $where = "WHERE (id LIKE :fv OR TYPE LIKE :fv OR gender LIKE :fv OR NAME LIKE :fv
                      OR description LIKE :fv OR icon_id LIKE :fv OR head LIKE :fv
                      OR body LIKE :fv OR leg LIKE :fv OR is_up_to_up LIKE :fv)";
        $params[':fv'] = '%' . $filterVal . '%';
    }
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM item_template $where");
$totalStmt->execute($params);
$total      = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$params[':limit']  = $perPage;
$params[':offset'] = $offset;
$stmt = $pdo->prepare("SELECT * FROM item_template $where ORDER BY id ASC LIMIT :limit OFFSET :offset");
foreach ($params as $k => &$v) {
    $stmt->bindValue($k, $v, ($k === ':limit' || $k === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR);
}
unset($v);
$stmt->execute();
$items = $stmt->fetchAll();

// Quick view — all items for modal
$quickItems = $pdo->query("SELECT id, NAME, icon_id FROM item_template ORDER BY id ASC")->fetchAll();

$colOpts = ['id'=>'ID','TYPE'=>'Type','gender'=>'Gen','NAME'=>'Tên',
            'description'=>'Mô Tả','icon_id'=>'Icon_ID',
            'head'=>'H','body'=>'B','leg'=>'L','is_up_to_up'=>'Cộng dồn'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<title>Quản Lý Vật Phẩm</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script>
(function(){
  function setPageZoom(){
    var zoom = (document.documentElement.clientWidth * 0.98) / 1500;
    document.documentElement.style.setProperty('--page-zoom', zoom);
  }
  setPageZoom();
  window.__setAdminPageZoom = setPageZoom;
})();
</script>
<style>
/* ── TOKENS ───────────────────────────────────── */
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
  --success:#0a8a5a;
  --danger:#d92b3a;
  --warn:#c47a00;
  --blu-lt:#cce3f8;
  --ind-lt:#e6deff;
  --sky-lt:#dbeeff;
  --glow:rgba(13,95,163,.15);
  --r:13px; --rs:8px;
  --sh:0 4px 24px rgba(13,95,163,.13);
  --sh2:0 1px 8px rgba(13,95,163,.08);

  /* ── LAYOUT: fixed 1500px, always fills 98vw ── */
  --page-w:1500px;
  --page-zoom:1;
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;scroll-behavior:smooth;overflow-y:scroll}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  line-height:1.6;
  background:var(--bg);color:var(--tx);min-height:100dvh;
  -webkit-font-smoothing:antialiased;
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:radial-gradient(circle,var(--bd) 1px,transparent 1px);
  background-size:22px 22px;opacity:.45;
}

/*
  ── PAGE WRAPPER ──────────────────────────────
  1500px design baseline.
  zoom is set by JS → browser handles layout
  reflow automatically (no height hacks needed).
*/
.W{
  position:relative;z-index:1;
  width:var(--page-w);           /* fixed 1500px */
  zoom:var(--page-zoom);
  margin:0 auto;
  padding:14px 14px 28px;
}

/* ── TOPBAR ──────────────────────────────────── */
.top{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:8px;
  background:var(--surf);border:1px solid var(--bd2);
  border-radius:var(--r);padding:16px 22px 14px;
  margin-bottom:10px;box-shadow:var(--sh);
  position:relative;overflow:hidden;text-align:center;
}

.ttit{font-size:1.35rem;font-weight:800;letter-spacing:-.4px;color:var(--tx)}
.tx-box{
  display:inline-flex;align-items:center;gap:10px;
  padding:11px 26px;border-radius:999px;
  background:linear-gradient(135deg,#a78bfa,#c4b5fd);
  color:#fff;font-weight:800;font-size:1.05rem;
  box-shadow:0 6px 18px rgba(167,139,250,.4);
  border:1px solid rgba(255,255,255,.3);
  letter-spacing:.3px;cursor:pointer;
  transition:all .15s;line-height:1.3;
}
.tx-ic{opacity:.95;filter:drop-shadow(0 0 4px rgba(255,255,255,.6));transition:.2s}
.tx-box:hover{filter:brightness(1.07);transform:translateY(-1px)}
.tx-box:hover .tx-ic{transform:scale(1.15) rotate(6deg)}

.tr2{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center}
.btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:11px 20px;border:none;border-radius:var(--rs);
  font-family:inherit;font-size:.9rem;font-weight:700;
  cursor:pointer;text-decoration:none;white-space:nowrap;
  transition:all .15s;letter-spacing:.2px;
  line-height:1;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.15)}
.btn:active{transform:scale(.96)}
.bblue{background:linear-gradient(135deg,var(--blue2),var(--indigo));color:#fff;box-shadow:0 3px 12px var(--glow)}
.bteal{background:linear-gradient(135deg,var(--teal),#0d9488);color:#fff}
.bpurple{background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;box-shadow:0 3px 12px rgba(124,58,237,.25)}

/* ── PAGINATION ──────────────────────────────── */
.pg{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:5px;margin:9px 0 13px}
.pb{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:37px;height:37px;padding:0 10px;
  background:var(--surf);border:1.5px solid var(--bd2);
  border-radius:var(--rs);color:var(--tx2);
  font-size:.86rem;font-weight:600;text-decoration:none;
  font-family:inherit;transition:all .14s;
}
.pb:hover:not(.on){border-color:var(--blue2);color:var(--blue);background:var(--blu-lt);transform:translateY(-2px)}
.pb.on{background:linear-gradient(135deg,var(--blue2),var(--indigo));border-color:transparent;color:#fff;font-weight:800;box-shadow:0 4px 12px var(--glow)}
.pd{color:var(--tx3);font-size:.86rem;padding:0 2px}
.pinfo{color:var(--tx3);font-size:.82rem;margin-left:4px}

/* ── TABLE WRAP ──────────────────────────────── */
.tc{
  background:var(--surf);border:1px solid var(--bd2);
  border-radius:var(--r);overflow:hidden;
  box-shadow:var(--sh);margin-bottom:11px;
}
.tsc{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{
  width:100%;border-collapse:collapse;
  /* table is always 1500px minus wrapper padding = fills exactly */
  table-layout:fixed;
}
thead tr{
  background:linear-gradient(90deg,var(--blu-lt),var(--ind-lt));
  border-bottom:2px solid var(--bd2);
}
th{
  padding:12px 8px 9px;
  font-size:.95rem;font-weight:800;
  text-transform:uppercase;letter-spacing:.7px;
  color:#09131f;white-space:nowrap;
  text-align:center;vertical-align:top;
}

col.c-id     { width:52px }
col.c-icon   { width:52px }
col.c-type   { width:60px }
col.c-gen    { width:60px }
col.c-name   { width:260px }
col.c-desc   { width:280px }
col.c-iconid { width:95px }
col.c-head   { width:58px }
col.c-body   { width:58px }
col.c-leg    { width:58px }
col.c-don    { width:85px }
col.c-act    { width:75px }

/* ── COLUMN SEARCH IN HEADER ─────────────────── */
.col-search{
  margin-top:5px;position:relative;
  font-weight:400;text-transform:none;letter-spacing:0;
}
.col-search input{
  width:100%;
  padding:4px 7px 4px 24px;
  background:rgba(255,255,255,.88);
  border:1.5px solid var(--bd2);
  border-radius:6px;color:var(--tx);
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:.82rem;font-weight:500;
  outline:none;transition:border-color .18s,box-shadow .18s;
}
.col-search input:focus{
  border-color:var(--blue2);
  box-shadow:0 0 0 3px rgba(13,95,163,.15);
  background:#fff;
}
.col-search input::placeholder{color:var(--tx3)}
.col-search .cs-ic{
  position:absolute;left:6px;top:50%;transform:translateY(-50%);
  color:var(--tx3);font-size:.68rem;pointer-events:none;
}
th.c-id-th .col-search input{ width:46px }

tbody tr{border-bottom:1px solid var(--bd);transition:background .12s}
tbody tr:nth-child(even){background:rgba(13,95,163,.03)}
tbody tr:hover{background:var(--sky-lt)}
td{
  padding:7px 8px;vertical-align:middle;
  text-align:center;color:var(--tx);
  border-left:none !important;border-right:none !important;
  overflow:hidden;
}

/* search result count row */
.cnt-tr td{
  padding:3px 10px 5px;background:var(--surf2);
  border-bottom:1px solid var(--bd);
  text-align:center;font-size:.72rem;color:var(--tx3);
}

/* ── CELL TYPES ──────────────────────────────── */
.cell-id{color:var(--indigo);font-weight:800;font-family:'JetBrains Mono',monospace;font-size:.82rem}
.icon-img{width:36px;height:36px;object-fit:contain;image-rendering:pixelated;display:block;margin:0 auto}
.icon-ph{width:36px;height:36px;background:var(--bd);border-radius:4px;display:none;align-items:center;justify-content:center;color:var(--tx3);font-size:.6rem;margin:0 auto}

/* ── EDITABLE INPUTS ─────────────────────────── */
.editable{
  background:transparent;border:1.5px solid transparent;
  color:var(--tx);font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;
  padding:3px 6px;border-radius:5px;width:100%;text-align:center;
  transition:border-color .15s,background .15s,box-shadow .15s;
  /* locked by default */
  pointer-events:none;user-select:none;opacity:.9;
}
/* when row is in edit mode */
tr.editing .editable{
  pointer-events:auto;user-select:auto;opacity:1;
  border-color:var(--bd2);background:var(--surf2);
}
tr.editing .editable:focus{
  outline:none;border-color:var(--blue2);
  background:#fff;
  box-shadow:0 0 0 3px rgba(13,95,163,.1);
}
textarea.editable{
  text-align:left;resize:none;min-height:2.8em;
  line-height:1.45;overflow:hidden;white-space:pre-wrap;
  word-break:break-word;display:block;
}
select.editable{cursor:default}
tr.editing select.editable{cursor:pointer}
select.editable.iutu-yes{color:var(--success);font-weight:700}

/* ── HIDE SPINNERS on number inputs ──────────── */
input[type=number].editable::-webkit-inner-spin-button,
input[type=number].editable::-webkit-outer-spin-button{ -webkit-appearance:none;margin:0 }
input[type=number].editable{ -moz-appearance:textfield }

/* ── ACTION BUTTON (Edit / Save) ─────────────── */
.action-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:5px;
  border:none;border-radius:6px;
  padding:5px 10px;
  font-family:inherit;font-size:.78rem;font-weight:700;
  cursor:pointer;transition:all .15s;white-space:nowrap;width:100%;
}
.action-btn:hover{filter:brightness(1.1);transform:translateY(-1px)}
.action-btn.edit-mode{
  background:linear-gradient(135deg,#f59e0b,#d97706);
  color:#fff;
  box-shadow:0 2px 8px rgba(245,158,11,.3);
}
.action-btn.save-mode{
  background:linear-gradient(135deg,var(--teal),#0d9488);
  color:#fff;
  box-shadow:0 2px 8px rgba(10,138,133,.3);
}
.action-btn.saving-mode{
  background:linear-gradient(135deg,var(--warn),#e09500);
  color:#fff;
}
/* Cancel button */
.cancel-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:4px;
  border:none;border-radius:6px;
  padding:5px 8px;
  font-family:inherit;font-size:.75rem;font-weight:700;
  cursor:pointer;transition:all .15s;white-space:nowrap;width:100%;
  background:linear-gradient(135deg,#ef4444,#dc2626);
  color:#fff;
  box-shadow:0 2px 8px rgba(239,68,68,.3);
  margin-top:4px;
}
.cancel-btn:hover{filter:brightness(1.1);transform:translateY(-1px)}
/* hide cancel by default, show when editing */
.cancel-btn{ display:none }
tr.editing .cancel-btn{ display:inline-flex }

/* ── TOAST — 30% smaller ─────────────────────── */
#toast{
  position:fixed;
  bottom:32px;
  right:32px;
  z-index:9999;

  font-size:1.5rem;
  padding:21px 33px;
  border-radius:18px;

  background:linear-gradient(135deg,#0d5fa3,#1272c2);
  color:#fff;
  font-weight:800;

  box-shadow:0 7px 21px rgba(13,95,163,.35);
  border:1.4px solid rgba(255,255,255,.25);

  opacity:0;
  transform:translateY(56px) scale(0.9);
  transition:all .25s ease;
  pointer-events:none;
  max-width:480px;
}
#toast.show{
  transform:translateY(0) scale(1.08);
  opacity:1;
}
#toast.ok{border-color:var(--success)}
#toast.err{border-color:var(--danger)}

/* ── MODAL ───────────────────────────────────── */
#modal-overlay{
  position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.65);
  display:none;
  align-items:center;
  justify-content:center;
  /* KHÔNG scroll overlay, KHÔNG đóng khi click ngoài */
}
#modal-overlay.open{ display:flex }

#modal-box{
  background:var(--surf);
  border:1px solid var(--bd2);
  border-radius:var(--r);
  /* ── CỐ ĐỊNH 80% ngang, 90% dọc, căn giữa ── */
  width:80vw;
  height:90vh;
  box-shadow:var(--sh);
  display:flex;
  flex-direction:column;
  overflow:hidden;
}

.modal-head{
  display:flex;align-items:center;gap:10px;
  padding:15px 20px;
  border-bottom:1px solid var(--bd2);
  flex-shrink:0;
}
.modal-head h2{font-weight:800;font-size:1.08rem;color:var(--tx);flex:1}

#modal-search{
  background:var(--surf2);border:1.5px solid var(--bd2);color:var(--tx);
  border-radius:var(--rs);padding:7px 13px;
  font-family:inherit;font-size:.88rem;width:230px;outline:none;
  transition:border-color .18s;
}
#modal-search:focus{border-color:var(--blue2)}

/* Nút X — to, tròn, đỏ — bấm X mới đóng */
.modal-close{
  background:linear-gradient(135deg,#ef4444,#dc2626);
  border:none;
  color:#fff;
  font-size:1.15rem;
  font-weight:900;
  width:38px;height:38px;
  border-radius:50%;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
  transition:all .15s;
  box-shadow:0 3px 10px rgba(220,38,38,.4);
}
.modal-close:hover{ transform:scale(1.13) rotate(90deg); filter:brightness(1.1) }

.modal-bar{
  display:flex;align-items:center;gap:6px;flex-wrap:wrap;
  padding:6px 18px;border-bottom:1px solid var(--bd2);
  font-size:.8rem;color:var(--tx3);flex-shrink:0;
}

/* body scrolls nội dung, header/search cố định trên cùng */
.modal-body{
  overflow-y:auto;
  padding:14px 16px;
  flex:1;
  -webkit-overflow-scrolling:touch;
}

/* ── GRID: 12 CỘT CỐ ĐỊNH ── */
.item-grid{
  display:grid;
  grid-template-columns:repeat(12, 1fr);
  gap:8px;
}

.item-card{
  background:var(--surf2);
  border:1px solid var(--bd);
  border-radius:var(--rs);
  padding:9px 5px 7px;
  display:flex;flex-direction:column;align-items:center;gap:4px;
  text-align:center;cursor:default;
  transition:border-color .15s,transform .15s,box-shadow .15s;
}
.item-card:hover{
  border-color:var(--blue2);
  transform:translateY(-2px);
  box-shadow:0 4px 12px var(--glow);
}
.item-card img{
  width:40px;height:40px;
  object-fit:contain;image-rendering:pixelated;
  display:block;
}
.item-card .ic-ph{
  width:40px;height:40px;
  background:var(--bd);border-radius:4px;
  display:none;
}
.item-card .ic-id{
  font-size:.67rem;color:var(--indigo);
  font-weight:700;font-family:'JetBrains Mono',monospace;
  line-height:1;
}
.item-card .ic-name{
  font-size:.63rem;color:var(--tx3);
  line-height:1.2;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  max-width:90px;
  width:100%;
}

/* ── SCROLLBAR ───────────────────────────────── */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--surf2)}
::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--tx3)}
</style>
</head>
<body>
<div class="W" id="W">

<!-- TOPBAR -->
<div class="top">
  <div class="ttit">
    <span class="tx-box" onclick="openModal()" style="cursor:pointer">
      <svg class="tx-ic" viewBox="0 0 24 24" width="16" height="16" fill="none">
        <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Check ITEM:
      <span style="font-family:'JetBrains Mono',monospace;font-size:.95em">
        <?= number_format($total) ?>
      </span>
    </span>
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

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="pg">
  <?php
    $pS = max(1,$page-3); $pE = min($totalPages,$page+3);
    if ($pS > 1){ echo '<a class="pb" href="?page=1&filter_col='.urlencode($filterCol).'&filter_val='.urlencode($filterVal).'">1</a>'; if($pS>2) echo '<span class="pd">…</span>'; }
    for ($i=$pS;$i<=$pE;$i++):
  ?>
    <a class="pb <?= $i===$page?'on':'' ?>" href="?page=<?= $i ?>&filter_col=<?= urlencode($filterCol) ?>&filter_val=<?= urlencode($filterVal) ?>"><?= $i ?></a>
  <?php endfor;
    if ($pE < $totalPages){ if($pE<$totalPages-1) echo '<span class="pd">…</span>'; echo '<a class="pb" href="?page='.$totalPages.'&filter_col='.urlencode($filterCol).'&filter_val='.urlencode($filterVal).'">'.$totalPages.'</a>'; }
  ?>
</div>
<?php endif; ?>

<!-- TABLE -->
<div class="tc"><div class="tsc">
<table>
  <colgroup>
    <col class="c-id">
    <col class="c-icon">
    <col class="c-type">
    <col class="c-gen">
    <col class="c-name">
    <col class="c-desc">
    <col class="c-iconid">
    <col class="c-head">
    <col class="c-body">
    <col class="c-leg">
    <col class="c-don">
    <col class="c-act">
  </colgroup>
  <thead>
    <tr>
      <th class="c-id-th">ID
        <div class="col-search">
          <span class="cs-ic">🔢</span>
          <input type="text" data-col="id" placeholder="ID…" oninput="colSearch()">
        </div>
      </th>
      <th>Icon</th>
      <th>Type
        <div class="col-search">
          <span class="cs-ic">🏷</span>
          <input type="text" data-col="type" placeholder="…" oninput="colSearch()">
        </div>
      </th>
      <th>Gen
        <div class="col-search">
          <span class="cs-ic">⚥</span>
          <input type="text" data-col="gender" placeholder="…" oninput="colSearch()">
        </div>
      </th>
      <th>Tên
        <div class="col-search">
          <span class="cs-ic">🔍</span>
          <input type="text" data-col="name" placeholder="Tên…" oninput="colSearch()">
        </div>
      </th>
      <th>Mô Tả
        <div class="col-search">
          <span class="cs-ic">🔍</span>
          <input type="text" data-col="desc" placeholder="Mô tả…" oninput="colSearch()">
        </div>
      </th>
      <th>Icon_ID
        <div class="col-search">
          <span class="cs-ic">🖼</span>
          <input type="text" data-col="icon_id" placeholder="Icon ID…" oninput="colSearch()">
        </div>
      </th>
      <th>Head</th>
      <th>Body</th>
      <th>Leg</th>
      <th>Up To</th>
      <th>VIP</th>
    </tr>
    <tr class="cnt-tr" id="cnt-tr" style="display:none">
      <td colspan="12"><span id="cnt-sc"></span></td>
    </tr>
  </thead>
  <tbody id="tb">
    <?php foreach ($items as $row):
      $iutu = intval($row['is_up_to_up']);
    ?>
    <tr class="item-row" data-id="<?= intval($row['id']) ?>"
        data-id-val="<?= intval($row['id']) ?>"
        data-type-val="<?= mb_strtolower(htmlspecialchars($row['TYPE'] ?? '')) ?>"
        data-gender-val="<?= mb_strtolower(htmlspecialchars($row['gender'] ?? '')) ?>"
        data-name-val="<?= mb_strtolower(htmlspecialchars($row['NAME'] ?? '')) ?>"
        data-desc-val="<?= mb_strtolower(htmlspecialchars($row['description'] ?? '')) ?>"
        data-name="<?= htmlspecialchars($row['NAME'] ?? '') ?>"
        data-icon-val="<?= intval($row['icon_id']) ?>">

      <td><span class="cell-id"><?= intval($row['id']) ?></span></td>
      <td>
        <img class="icon-img" src="/icon/<?= intval($row['icon_id']) ?>.png" alt=""
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="icon-ph">?</div>
      </td>
      <td><input class="editable" name="type"   value="<?= htmlspecialchars($row['TYPE']   ?? '') ?>" readonly></td>
      <td><input class="editable" name="gender" value="<?= htmlspecialchars($row['gender'] ?? '') ?>" readonly></td>
      <td><input class="editable" name="name"   value="<?= htmlspecialchars($row['NAME']   ?? '') ?>" readonly></td>
      <td>
        <textarea class="editable" name="description" rows="2" readonly><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
      </td>
      <td><input class="editable" name="icon_id" type="number" value="<?= intval($row['icon_id']) ?>" readonly></td>
      <td><input class="editable" name="head"    type="number" value="<?= intval($row['head']) ?>" readonly></td>
      <td><input class="editable" name="body"    type="number" value="<?= intval($row['body']) ?>" readonly></td>
      <td><input class="editable" name="leg"     type="number" value="<?= intval($row['leg']) ?>" readonly></td>
      <td>
        <select class="editable<?= $iutu===1?' iutu-yes':'' ?>" name="is_up_to_up" disabled
                onchange="this.className='editable'+(this.value==='1'?' iutu-yes':'')">
          <option value="0" <?= $iutu===0?'selected':'' ?>>0 - No</option>
          <option value="1" <?= $iutu===1?'selected':'' ?>>1 - Có</option>
        </select>
      </td>
      <td>
        <button class="action-btn edit-mode" onclick="toggleEdit(this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                  stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                  stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Sửa
        </button>
        <button class="cancel-btn" onclick="cancelEdit(this)">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
            <path d="M18 6 6 18M6 6l12 12" stroke="white" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Hủy
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>

</div><!-- /W -->

<div id="toast"></div>

<!-- MODAL — chỉ đóng bằng nút X -->
<div id="modal-overlay">
  <div id="modal-box">
    <div class="modal-head">
      <h2>⚡ Check Item Nhanh</h2>
      <input id="modal-search" type="text" placeholder="🔍 ID hoặc tên…" oninput="filterModal()">
      <button class="modal-close" onclick="closeModal()" title="Đóng">✕</button>
    </div>
    <div class="modal-bar" id="modal-bar" style="display:none"></div>
    <div class="modal-body">
      <div class="item-grid" id="modal-grid"></div>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════
//  RESPONSIVE SCALE — 1500px always fills 98vw
//  Uses CSS zoom: browser handles reflow natively
// ══════════════════════════════
const PAGE_W = 1500;

function applyScale() {
  if (window.__setAdminPageZoom) window.__setAdminPageZoom();
}

applyScale();
window.addEventListener('resize', applyScale);

// ══════════════════════════════
//  COLUMN SEARCH (client-side)
// ══════════════════════════════
const trs   = document.querySelectorAll('#tb tr.item-row');
const cntTr = document.getElementById('cnt-tr');
const cntSc = document.getElementById('cnt-sc');

function colSearch() {
  const filters = {};
  document.querySelectorAll('.col-search input').forEach(inp => {
    const v = inp.value.toLowerCase().trim();
    if (v) filters[inp.dataset.col] = v;
  });

  let n = 0;
  trs.forEach(r => {
    let ok = true;
    for (const [col, val] of Object.entries(filters)) {
      const attr = r.dataset[col + 'Val'] ?? '';
      if (!attr.includes(val)) { ok = false; break; }
    }
    r.style.display = ok ? '' : 'none';
    if (ok) n++;
  });

  if (Object.keys(filters).length) {
    cntSc.textContent = `${n} / ${trs.length} kết quả`;
    cntTr.style.display = '';
  } else {
    cntTr.style.display = 'none';
  }
}

// ══════════════════════════════
//  TOGGLE EDIT / SAVE
// ══════════════════════════════
function toggleEdit(btn) {
  const tr = btn.closest('tr');

  if (btn.classList.contains('edit-mode')) {
    // Snapshot current values for cancel
    tr.querySelectorAll('.editable').forEach(el => {
      el.dataset.orig = el.value;
    });

    tr.classList.add('editing');
    tr.querySelectorAll('.editable').forEach(el => {
      if (el.tagName === 'SELECT') el.disabled = false;
      else el.removeAttribute('readonly');
    });

    btn.classList.remove('edit-mode');
    btn.classList.add('save-mode');
    btn.innerHTML = `
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <polyline points="17 21 17 13 7 13 7 21" stroke="white" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
        <polyline points="7 3 7 8 15 8" stroke="white" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Lưu`;

  } else if (btn.classList.contains('save-mode')) {
    saveRow(btn);
  }
}

// ══════════════════════════════
//  CANCEL EDIT — restore snapshot
// ══════════════════════════════
function cancelEdit(cancelBtn) {
  const tr = cancelBtn.closest('tr');
  tr.querySelectorAll('.editable').forEach(el => {
    if (el.dataset.orig !== undefined) el.value = el.dataset.orig;
  });
  lockRow(tr);
}

function lockRow(tr) {
  tr.classList.remove('editing');
  tr.querySelectorAll('.editable').forEach(el => {
    if (el.tagName === 'SELECT') el.disabled = true;
    else el.setAttribute('readonly', '');
  });
  const saveBtn = tr.querySelector('.action-btn');
  if (saveBtn) {
    saveBtn.classList.remove('save-mode','saving-mode');
    saveBtn.classList.add('edit-mode');
    saveBtn.innerHTML = `
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Sửa`;
  }
}

// ══════════════════════════════
//  SAVE ROW
// ══════════════════════════════
function saveRow(btn) {
  const tr = btn.closest('tr');
  const id = parseInt(tr.dataset.id, 10);
  if (isNaN(id) || id < 0) { toast('❌ Không đọc được ID hàng!', 'err'); return; }

  const g = n => tr.querySelector(`[name="${n}"]`).value;

  const body = new URLSearchParams({
    action: 'update', id,
    type: g('type'), gender: g('gender'), name: g('name'),
    description: g('description'), icon_id: g('icon_id'),
    head: g('head'), body: g('body'), leg: g('leg'),
    is_up_to_up: g('is_up_to_up'),
  });

  btn.classList.remove('save-mode');
  btn.classList.add('saving-mode');
  btn.innerHTML = `
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="12" r="9" stroke="white" stroke-width="2" stroke-dasharray="28" stroke-dashoffset="8">
        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur=".7s" repeatCount="indefinite"/>
      </circle>
    </svg>
    …`;

  fetch('item', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
    body: body.toString(),
  })
  .then(r => r.text())
  .then(raw => {
    let res;
    try { res = JSON.parse(raw); }
    catch { throw new Error('Phản hồi lạ: ' + raw.substring(0, 80)); }

    if (res.status === 'success') {
      lockRow(tr);
      const name = tr.dataset.name || `ID ${id}`;
      toast(`✅ Đã lưu: ${name}`, 'ok');
    } else {
      btn.classList.remove('saving-mode');
      btn.classList.add('save-mode');
      btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none">
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <polyline points="17 21 17 13 7 13 7 21" stroke="white" stroke-width="2"/>
        <polyline points="7 3 7 8 15 8" stroke="white" stroke-width="2"/>
      </svg>Lưu`;
      toast('❌ ' + (res.message || 'Lỗi'), 'err');
    }
  })
  .catch(err => {
    btn.classList.remove('saving-mode');
    btn.classList.add('save-mode');
    btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none">
      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
            stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <polyline points="17 21 17 13 7 13 7 21" stroke="white" stroke-width="2"/>
      <polyline points="7 3 7 8 15 8" stroke="white" stroke-width="2"/>
    </svg>Lưu`;
    toast('❌ ' + err.message, 'err');
  });
}

// ══════════════════════════════
//  TOAST
// ══════════════════════════════
let toastTimer;
function toast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'show ' + type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.className = ''; }, 2000);
}

// ══════════════════════════════
//  MODAL — chỉ đóng bằng nút X
//  KHÔNG click ngoài, KHÔNG Escape
// ══════════════════════════════
const ALL_ITEMS = <?= json_encode($quickItems, JSON_UNESCAPED_UNICODE) ?>;
let mItems = [...ALL_ITEMS];

function openModal() {
  mItems = [...ALL_ITEMS];
  document.getElementById('modal-search').value = '';
  renderModal();
  document.getElementById('modal-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  // focus vào ô search để gõ ngay
  setTimeout(() => document.getElementById('modal-search').focus(), 80);
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  document.body.style.overflow = '';
}

function filterModal() {
  const q = document.getElementById('modal-search').value.toLowerCase().trim();
  mItems = q
    ? ALL_ITEMS.filter(it => String(it.id).includes(q) || (it.NAME || '').toLowerCase().includes(q))
    : [...ALL_ITEMS];
  renderModal();

  // cập nhật bar đếm
  const bar = document.getElementById('modal-bar');
  if (q) {
    bar.style.display = 'flex';
    bar.textContent = `${mItems.length} / ${ALL_ITEMS.length} kết quả`;
  } else {
    bar.style.display = 'none';
  }
}

function renderModal() {
  document.getElementById('modal-grid').innerHTML = mItems.map(it => {
    const name = it.NAME ? it.NAME.substring(0, 18) : '—';
    return `<div class="item-card" title="#${it.id} — ${it.NAME || ''}">
      <img src="/icon/${it.icon_id}.png" alt=""
           onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
      <div class="ic-ph"></div>
      <span class="ic-id">#${it.id}</span>
      <span class="ic-name">${name}</span>
    </div>`;
  }).join('');
}
</script>
</body>
</html>

