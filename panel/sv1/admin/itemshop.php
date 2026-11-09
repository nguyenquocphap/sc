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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Quản Lý Cửa Hàng</title>
    <link rel="icon" type="image/jpeg" href="/img/icon.jpg">
    <style>
        /* ========== TOKENS (giống account.php) ========== */
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

        /* ===== TOPBAR ===== */
        .top {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 8px;
            background: var(--surf); border: 1px solid var(--bd2);
            border-radius: var(--r); padding: 16px 22px 14px;
            margin-bottom: 10px; box-shadow: var(--sh);
            position: relative; overflow: hidden; text-align: center;
        }

        .ttit {
            font-size: 1.35rem; font-weight: 800; letter-spacing: -.4px; color: var(--tx);
        }

        .tx-box {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 11px 26px;
            border-radius: 999px;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            color: #fff; font-weight: 800; font-size: 1.05rem;
            box-shadow: 0 6px 18px rgba(167,139,250,.4);
            border: 1px solid rgba(255,255,255,.3);
            letter-spacing: .3px; cursor: pointer;
            transition: all .15s; line-height: 1.3;
        }

        .tx-box:hover { filter: brightness(1.07); transform: translateY(-1px); }

        .tr2 {
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center;
        }

        .header-box {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 8px;
            background: var(--surf); border: 1px solid var(--bd2);
            border-radius: var(--r); padding: 16px 22px 14px;
            margin-bottom: 10px; box-shadow: var(--sh);
            position: relative; overflow: hidden; text-align: center;
        }

        .header-left,
        .header-content {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 8px;
        }

        .header-left > img,
        .header-right {
            display: none;
        }

        .header-content h1 {
            display: inline-flex; align-items: center; gap: 10px;
            margin: 0;
            padding: 11px 26px;
            border-radius: 999px;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            color: #fff; font-weight: 800; font-size: 1.05rem;
            box-shadow: 0 6px 18px rgba(167,139,250,.4);
            border: 1px solid rgba(255,255,255,.3);
            letter-spacing: .3px; cursor: pointer;
            transition: all .15s; line-height: 1.3;
        }

        .header-content h1:hover {
            filter: brightness(1.07); transform: translateY(-1px);
        }

        .menu-buttons {
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 11px 20px;
            border: none;
            border-radius: var(--rs);
            font-family: inherit;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: all .15s;
            letter-spacing: .2px;
            line-height: 1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.15);
        }

        .btn:active {
            transform: scale(.96);
        }

        .bblue {
            background: linear-gradient(135deg, var(--blue2), var(--indigo));
            color: #fff;
            box-shadow: 0 3px 12px var(--glow);
        }

        .bteal {
            background: linear-gradient(135deg, var(--teal), #0d9488);
            color: #fff;
        }

        /* ===== SHOP SELECTION AREA ===== */
        .shop-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 10px;
        }

        .shop-item {
            background: var(--surf);
            border-radius: var(--r);
            padding: 50px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--tx2);
            box-shadow: var(--sh2);
            border: 2px solid var(--bd);
            transition: all .25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .shop-item:hover {
            transform: translateY(-8px);
            border-color: var(--blue2);
            background: var(--surf2);
            box-shadow: 0 15px 35px var(--glow);
            color: var(--blue);
        }

        .icon-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .icon-container img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            image-rendering: pixelated;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.1));
        }

        .shop-item h2 {
            margin: 15px 0 8px 0;
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--tx);
        }

        .shop-item span {
            color: var(--tx3);
            font-size: 1.05rem;
            font-weight: 600;
        }

        /* Tùy chỉnh scrollbar đồng bộ */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--surf2); }
        ::-webkit-scrollbar-thumb { background: var(--bd2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--tx3); }
    </style>
</head>
<body>
<div id="scale-root">
    <div class="W">

        <div class="header-box">
            <div class="header-left">
                <img src="/img/account.gif" alt="account">
                <div class="header-content">
                    <h1>🛒 Quản Lý Cửa Hàng</h1>
                    <div class="menu-buttons">
                        <button class="btn bblue" onclick="location.href='/sv1/admin/panel'">🏠 Panel</button>
                        <button class="btn bteal" onclick="location.reload()">🔄 Làm Mới</button>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <img src="/img/111.jpg" alt="vip">
            </div>
        </div>

        <div class="shop-selection">
            <a href="ngoc" class="shop-item">
                <div class="icon-container">
                    <img src="/img/930.png" onerror="this.src='/icon/930.png'" alt="ngọc">
                    <img src="/img/932.png" onerror="this.src='/icon/932.png'" alt="vàng">
                </div>
                <h2>Shop Vàng, Ngọc</h2>
                <span>Chỉnh sửa vật phẩm bán bằng vàng và ngọc</span>
            </a>

            <a href="/sv1/admin/vang" class="shop-item">
                <div class="icon-container">
                    <img src="/icon/4028.png" alt="item1">
                    <img src="/icon/14116.png" alt="item2">
                    <img src="/icon/15485.png" alt="item3">
                    <img src="/icon/7223.png" alt="item4">
                </div>
                <h2>Shop Thỏi Vàng</h2>
                <span>Vật Phẩm Đặc Biệt & Thỏi Vàng</span>
            </a>
        </div>

    </div><!-- /W -->
</div><!-- /scale-root -->

<script>
    /* ================= SCALE (giống account.php) ================= */
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
</script>
</body>
</html>
