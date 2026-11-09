<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";

if (empty($_SESSION['account_id'])) {
    header("Location: /sv1/trangchu.php");
    exit;
}

$account_id = (int)$_SESSION['account_id'];

$stmt = $pdo->prepare("SELECT id, is_admin FROM account WHERE id = ? LIMIT 1");
$stmt->execute([$account_id]);
$user = $stmt->fetch();

// ❌ Session fake hoặc user không tồn tại
if (!$user) {
    session_destroy();
    header("Location: /sv1/trangchu.php");
    exit;
}

// ❌ Không phải admin
if ((int)$user['is_admin'] !== 1) {
    echo "Không có quyền!";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM player WHERE account_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM account WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'save') {
        $id       = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $ban      = (int)$_POST['ban'];
        $is_admin = (int)$_POST['is_admin'];
        $active   = (int)$_POST['active'];
        $vnd     = (int)str_replace('.', '', $_POST['vnd']);
        $tongnap    = (int)str_replace('.', '', $_POST['tongnap']);

        try {
            $check = $pdo->prepare("SELECT id FROM account WHERE username = ? AND id != ? LIMIT 1");
            $check->execute([$username, $id]);

            if ($check->fetch()) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Tài khoản đã tồn tại!'
                ]);
                exit;
            }

            $sql = "UPDATE account 
                    SET username=?, password=?, ban=?, is_admin=?, active=?, vnd=?, tongnap=? 
                    WHERE id=?";

            $pdo->prepare($sql)->execute([
                $username, $password, $ban, $is_admin, $active, $vnd, $tongnap, $id
            ]);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    if ($_POST['action'] === 'quick_create') {
        $tk = '0325420834';
        try {
            $check = $pdo->prepare("SELECT id FROM account WHERE username = ?");
            $check->execute([$tk]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Tài khoản đã tồn tại!']);
            } else {
                $pdo->prepare("INSERT INTO account (username, password, ban, is_admin, active, vnd, tongnap) VALUES (?,?,0,0,0,0,0)")
                    ->execute([$tk, $tk]);
                echo json_encode(['success' => true]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    exit;
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 100;
$offset   = ($page - 1) * $perPage;

$filters = [
    'id'         => trim($_GET['f_id']         ?? ''),
    'name'       => trim($_GET['f_name']       ?? ''),
    'username'   => trim($_GET['f_username']   ?? ''),
    'password'   => trim($_GET['f_password']   ?? ''),
    'ban'        => trim($_GET['f_ban']        ?? ''),
    'is_admin'   => trim($_GET['f_is_admin']   ?? ''),
    'ip_address' => trim($_GET['f_ip_address'] ?? ''),
    'active'     => trim($_GET['f_active']     ?? ''),
    'vnd'       => str_replace('.', '', trim($_GET['f_vnd'] ?? '')),
    'tongnap'      => str_replace('.', '', trim($_GET['f_tongnap'] ?? '')),
];

$where  = [];
$params = [];

foreach ($filters as $col => $val) {
    if ($val !== '') {
        if ($col === 'name') {
            $where[]  = "p.name LIKE ?";
            $params[] = "%$val%";
            continue;
        }
        if (in_array($col, ['id','ban','is_admin','active'])) {
            $where[]  = "a.$col = ?";
            $params[] = $val;
        } elseif (in_array($col, ['vnd','tongnap'])) {
            $where[]  = "CAST(a.$col AS CHAR) LIKE ?";
            $params[] = "%$val%";
        } else {
            $where[]  = "a.$col LIKE ?";
            $params[] = "%$val%";
        }
    }
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM account a LEFT JOIN player p ON p.account_id = a.id $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$sql = "SELECT a.id, a.username, a.password, a.ban, a.is_admin, a.ip_address, a.active, a.vnd, a.tongnap,
               p.name as char_name
        FROM account a
        LEFT JOIN player p ON p.account_id = a.id
        $whereSql
        ORDER BY a.tongnap DESC, a.id ASC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$stBan   = $pdo->query("SELECT COUNT(*) FROM account WHERE ban=1")->fetchColumn();
$stAdmin = $pdo->query("SELECT COUNT(*) FROM account WHERE is_admin=1")->fetchColumn();
$stUser  = $pdo->query("SELECT COUNT(*) FROM account WHERE is_admin=0")->fetchColumn();
$stAct   = $pdo->query("SELECT COUNT(*) FROM account WHERE active=1")->fetchColumn();
$stTotal = $pdo->query("SELECT COUNT(*) FROM account")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Quản Lý Tài Khoản</title>
<link rel="icon" type="image/jpeg" href="/img/icon.jpg">
<style>
/* ========== TOKENS ========== */
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

/* STATS */
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

/* LEGEND */
.legend-row {
  display: flex; flex-wrap: nowrap; gap: 8px; align-items: center;
  justify-content: center; margin-bottom: 10px;
}
.legend-item {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .78rem; font-weight: 700; color: var(--tx2);
  background: var(--surf); border: 1px solid var(--bd2);
  border-radius: 6px; padding: 4px 10px; box-shadow: var(--sh2);
}
.legend-dot { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(0,0,0,.12); flex-shrink: 0; }

/* PAGINATION */
.pag-wrap {
  display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;
  margin-bottom: 10px; align-items: center;
}
.page-link {
  padding: 5px 11px; border-radius: var(--rs);
  text-decoration: none; font-weight: 700; font-size: .82rem;
  border: 1.5px solid var(--bd2);
  background: var(--surf); color: var(--tx2); transition: all .15s;
}
.page-link:hover { background: var(--blu-lt); border-color: var(--blue2); }
.page-link.active { background: linear-gradient(135deg, var(--blue2), var(--indigo)); color: #fff; border-color: transparent; pointer-events: none; }
.page-info { font-size: .82rem; color: var(--tx3); font-weight: 600; }

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
col.c-name { width: 11%; }
col.c-user { width: 11%; }
col.c-pass { width: 11%; }
col.c-ban  { width: 8.2%; }
col.c-adm  { width: 8.2%; }
col.c-ip   { width: 11%; }
col.c-act  { width: 8.2%; }
col.c-vnd { width: 10.2%; }
col.c-dnap { width: 10.2%; }
col.c-hd   { width: 6%; }  

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
  overflow: hidden; text-overflow: ellipsis;
}

thead tr.filter-row th {
  background: var(--surf3);
  padding: 5px 5px;
  border-bottom: 2px solid var(--bd2);
}
thead tr.filter-row input {
  width: 100%; padding: 5px 7px;
  border-radius: var(--rs);
  border: 1.5px solid var(--bd2);
  font-size: .78rem; font-weight: 600;
  background: var(--surf); color: var(--tx);
  font-family: 'Times New Roman', Times, serif;
  outline: none; transition: border .15s;
  text-align: center;
}
thead tr.filter-row input:focus {
  border-color: var(--blue2);
  box-shadow: 0 0 0 3px rgba(13,95,163,.13);
}
thead tr.filter-row input::placeholder { color: var(--bd2); }

tbody tr { transition: filter .12s; }
tbody tr:hover td { filter: brightness(.95); }
tbody tr:nth-child(odd)  td { background: #f5faff; }
tbody tr:nth-child(even) td { background: #eaf3fb; }

tbody tr.row-ban    td { background: #fecaca !important; }
tbody tr.row-admin  td { background: #fde68a !important; }
tbody tr.row-active1 td { background: #bbf7d0 !important; }
tbody tr.row-active0 td { background: #e5e7eb !important; }

td {
  padding: 7px 6px;
  text-align: center;
  border-bottom: 1px solid var(--bd);
  vertical-align: middle;
  overflow: hidden;
}

.view-value {
  display: inline-block;
  width: 100%;
  font-size: .82rem;
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
select.edit-input { cursor: pointer; }

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
.btn-del-p   { background: #fde8ea; color: var(--danger); border-color: #f5a3aa; }
.btn-del-p:hover   { background: #fbcfd4; }
.btn-save-p  { background: #d6f5ec; color: var(--success); border-color: #7ddab8; }
.btn-save-p:hover  { background: #b8edda; }
.btn-cancel-p {
  background: #fde8ea; color: var(--danger); border-color: #f5a3aa;
}
.btn-cancel-p:hover { background: #fbcfd4; }

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

/* ========== CONFIRM MODAL ========== */
.confirm-modal {
  display: none; position: fixed; z-index: 10000;
  left: 0; top: 0; width: 100%; height: 100%;
  background: rgba(9,19,31,.65);
  align-items: center; justify-content: center;
}
.confirm-box {
  position: relative;
  background: var(--surf); border: 1.5px solid var(--bd2);
  border-radius: var(--r); padding: 2rem;
  max-width: 550px; width: 90%; text-align: center;
  animation: pop .2s ease; box-shadow: var(--sh);
  font-size: 1.2rem;
}
/* Dấu X có khung tròn nổi bật */
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
.confirm-box h3 {
  color: #f4c542;  /* Màu vàng cho tiêu đề */
  font-size: 1.4rem; margin-bottom: 16px;
}
.confirm-msg-main {
  color: #2ecc71;  /* Màu xanh lá */
  font-weight: 700;
  font-size: 1.5rem;
}
.confirm-msg-warning {
  color: #e74c3c;  
  font-weight: 600;
  display: block;
  margin-top: 8px;
  font-size: 1.5rem;
}
.confirm-box p {
  color: var(--tx3); margin-bottom: 24px;
  font-size: 1em;
  word-break: break-word;
  white-space: normal;
  line-height: 1.4;
}
.confirm-btns {
  display: flex; gap: 20px; justify-content: center;
}
.confirm-btns .btn-sm {
  font-size: 1rem; padding: 8px 20px; width: auto;
}
.btn-cancel-con {
  background: #fde8ea; color: var(--danger); border: 1.5px solid #f5a3aa;
}
.btn-cancel-con:hover { background: #fbcfd4; }
.btn-ok-con {
  background: var(--danger); color: #fff; border: none;
}
.btn-ok-con:hover { filter: brightness(1.08); transform: translateY(-2px); }

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

<!-- TOPBAR -->
<div class="top">
  <div class="ttit">
    <button class="tx-box" onclick="quickCreate()" style="border:none;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
        <circle cx="9" cy="7" r="4" stroke="white" stroke-width="1.8"/>
        <circle cx="17" cy="9" r="3" stroke="white" stroke-width="1.6"/>
        <path d="M2 21c0-4 3.1-7 7-7s7 3 7 7" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M22 21c0-2.8-2-5-4.5-5.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      Tạo Tài Khoản Nhanh:
      <span style="font-family:'Times New Roman', monospace;font-size:.95em"><?= number_format($stTotal) ?></span>
    </button>
  </div>

  <div class="tr2">
    <a href="/sv1/admin/panel.php" class="btn bblue">
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
    <div>
      <div class="stat-val" style="color:var(--success)"><?= number_format($stAct) ?></div>
      <div class="stat-lbl">MTV</div>
    </div>
  </div>
  <div class="stat-card">
    <div>
      <div class="stat-val" style="color:var(--danger)"><?= number_format($stBan) ?></div>
      <div class="stat-lbl">Khóa</div>
    </div>
  </div>
  <div class="stat-card">
    <div>
      <div class="stat-val" style="color:var(--warn)"><?= number_format($stAdmin) ?></div>
      <div class="stat-lbl">Admin</div>
    </div>
  </div>
  <div class="stat-card">
    <div>
      <div class="stat-val" style="color:var(--tx3)"><?= number_format($stUser) ?></div>
      <div class="stat-lbl">User</div>
    </div>
  </div>
</div>

<!-- LEGEND -->
<div class="legend-row">
  <div class="legend-item"><div class="legend-dot" style="background:#bbf7d0"></div>Đã MTV</div>
  <div class="legend-item"><div class="legend-dot" style="background:#fecaca"></div>Bị khóa</div>
  <div class="legend-item"><div class="legend-dot" style="background:#fde68a"></div>Admin</div>
  <div class="legend-item"><div class="legend-dot" style="background:#e5e7eb"></div>User</div>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1):
  $qs = $_GET;
  $start = max(1, $page-3); $end = min($totalPages, $page+3);
?>
<div class="pag-wrap">
  <?php if ($page > 1): $qs['page'] = 1; ?>
    <a href="?<?= http_build_query($qs) ?>" class="page-link">«</a>
  <?php endif; ?>
  <?php if ($page > 1): $qs['page'] = $page-1; ?>
    <a href="?<?= http_build_query($qs) ?>" class="page-link">‹</a>
  <?php endif; ?>
  <?php for ($i = $start; $i <= $end; $i++): $qs['page'] = $i; ?>
    <a href="?<?= http_build_query($qs) ?>" class="page-link <?= ($i==$page)?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): $qs['page'] = $page+1; ?>
    <a href="?<?= http_build_query($qs) ?>" class="page-link">›</a>
  <?php endif; ?>
  <?php if ($page < $totalPages): $qs['page'] = $totalPages; ?>
    <a href="?<?= http_build_query($qs) ?>" class="page-link">»</a>
  <?php endif; ?>
  <span class="page-info">Trang <?= $page ?> / <?= $totalPages ?></span>
</div>
<?php endif; ?>

<!-- FILTER FORM -->
<form id="filterForm" method="GET">
  <input type="hidden" name="page" value="1">
</form>

<!-- TABLE -->
<div class="tc"><div class="tsc">
<table>
<colgroup>
  <col class="c-id">
  <col class="c-name">
  <col class="c-user">
  <col class="c-pass">
  <col class="c-ban">
  <col class="c-adm">
  <col class="c-ip">
  <col class="c-act">
  <col class="c-vnd">
  <col class="c-dnap">
  <col class="c-hd">
</colgroup>
<thead>
  <tr class="header-row">
    <th>ID</th>
    <th>Name</th>
    <th>Tài Khoản</th>
    <th>Pass</th>
    <th>Khóa</th>
    <th>Admin</th>
    <th>IP</th>
    <th>Thành Viên</th>
    <th>Số Dư</th>
    <th>Tổng Nạp</th>
    <th>VIP</th>
  </tr>
  <tr class="filter-row">
    <th><input type="text" name="f_id"         form="filterForm" value="<?= htmlspecialchars($filters['id']) ?>"         placeholder="ID"        oninput="submitFilter()"></th>
    <th><input type="text" name="f_name"       form="filterForm" value="<?= htmlspecialchars($filters['name']) ?>"       placeholder="Tên NV"    oninput="submitFilter()"></th>
    <th><input type="text" name="f_username"   form="filterForm" value="<?= htmlspecialchars($filters['username']) ?>"   placeholder="Tài khoản" oninput="submitFilter()"></th>
    <th><input type="text" name="f_password"   form="filterForm" value="<?= htmlspecialchars($filters['password']) ?>"   placeholder="Mật khẩu" oninput="submitFilter()"></th>
    <th><input type="text" name="f_ban"        form="filterForm" value="<?= htmlspecialchars($filters['ban']) ?>"        placeholder="0/1"       oninput="submitFilter()"></th>
    <th><input type="text" name="f_is_admin"   form="filterForm" value="<?= htmlspecialchars($filters['is_admin']) ?>"   placeholder="0/1"       oninput="submitFilter()"></th>
    <th><input type="text" name="f_ip_address" form="filterForm" value="<?= htmlspecialchars($filters['ip_address']) ?>" placeholder="IP"        oninput="submitFilter()"></th>
    <th><input type="text" name="f_active"     form="filterForm" value="<?= htmlspecialchars($filters['active']) ?>"     placeholder="0/1"       oninput="submitFilter()"></th>
    <th><input type="text" name="f_vnd"       form="filterForm" value="<?= htmlspecialchars($filters['vnd']) ?>"       placeholder="Số dư"     oninput="submitFilter()"></th>
    <th><input type="text" name="f_tongnap"      form="filterForm" value="<?= htmlspecialchars($filters['tongnap']) ?>"      placeholder="Tổng nạp"  oninput="submitFilter()"></th>
    <th></th>
  </tr>
</thead>
<tbody>
<?php foreach ($accounts as $row):
  $isBan   = $row['ban']      == 1;
  $isActive= $row['active']   == 1;
  $isAdmin = $row['is_admin'] == 1;
  $rowClass = $isBan ? 'row-ban' : ($isAdmin ? 'row-admin' : ($isActive ? 'row-active1' : 'row-active0'));
?>
<tr class="<?= $rowClass ?>" data-id="<?= $row['id'] ?>">
  <td><span class="no-edit view-value"><?= $row['id'] ?></span></td>
  <td><strong style="color:var(--blue2);font-size:.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?= htmlspecialchars($row['char_name'] ?? '—') ?></strong></td>
  <td><span class="view-value" data-field="username" data-original="<?= htmlspecialchars($row['username']) ?>"><?= htmlspecialchars($row['username']) ?></span></td>
  <td><span class="view-value" data-field="password" data-original="<?= htmlspecialchars($row['password']) ?>"><?= htmlspecialchars($row['password']) ?></span></td>
  <td><span class="view-value" data-field="ban" data-original="<?= $row['ban'] ?>"><?= $row['ban'] == 1 ? '1 - Khóa' : '0 - Mở' ?></span></td>
  <td><span class="view-value" data-field="is_admin" data-original="<?= $row['is_admin'] ?>"><?= $row['is_admin'] == 1 ? '1 - Admin' : '0 - User' ?></span></td>
  <td><span class="no-edit view-value"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></span></td>
  <td><span class="view-value" data-field="active" data-original="<?= $row['active'] ?>"><?= $row['active'] == 1 ? '1 - Mở' : '0 - Đóng' ?></span></td>
  <td><span class="view-value" data-field="vnd" data-original="<?= $row['vnd'] ?>"><?= number_format($row['vnd'], 0, ',', '.') ?></span></td>
  <td><span class="view-value" data-field="tongnap" data-original="<?= $row['tongnap'] ?>"><?= number_format($row['tongnap'], 0, ',', '.') ?></span></td>
  <td>
    <div class="action-wrap">
      <button class="btn-sm btn-edit" onclick="editRow(this)">✏️ Sửa</button>
      <button class="btn-sm btn-del-p" onclick="deleteRow(this, <?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')">🗑️ Xóa</button>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($accounts)): ?>
<tr><td colspan="11" style="padding:32px;color:var(--tx3);font-style:italic">Không tìm thấy tài khoản nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div></div>

</div><!-- /W -->
</div><!-- /scale-root -->

<!-- CONFIRM MODAL -->
<div id="confirmModal" class="confirm-modal">
  <div class="confirm-box">
    <span class="close-x">&times;</span>
    <h3 id="confirmTitle">⚠️ Xác Nhận</h3>
    <p id="confirmMsg">Bạn có chắc chắn?</p>
    <div class="confirm-btns">
      <button class="btn-sm btn-cancel-con" id="confirmCancelBtn">✕ Hủy</button>
      <button class="btn-sm btn-ok-con" id="confirmOkBtn">OK</button>
    </div>
  </div>
</div>

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
    setTimeout(() => t.style.display = 'none', 2000);
}
function showLoading(v) { document.getElementById('loading').style.display = v ? 'flex' : 'none'; }

/* ================= CONFIRM MODAL ================= */
let confirmCallback = null;
function showConfirm({title, msgMain, msgWarning, okText='OK'}, callback) {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const msgEl = document.getElementById('confirmMsg');
    titleEl.textContent = title || '⚠️ Xác Nhận';
    msgEl.innerHTML = `
        <span class="confirm-msg-main">${escapeHtml(msgMain)}</span>
        ${msgWarning ? `<span class="confirm-msg-warning">${escapeHtml(msgWarning)}</span>` : ''}
    `;
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent = okText || 'OK';
    confirmCallback = callback;
    modal.style.display = 'flex';
}
function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    confirmCallback = null;
}
document.getElementById('confirmOkBtn').onclick = () => {
    if (confirmCallback) confirmCallback();
    closeConfirmModal();
};
document.getElementById('confirmCancelBtn').onclick = closeConfirmModal;
document.querySelector('.close-x').onclick = closeConfirmModal;
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirmModal(); });

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

/* ================= DELETE ROW ================= */
async function deleteRow(btn, id, username) {
    showConfirm({
        title: '🗑️ Xóa tài khoản',
        msgMain: `Xóa TK "${escapeHtml(username)}" — ID ${id}.`,
        msgWarning: 'Không thể hoàn tác!',
        okText: 'Xóa'
    }, async () => {
        showLoading(true);
        try {
            const res  = await fetch('account.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'delete', id}) });
            const json = await res.json();
            if (json.success) { 
                btn.closest('tr').remove(); 
                showToast('🗑️ Đã xóa Tài Khoản: ' + username); 
            }
            else showToast('❌ ' + json.error, 'error');
        } catch(e) { showToast('❌ Lỗi kết nối!', 'error'); }
        showLoading(false);
    });
}

/* ================= QUICK CREATE ================= */
function quickCreate() {
    showConfirm({
        title: '⚡ Tạo Tài Khoản Nhanh',
        msgMain: 'Ấn nút OK là được',
        msgWarning: '',
        okText: 'OK'
    }, async () => {
        showLoading(true);
        try {
            const res  = await fetch('account.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'quick_create'}) });
            const json = await res.json();
            if (json.success) { showToast('✅ Tạo thành công!'); setTimeout(() => location.reload(), 1200); }
            else showToast('❌ ' + json.error, 'error');
        } catch(e) { showToast('❌ Lỗi kết nối!', 'error'); }
        showLoading(false);
    });
}

/* ================= EDIT MODE LOGIC ================= */
function editRow(btn) {
    const tr = btn.closest('tr');
    if (tr.classList.contains('editing')) return;

    tr.classList.add('editing');

    const originalValues = {};
    const editableSpans = tr.querySelectorAll('td .view-value[data-field]');
    editableSpans.forEach(span => {
        const field = span.getAttribute('data-field');
        originalValues[field] = span.getAttribute('data-original') || span.innerText;
    });

    const delBtn = tr.querySelector('.btn-del-p');
    if (delBtn) delBtn.style.display = 'none';

    editableSpans.forEach(span => {
        const field = span.getAttribute('data-field');
        const currentValue = span.getAttribute('data-original') || span.innerText;
        const td = span.parentNode;
        let inputEl = null;

        if (field === 'username' || field === 'password') {
            inputEl = document.createElement('input');
            inputEl.type = 'text';
            inputEl.value = currentValue;
            inputEl.classList.add('edit-input');
        } else if (field === 'ban' || field === 'is_admin' || field === 'active') {
            inputEl = document.createElement('select');
            inputEl.classList.add('edit-input');
            if (field === 'ban') {
                inputEl.innerHTML = '<option value="0">0 - Mở</option><option value="1">1 - Khóa</option>';
                inputEl.value = currentValue;
            } else if (field === 'is_admin') {
                inputEl.innerHTML = '<option value="0">0 - User</option><option value="1">1 - Admin</option>';
                inputEl.value = currentValue;
            } else if (field === 'active') {
                inputEl.innerHTML = '<option value="0">0 - Đóng</option><option value="1">1 - Mở</option>';
                inputEl.value = currentValue;
            }
            inputEl.style.color = inputEl.value == '1' ? 'var(--danger)' : 'var(--success)';
            inputEl.addEventListener('change', function() {
                this.style.color = this.value == '1' ? 'var(--danger)' : 'var(--success)';
            });
        } else if (field === 'vnd' || field === 'tongnap') {
            inputEl = document.createElement('input');
            inputEl.type = 'text';
            let raw = currentValue;
            if (raw.indexOf('.') !== -1) raw = raw.replace(/\./g, '');
            inputEl.value = Number(raw).toLocaleString('vi-VN');
            inputEl.classList.add('edit-input');
            inputEl.addEventListener('input', function() {
                let v = this.value.replace(/\D/g, '');
                this.value = v ? Number(v).toLocaleString('vi-VN') : '';
            });
        }

        if (inputEl) {
            inputEl.setAttribute('data-field', field);
            span.style.display = 'none';
            td.appendChild(inputEl);
            inputEl.focus();
        }
    });

    const actionDiv = tr.querySelector('.action-wrap');
    const editBtn = actionDiv.querySelector('.btn-edit');
    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn-sm btn-save-p';
    saveBtn.innerHTML = '💾 Lưu';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-sm btn-cancel-p';
    cancelBtn.innerHTML = '✖️ Hủy';

    editBtn.remove();
    actionDiv.prepend(saveBtn);
    actionDiv.prepend(cancelBtn);

    saveBtn.onclick = () => saveEdit(tr, originalValues);
    cancelBtn.onclick = () => cancelEdit(tr, originalValues);
}

async function saveEdit(tr, originalValues) {
    const id = tr.getAttribute('data-id');
    const inputs = tr.querySelectorAll('.edit-input');
    const data = {
        action: 'save',
        id: id,
        username: '',
        password: '',
        ban: '',
        is_admin: '',
        active: '',
        vnd: '',
        tongnap: ''
    };
    inputs.forEach(inp => {
        const field = inp.getAttribute('data-field');
        if (field) {
            let value = inp.value;
            if (field === 'vnd' || field === 'tongnap') {
                value = value.replace(/\./g, '');
            }
            data[field] = value;
        }
    });

    if (!data.username) data.username = originalValues.username || '';
    if (!data.password) data.password = originalValues.password || '';
    if (!data.ban) data.ban = originalValues.ban || '0';
    if (!data.is_admin) data.is_admin = originalValues.is_admin || '0';
    if (!data.active) data.active = originalValues.active || '0';
    if (!data.vnd) data.vnd = originalValues.vnd || '0';
    if (!data.tongnap) data.tongnap = originalValues.tongnap || '0';

    showLoading(true);
    try {
        const res = await fetch('account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        });
        const json = await res.json();
        if (json.success) {
            const updatedFields = {};
            inputs.forEach(inp => {
                const field = inp.getAttribute('data-field');
                let newVal = inp.value;
                if (field === 'vnd' || field === 'tongnap') {
                    newVal = newVal.replace(/\./g, '');
                    updatedFields[field] = newVal;
                } else if (field === 'ban' || field === 'is_admin' || field === 'active') {
                    updatedFields[field] = newVal;
                } else {
                    updatedFields[field] = newVal;
                }
            });
            const spans = tr.querySelectorAll('.view-value[data-field]');
            spans.forEach(span => {
                const field = span.getAttribute('data-field');
                const newVal = updatedFields[field];
                if (newVal !== undefined) {
                    if (field === 'ban') {
                        span.innerText = newVal == '1' ? '1 - Khóa' : '0 - Mở';
                        span.setAttribute('data-original', newVal);
                    } else if (field === 'is_admin') {
                        span.innerText = newVal == '1' ? '1 - Admin' : '0 - User';
                        span.setAttribute('data-original', newVal);
                    } else if (field === 'active') {
                        span.innerText = newVal == '1' ? '1 - Mở' : '0 - Đóng';
                        span.setAttribute('data-original', newVal);
                    } else if (field === 'vnd' || field === 'tongnap') {
                        const formatted = Number(newVal).toLocaleString('vi-VN');
                        span.innerText = formatted;
                        span.setAttribute('data-original', newVal);
                    } else {
                        span.innerText = newVal;
                        span.setAttribute('data-original', newVal);
                    }
                }
            });
            tr.querySelectorAll('.view-value').forEach(span => span.style.display = 'inline-block');

            const newBan = updatedFields.ban !== undefined ? parseInt(updatedFields.ban) : (originalValues.ban || 0);
            const newActive = updatedFields.active !== undefined ? parseInt(updatedFields.active) : (originalValues.active || 0);
            const newAdmin = updatedFields.is_admin !== undefined ? parseInt(updatedFields.is_admin) : (originalValues.is_admin || 0);
            tr.className = newBan ? 'row-ban' : (newAdmin ? 'row-admin' : (newActive ? 'row-active1' : 'row-active0'));
            tr.setAttribute('data-id', id);
          const username = data.username || originalValues.username || '';
showToast('✅ Đã lưu Tài khoản: ' + username);
            
        } else {
            showToast('❌ Lỗi: ' + (json.error||'Không rõ'), 'error');
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
    const inputs = tr.querySelectorAll('.edit-input');
    inputs.forEach(inp => inp.remove());
    const spans = tr.querySelectorAll('.view-value');
    spans.forEach(span => span.style.display = 'inline-block');
    finishEdit(tr);
}

function finishEdit(tr) {
    const actionDiv = tr.querySelector('.action-wrap');
    const saveBtn = actionDiv.querySelector('.btn-save-p');
    const cancelBtn = actionDiv.querySelector('.btn-cancel-p');
    if (saveBtn) saveBtn.remove();
    if (cancelBtn) cancelBtn.remove();
    if (!actionDiv.querySelector('.btn-edit')) {
        const editBtn = document.createElement('button');
        editBtn.className = 'btn-sm btn-edit';
        editBtn.innerHTML = '✏️ Sửa';
        editBtn.onclick = function() { editRow(this); };
        actionDiv.prepend(editBtn);
    }
    const delBtn = tr.querySelector('.btn-del-p');
    if (delBtn) delBtn.style.display = 'inline-flex';
    tr.classList.remove('editing');
    tr.querySelectorAll('.edit-input').forEach(inp => inp.remove());
}

/* ================= FILTER ================= */
let debounce;
function submitFilter() {
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        const form = document.getElementById('filterForm');
        const params = new URLSearchParams(new FormData(form));
        document.querySelectorAll('thead tr.filter-row input').forEach(inp => {
            if (inp.name) params.set(inp.name, inp.value);
        });
        try {
            const res = await fetch('account.php?' + params.toString());
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newTbody = doc.querySelector('tbody');
            if (newTbody) document.querySelector('tbody').innerHTML = newTbody.innerHTML;
            setTimeout(applyScale, 50);
        } catch(e) { console.log('Lỗi filter'); }
    }, 300);
}
</script>
</body>
</html>