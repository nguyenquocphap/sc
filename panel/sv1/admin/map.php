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

// 2. Xử lý lưu dữ liệu AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $data = json_decode(file_get_contents("php://input"), true);
    if ($data) {
        $id = (int)$data['id'];
        $name = trim($data['name'] ?? '');
        $zones = (int)($data['zones'] ?? 0);
        $max_player = (int)($data['max_player'] ?? 0);
        $mobs = !empty($data['mobs']) ? $data['mobs'] : '[]';
        $npcs = !empty($data['npcs']) ? $data['npcs'] : '[]';

        $stmt = $pdo->prepare("UPDATE map_template SET 
                                NAME = :name, 
                                zones = :zones, 
                                max_player = :max_player, 
                                mobs = :mobs, 
                                npcs = :npcs 
                                WHERE id = :id");
        $stmt->execute([
            ':name'       => $name,
            ':zones'      => $zones,
            ':max_player' => $max_player,
            ':mobs'       => $mobs,
            ':npcs'       => $npcs,
            ':id'         => $id
        ]);

        ob_clean();
        header('Content-Type: application/json');
        if ($stmt->errorCode() === '00000') {
            echo json_encode(["status" => "success"]);
        } else {
            $errorInfo = $stmt->errorInfo();
            echo json_encode(["status" => "error", "msg" => $errorInfo[2] ?? 'Unknown error']);
        }
        exit;
    }
}

// 3. Load Data bổ trợ
$npcAvatars = [];
$npcNames = [];
$npcFullList = [];

$npcQuery = $pdo->query("SELECT id, NAME, avatar FROM npc_template");
while ($n = $npcQuery->fetch(PDO::FETCH_ASSOC)) {
    $npcAvatars[$n['id']] = $n['avatar'];
    $npcNames[$n['id']] = $n['NAME'];
    $npcFullList[] = $n;
}

$mobNames = [];
$mobFullList = [];

$mobQuery = $pdo->query("SELECT id, NAME FROM mob_template");
while ($m = $mobQuery->fetch(PDO::FETCH_ASSOC)) {
    $mobNames[$m['id']] = $m['NAME'];
    $mobFullList[] = $m;
}

function cleanJsonString($json) {
    return preg_replace('/[\r\n\t]/', '', $json);
}

$list = $pdo->query("SELECT id, NAME, zones, max_player, mobs, npcs FROM map_template ORDER BY id ASC");

function renderMobList(string $mobsJson, array $mobNames): string {
    $mobs = json_decode($mobsJson, true);
    if (!is_array($mobs) || empty($mobs)) return '<span class="empty-label">Trống</span>';

    $seen = [];
    $html = '<div class="mob-list">';
    foreach ($mobs as $mob) {
        if (is_string($mob)) {
            $decodedMob = json_decode($mob, true);
            if (is_array($decodedMob)) {
                $mob = $decodedMob;
            }
        }
        if (!is_array($mob) || !isset($mob[0])) continue;
        $id = (int)$mob[0];
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $hp = $mob[2] ?? '?';
        $name = $mobNames[$id] ?? 'Quái #'.$id;
        $html .= '<div class="mob-tag">'.htmlspecialchars($name).' <span class="mob-hp-badge">HP '.$hp.'</span></div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1500">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <title>Quản Lý Maps</title>
    <link rel="icon" type="image/png" href="/img/icon.jpg">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* =====================================================
           ROOT: font-size = 16px (browser default)
           Dùng rem cho mọi kích thước để Ctrl+/- scale đúng
           ===================================================== */
        :root{
          font-size: 16px; /* = browser default, KHÔNG override để Ctrl+/- hoạt động chuẩn */
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
          --r: 0.8125rem;   /* 13px */
          --rs: 0.5rem;     /* 8px  */
          --sh: 0 0.25rem 1.5rem rgba(13,95,163,.13);
          --sh2: 0 0.0625rem 0.5rem rgba(13,95,163,.08);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
          font-family:'Plus Jakarta Sans',sans-serif;
          background:var(--bg);color:var(--tx);min-height:100dvh;
          -webkit-font-smoothing:antialiased;
          overflow-x:hidden;
        }
        body::before{
          content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
          background-image:radial-gradient(circle,var(--bd) 1px,transparent 1px);
          background-size:1.375rem 1.375rem; /* 22px */
          opacity:.45;
        }
        #app{
          width:1500px;
          transform-origin:top left;
          position:relative;
          z-index:1;
        }
        .W{width:100%;margin:0;padding:0.875rem 1.25rem 1.75rem;} /* 14 20 28 */

        .top{
          display:flex;flex-direction:column;align-items:center;justify-content:center;
          gap:8px;
          background:var(--surf);border:1px solid var(--bd2);
          border-radius:var(--r);padding:16px 22px 14px;
          margin-bottom:10px;
          box-shadow:var(--sh);
          position:relative;overflow:hidden;text-align:center;
        }
        .ttit{font-size:1.35rem;font-weight:800;letter-spacing:-.4px;color:var(--tx)}
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
        .tr2{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center}

        .btn{
          display:inline-flex;align-items:center;gap:6px;
          padding:11px 20px;
          border:none;border-radius:var(--rs);
          font-family:inherit;font-size:.9rem;font-weight:700;
          cursor:pointer;text-decoration:none;white-space:nowrap;
          transition:all .15s;letter-spacing:.2px;
          line-height:1;
        }
        .btn:hover{transform:translateY(-2px);box-shadow:0 0.375rem 1.125rem rgba(0,0,0,.15)}
        .bblue{background:linear-gradient(135deg,var(--blue2),var(--indigo));color:#fff;box-shadow:0 0.1875rem 0.75rem var(--glow)}
        .bteal{background:linear-gradient(135deg,var(--teal),#0d9488);color:#fff}

        .tc{
          background:var(--surf);border:1px solid var(--bd2);
          border-radius:var(--r);overflow:hidden;
          box-shadow:var(--sh);margin-bottom:0.6875rem;
        }
        .tsc{overflow-x:auto;-webkit-overflow-scrolling:touch;}

        table{
          width:100%;min-width:0;border-collapse:collapse;table-layout:fixed;
        }
        thead tr{
          background:linear-gradient(90deg,var(--blu-lt),var(--ind-lt));
          border-bottom:2px solid var(--bd2);
        }
        th{
          padding:0.875rem 0.625rem 0.625rem; /* 14 10 10 */
          font-size:.95rem;font-weight:800;
          text-transform:uppercase;letter-spacing:.8px;
          color:#09131f;white-space:nowrap;text-align:center;vertical-align:top;
        }
        th:nth-child(1){width:5%}
        th:nth-child(2){width:13%}
        th:nth-child(3){width:8%}
        th:nth-child(4){width:8%}
        th:nth-child(5){width:28%}
        th:nth-child(6){width:22%}
        th:nth-child(7){width:8%}

        td{padding:0.5rem 0.625rem;border-bottom:1px solid var(--bd);vertical-align:middle;text-align:center;color:var(--tx)} /* 8 10 */
        tbody tr{transition:background .12s}
        tbody tr:hover{background:var(--sky-lt)}
        .id-cell{font-weight:700;color:var(--indigo);font-family:'JetBrains Mono',monospace;}

        input.data{
          border:none;background:transparent;text-align:center;
          font-weight:600;width:100%;font-size:1em;outline:none;
          color:var(--tx2);font-family:inherit;
        }
        input.data:focus{
          background:var(--surf3);border-radius:0.375rem;padding:0.375rem 0.5rem; /* 6 8 */
        }
        input.data:disabled{
          background:transparent;color:var(--tx3);
          cursor:default;opacity:0.8;
        }
        .changed{background:#93c5fd !important;border-radius:0.25rem;}

        /* NPC preview */
        .npc-container{
          display:flex;flex-wrap:wrap;gap:0.25rem;justify-content:center;margin-top:0.25rem;
          padding:0.25rem;border-radius:0.5rem;transition:.15s;
          min-height:2rem;
        }
        .npc-container.editable{cursor:pointer;}
        .npc-container.editable:hover{
          background:rgba(139,92,246,.08);
          outline:2px dashed #c4b5fd;
        }
        .npc-container.locked{cursor:not-allowed;opacity:0.75;}
        .npc-item{
          width:2.25rem;height:2.25rem; /* 36px */
          background:var(--surf2);border:1px solid var(--bd);
          border-radius:0.375rem;display:flex;align-items:center;justify-content:center;
        }
        .npc-item img{max-width:1.75rem;max-height:1.75rem;} /* 28px */
        .npc-empty-hint{
          font-size:.72rem;color:#94a3b8;font-style:italic;
          display:flex;align-items:center;gap:0.25rem;
          padding:0.125rem 0;
        }

        /* Mob tags */
        .mob-preview{
          padding:0.25rem;border-radius:0.5rem;transition:.15s;
          min-height:2rem;
        }
        .mob-preview.editable{cursor:pointer;}
        .mob-preview.editable:hover{
          background:rgba(29,78,216,.06);
          outline:2px dashed #93c5fd;
        }
        .mob-preview.locked{cursor:not-allowed;opacity:0.75;}
        .mob-list{display:flex;flex-wrap:wrap;gap:0.25rem;justify-content:center;padding:0.125rem 0;}
        .mob-tag{
          display:inline-flex;align-items:center;gap:0.3125rem; /* 5px */
          background:linear-gradient(135deg,#1e1b4b,#312e81);
          color:#e0e7ff;
          border:1px solid rgba(129,140,248,.35);
          border-radius:1.25rem; /* 20px */
          padding:0.1875rem 0.625rem 0.1875rem 0.5rem; /* 3 10 3 8 */
          font-size:0.871rem;
          font-weight:700;
          white-space:nowrap;
          cursor:pointer;
          transition:.15s;
          box-shadow:0 0.125rem 0.375rem rgba(79,70,229,.25);
        }
        .mob-tag::before{content:'⚔';font-size:.83rem;opacity:.8;}
        .mob-tag:hover{
          background:linear-gradient(135deg,#312e81,#4338ca);
          transform:translateY(-1px);
          box-shadow:0 0.25rem 0.625rem rgba(79,70,229,.35);
        }
        .mob-hp-badge{
          background:rgba(239,68,68,.2);
          color:#fca5a5;
          border:1px solid rgba(239,68,68,.3);
          border-radius:0.625rem;
          padding:0.0625rem 0.375rem; /* 1 6 */
          font-size:0.811rem;
          font-weight:800;
          letter-spacing:.3px;
        }
        .empty-label{color:#94a3b8;font-style:italic;font-size:.82rem;}

        .search-row td{padding:0.5rem 0.3125rem;background:var(--surf2);}
        .search-row input{width:100%;padding:0.5rem 0.75rem;border-radius:1.25rem;border:1px solid var(--bd2);outline:none;font-family:inherit;font-size:.85rem;}
        .search-row input:focus{border-color:var(--blue2);box-shadow:0 0 0 2px var(--glow);}

        /* Toast */
        .toast {
            visibility: hidden;
            min-width: 21.125rem;   /* 338px */
            background: linear-gradient(135deg,#059669,#10b981);
            color: #fff;
            text-align: center;
            border-radius: 1rem;    /* 16px */
            padding: 1.375rem 2rem; /* 22 32 */
            position: fixed;
            z-index: 99999;
            bottom: 1.875rem;       /* 30px */
            right: 1.875rem;
            font-weight: 800;
            font-size: 1.3rem;
            box-shadow: 0 1rem 2rem rgba(0,0,0,0.25);
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: translateY(1.25rem);
        }
        .toast.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }
        .toast.error{background:linear-gradient(135deg,#dc2626,#ef4444);}
        .toast.warning{background:linear-gradient(135deg,#d97706,#f59e0b);}

        /* ===== MODAL STYLES ===== */
        .modal{
          display:none;position:fixed;top:0;left:0;width:100%;height:100%;
          background:rgba(9,19,31,.65);z-index:9999;
          align-items:center;justify-content:center;
          backdrop-filter:blur(8px);
        }
        .modal-content{
          background:var(--surf);
          width:80vw;height:90vh;
          max-width:none;max-height:none;
          border-radius:1.25rem; /* 20px */
          overflow:hidden;
          position:relative;display:flex;flex-direction:column;
          box-shadow:0 2rem 5rem rgba(9,19,31,.35), 0 0 0 1px rgba(255,255,255,.5);
          animation:modalIn .22s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes modalIn{
          from{transform:scale(.92) translateY(1rem);opacity:0}
          to{transform:scale(1) translateY(0);opacity:1}
        }
        .modal-header{
          display:flex;align-items:center;justify-content:space-between;
          padding:1.25rem 1.625rem 1rem; /* 20 26 16 */
          border-bottom:1px solid var(--bd);flex-shrink:0;
        }
        .modal-header-left{display:flex;align-items:center;gap:0.75rem;}
        .modal-icon{
          width:2.625rem;height:2.625rem; /* 42px */
          border-radius:0.75rem;          /* 12px */
          display:flex;align-items:center;justify-content:center;
          font-size:1.2rem;flex-shrink:0;
        }
        .modal-icon.mob{background:linear-gradient(135deg,#1e1b4b,#3730a3);color:#e0e7ff;}
        .modal-icon.npc{background:linear-gradient(135deg,#ede9fe,#f5f3ff);}
        .modal-title{font-size:1.18rem;font-weight:800;color:var(--tx);letter-spacing:-.3px;}
        .modal-subtitle{font-size:.78rem;color:var(--tx3);font-weight:500;margin-top:0.0625rem;}
        .modal-close{
          width:2.25rem;height:2.25rem; /* 36px */
          border-radius:0.625rem;        /* 10px */
          background:#fee2e2;border:none;
          color:#ef4444;font-size:1.1rem;font-weight:900;
          cursor:pointer;transition:.2s;
          display:flex;align-items:center;justify-content:center;flex-shrink:0;
        }
        .modal-close:hover{background:#ef4444;color:#fff;transform:rotate(90deg) scale(1.1);}
        .modal-body{flex:1;overflow-y:auto;overflow-x:auto;padding:1rem 1.625rem;min-height:0;} /* 16 26 */
        .modal-footer{
          padding:0.875rem 1.625rem 1.125rem; /* 14 26 18 */
          border-top:1px solid var(--bd);
          display:flex;align-items:center;justify-content:space-between;
          gap:0.625rem;flex-shrink:0;background:var(--surf2);
        }

        .modal-table{width:100%;border-collapse:collapse;table-layout:fixed;}
        .modal-table thead th{
          padding:0.625rem 0.75rem; /* 10 12 */
          background:linear-gradient(90deg,#1e1b4b,#2d3e8a);
          color:#e0e7ff;font-size:.78rem;font-weight:700;
          text-transform:uppercase;letter-spacing:.7px;white-space:nowrap;
        }
        .modal-table thead th:first-child{border-radius:0.625rem 0 0 0.625rem;}
        .modal-table thead th:last-child{border-radius:0 0.625rem 0.625rem 0;}
        .modal-table tbody tr{transition:.12s;border-bottom:1px solid #f1f5f9;}
        .modal-table tbody tr:hover{background:#f8faff;}
        .modal-table td{padding:0.5625rem 0.625rem;vertical-align:middle;text-align:center;} /* 9 10 */
        .modal-table input[type="number"]{
          width:100%;padding:0.4375rem 0.625rem; /* 7 10 */
          border:1.5px solid #e2e8f0;border-radius:0.5rem;
          font-family:inherit;font-size:.85rem;
          color:var(--tx2);font-weight:600;
          outline:none;transition:.15s;background:#fff;text-align:center;
        }
        .modal-table input[type="number"]:focus{
          border-color:var(--blue2);box-shadow:0 0 0 0.1875rem rgba(18,114,194,.12);
        }

        .name-label{
          display:inline-flex;align-items:center;gap:0.375rem;
          background:linear-gradient(135deg,#1e1b4b,#312e81);
          border:1.5px solid rgba(129,140,248,.4);
          border-radius:0.625rem;padding:0.375rem 0.75rem; /* 6 12 */
          font-size:.8rem;font-weight:700;color:#e0e7ff;
          cursor:pointer;transition:.15s;max-width:10rem;
          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
          box-shadow:0 0.125rem 0.5rem rgba(79,70,229,.2);
        }
        .name-label:hover{
          background:linear-gradient(135deg,#312e81,#4338ca);
          border-color:rgba(165,180,252,.6);
          transform:translateY(-1px);
          box-shadow:0 0.25rem 0.75rem rgba(79,70,229,.3);
        }
        .name-label::before{content:'⚔';font-size:.85rem;flex-shrink:0;}

        .npc-avatar-img{
          width:3rem;height:3rem; /* 48px */
          border-radius:0.625rem;
          border:2px solid #e2e8f0;object-fit:contain;
          cursor:pointer;transition:.2s;background:#f8fafc;padding:0.125rem;
        }
        .npc-avatar-img:hover{border-color:#8b5cf6;transform:scale(1.1);box-shadow:0 0.25rem 0.75rem rgba(139,92,246,.25);}

        .btn-del{
          width:1.875rem;height:1.875rem; /* 30px */
          border-radius:0.5rem;
          background:#fee2e2;border:none;color:#ef4444;font-size:.9rem;
          cursor:pointer;transition:.2s;
          display:inline-flex;align-items:center;justify-content:center;
        }
        .btn-del:hover{background:#ef4444;color:#fff;transform:scale(1.1);}

        .btn-add{
          display:inline-flex;align-items:center;gap:0.4375rem; /* 7px */
          padding:0.5625rem 1.125rem; /* 9 18 */
          border-radius:0.625rem;
          border:2px dashed var(--bd2);
          background:transparent;color:var(--tx3);
          font-family:inherit;font-size:.84rem;font-weight:700;
          cursor:pointer;transition:.15s;
        }
        .btn-add:hover{border-color:var(--blue2);color:var(--blue2);background:var(--sky-lt);}
        .btn-add.npc-add:hover{border-color:#8b5cf6;color:#8b5cf6;background:#f5f3ff;}

        /* Nút Lưu Tạm trong modal */
        .btn-save-temp{
          display:inline-flex;align-items:center;gap:0.4375rem;
          padding:0.625rem 1.25rem; /* 10 20 */
          border-radius:0.625rem;border:none;
          background:linear-gradient(135deg,#d97706,#f59e0b);
          color:#fff;font-family:inherit;font-size:.95rem;font-weight:800;
          cursor:pointer;transition:.15s;
          box-shadow:0 0.25rem 0.875rem rgba(217,119,6,.35);
        }
        .btn-save-temp:hover{
          background:linear-gradient(135deg,#b45309,#d97706);
          transform:translateY(-2px);box-shadow:0 0.5rem 1.375rem rgba(217,119,6,.4);
        }
        .btn-save-temp:disabled{opacity:.6;cursor:not-allowed;transform:none;}
        .btn-save-temp.npc-save{
          background:linear-gradient(135deg,#5b21b6,#6d28d9);
          box-shadow:0 0.25rem 0.875rem rgba(109,40,217,.35);
        }
        .btn-save-temp.npc-save:hover{
          background:linear-gradient(135deg,#4c1d95,#5b21b6);
          box-shadow:0 0.5rem 1.375rem rgba(109,40,217,.4);
        }

        /* Nút hành động dòng */
        .row-actions{display:flex;gap:0.5rem;justify-content:center;align-items:center;flex-wrap:wrap;}
        .btn-sm{
          padding:0.4375rem 0.875rem; /* 7 14 */
          font-size:0.908rem;
          font-weight:700;
          border-radius:1.5rem; /* 24px */
          border:none;
          cursor:pointer;
          transition:0.1s;
        }
        .btn-edit{background:#e0f2fe;color:#0369a1;border:1px solid #7dd3fc;}
        .btn-edit:hover{background:#bae6fd;transform:translateY(-1px);}
        .btn-save-row{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;box-shadow:0 0.125rem 0.5rem rgba(5,150,105,.3);}
        .btn-save-row:hover{background:linear-gradient(135deg,#047857,#059669);transform:translateY(-1px);}
        .btn-cancel{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;}
        .btn-cancel:hover{background:#fecaca;}

        /* Badge "chưa lưu" */
        .unsaved-badge{
          display:inline-flex;align-items:center;gap:0.25rem;
          background:#fef3c7;color:#92400e;border:1px solid #fcd34d;
          border-radius:0.75rem;padding:0.125rem 0.5rem;font-size:.72rem;font-weight:700;
          animation:pulse-badge 1.5s infinite;
        }
        @keyframes pulse-badge{0%,100%{opacity:1}50%{opacity:.6}}

        /* ===== SELECTION MODAL ===== */
        #selectionModal .modal-content{
          width:80vw;height:90vh;max-width:none;max-height:none;
          display:flex;flex-direction:column;padding:0;overflow:hidden;
        }
        .sel-search-wrap{position:relative;margin:0 1.625rem 0;flex-shrink:0;}
        .sel-search-wrap input{
          width:100%;padding:0.6875rem 1rem 0.6875rem 2.625rem; /* 11 16 11 42 */
          border:1.5px solid var(--bd);border-radius:0.75rem;
          font-family:inherit;font-size:.92rem;outline:none;
          background:#fff;color:var(--tx);transition:.15s;
        }
        .sel-search-wrap input:focus{border-color:var(--blue2);box-shadow:0 0 0 0.1875rem var(--glow);}
        .sel-search-wrap::before{
          content:'🔍';position:absolute;left:0.8125rem;top:50%;transform:translateY(-50%);
          font-size:.9rem;pointer-events:none;
        }

        .selection-grid{
          display:grid;
          grid-template-columns:repeat(10, 1fr);
          grid-auto-rows:calc((90vh - 110px) / 7);
          gap:0.5rem;padding:0.875rem 1.625rem 1rem;
          overflow-y:auto;flex:1;min-height:0;align-content:start;
        }
        .select-card{
          background:#fff;border:2px solid #e8f0fe;border-radius:0.875rem; /* 14px */
          padding:0.5rem 0.25rem 0.375rem;text-align:center;cursor:pointer;
          transition:.18s cubic-bezier(.34,1.56,.64,1);position:relative;
          overflow:hidden;display:flex;flex-direction:column;
          align-items:center;justify-content:center;
        }
        .select-card::before{
          content:'';position:absolute;inset:0;
          background:linear-gradient(135deg,rgba(59,130,246,.06),rgba(139,92,246,.04));
          opacity:0;transition:.15s;
        }
        .select-card:hover{
          border-color:#93c5fd;transform:translateY(-4px) scale(1.02);
          box-shadow:0 0.5rem 1.5rem rgba(59,130,246,.18);
        }
        .select-card:hover::before{opacity:1;}
        .select-card.npc-card:hover{border-color:#c4b5fd;box-shadow:0 0.5rem 1.5rem rgba(139,92,246,.18);}
        .select-card img{
          width:min(38px,3vw);height:min(38px,3vw);
          object-fit:contain;margin:0 auto 0.25rem;display:block;
        }
        .mob-icon-badge{
          width:min(36px,3vw);height:min(36px,3vw);
          background:linear-gradient(135deg,#312e81,#4338ca);
          border-radius:0.625rem;
          display:flex;align-items:center;justify-content:center;
          font-size:min(18px,1.5vw);
          margin:0 auto 0.25rem;
          box-shadow:0 0.1875rem 0.5rem rgba(79,70,229,.4);
          flex-shrink:0;
        }
        .select-card .sc-name{
          font-weight:700;font-size:clamp(.58rem,.7vw,.75rem);
          color:#1e293b;overflow:hidden;text-overflow:ellipsis;
          white-space:nowrap;margin-bottom:0.125rem;width:100%;
        }
        .select-card .sc-id{
          display:inline-block;background:#f1f5f9;color:#64748b;
          font-size:clamp(.56rem,.65vw,.68rem);font-weight:700;
          padding:0.0625rem 0.3125rem;border-radius:1.25rem;
          font-family:'JetBrains Mono',monospace;
        }
        .select-card.mob-card{border-color:#c7d2fe;}
        .select-card.mob-card:hover{border-color:#818cf8;box-shadow:0 0.5rem 1.5rem rgba(79,70,229,.25);}

        /* Confirm modal */
        .confirm-modal{
          display:none;position:fixed;z-index:10001;left:0;top:0;
          width:100%;height:100%;background:rgba(9,19,31,.65);
          align-items:center;justify-content:center;
        }
        #confirmModal .confirm-box{
          background:var(--surf);border:1.5px solid var(--bd2);
          border-radius:var(--r);padding:2rem;max-width:34.375rem; /* 550px */
          width:90%;text-align:center;animation:pop .2s ease;
          box-shadow:var(--sh);position:relative;
        }
        #confirmModal .close-x{
          position:absolute;top:0.75rem;right:1rem;font-size:1.8rem;
          font-weight:bold;cursor:pointer;color:#ef4444;
          background:none;border:none;line-height:1;transition:0.1s;
        }
        #confirmModal .close-x:hover{transform:scale(1.2);color:#b91c1c;}
        #confirmModal .confirm-box h3{color:#f4c542;font-size:1.4em;margin-bottom:0.75rem;}
        .confirm-msg-main{color:#2ecc71;font-weight:700;}
        .confirm-msg-warning{color:#e74c3c;font-weight:600;display:block;margin-top:0.5rem;}
        #confirmModal .confirm-box p{
          color:var(--tx3);margin-bottom:1.5rem;font-size:1em;
          word-break:break-word;white-space:normal;line-height:1.4;
        }
        #confirmModal .confirm-btns .btn-sm{font-size:1rem;padding:0.5rem 1rem;width:auto;}
        @keyframes pop{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
        .btn-sm.btn-save-p{background:#d6f5ec;color:#0a8a5a;border-color:#7ddab8;}
        .btn-sm.btn-del-p{background:#fde8ea;color:#d92b3a;border-color:#f5a3aa;}
    </style>
</head>
<body>

<!-- Toast notification -->
<div id="toast" class="toast"></div>

<div id="app">
<div class="W">

<!-- TOP BAR -->
<div class="top">
 <div class="ttit">
  <span class="tx-box">
    <svg class="tx-ic" viewBox="0 0 24 24" width="16" height="16" fill="none">
      <path d="M4 7h16M4 12h10M4 17h7" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
    Quản Lý Maps
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
    <span>Làm Mới</span>
  </button>
 </div>
</div>

<!-- BẢNG DỮ LIỆU -->
<div class="tc"><div class="tsc">
<table>
 <colgroup>
  <col style="width:5%">
  <col style="width:15%">
  <col style="width:6%">
  <col style="width:6%">
  <col style="width:32%">
  <col style="width:30%">
  <col style="width:6%">
 </colgroup>
 <thead>
  <tr>
    <th>ID</th>
    <th>NAME</th>
    <th>Zone</th>
    <th>Player</th>
    <th>List Quái</th>
    <th>List NPC</th>
    <th>Sửa</th>
  </tr>
  <tr class="search-row">
    <td><input type="text" placeholder="ID.." id="searchId"></td>
    <td><input type="text" placeholder="Tên.." id="searchName"></td>
    <td><input type="text" placeholder="Max.." id="searchZones"></td>
    <td><input type="text" placeholder="Max.." id="searchPlayers"></td>
    <td></td><td></td><td></td>
   </tr>
 </thead>
 <tbody>
  <?php while($row = $list->fetch(PDO::FETCH_ASSOC)): 
    $cleanMobs = cleanJsonString($row['mobs']);
    $cleanNpcs = cleanJsonString($row['npcs']);
  ?>
  <tr id="row_<?= $row['id'] ?>" data-id="<?= $row['id'] ?>"
      data-initial-name="<?= htmlspecialchars($row['NAME']) ?>"
      data-initial-zones="<?= $row['zones'] ?>"
      data-initial-max="<?= $row['max_player'] ?>"
      data-initial-mobs="<?= htmlspecialchars($cleanMobs) ?>"
      data-initial-npcs="<?= htmlspecialchars($cleanNpcs) ?>">
    <td class="id-cell"><?= $row['id'] ?></td>
    <td><input class="data track" id="n<?= $row['id'] ?>" value="<?= htmlspecialchars($row['NAME']) ?>" disabled></td>
    <td><input class="data track" id="z<?= $row['id'] ?>" value="<?= $row['zones'] ?>" disabled></td>
    <td><input class="data track" id="m<?= $row['id'] ?>" value="<?= $row['max_player'] ?>" disabled></td>
    <td class="mob-col">
      <div class="mob-preview locked" id="mobpreview_<?= $row['id'] ?>" title="Ấn Sửa để chỉnh sửa quái">
        <?= renderMobList($row['mobs'], $mobNames) ?>
      </div>
      <input type="hidden" class="track" id="mb<?= $row['id'] ?>" value="<?= htmlspecialchars($cleanMobs) ?>">
    </td>
    <td>
      <input type="hidden" class="track" id="np<?= $row['id'] ?>" value="<?= htmlspecialchars($cleanNpcs) ?>">
      <div class="npc-container locked" id="preview_<?= $row['id'] ?>" title="Ấn Sửa để chỉnh sửa NPC">
        <?php 
        $npcData = json_decode($row['npcs'], true);
        if(is_array($npcData) && count($npcData) > 0){
            foreach($npcData as $npcItem){
                $nid = (int)$npcItem[0];
                $img = in_array($nid, [3,4,6]) ? [3=>265, 4=>84, 6=>545][$nid] : ($npcAvatars[$nid] ?? 0);
                echo "<div class='npc-item'><img src='/icon/$img.png' onerror=\"this.src='/icon/0.png'\"></div>";
            }
        } else {
            echo "<span class='npc-empty-hint'>👤 Trống</span>";
        }
        ?>
      </div>
     </td>
    <td>
      <div class="row-actions">
        <button class="btn-sm btn-edit edit-row-btn" data-id="<?= $row['id'] ?>">✏️ Sửa</button>
        <button class="btn-sm btn-save-row save-row-btn" data-id="<?= $row['id'] ?>" style="display:none;">💾 Lưu</button>
        <button class="btn-sm btn-cancel cancel-row-btn" data-id="<?= $row['id'] ?>" style="display:none;">❌ Hủy</button>
      </div>
     </td>
   </tr>
  <?php endwhile; ?>
 </tbody>
</table>
</div></div>

</div>
</div>

<!-- ===== SELECTION MODAL ===== -->
<div id="selectionModal" class="modal" style="z-index:10500;">
    <div class="modal-content">
        <div class="modal-header" id="selectionHeader">
            <div class="modal-header-left">
                <div class="modal-icon mob" id="selectionIcon">⚔</div>
                <div>
                    <div class="modal-title" id="selectionTitle">Chọn Quái Vật</div>
                </div>
            </div>
            <button class="modal-close" id="closeSelectionModalBtn">✕</button>
        </div>
        <div style="padding:0.875rem 0 0.25rem;flex-shrink:0;">
            <div class="sel-search-wrap">
                <input type="text" id="selectionSearch" placeholder="Tìm theo tên hoặc ID...">
            </div>
        </div>
        <div id="selectionList" class="selection-grid"></div>
    </div>
</div>

<!-- ===== MOB MODAL ===== -->
<div id="mobModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon mob">⚔️</div>
                <div>
                    <div class="modal-title">Chỉnh Sửa Quái Vật</div>
                    <div class="modal-subtitle" id="mobModalSubtitle">Lưu tạm — ấn 💾 Lưu DB ở dòng để lưu vào database</div>
                </div>
            </div>
            <button class="modal-close" id="closeMobModalBtn">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editingMapId">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th style="width:30%">Quái Vật</th>
                        <th style="width:10%">TNSM</th>
                        <th style="width:18%">HP</th>
                        <th style="width:16%">Tọa Độ X</th>
                        <th style="width:16%">Tọa Độ Y</th>
                        <th style="width:10%">Xóa</th>
                    </tr>
                </thead>
                <tbody id="mobEditBody"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button class="btn-add" id="addMobRowBtn">➕ Thêm Quái</button>
            <button class="btn-save-temp" id="saveMobTempBtn">💾 Lưu Tạm</button>
        </div>
    </div>
</div>

<!-- ===== NPC MODAL ===== -->
<div id="npcModal" class="modal">
    <div class="modal-content">
        <div class="modal-header npc-header">
            <div class="modal-header-left">
                <div class="modal-icon npc">👥</div>
                <div>
                    <div class="modal-title" style="color:#6d28d9;">Chỉnh Sửa NPC</div>
                    <div class="modal-subtitle" id="npcModalSubtitle">Lưu tạm — ấn 💾 Lưu DB ở dòng để lưu vào database</div>
                </div>
            </div>
            <button class="modal-close" id="closeNpcModalBtn">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editingNpcMapId">
            <table class="modal-table npc-table">
                <thead>
                    <tr>
                        <th style="width:20%">Avatar</th>
                        <th style="width:34%">Tọa Độ X</th>
                        <th style="width:34%">Tọa Độ Y</th>
                        <th style="width:12%">Xóa</th>
                    </tr>
                </thead>
                <tbody id="npcEditBody"></tbody>
            </table>
        </div>
        <div class="modal-footer" style="background:#faf5ff;">
            <button class="btn-add npc-add" id="addNpcRowBtn">➕ Thêm NPC</button>
            <button class="btn-save-temp npc-save" id="saveNpcTempBtn">💾 Lưu Tạm</button>
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div id="confirmModal" class="confirm-modal">
  <div class="confirm-box">
    <span class="close-x" id="confirmCloseX">&times;</span>
    <h3>⚠️ Xác Nhận Xóa</h3>
    <p id="confirmMsg">Bạn có chắc muốn xóa?</p>
    <div class="confirm-btns">
      <button class="btn-sm btn-save-p" id="confirmCancelBtn">✖️ Hủy</button>
      <button class="btn-sm btn-del-p" id="confirmYesBtn">🗑️ Xóa</button>
    </div>
  </div>
</div>

<script>
(function(){
    const BASE = 1500;
    const app = document.getElementById('app');
    if (!app) return;

    function applyScale(){
        const vw = window.innerWidth;
        const ratio = (vw * 0.98) / BASE;
        const scaledW = BASE * ratio;
        const marginL = (vw - scaledW) / 2;

        app.style.transform = 'scale(' + ratio + ')';
        app.style.transformOrigin = 'top left';
        app.style.marginLeft = marginL + 'px';
        document.body.style.minHeight = (app.offsetHeight * ratio) + 'px';
    }

    applyScale();
    window.addEventListener('resize', applyScale);
})();

const npcMap = <?= json_encode($npcAvatars) ?>;
const npcNamesMap = <?= json_encode($npcNames) ?>;
const mobNamesMap = <?= json_encode($mobNames) ?>;
const npcFullList = <?= json_encode($npcFullList) ?>;
const mobFullList = <?= json_encode($mobFullList) ?>;

const MOB_EMOJIS = ['🐉','🦇','👹','💀','🧟','🦂','🐺','🦁','🐍','🦈','🧛','👺','🦅','🐗','🦏'];
function getMobEmoji(id) {
    return MOB_EMOJIS[id % MOB_EMOJIS.length];
}

let currentTargetInput = null;
let currentSelectionType = 'mob';
let rowToDelete = null;
let deleteCallback = null;
let currentEditingRowId = null;
let currentMobMapId = null;
let currentNpcMapId = null;

function escapeHtml(str) {
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function normalizeMobEntry(mob) {
    if (typeof mob === 'string') {
        try {
            mob = JSON.parse(mob);
        } catch(e) {
            return null;
        }
    }
    return Array.isArray(mob) ? mob : null;
}

function showToast(message, duration = 2000, isError = false, isWarning = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.remove('error', 'warning');
    if (isError) toast.classList.add('error');
    if (isWarning) toast.classList.add('warning');
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); }, duration);
}

// ==================== LƯU VÀO DB ====================
async function saveMapToDB(mapId, showSuccessMsg = true) {
    const vals = getRowValues(mapId);
    const payload = { id: parseInt(mapId), ...vals };
    let rawText = '';
    try {
        const res = await fetch(window.location.href, {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify(payload)
        });
        rawText = await res.text();
        console.log('[saveMapToDB] raw response:', rawText);
        let result;
        try { result = JSON.parse(rawText); }
        catch(parseErr) {
            console.error('[saveMapToDB] JSON parse error:', parseErr, 'Raw:', rawText);
            showToast("❌ Lỗi response server (xem Console F12)", 3500, true);
            return false;
        }
        if (result.status === "success") {
            const rowEl = document.getElementById('row_' + mapId);
            if (rowEl) {
                rowEl.setAttribute('data-initial-name',  vals.name);
                rowEl.setAttribute('data-initial-zones', vals.zones);
                rowEl.setAttribute('data-initial-max',   vals.max_player);
                rowEl.setAttribute('data-initial-mobs',  vals.mobs);
                rowEl.setAttribute('data-initial-npcs',  vals.npcs);
            }
            updatePreview(mapId);
            renderMobPreview(mapId, vals.mobs);
            $('#row_' + mapId).find('.unsaved-badge').remove();
            const mapName = vals.name || ('#' + mapId);
            if (showSuccessMsg) showToast("✅ Đã lưu map: " + mapName, 2000);
            return true;
        } else {
            const msg = result.msg || 'unknown';
            console.error('[saveMapToDB] server error:', msg);
            showToast("❌ Lỗi DB: " + msg, 2000, true);
            return false;
        }
    } catch(e) {
        console.error('[saveMapToDB] fetch error:', e, 'Raw was:', rawText);
        showToast("❌ Lỗi kết nối: " + e.message, 2000, true);
        return false;
    }
}

function getRowValues(mapId) {
    const $row = $('#row_' + mapId);
    const el = $row[0];
    const nameInput = $row.find('#n'+mapId);
    const zonesInput = $row.find('#z'+mapId);
    const maxInput = $row.find('#m'+mapId);
    return {
        name:       nameInput.prop('disabled')  ? el.getAttribute('data-initial-name')  : nameInput.val(),
        zones:      zonesInput.prop('disabled') ? el.getAttribute('data-initial-zones') : zonesInput.val(),
        max_player: maxInput.prop('disabled')   ? el.getAttribute('data-initial-max')   : maxInput.val(),
        mobs:       $row.find('#mb'+mapId).val(),
        npcs:       $row.find('#np'+mapId).val()
    };
}

// Search
$("#searchId, #searchName, #searchZones, #searchPlayers").on("input", function() {
    const idF = $('#searchId').val().toLowerCase();
    const nameF = $('#searchName').val().toLowerCase();
    const zonesF = $('#searchZones').val().toLowerCase();
    const playersF = $('#searchPlayers').val().toLowerCase();
    $("tbody tr").each(function() {
        const $tr = $(this);
        const id = $tr.data('id')?.toString() || '';
        const el = $tr[0];
        const name = (el?.getAttribute('data-initial-name') || '').toLowerCase();
        const zones = (el?.getAttribute('data-initial-zones') || '').toString();
        const max   = (el?.getAttribute('data-initial-max') || '').toString();
        const show = (!idF || id.includes(idF)) &&
                     (!nameF || name.includes(nameF)) &&
                     (!zonesF || zones.includes(zonesF)) &&
                     (!playersF || max.includes(playersF));
        $tr.toggle(show);
    });
});

// ==================== CHẾ ĐỘ SỬA DÒNG ====================
function enableRowEdit(rowId) {
    if (currentEditingRowId && currentEditingRowId !== rowId) {
        showToast("Vui lòng lưu hoặc hủy dòng đang chỉnh sửa trước!", 1800, false, true);
        return false;
    }
    const $row = $('#row_' + rowId);
    $row.find('#n'+rowId).prop('disabled', false);
    $row.find('#z'+rowId).prop('disabled', false);
    $row.find('#m'+rowId).prop('disabled', false);
    $row.find('.edit-row-btn').hide();
    $row.find('.save-row-btn').show();
    $row.find('.cancel-row-btn').show();
    currentEditingRowId = rowId;

    $('#mobpreview_' + rowId)
        .removeClass('locked')
        .addClass('editable')
        .attr('title', 'Click để sửa quái')
        .off('click')
        .on('click', function() { editMobs(rowId); });

    $('#preview_' + rowId)
        .removeClass('locked')
        .addClass('editable')
        .attr('title', 'Click để sửa NPC')
        .off('click')
        .on('click', function() { editNpcs(rowId); });

    return true;
}

function cancelRowEdit(rowId) {
    const $row = $('#row_' + rowId);
    const el = $row[0];
    $row.find('#n'+rowId).val(el.getAttribute('data-initial-name'));
    $row.find('#z'+rowId).val(el.getAttribute('data-initial-zones'));
    $row.find('#m'+rowId).val(el.getAttribute('data-initial-max'));
    $row.find('#mb'+rowId).val(el.getAttribute('data-initial-mobs'));
    $row.find('#np'+rowId).val(el.getAttribute('data-initial-npcs'));
    updatePreview(rowId);
    renderMobPreview(rowId, el.getAttribute('data-initial-mobs'));
    $row.find('.unsaved-badge').remove();
    disableRowEdit(rowId);
    showToast("Đã hủy thay đổi", 1200);
}

function disableRowEdit(rowId) {
    const $row = $('#row_' + rowId);
    $row.find('#n'+rowId).prop('disabled', true);
    $row.find('#z'+rowId).prop('disabled', true);
    $row.find('#m'+rowId).prop('disabled', true);
    $row.find('.edit-row-btn').show();
    $row.find('.save-row-btn').hide().text('💾 Lưu').prop('disabled', false);
    $row.find('.cancel-row-btn').hide();
    currentEditingRowId = null;

    $('#mobpreview_' + rowId)
        .removeClass('editable')
        .addClass('locked')
        .attr('title', 'Ấn Sửa để chỉnh sửa quái')
        .off('click');

    $('#preview_' + rowId)
        .removeClass('editable')
        .addClass('locked')
        .attr('title', 'Ấn Sửa để chỉnh sửa NPC')
        .off('click');
}

async function saveRowEdit(rowId) {
    const $saveBtn = $('#row_' + rowId).find('.save-row-btn');
    $saveBtn.text('⏳...').prop('disabled', true);
    const success = await saveMapToDB(rowId, true);
    if (success) {
        disableRowEdit(rowId);
    } else {
        $saveBtn.text('💾 Lưu').prop('disabled', false);
    }
}

// ==================== RENDER PREVIEW ====================
function renderMobPreview(mapId, mobsJson) {
    const $container = $('#mobpreview_' + mapId);
    try {
        const mobs = JSON.parse(mobsJson || '[]');
        if (!mobs.length) {
            $container.html('<span class="empty-label">Trống</span>');
            return;
        }
        let seen = {};
        let html = '<div class="mob-list">';
        for (let m of mobs) {
            m = normalizeMobEntry(m);
            if (!m) continue;
            let id = m[0];
            if (seen[id]) continue;
            seen[id] = true;
            let hp = m[2] || '?';
            let name = mobNamesMap[id] || `Quái #${id}`;
            html += `<div class="mob-tag">${escapeHtml(name)} <span class="mob-hp-badge">HP ${hp}</span></div>`;
        }
        html += '</div>';
        $container.html(html);
    } catch(e) {
        $container.html('<span class="empty-label">Lỗi dữ liệu</span>');
    }
}

function updatePreview(id) {
    const container = $('#preview_' + id);
    container.empty();
    try {
        let npcVal = $('#np' + id).val().replace(/[\r\n\t]/g, '');
        const arr = JSON.parse(npcVal);
        if (!arr.length) {
            container.html("<span class='npc-empty-hint'>👤 Trống</span>");
            return;
        }
        arr.forEach(item => {
            const nid = parseInt(item[0]);
            let img = [3,4,6].includes(nid) ? {3:265,4:84,6:545}[nid] : (npcMap[nid] || 0);
            container.append(`<div class='npc-item'><img src='/icon/${img}.png' onerror="this.src='/icon/0.png'"></div>`);
        });
    } catch(e) {}
}

function markRowUnsaved(mapId) {
    const $row = $('#row_' + mapId);
    if ($row.find('.unsaved-badge').length === 0) {
        $row.find('.save-row-btn').after('<span class="unsaved-badge">⚠ Chưa lưu DB</span>');
    }
}

// ==================== SELECTION MODAL ====================
function openSelection(type, inputEl) {
    if (inputEl instanceof jQuery) inputEl = inputEl[0];
    currentTargetInput = inputEl;
    currentSelectionType = type;
    const isMob = type === 'mob';
    $('#selectionTitle').text(isMob ? 'Chọn Quái Vật' : 'Chọn NPC');
    $('#selectionIcon').text(isMob ? '⚔' : '👤')
        .removeClass('mob npc').addClass(isMob ? 'mob' : 'npc');
    $('#selectionModal').css('display','flex');
    $('#selectionSearch').val('').focus();
    renderSelectionList(type);
}

function renderSelectionList(type, filter = '') {
    const list = type === 'mob' ? mobFullList : npcFullList;
    const container = $('#selectionList').empty();
    list.forEach(item => {
        if (filter && !item.NAME.toLowerCase().includes(filter) && !String(item.id).includes(filter)) return;
        if (type === 'mob') {
            const emoji = getMobEmoji(parseInt(item.id));
            container.append(`
                <div class="select-card mob-card" onclick="selectItem('${item.id}', '${escapeHtml(item.NAME)}')">
                    <div class="mob-icon-badge">${emoji}</div>
                    <div class="sc-name">${escapeHtml(item.NAME)}</div>
                    <span class="sc-id">#${item.id}</span>
                </div>
            `);
        } else {
            const nid = parseInt(item.id);
            let avatar = [3,4,6].includes(nid) ? {3:265, 4:84, 6:545}[nid] : item.avatar;
            container.append(`
                <div class="select-card npc-card" onclick="selectItem('${item.id}', '${escapeHtml(item.NAME)}')">
                    <img src="/icon/${avatar}.png" onerror="this.src='/icon/0.png'">
                    <div class="sc-name">${escapeHtml(item.NAME)}</div>
                    <span class="sc-id">#${item.id}</span>
                </div>
            `);
        }
    });
}

$('#selectionSearch').on('input', function() {
    renderSelectionList(currentSelectionType, $(this).val().toLowerCase());
});

function selectItem(id, name) {
    if (currentTargetInput) {
        currentTargetInput.value = id;
        const $row = $(currentTargetInput).closest('tr');
        if ($row.find('.npc-avatar-img').length === 0) {
            const emoji = getMobEmoji(parseInt(id));
            $row.find('.name-label').html(emoji + ' ' + escapeHtml(name) + ' <span style="opacity:.6;font-size:.75em">#' + id + '</span>');
        } else {
            const nid = parseInt(id);
            let avatar = [3,4,6].includes(nid) ? {3:265,4:84,6:545}[nid] : npcMap[nid];
            $row.find('.npc-avatar-img').attr('src', '/icon/' + (avatar||0) + '.png').attr('title', name);
        }
    }
    $('#selectionModal').hide();
}

// ==================== MOB MODAL ====================
function editMobs(mapId) {
    currentMobMapId = mapId;
    let mobJson = $('#mb' + mapId).val().replace(/[\r\n\t]/g, '');
    $('#editingMapId').val(mapId);
    $('#mobEditBody').empty();

    const mapName = document.getElementById('row_' + mapId)?.getAttribute('data-initial-name') || ('Map #' + mapId);
    $('#mobModalSubtitle').text('Map: ' + mapName + ' — Lưu tạm, ấn 💾 Lưu DB ở dòng để lưu vào database');

    try {
        const dataArr = JSON.parse(mobJson || "[]");
        if (!dataArr.length) addNewMobRow(false);
        else dataArr.forEach(m => {
            let d = normalizeMobEntry(m);
            if (d && d.length >= 5) addMobRowHTML(d[0], d[1], d[2], d[3], d[4]);
            else addNewMobRow(false);
        });
    } catch(e) {
        addNewMobRow(false);
        showToast("Lỗi dữ liệu quái, đã tạo dòng mới", 2000, true);
    }
    $('#mobModal').css('display', 'flex');
}

function addMobRowHTML(id, tnsm, hp, x, y) {
    const name = mobNamesMap[id] || "Nhấn để chọn";
    const emoji = getMobEmoji(parseInt(id));
    const $tr = $(`
        <tr>
            <td>
                <input type="hidden" class="mob-id-input" value="${id}">
                <span class="name-label">${emoji} ${escapeHtml(name)} <span style="opacity:.6;font-size:.75em">#${id}</span></span>
             </td>
            <td><input type="number" class="mob-tnsm" value="${tnsm}"></td>
            <td><input type="number" class="mob-hp" value="${hp}"></td>
            <td><input type="number" class="mob-x" value="${x}"></td>
            <td><input type="number" class="mob-y" value="${y}"></td>
            <td><button type="button" class="btn-del">✕</button></td>
         </tr>
    `);
    $tr.find('.name-label').on('click', function() {
        openSelection('mob', $(this).siblings('input.mob-id-input')[0]);
    });
    $tr.find('.btn-del').on('click', function() { confirmDeleteRowTemp(this); });
    $('#mobEditBody').append($tr);
}

function addNewMobRow(openSelector = true) {
    addMobRowHTML(0, 1, 100, 400, 400);
    if (openSelector) {
        const inputEl = $('#mobEditBody tr:last .mob-id-input')[0];
        openSelection('mob', inputEl);
    }
}

function saveTempMob() {
    const mapId = currentMobMapId || $('#editingMapId').val();
    if (!mapId) {
        showToast("❌ Không xác định được Map ID!", 2500, true);
        return;
    }
    let res = [];
    $('#mobEditBody tr').each(function() {
        res.push([
            parseInt($(this).find('.mob-id-input').val()) || 0,
            parseInt($(this).find('.mob-tnsm').val()) || 1,
            parseInt($(this).find('.mob-hp').val()) || 100,
            parseInt($(this).find('.mob-x').val()) || 400,
            parseInt($(this).find('.mob-y').val()) || 400
        ]);
    });
    const newMobsJson = JSON.stringify(res);
    $('#mb' + mapId).val(newMobsJson);
    renderMobPreview(mapId, newMobsJson);
    markRowUnsaved(mapId);
    showToast("💾 Đã lưu tạm danh sách quái!", 2500, false, true);
    $('#mobModal').hide();
}

// ==================== NPC MODAL ====================
function editNpcs(mapId) {
    currentNpcMapId = mapId;
    let npcJson = $('#np' + mapId).val().replace(/[\r\n\t]/g, '');
    $('#editingNpcMapId').val(mapId);
    $('#npcEditBody').empty();

    const mapName = document.getElementById('row_' + mapId)?.getAttribute('data-initial-name') || ('Map #' + mapId);
    $('#npcModalSubtitle').text('Map: ' + mapName + ' — Lưu tạm, ấn 💾 Lưu DB ở dòng để lưu vào database');

    try {
        const dataArr = JSON.parse(npcJson || "[]");
        if (!dataArr.length) addNewNpcRow(false);
        else dataArr.forEach(n => addNpcRowHTML(n[0], n[1], n[2]));
    } catch(e) {
        addNewNpcRow(false);
        showToast("Lỗi dữ liệu NPC, đã tạo dòng mới", 2000, true);
    }
    $('#npcModal').css('display', 'flex');
}

function addNpcRowHTML(id, x, y) {
    const name = npcNamesMap[id] || "Nhấn để chọn";
    const nid = parseInt(id);
    let avatar = [3,4,6].includes(nid) ? {3:265,4:84,6:545}[nid] : npcMap[nid];
    if (!avatar) avatar = 0;
    const $tr = $(`
        <tr>
            <td>
                <input type="hidden" class="npc-id-input" value="${id}">
                <img src="/icon/${avatar}.png" class="npc-avatar-img" onerror="this.src='/icon/0.png'" title="${escapeHtml(name)}">
             </td>
            <td><input type="number" class="npc-x-val" value="${x}"></td>
            <td><input type="number" class="npc-y-val" value="${y}"></td>
            <td><button type="button" class="btn-del">✕</button></td>
         </tr>
    `);
    $tr.find('.npc-avatar-img').on('click', function() {
        openSelection('npc', $(this).siblings('input.npc-id-input')[0]);
    });
    $tr.find('.btn-del').on('click', function() { confirmDeleteRowTemp(this); });
    $('#npcEditBody').append($tr);
}

function addNewNpcRow(openSelector = true) {
    addNpcRowHTML(0, 400, 400);
    if (openSelector) {
        const inputEl = $('#npcEditBody tr:last .npc-id-input')[0];
        openSelection('npc', inputEl);
    }
}

function saveTempNpc() {
    const mapId = currentNpcMapId || $('#editingNpcMapId').val();
    if (!mapId) {
        showToast("❌ Không xác định được Map ID!", 2500, true);
        return;
    }
    let res = [];
    $('#npcEditBody tr').each(function() {
        res.push([
            parseInt($(this).find('.npc-id-input').val()) || 0,
            parseInt($(this).find('.npc-x-val').val()) || 400,
            parseInt($(this).find('.npc-y-val').val()) || 400
        ]);
    });
    const newNpcsJson = JSON.stringify(res);
    $('#np' + mapId).val(newNpcsJson);
    updatePreview(mapId);
    markRowUnsaved(mapId);
    showToast("💾 Đã lưu tạm danh sách NPC!", 2500, false, true);
    $('#npcModal').hide();
}

// Xóa dòng tạm trong modal
function confirmDeleteRowTemp(btn) {
    rowToDelete = $(btn).closest('tr');
    let nameLabel = rowToDelete.find('.name-label').text();
    if (!nameLabel) nameLabel = rowToDelete.find('.npc-avatar-img').attr('title') || 'mục này';
    const cleanName = nameLabel.split(' (ID:')[0].replace(/[⚔👤]/g,'').trim();
    $('#confirmMsg').html(`
        <span class="confirm-msg-main">Xóa "${escapeHtml(cleanName)}"?</span>
        <span class="confirm-msg-warning">Không thể hoàn tác!</span>
    `);
    $('#confirmModal').css('display', 'flex');
    deleteCallback = function() {
        if (rowToDelete) {
            rowToDelete.fadeOut(300, function() { $(this).remove(); rowToDelete = null; });
            showToast('🗑️ Đã xóa khỏi danh sách!', 1500);
        }
        deleteCallback = null;
        $('#confirmModal').hide();
    };
}

// Đóng modal
$('#closeMobModalBtn, #closeNpcModalBtn, #closeSelectionModalBtn').on('click', function() {
    $('#mobModal, #npcModal, #selectionModal').hide();
});

$('#saveMobTempBtn').on('click', function() { saveTempMob(); });
$('#saveNpcTempBtn').on('click', function() { saveTempNpc(); });
$('#addMobRowBtn').on('click', () => addNewMobRow(true));
$('#addNpcRowBtn').on('click', () => addNewNpcRow(true));

$(document).on('click', '.edit-row-btn', function() { enableRowEdit($(this).data('id')); });
$(document).on('click', '.save-row-btn', function() { saveRowEdit($(this).data('id')); });
$(document).on('click', '.cancel-row-btn', function() { cancelRowEdit($(this).data('id')); });

$('#confirmYesBtn').on('click', function() { if (deleteCallback) deleteCallback(); });
$('#confirmCancelBtn, #confirmCloseX').on('click', function() {
    $('#confirmModal').hide();
    rowToDelete = null; deleteCallback = null;
});
</script>
</body>
</html>
