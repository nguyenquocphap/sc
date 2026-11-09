<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . "/config.php";
ensure_csrf_token();

function tc_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function tc_client_hash(): string { return hash('sha256', ($_SESSION['account_id'] ?? 'guest') . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')); }

function tc_download_url(string $filename): string {
    $safeFilename = basename($filename);
    $filePath = __DIR__ . '/down/' . $safeFilename;
    $version = is_file($filePath) ? (string) filemtime($filePath) : (string) time();

    return '/sv1/down/' . rawurlencode($safeFilename) . '?v=' . rawurlencode($version);
}

function tc_ensure_comment_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NULL,
        section ENUM('admin','player') NOT NULL DEFAULT 'player',
        account_id INT NULL,
        author_name VARCHAR(80) NOT NULL DEFAULT 'conga',
        author_icon INT NOT NULL DEFAULT 1474,
        is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        content TEXT NOT NULL,
        status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_section_parent (section, parent_id, created_at),
        INDEX idx_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $existsIcon = $pdo->query("SHOW COLUMNS FROM site_comments LIKE 'author_icon'")->fetch();
    if (!$existsIcon) $pdo->exec("ALTER TABLE site_comments ADD COLUMN author_icon INT NOT NULL DEFAULT 1474 AFTER author_name");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_comment_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        account_id INT NULL,
        client_hash CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_comment_client (comment_id, client_hash),
        INDEX idx_comment_id (comment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tc_current_user(PDO $pdo): array {
    $accountId = (int)($_SESSION['account_id'] ?? 0);
    $account = null; $playerName = 'conga'; $gender = 0; $power = 0; $hasPlayer = false;
    if ($accountId > 0) {
        $stmt = $pdo->prepare("SELECT id, username, is_admin FROM account WHERE id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmt = $pdo->prepare("SELECT name, gender, data_point FROM player WHERE account_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$accountId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($player) {
            $hasPlayer = true;
            $name = trim((string)($player['name'] ?? ''));
            if ($name !== '') $playerName = $name;
            $gender = (int)($player['gender'] ?? 0);
            if (!empty($player['data_point'])) {
                $arr = explode(',', trim((string)$player['data_point'], '[]'));
                $power = isset($arr[1]) ? (int)$arr[1] : 0;
            }
        }
    }
    $isAdmin = !empty($account['is_admin']);
    $icon = $isAdmin ? 16103 : ($gender === 2 ? 1475 : ($gender === 1 ? 1476 : 1474));
    return ['account_id'=>$accountId,'is_logged'=>$accountId > 0 && $account,'is_admin'=>$isAdmin,'player_name'=>$playerName,'gender'=>$gender,'power'=>$power,'has_player'=>$hasPlayer,'icon'=>$icon];
}

function tc_daily_comment_count(PDO $pdo, int $accountId, bool $reply): int {
    if ($accountId <= 0) return 0;
    $where = $reply ? 'parent_id IS NOT NULL' : 'parent_id IS NULL';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_comments WHERE account_id = ? AND section = 'player' AND $where AND created_at >= CURDATE()");
    $stmt->execute([$accountId]);
    return (int)$stmt->fetchColumn();
}

function tc_relative_time($datetime): string {
    $ts = strtotime((string)$datetime);
    if (!$ts) return '';
    $diff = max(0, time() - $ts);
    if ($diff < 60) return 'vừa xong';
    $minutes = intdiv($diff, 60);
    if ($minutes < 60) return 'cách đây ' . $minutes . ' phút';
    $hours = intdiv($minutes, 60);
    if ($hours < 24) return 'cách đây ' . $hours . ' giờ';
    $days = intdiv($hours, 24);
    if ($days < 30) return 'cách đây ' . $days . ' ngày';
    $months = intdiv($days, 30);
    if ($months < 12) return 'cách đây ' . $months . ' tháng';
    return 'cách đây ' . intdiv($months, 12) . ' năm';
}

function tc_latest_activity(array $comment, array $children): int {
    $latest = strtotime((string)($comment['created_at'] ?? '')) ?: 0;
    $id = (int)($comment['id'] ?? 0);
    foreach ($children[$id] ?? [] as $child) {
        $latest = max($latest, tc_latest_activity($child, $children));
    }
    return $latest;
}

function tc_comment_preview(string $content): string {
    $lines = preg_split('/\R/u', trim($content));
    $preview = trim((string)($lines[0] ?? $content));
    if ($preview === '') $preview = trim(preg_replace('/\s+/u', ' ', $content));
    return $preview !== '' ? $preview : 'Nội dung';
}
function tc_render_comment(array $comment, array $children, array $likes, array $serials = []): string {
    global $tcUser, $newPlayerCommentIds;
    $id = (int)$comment['id'];
    $isAdmin = !empty($comment['is_admin']);
    $isRoot = empty($comment['parent_id']);
    $canAdminManage = !empty($tcUser['is_admin']);
    $isOwner = !empty($tcUser['is_logged']) && (int)($comment['account_id'] ?? 0) === (int)$tcUser['account_id'];
    $canDelete = $canAdminManage || (!$isAdmin && $isOwner);
    $canEdit = $canAdminManage;
    $badge = $isAdmin ? '<span class="comment-badge admin">ADMIN</span>' : (!empty($comment['is_anonymous']) ? '<span class="comment-badge anon">ẨN DANH</span>' : '<span class="comment-badge player">PLAYER</span>');
    $likeCount = (int)($likes[$id] ?? 0);
    $replyCount = count($children[$id] ?? []);
    $icon = $isAdmin ? 16103 : (int)($comment['author_icon'] ?? 1474);
    if (!empty($comment['is_anonymous'])) $icon = 1474;
    $serial = $isRoot && isset($serials[$id]) ? '<span class="comment-stt">#' . (int)$serials[$id] . '</span>' : '';
    $newBadge = (!$isAdmin && $isRoot && in_array($id, $newPlayerCommentIds ?? [], true)) ? '<span class="comment-new-badge">NEW</span>' : '';
    $html = '<article class="comment-card ' . ($isAdmin ? 'is-admin' : '') . '" id="comment-' . $id . '">';
    $html .= '<div class="comment-head"><img class="comment-avatar" src="../img/' . $icon . '.png" alt="avatar"><div class="comment-meta">' . $serial . '<strong>' . tc_h($comment['author_name']) . '</strong>' . $badge . '<time title="' . tc_h(date('d/m/Y H:i', strtotime($comment['created_at']))) . '">' . tc_h(tc_relative_time($comment['created_at'])) . '</time>' . $newBadge . '</div></div>';
    $contentText = (string)$comment['content'];
    $previewText = tc_comment_preview($contentText);
    $html .= '<div class="comment-content collapsed" id="commentContent-' . $id . '"><span class="comment-preview">' . tc_h($previewText) . '</span><span class="comment-full">' . nl2br(tc_h($contentText)) . '</span></div> <button type="button" class="comment-more" onclick="toggleCommentFull(' . $id . ', this)">... Xem thêm</button>';
    $html .= '<div class="comment-actions"><form method="post"><input type="hidden" name="csrf_token" value="' . tc_h(csrf_token()) . '"><input type="hidden" name="comment_action" value="like"><input type="hidden" name="comment_id" value="' . $id . '"><button type="submit"><i class="fa-solid fa-thumbs-up"></i> Like <span>' . $likeCount . '</span></button></form>';
    if (!$isAdmin) $html .= '<button type="button" onclick="toggleReplyForm(' . $id . ')"><i class="fa-solid fa-reply"></i> Trả lời</button>';
    if (!$isAdmin && $replyCount > 0) $html .= '<button type="button" onclick="toggleReplies(' . $id . ', this)"><i class="fa-solid fa-comments"></i> Hiện (' . $replyCount . ')</button>';
    if ($canEdit) $html .= '<button type="button" onclick="toggleEditForm(' . $id . ')"><i class="fa-solid fa-pen"></i> Sửa</button>';
    if ($canDelete) $html .= '<button type="button" onclick="openDeleteCommentModal(' . $id . ')"><i class="fa-solid fa-trash"></i> Xóa</button>';
    $html .= '</div>';
    if ($canEdit) $html .= '<form class="edit-form" id="editForm-' . $id . '" method="post"><input type="hidden" name="csrf_token" value="' . tc_h(csrf_token()) . '"><input type="hidden" name="comment_action" value="update_comment"><input type="hidden" name="comment_id" value="' . $id . '"><textarea name="content" maxlength="1000" required>' . tc_h($comment['content']) . '</textarea><div class="edit-actions"><button class="edit-save" type="submit"><i class="fa-solid fa-check"></i> Lưu</button><button class="edit-cancel" type="button" onclick="toggleEditForm(' . $id . ')"><i class="fa-solid fa-xmark"></i> Hủy</button></div></form>';
    if (!$isAdmin) $html .= '<form class="reply-form" id="replyForm-' . $id . '" method="post"><input type="hidden" name="csrf_token" value="' . tc_h(csrf_token()) . '"><input type="hidden" name="comment_action" value="create_comment"><input type="hidden" name="parent_id" value="' . $id . '"><textarea name="content" maxlength="1000" placeholder="Viết trả lời..." required></textarea><label><input type="checkbox" name="anonymous" value="1"> Ẩn danh</label><button type="submit">Gửi trả lời</button></form>';
    if (!empty($children[$id])) {
        $html .= '<div class="comment-replies" id="replies-' . $id . '">';
        foreach ($children[$id] as $reply) $html .= tc_render_comment($reply, $children, $likes, $serials);
        $html .= '</div>';
    }
    return $html . '</article>';
}

tc_ensure_comment_tables($pdo);
$tcUser = tc_current_user($pdo);
$commentNotice = '';
$minCommentPower = 10000000000;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['comment_action'])) {
    require_csrf_token();
    $action = (string)$_POST['comment_action'];
    if ($action === 'forum_logout') {
        $_SESSION = [];
        session_unset();
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#communityComments');
        exit;
    }
    if ($action === 'create_comment') {
        $content = mb_substr(trim((string)($_POST['content'] ?? '')), 0, 1000, 'UTF-8');
        $parentId = max(0, (int)($_POST['parent_id'] ?? 0));
        $section = ($_POST['section'] ?? 'player') === 'admin' ? 'admin' : 'player';
        $anonymous = !empty($_POST['anonymous']);
        if ($content !== '') {
            if ($parentId > 0) {
                $stmt = $pdo->prepare("SELECT id, section, is_admin FROM site_comments WHERE id = ? AND status = 'visible' LIMIT 1");
                $stmt->execute([$parentId]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($parent) { $section = $parent['section']; if (!empty($parent['is_admin'])) $parentId = 0; } else $parentId = 0;
            }
            $isReply = $parentId > 0;
            if ($section === 'admin') {
                if (!$tcUser['is_admin']) $commentNotice = 'Bạn không có quyền đăng bài admin.';
                else {
                    $stmt = $pdo->prepare("INSERT INTO site_comments (parent_id, section, account_id, author_name, author_icon, is_anonymous, is_admin, content) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([null, 'admin', $tcUser['account_id'] ?: null, 'Admin', 16103, 0, 1, $content]);
                    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#communityComments'); exit;
                }
            } else {
                if (!$tcUser['is_logged']) $commentNotice = 'Bạn cần đăng nhập diễn đàn trước khi đăng bài.';
                elseif (!$tcUser['is_admin'] && (!$tcUser['has_player'] || $tcUser['power'] < $minCommentPower)) $commentNotice = 'Nhân vật cần trên 10 tỷ sức mạnh mới được đăng bài hoặc trả lời.';
                elseif (!$tcUser['is_admin'] && !$isReply && tc_daily_comment_count($pdo, (int)$tcUser['account_id'], false) >= 3) $commentNotice = 'Mỗi tài khoản chỉ được đăng 3 bài mỗi ngày.';
                elseif (!$tcUser['is_admin'] && $isReply && tc_daily_comment_count($pdo, (int)$tcUser['account_id'], true) >= 100) $commentNotice = 'Mỗi tài khoản chỉ được trả lời 100 lần mỗi ngày.';
                else {
                    $author = $anonymous ? 'Ẩn danh' : $tcUser['player_name'];
                    $icon = $anonymous ? 1474 : (int)$tcUser['icon'];
                    $stmt = $pdo->prepare("INSERT INTO site_comments (parent_id, section, account_id, author_name, author_icon, is_anonymous, is_admin, content) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$parentId ?: null, 'player', $tcUser['account_id'], $author, $icon, $anonymous ? 1 : 0, 0, $content]);
                    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#communityComments'); exit;
                }
            }
        }
    } elseif ($action === 'update_comment') {
        $commentId = max(0, (int)($_POST['comment_id'] ?? 0));
        $content = mb_substr(trim((string)($_POST['content'] ?? '')), 0, 1000, 'UTF-8');
        if (!$tcUser['is_admin']) $commentNotice = 'Chỉ admin được chỉnh sửa bài viết.';
        elseif ($commentId > 0 && $content !== '') {
            $stmt = $pdo->prepare("UPDATE site_comments SET content = ? WHERE id = ? AND status = 'visible'");
            $stmt->execute([$content, $commentId]);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#comment-' . $commentId); exit;
        }
    } elseif ($action === 'delete_comment') {
        $commentId = max(0, (int)($_POST['comment_id'] ?? 0));
        if ($commentId > 0) {
            $stmt = $pdo->prepare("SELECT id, account_id, is_admin FROM site_comments WHERE id = ? AND status = 'visible' LIMIT 1");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            $canDelete = $comment && ($tcUser['is_admin'] || (empty($comment['is_admin']) && $tcUser['is_logged'] && (int)$comment['account_id'] === (int)$tcUser['account_id']));
            if (!$canDelete) {
                $commentNotice = 'Bạn không có quyền xóa bài viết này.';
            } else {
                $ids = [$commentId]; $queue = [$commentId];
                while ($queue) {
                    $current = array_pop($queue);
                    $stmt = $pdo->prepare("SELECT id FROM site_comments WHERE parent_id = ?");
                    $stmt->execute([$current]);
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) { $ids[] = (int)$childId; $queue[] = (int)$childId; }
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM site_comment_likes WHERE comment_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM site_comments WHERE id IN ($placeholders)")->execute($ids);
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#communityComments'); exit;
            }
        }
    } elseif ($action === 'like') {
        $commentId = max(0, (int)($_POST['comment_id'] ?? 0));
        if ($commentId > 0) {
            $hash = tc_client_hash();
            $stmt = $pdo->prepare("SELECT id FROM site_comment_likes WHERE comment_id = ? AND client_hash = ? LIMIT 1");
            $stmt->execute([$commentId, $hash]);
            $existing = $stmt->fetchColumn();
            if ($existing) $pdo->prepare("DELETE FROM site_comment_likes WHERE id = ?")->execute([$existing]);
            else $pdo->prepare("INSERT IGNORE INTO site_comment_likes (comment_id, account_id, client_hash) VALUES (?, ?, ?)")->execute([$commentId, $tcUser['account_id'] ?: null, $hash]);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#comment-' . $commentId); exit;
        }
    }
}

$stmt = $pdo->query("SELECT * FROM site_comments WHERE status = 'visible' ORDER BY created_at ASC, id ASC");
$allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$commentChildren = []; $adminComments = []; $playerComments = []; $rootSerials = []; $serial = 1;
foreach ($allComments as $row) {
    $pid = (int)($row['parent_id'] ?? 0);
    if ($pid > 0) $commentChildren[$pid][] = $row;
    elseif ($row['section'] === 'admin') { $adminComments[] = $row; $rootSerials[(int)$row['id']] = $serial++; }
    else { $playerComments[] = $row; $rootSerials[(int)$row['id']] = $serial++; }
}
$newPlayerCommentRows = $playerComments;
usort($newPlayerCommentRows, fn($a, $b) => (strtotime((string)$b['created_at']) ?: 0) <=> (strtotime((string)$a['created_at']) ?: 0));
$newPlayerCommentIds = array_map(fn($row) => (int)$row['id'], array_slice($newPlayerCommentRows, 0, 3));
usort($adminComments, fn($a, $b) => (strtotime((string)$b['created_at']) ?: 0) <=> (strtotime((string)$a['created_at']) ?: 0));
usort($playerComments, fn($a, $b) => tc_latest_activity($b, $commentChildren) <=> tc_latest_activity($a, $commentChildren));
$commentLikes = [];
foreach ($pdo->query("SELECT comment_id, COUNT(*) total FROM site_comment_likes GROUP BY comment_id") as $row) $commentLikes[(int)$row['comment_id']] = (int)$row['total'];
?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trang Chủ - Chào Mừng</title>
  <link rel="icon" type="image/jpeg" href="../img/icon.jpg">
  <link rel="stylesheet" href="/sv1/trangchu.css?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/trangchu.css') ?: time())) ?>">
  <style>
    /* Critical SMS verification styles: kept inline so the online site does not break when old CSS is cached. */
    .sms-code-button{position:relative;display:flex!important;align-items:center;gap:12px;width:100%;min-height:58px;padding:9px 14px!important;overflow:hidden;border:1px solid rgba(255,213,79,.85)!important;border-radius:13px!important;color:#04142b;text-align:left;font-family:'Exo 2',sans-serif;cursor:pointer;background:linear-gradient(135deg,#ffe082,#ff8f00)!important;box-shadow:0 8px 24px rgba(255,143,0,.3),inset 0 1px rgba(255,255,255,.45);transition:transform .2s,box-shadow .2s,filter .2s,opacity .2s;}
    .sms-code-button::after{content:"";position:absolute;inset:0;transform:translateX(-110%);background:linear-gradient(110deg,transparent 25%,rgba(255,255,255,.42) 50%,transparent 75%);transition:transform .55s ease;}
    .sms-code-button:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 11px 30px rgba(255,143,0,.5);}
    .sms-code-button:not(:disabled):hover::after{transform:translateX(110%);}
    .sms-code-button__icon{position:relative;z-index:1;flex:0 0 38px;height:38px;border-radius:10px;display:grid;place-items:center;color:#fff;font-size:1.05rem;background:rgba(4,20,43,.82);box-shadow:0 4px 12px rgba(4,20,43,.25);}
    .sms-code-button__content{position:relative;z-index:1;display:flex;flex:1;flex-direction:column;gap:2px;min-width:0;}
    .sms-code-button__content strong{font-size:.82rem;letter-spacing:.2px;line-height:1.2;white-space:normal;}
    .sms-code-button__content small{font-size:.62rem;font-weight:600;line-height:1.25;opacity:.72;white-space:normal;}
    .sms-code-button__arrow{position:relative;z-index:1;font-size:.8rem;transition:transform .2s;}
    .sms-code-button:not(:disabled):hover .sms-code-button__arrow{transform:translateX(3px);}
    .sms-code-button:disabled{color:#7890a8;cursor:not-allowed;opacity:.58;filter:grayscale(1);border-color:rgba(144,202,249,.22)!important;background:rgba(255,255,255,.055)!important;box-shadow:none;}
    .sms-code-button:disabled .sms-code-button__icon{color:#7890a8;background:rgba(4,20,43,.55);box-shadow:none;}
    .sms-code-display{margin-top:10px;padding:10px 14px;border:1px dashed rgba(255,213,79,.7);border-radius:11px;color:#ffd54f;background:linear-gradient(135deg,rgba(255,213,79,.1),rgba(255,109,0,.07));text-align:center;box-shadow:inset 0 0 18px rgba(255,213,79,.05);}
    .sms-code-display span{display:block;color:#90caf9;font-size:.65rem;letter-spacing:.4px;}
    .sms-code-display strong{display:block;margin-top:3px;font-family:'Orbitron',sans-serif;font-size:1.12rem;letter-spacing:5px;}
    .sms-code-status{min-height:18px;margin-top:6px;color:#90caf9;font-size:.72rem;}
    .sms-code-status.success{color:#69f0ae;}
    .sms-code-status.error{color:#ff8a80;}
    #btnRegister:disabled{cursor:not-allowed;box-shadow:none;filter:grayscale(.35);}
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800;900&family=Exo+2:wght@300;400;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    .boss-widget { margin-top: 24px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); border-radius: 20px; padding: 18px; max-width: 1200px; margin-left:auto; margin-right:auto; }
    .boss-widget__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 0.98rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #ffd966; margin-bottom: 14px; }
    .boss-widget__title { margin: 0; }
    .boss-widget__toggle { background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 12px; padding: 10px 16px; font-size: 0.9rem; cursor: pointer; transition: background .2s ease; }
    .boss-widget__toggle:hover { background: rgba(255,255,255,.18); }
    .boss-list { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: 12px; margin-top: 12px; }
    .boss-list.hidden { display: none; }
    .boss-card { width: 100%; background: rgba(1,12,30,.75); border: 1px solid rgba(255,255,255,.13); border-radius: 13px; padding: 10px; display: grid; grid-template-rows: auto 1fr auto; min-height: 215px; }
    .boss-card__image { width: 100%; aspect-ratio: 1.1; border-radius: 10px; background: linear-gradient(135deg,#18213b,#0b0f22); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .65rem; font-weight: 700; text-align: center; padding: 9px; overflow: hidden; position: relative; }
    .boss-card__image img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .boss-card__name { margin-top: 9px; font-weight: 800; color: #f4f4f4; font-size: .83rem; }
    .boss-card__state-badge { position: absolute; top: 7px; left: 7px; width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .62rem; border: 1px solid rgba(255,255,255,.88); box-shadow: 0 4px 12px rgba(0,0,0,.36); }
    .boss-card__state-badge.is-alive { background: #16a34a; }
    .boss-card__state-badge.is-dead { background: #dc2626; }
    .boss-card__hp { margin-top: 9px; font-size: .75rem; color: #e6e6e6; }
    .boss-card__bar { height: 7px; border-radius: 999px; overflow: hidden; background: rgba(255,255,255,.12); margin-top: 7px; }
    .boss-card__bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#22c55e,#86efac); transition: width 0.4s ease; }
    .boss-card__details { margin-top: 8px; color: #b5c4ff; font-size: .73rem; line-height: 1.4; }
    .boss-card__detail-item { margin-top: 4px; }
    .boss-card__loading { text-align: center; color: #fff; opacity: .85; padding: 18px 0; }
    @media (max-width: 1024px) {
      .boss-list { grid-template-columns: repeat(6,minmax(0,1fr)); gap: 10px; }
    }
    .landing-center .server-info { margin: 8px 0 30px; position: relative; z-index: 1; }
    .landing-center .hero-cta-row { margin-top: 0; position: relative; z-index: 1; }
    #downloadModal .download-actions { gap: 10px; margin-top: 8px; }
    #downloadModal .dl-primary, #downloadModal .dl-secondary { padding: 10px 11px; border-radius: 10px; font-size: .8em; }
    .dl-primary { font-size: 1.3em; }
    .download-confirm-text { color:#d8eaff; line-height:1.55; margin: 8px 0 18px; }
    @media (max-width: 768px) {
      #bossModal .modal-box.modal-large { width: 98vw; padding-left: 14px; padding-right: 14px; }
      #bossModal .modal-scroll-body { padding-right: 2px; }
      .boss-list { grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
      .boss-card { min-height: 203px; }
    }
    @media (max-width: 420px) {
      #bossModal .modal-box.modal-large { width: calc(100vw - 12px); padding-left: 10px; padding-right: 10px; }
      .boss-list { gap: 8px; }
      .boss-card { padding: 8px; min-height: 188px; }
    }
  
    /* NPC command redesign */
    .site-shell { width: min(1200px, calc(100vw - 28px)); margin: 0 auto; }
    .landing-shell {
      width: min(1200px, calc(100vw - 28px)); margin: 28px auto 44px;
      min-height: 700px; display: grid; grid-template-columns: 270px minmax(0,1fr) 270px;
      gap: 18px; align-items: center; position: relative;
    }
    .landing-center {
      min-height: 560px; border: 1.5px solid rgba(77,184,255,.34); border-radius: 28px;
      background: linear-gradient(180deg, rgba(4,20,43,.74), rgba(8,44,88,.58));
      box-shadow: 0 22px 70px rgba(0,0,0,.38), inset 0 0 36px rgba(77,184,255,.08);
      display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 28px;
      text-align: center; overflow: hidden; position: relative;
    }
    .landing-center::before {
      content: ''; position: absolute; inset: 18px; border-radius: 22px;
      border: 1px dashed rgba(255,213,79,.24); pointer-events: none;
    }
    .npc-column { display: flex; flex-direction: column; gap: 26px; }
    .npc-column.left { align-items: flex-end; }
    .npc-column.right { align-items: flex-start; padding-top: 82px; }
    .npc-node {
      width: 250px; min-height: 250px; border: 1.5px solid rgba(77,184,255,.28); border-radius: 18px;
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(4,20,43,.72));
      padding: 14px; display: grid; grid-template-rows: 132px auto auto; gap: 8px;
      cursor: pointer; transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
      box-shadow: 0 12px 28px rgba(0,0,0,.25); position: relative;
    }
    .npc-node:nth-child(2) { transform: translateX(-34px); }
    .npc-column.right .npc-node:nth-child(1) { transform: translateX(26px); }
    .npc-column.right .npc-node:nth-child(2) { transform: translateX(-10px); }
    .npc-node:hover { border-color: rgba(255,213,79,.7); box-shadow: 0 18px 40px rgba(255,213,79,.13); }
    .npc-node:hover .npc-img { transform: translateY(-5px) scale(1.04); }
    .npc-img { height: 132px; width: 100%; object-fit: contain; image-rendering: auto; transition: transform .2s ease; }
    .npc-title { color: var(--gold); font-family: 'Orbitron', sans-serif; font-size: .92rem; font-weight: 800; letter-spacing: .03em; }
    .npc-desc { color: #cfe4ff; font-size: .82rem; line-height: 1.35; }
    .npc-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; margin-top: 5px; }
    .npc-action-btn {
      border: 1px solid rgba(77,184,255,.28); border-radius: 9px; background: rgba(255,255,255,.08);
      color: #e7f3ff; padding: 8px 6px; cursor: pointer; font-weight: 700; font-size: .78rem;
      font-family: 'Exo 2', sans-serif;
    }
    .npc-action-btn:hover { color: #08152a; background: var(--gold); border-color: var(--gold); }
    .landing-orbit { position: relative; width: 270px; height: 270px; margin: 0 auto 24px; }
    .landing-orbit .orbit-wrap { width: 270px; height: 270px; margin: 0; }
    .landing-orbit .center-gif { width: 138px; height: 138px; }
    .landing-orbit .orbit-icon { width: 42px; height: 42px; }
    .download-choice-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin: 16px 0; }
    .download-choice { border: 1px solid rgba(77,184,255,.28); border-radius: 14px; background: rgba(255,255,255,.08); color: #fff; padding: 14px 10px; cursor: pointer; font-weight: 800; }
    .download-choice.recommended { border-color: var(--gold); box-shadow: 0 0 0 1px rgba(255,213,79,.25); }
    .download-choice small { display:block; color:#b7d7ff; font-weight:600; margin-top:4px; }
    .npc-node { cursor: default; }
    .npc-action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .npc-action-btn.single-action { grid-column: 1 / -1; min-height: 44px; font-size: .92rem; }
    .modal-box.modal-large {
      width: min(90vw, 1180px); height: min(90vh, 860px); max-width: none;
      display: flex; flex-direction: column; overflow: hidden;
    }
    .modal-scroll-body { overflow: auto; padding-right: 6px; }
    #downloadModal .modal-box { max-width: 544px !important; padding: 29px 26px; }
    #downloadModal h2 { font-size: .88rem; margin-bottom: 19px; }
    #downloadModal #downloadDeviceText { font-size: .72rem !important; margin-bottom: 10px !important; line-height: 1.45 !important; }
    .download-device-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 11px; margin: 14px 0; }
    #downloadModal .download-choice { min-height: 106px; padding: 11px 8px; gap: 6px; border-radius: 11px; }
    .download-choice { min-height: 132px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; }
    #downloadModal .download-choice i { font-size: 2.24rem; }
    .download-choice i { font-size: 2.8rem; color: var(--gold); }
    #downloadModal .download-choice span { font-size: .84rem; }
    #downloadModal .download-choice small { font-size: .8em; }
    .download-choice span { font-size: 1.05rem; }
    .terms-sections { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; color: #d8eaff; text-align: left; }
    .terms-section { border: 1px solid rgba(77,184,255,.22); border-radius: 16px; padding: 16px; background: rgba(255,255,255,.06); }
    .terms-section h3 { margin: 0 0 10px; color: var(--gold); font-size: 1rem; }
    .terms-section ul { margin: 0; padding-left: 18px; line-height: 1.55; }
    .boss-card__map { margin-top: 7px; color: #9ed8ff; font-size: .75rem; line-height: 1.35; }
    .landing-center .server-info { margin: 8px 0 30px; position: relative; z-index: 1; }
    .landing-center .hero-cta-row { margin-top: 0; position: relative; z-index: 1; }
    #downloadModal .download-actions { gap: 10px; margin-top: 8px; }
    #downloadModal .dl-primary, #downloadModal .dl-secondary { padding: 10px 11px; border-radius: 10px; font-size: .8em; }
    .dl-primary { font-size: 1.3em; }
    .download-confirm-text { color:#d8eaff; line-height:1.55; margin: 8px 0 18px; }
    @media (max-width: 700px) {
      .modal-box.modal-large { width: 94vw; height: 90vh; }
      .download-device-grid, .terms-sections { grid-template-columns: 1fr; }
    }
    .community-board { width: min(1200px, calc(100vw - 28px)); margin: 32px auto 46px; border: 1.5px solid rgba(77,184,255,.28); border-radius: 24px; background: rgba(4,20,43,.78); box-shadow: 0 18px 55px rgba(0,0,0,.32); padding: 22px; position: relative; z-index: 1; }
    .community-board h2 { margin: 0 0 8px; color: var(--gold); font-family: 'Orbitron', sans-serif; font-size: 1.25rem; }
    .community-board__note { color: #bcd8ff; margin: 0 0 18px; line-height: 1.5; }
    .comment-zone { display: grid; gap: 28px; }
    .comment-section { border: 1px solid rgba(255,255,255,.12); border-radius: 18px; padding: 16px; background: rgba(255,255,255,.055); }
    .comment-section.admin-zone { border: 2px solid rgba(255,213,79,.68); background: linear-gradient(180deg, rgba(255,213,79,.16), rgba(48,33,4,.50)); box-shadow: 0 0 32px rgba(255,213,79,.18), inset 0 0 18px rgba(255,213,79,.08); }
    .comment-section h3 { margin: 0 0 12px; color: #eaf6ff; font-size: 1rem; display:flex; align-items:center; gap:8px; }
    .comment-form { display: grid; gap: 10px; margin-bottom: 16px; }
    .comment-form textarea, .reply-form textarea { width: 100%; min-height: 86px; resize: vertical; border-radius: 12px; border: 1px solid rgba(77,184,255,.28); background: rgba(0,0,0,.22); color: #fff; padding: 12px; font-family: 'Exo 2', sans-serif; }
    .comment-form__meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; color: #cfe4ff; font-size: .9rem; }
    .comment-form button, .reply-form button, .comment-actions button { border: 1px solid rgba(77,184,255,.28); border-radius: 10px; background: rgba(255,255,255,.08); color: #eaf6ff; padding: 9px 12px; cursor: pointer; font-weight: 800; }
    .comment-form button:hover, .reply-form button:hover, .comment-actions button:hover { background: var(--gold); color: #071529; border-color: var(--gold); }
    .comment-list { display: grid; gap: 12px; }
    .comment-empty { color: #9fb8dc; padding: 12px; border: 1px dashed rgba(255,255,255,.16); border-radius: 12px; }
    .comment-card { border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 13px; background: rgba(1,12,30,.62); }
    .comment-card.is-admin { border-color: rgba(255,213,79,.58); background: rgba(48,33,4,.52); box-shadow: 0 0 18px rgba(255,213,79,.12); }
    .comment-head { display:flex; align-items:center; justify-content:flex-start; gap:10px; color:#fff; margin-bottom:8px; }
    .comment-avatar { width:42px; height:42px; object-fit:contain; border-radius:12px; background:rgba(0,0,0,.18); border:1px solid rgba(255,255,255,.14); padding:3px; flex:0 0 auto; }
    .comment-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .comment-stt { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:24px; border-radius:999px; color:#071529; background:var(--gold); font-size:.78rem; font-weight:900; }
    .comment-head strong { color:#fff; margin-right:0; }
    .comment-head time { color:#8fb4dc; font-size:.8rem; margin-left:0; }
    .comment-badge { display:inline-flex; align-items:center; border-radius:999px; padding:2px 7px; font-size:.68rem; font-weight:900; letter-spacing:.04em; }
    .comment-badge.admin { color:#251600; background:var(--gold); }
    .comment-badge.player { color:#dff7ff; background:rgba(77,184,255,.22); }
    .comment-badge.anon { color:#e9e9e9; background:rgba(255,255,255,.16); }
    .comment-new-badge {
      display:inline-flex; align-items:center; border-radius:999px; padding:2px 8px;
      color:#08152a; background:#7cff7c; font-size:.68rem; font-weight:1000; letter-spacing:.06em;
      box-shadow:0 0 10px rgba(124,255,124,.75), 0 0 18px rgba(124,255,124,.45);
      animation:newGlow 1.25s ease-in-out infinite alternate;
    }
    @keyframes newGlow { from { filter:brightness(1); } to { filter:brightness(1.35); box-shadow:0 0 14px rgba(124,255,124,.9), 0 0 28px rgba(124,255,124,.55); } }
    .comment-content { color:#e4f1ff; line-height:1.55; display:inline; }
    .comment-content.collapsed { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
    .comment-content .comment-full { display:none; }
    .comment-content.expanded .comment-preview { display:none; }
    .comment-content.expanded .comment-full { display:inline; }
    .comment-content.expanded { white-space:normal; overflow:visible; }
    .comment-more { border:none; background:transparent; color:var(--gold); padding:4px 0 0; cursor:pointer; font-weight:900; }
    .comment-actions { display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
    .comment-actions form { margin:0; }
    .reply-form { display:none; gap:8px; margin-top:10px; }
    .reply-form.show { display:grid; }
    .reply-form label, .comment-form label { color:#cfe4ff; font-size:.88rem; }
    .comment-replies { margin-top:12px; padding-left:14px; border-left:2px solid rgba(77,184,255,.18); display:none; gap:10px; }
    .comment-replies.show { display:grid; }
    .community-divider { color: var(--gold); font-family: 'Orbitron', sans-serif; text-align:center; font-size:1.15rem; font-weight:900; letter-spacing:.08em; margin: 4px 0 -8px; text-shadow:0 0 16px rgba(255,213,79,.3); }
    .comment-notice { color:#ffd7d7; border:1px solid rgba(255,93,93,.35); background:rgba(255,93,93,.08); border-radius:12px; padding:10px 12px; margin-bottom:12px; }
    .edit-form { display:none; gap:8px; margin-top:10px; }
    .edit-form.show { display:grid; }
    .edit-form textarea { width:100%; min-height:90px; resize:vertical; border-radius:12px; border:1px solid rgba(255,213,79,.28); background:rgba(0,0,0,.22); color:#fff; padding:12px; font-family:'Exo 2', sans-serif; }
    .edit-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .edit-form .edit-save { background:#16a34a; border-color:#22c55e; color:#fff; }
    .edit-form .edit-cancel { background:#dc2626; border-color:#ef4444; color:#fff; }
    .forum-tools { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin: 0 0 14px; }
    .forum-tools button { border:1px solid rgba(77,184,255,.28); border-radius:10px; background:rgba(255,255,255,.08); color:#eaf6ff; padding:9px 12px; cursor:pointer; font-weight:800; }
    .forum-tools button:hover { background:var(--gold); color:#071529; border-color:var(--gold); }
    .forum-notice-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:16px; }
    .forum-notice-actions button { border:none; border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:900; }
    .forum-login-btn { background:linear-gradient(135deg,#0a4fa3,#1e7ee8); color:#fff; }
    .forum-logout-btn { background:rgba(255,255,255,.1); color:#cfe4ff; }
    @media (max-width: 640px) { .comment-replies { padding-left:8px; } .community-board { padding:16px; } }
    @media (max-width: 980px) {
      .landing-shell { grid-template-columns: 1fr; min-height: auto; }
      .landing-center { order: 1; min-height: auto; }
      .npc-column { order: 2; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
      .npc-column.left, .npc-column.right { align-items: stretch; padding-top: 0; }
      .npc-node, .npc-node:nth-child(2), .npc-column.right .npc-node:nth-child(1), .npc-column.right .npc-node:nth-child(2) { width: 100%; transform: none; }
    }
    @media (max-width: 560px) {
      .npc-column { grid-template-columns: 1fr; }
      .landing-center { padding: 24px 16px; }
      .download-choice-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>


<div class="bg-layer"></div>
<div class="bg-overlay"></div>
<div class="bg-stars"></div>

<div class="wrap">
<section class="landing-shell" id="hero">
  <div class="npc-column left">
    <article class="npc-node">
      <img class="npc-img" src="../img/account.gif" alt="NPC đăng nhập">
      <div class="npc-title">Chào mừng</div>
      <div class="npc-desc">Bắt đầu hành trình.</div>
      <div class="npc-actions">
        <button class="npc-action-btn" onclick="openModal('loginModal')"><i class="fa-solid fa-right-to-bracket"></i>Login</button>
        <button class="npc-action-btn" onclick="openModal('registerModal')"><i class="fa-solid fa-user-plus"></i>Đăng ký</button>
        <button class="npc-action-btn" onclick="openModal('forgotPwModal')"><i class="fa-solid fa-key"></i>Quên MK</button>
        <button class="npc-action-btn" onclick="openModal('changePwModal')"><i class="fa-solid fa-rotate"></i>Đổi MK</button>
      </div>
    </article>

    <article class="npc-node">
      <img class="npc-img" src="../img/nap.png" alt="NPC tải game">
      <div class="npc-title">Tải game</div>
      <div class="npc-desc">Trải nghiệm miễn phí 100%.</div>
      <div class="npc-actions"><button class="npc-action-btn single-action" onclick="downloadGame()"><i class="fa-solid fa-download"></i>Download</button></div>
    </article>
  </div>

  <div class="landing-center">
    <div class="landing-orbit">
      <div class="orbit-wrap">
        <div class="orbit-ring"></div>
        <img class="center-gif" src="../img/v0.gif" alt="Vong ngoc rong">
        <img class="orbit-icon" src="../img/112.png" alt="icon1">
        <img class="orbit-icon" src="../img/113.png" alt="icon2">
        <img class="orbit-icon" src="../img/114.png" alt="icon3">
        <img class="orbit-icon" src="../img/115.png" alt="icon4">
        <img class="orbit-icon" src="../img/116.png" alt="icon5">
        <img class="orbit-icon" src="../img/117.png" alt="icon6">
        <img class="orbit-icon" src="../img/118.png" alt="icon7">
      </div>
    </div>
    <div class="server-info" id="serverInfo"><i class="fa-solid fa-server"></i> Đang tải thông tin server...</div>
    <div class="hero-cta-row">
      <a class="cta-primary" href="<?= tc_h($SITE_ZALO_URL ?? 'https://chat.zalo.me/') ?>" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg" style="width:20px;height:20px">Box Zalo</a>
      <a class="cta-secondary" href="<?= tc_h($SITE_FACEBOOK_URL ?? 'https://www.facebook.com/') ?>" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg" style="width:20px;height:20px">Fan Page</a>
    </div>
  </div>

  <div class="npc-column right">
    <article class="npc-node">
      <img class="npc-img" src="../img/b.png" alt="NPC boss nhanh">
      <div class="npc-title">Check Boss</div>
      <div class="npc-desc">Xem danh sách Boss.</div>
      <div class="npc-actions">
        <button class="npc-action-btn single-action" onclick="openBossModal()"><i class="fa-solid fa-skull-crossbones"></i>Xem boss</button>
      </div>
    </article>

    <article class="npc-node">
      <img class="npc-img" src="../img/icon.jpg" alt="NPC điều khoản">
      <div class="npc-title">Điều khoản</div>
      <div class="npc-desc">Nội Quy, Quyền Lợi, Cam Kết, Lời Cảm Ơn .</div>
      <div class="npc-actions">
        <button class="npc-action-btn single-action" onclick="openModal('termsModal')"><i class="fa-solid fa-scroll"></i>Xem</button>
      </div>
    </article>
  </div>
</section>

<div class="modal-overlay" id="bossModal">
  <div class="modal-box modal-relative modal-large">
    <button class="modal-close" onclick="closeModal('bossModal')">✕</button>
    <h2><i class="fa-solid fa-skull-crossbones"></i> Boss đang hoạt động</h2>
    <div class="modal-scroll-body">
      <div class="boss-list" id="bossList"><div class="boss-card__loading">Đang tải dữ liệu boss...</div></div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="termsModal">
  <div class="modal-box modal-relative modal-large">
    <button class="modal-close" onclick="closeModal('termsModal')">✕</button>
    <h2><i class="fa-solid fa-scroll"></i> Nội Quy, Quyền Lợi, Cam Kết, Lời Cảm Ơn</h2>
    <div class="modal-scroll-body terms-sections">
      <section class="terms-section">
        <h3>1. Nội Quy</h3>
        <ul>
          <li>Không chia sẻ tài khoản, mật khẩu hoặc thông tin khôi phục cho người khác.</li>
          <li>Không sử dụng phần mềm can thiệp, bot, auto, bug hoặc hành vi phá cân bằng server.</li>
          <li>Không lừa đảo giao dịch, mạo danh admin, phát tán link độc hoặc nội dung gây hại.</li>
          <li>Không lợi dụng lỗi game hoặc lỗi web để nhận vật phẩm, tiền, điểm thưởng bất thường.</li>
        </ul>
      </section>
      <section class="terms-section">
        <h3>2. Quyền Lợi</h3>
        <ul>
          <li>Người chơi được hỗ trợ kiểm tra tài khoản khi có bằng chứng rõ ràng.</li>
          <li>Được tham gia sự kiện, nhận quà và sử dụng chức năng cộng đồng theo quy định server.</li>
          <li>Dữ liệu hợp lệ của người chơi được ưu tiên bảo vệ trong quá trình vận hành.</li>
        </ul>
      </section>
      <section class="terms-section">
        <h3>3. Cam Kết</h3>
        <ul>
          <li>Admin không bao giờ hỏi mật khẩu hoặc mã xác minh của người chơi.</li>
          <li>Các xử lý khóa, xóa vật phẩm hoặc reset dữ liệu chỉ áp dụng khi có vi phạm rõ ràng.</li>
          <li>Server hướng đến môi trường chơi công bằng, ổn định và minh bạch.</li>
        </ul>
      </section>
      <section class="terms-section">
        <h3>4. Lời Cảm Ơn</h3>
        <ul>
          <li>Cảm ơn bạn đã đồng hành, góp ý và báo lỗi để server ngày càng hoàn thiện.</li>
          <li>Chúc bạn có trải nghiệm vui vẻ, tôn trọng người chơi khác và giữ tài khoản an toàn.</li>
        </ul>
      </section>
    </div>
  </div>
</div>
<section class="community-board" id="communityComments">
  <h2><i class="fa-solid fa-comments"></i> Diễn Đàn Chia Sẻ</h2>
  <p class="community-board__note">Tôi, Admin, Xin Cảm Ơn Các Bạn.</p>
  <div class="forum-tools">
    <?php if ($tcUser['is_logged']): ?>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= tc_h(csrf_token()) ?>">
      <input type="hidden" name="comment_action" value="forum_logout">
      <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout diễn đàn</button>
    </form>
    <?php else: ?>
    <button type="button" onclick="openModal('loginModal')"><i class="fa-solid fa-right-to-bracket"></i> Login diễn đàn</button>
    <?php endif; ?>
  </div>
  <div class="comment-zone">
    <section class="comment-section admin-zone">
      <h3><i class="fa-solid fa-shield-halved"></i> Bài Viết Thông Báo</h3>
      <?php if ($tcUser['is_admin']): ?>
      <form class="comment-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= tc_h(csrf_token()) ?>">
        <input type="hidden" name="comment_action" value="create_comment">
        <input type="hidden" name="section" value="admin">
        <textarea name="content" maxlength="1000" placeholder="Admin đăng thông báo hoặc chia sẻ..." required></textarea>
        <div class="comment-form__meta"><span>Đăng với tên: <strong>Admin</strong></span><button type="submit"><i class="fa-solid fa-paper-plane"></i> Đăng bài admin</button></div>
      </form>
      <?php endif; ?>
      <div class="comment-list">
        <?php if (empty($adminComments)): ?>
          <div class="comment-empty">Chưa Có Bài Viết.</div>
        <?php else: foreach ($adminComments as $comment): ?>
          <?= tc_render_comment($comment, $commentChildren, $commentLikes, $rootSerials) ?>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <div class="community-divider">Cộng Đồng</div>

    <section class="comment-section">
      <h3><i class="fa-solid fa-users"></i> Bài của người chơi</h3>
      <form class="comment-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= tc_h(csrf_token()) ?>">
        <input type="hidden" name="comment_action" value="create_comment">
        <input type="hidden" name="section" value="player">
        <textarea name="content" maxlength="1000" placeholder="Bạn muốn chia sẻ điều gì?" required></textarea>
        <div class="comment-form__meta">
          <span>Tên hiển thị: <strong><?= tc_h($tcUser['is_logged'] ? $tcUser['player_name'] : 'conga') ?></strong></span>
          <label><input type="checkbox" name="anonymous" value="1"> Đăng ẩn danh</label>
          <button type="submit"><i class="fa-solid fa-paper-plane"></i> Đăng bài</button>
        </div>
      </form>
      <div class="comment-list">
        <?php if (empty($playerComments)): ?>
          <div class="comment-empty">Chưa có bài của người chơi.</div>
        <?php else: foreach ($playerComments as $comment): ?>
          <?= tc_render_comment($comment, $commentChildren, $commentLikes, $rootSerials) ?>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </div>
</section>
</div><!-- /wrap -->

<!-- ============ MODALS ============ -->

<!-- Login -->
<div class="modal-overlay" id="loginModal">
  <div class="modal-box modal-relative">
    <button class="modal-close" onclick="closeModal('loginModal')">✕</button>
    <h2>🔑 Đăng Nhập</h2>
    <div class="form-group"><label>Tên đăng nhập</label><input type="text" id="loginUser" placeholder="Nhập tài khoản..."></div>
    <div class="form-group"><label>Mật khẩu</label><input type="password" id="loginPass" placeholder="Nhập mật khẩu..."></div>
    <div class="modal-actions">
 <button class="btn-submit" onclick="login()">Đăng Nhập</button>
      <button class="btn-cancel" onclick="closeModal('loginModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Register -->
<div class="modal-overlay" id="registerModal">
  <div class="modal-box modal-relative">
    <button class="modal-close" onclick="closeModal('registerModal')">✕</button>
    <h2>✨ Đăng Ký Tài Khoản</h2>

    <div class="form-group">
      <label>Tên đăng nhập</label>
      <input type="text" id="regUser" placeholder="> 4 ký tự...">
    </div>

    <div class="form-group">
      <label>Mật khẩu</label>

      <input type="password" id="regPass" placeholder="> 6 ký tự...">
    </div>

 <div class="form-group">
  <label>Số Điện Thoại</label>
  <input type="tel" id="regPhone" placeholder="Nhập số điện thoại...">
</div>

    <div class="form-group">
      <button type="button" class="sms-code-button" id="btnGetSmsCode" disabled>
        <span class="sms-code-button__icon"><i class="fa-solid fa-comment-sms"></i></span>
        <span class="sms-code-button__content">
          <strong id="smsButtonLabel">Lấy Mã Xác Thực SMS</strong>
          <small id="smsButtonHint">Vui lòng nhập SĐT chính chủ</small>
        </span>
        <i class="fa-solid fa-arrow-right sms-code-button__arrow"></i>
      </button>
      <div class="sms-code-display" id="smsCodeDisplay" hidden></div>
    </div>

    <div class="form-group">
      <label for="regSmsCode">Nhập Mã Xác Thực SMS</label>
      <input type="text" id="regSmsCode" inputmode="numeric" maxlength="6"
        autocomplete="one-time-code" placeholder="Nhập mã 6 chữ số...">
      <div class="sms-code-status" id="smsCodeStatus" aria-live="polite"></div>
    </div>

    <div class="modal-actions">
      <button class="btn-submit" id="btnRegister" disabled
        style="background:linear-gradient(135deg,#e65100,#ff6d00);opacity:.5">
        Đăng ký
      </button>
      <button class="btn-cancel" onclick="closeModal('registerModal')">Huỷ</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="changePwModal">
  <div class="modal-box modal-relative">
    <button class="modal-close" onclick="closeModal('changePwModal')">✕</button>
    <h2>🔄 Đổi Mật Khẩu</h2>

    <div class="form-group">
      <label>Tài khoản</label>
      <input type="text" id="cpUser" placeholder="Nhập tài khoản...">
    </div>

    <div class="form-group">
      <label>Mật khẩu cũ</label>
      <input type="password" id="cpOldPass" placeholder="Nhập mật khẩu cũ...">
    </div>

    <div class="form-group">
      <label>Mật khẩu mới</label>
      <input type="password" id="cpNewPass" placeholder="Nhập mật khẩu mới...">
    </div>

    <div class="form-group">
      <label>Số điện thoại mới (nếu cần thì nhập)</label>
      <input type="tel" id="cpPhone" placeholder="Nhập số điện thoại...">
    </div>

    <div class="modal-actions">
      <button class="btn-submit" onclick="changePassword()">Xác Nhận</button>
      <button class="btn-cancel" onclick="closeModal('changePwModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Forgot Password -->
<div class="modal-overlay" id="forgotPwModal">
  <div class="modal-box modal-relative">
    <button class="modal-close" onclick="closeModal('forgotPwModal')">✕</button>
    <h2>❓ Quên Mật Khẩu</h2>
    <div class="form-group"><label>Tài khoản cần</label><input type="text" placeholder="Nhập Tài khoản..."></div>
<div class="form-group">
  <label>Số điện thoại đăng ký</label>
  <input type="tel"
         id="fpPhone"
         placeholder="Nhập số điện thoại...">
</div>
    <div class="modal-actions">
<button class="btn-submit" onclick="forgotPassword()">Gửi Xác Nhận</button>
      <button class="btn-cancel" onclick="closeModal('forgotPwModal')">Huỷ</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="downloadModal">
  <div class="modal-box modal-relative" style="text-align:center;max-width:680px">
    <button class="modal-close" onclick="closeDownloadModal()">✕</button>

    <h2>⬇ Tải Game</h2>
    <p id="downloadDeviceText" style="font-size:0.9rem;color:#b0c8e8;margin-bottom:12px;line-height:1.6">
      Trải nghiệm miễn phí 100%.
    </p>

    <div class="download-choice-grid download-device-grid">
      <button class="download-choice" data-platform="apk" onclick="selectDownload('apk')"><i class="fa-brands fa-android"></i><span>APK</span><small>Android</small></button>
      <button class="download-choice" data-platform="pc" onclick="selectDownload('pc')"><i class="fa-solid fa-desktop"></i><span>PC</span><small>Windows</small></button>
      <button class="download-choice" data-platform="ios" onclick="selectDownload('ios')"><i class="fa-brands fa-apple"></i><span>iOS</span><small>TestFlight</small></button>
      <button class="download-choice" data-platform="ipa" onclick="selectDownload('ipa')"><i class="fa-solid fa-mobile-screen-button"></i><span>IPA</span><small>iPhone file</small></button>
    </div>

    <div class="download-actions">
      <button class="dl-primary" onclick="confirmDownload()">⬇ Tải</button>
      <button class="dl-secondary" onclick="closeDownloadModal()">✖ Huỷ</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="downloadConfirmModal">
  <div class="modal-box modal-relative" style="text-align:center;max-width:460px">
    <button class="modal-close" onclick="closeModal('downloadConfirmModal')">✕</button>
    <h2><i class="fa-solid fa-circle-check"></i> Xác nhận tải</h2>
    <p class="download-confirm-text" id="downloadConfirmText">Bạn muốn tải bản đã chọn?</p>
    <div class="download-actions">
      <button class="dl-primary" onclick="executeDownload()">Đồng ý</button>
      <button class="dl-secondary" onclick="closeModal('downloadConfirmModal')">Huỷ</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="forumNoticeModal">
  <div class="modal-box modal-relative" style="text-align:center;max-width:520px">
    <button class="modal-close" onclick="closeModal('forumNoticeModal')">✕</button>
    <h2><i class="fa-solid fa-circle-info"></i> Thông báo diễn đàn</h2>
    <p style="color:#d8eaff;line-height:1.55"><?= tc_h($commentNotice) ?></p>
    <div class="forum-notice-actions">
      <button class="forum-login-btn" type="button" onclick="closeModal('forumNoticeModal')"><i class="fa-solid fa-check"></i> Đồng ý</button>
      <button class="forum-logout-btn" type="button" onclick="closeModal('forumNoticeModal')"><i class="fa-solid fa-xmark"></i> Hủy</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="deleteCommentModal">
  <div class="modal-box modal-relative" style="text-align:center;max-width:460px">
    <button class="modal-close" onclick="closeModal('deleteCommentModal')">✕</button>
    <h2><i class="fa-solid fa-triangle-exclamation"></i> Xác nhận xóa</h2>
    <p style="color:#d8eaff;line-height:1.55">Bạn chắc chắn muốn xóa bài viết hoặc bình luận này?</p>
    <form method="post" class="forum-notice-actions">
      <input type="hidden" name="csrf_token" value="<?= tc_h(csrf_token()) ?>">
      <input type="hidden" name="comment_action" value="delete_comment">
      <input type="hidden" name="comment_id" id="deleteCommentId" value="0">
      <button class="forum-login-btn" type="submit"><i class="fa-solid fa-trash"></i> Xóa</button>
      <button class="forum-logout-btn" type="button" onclick="closeModal('deleteCommentModal')"><i class="fa-solid fa-xmark"></i> Hủy</button>
    </form>
  </div>
</div>
<script>
  /* === Modal routing === */
  function closeAllModals(exceptId = null) {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      if (modal.id !== exceptId) modal.classList.remove('show');
    });
  }
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    closeAllModals(id);
    modal.classList.add('show');
  }
  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
    if (id === 'registerModal') resetRegisterSms();
  }
  function openBossModal() {
    openModal('bossModal');
    updateBossWidget();
  }
  document.querySelectorAll('.modal-box').forEach(box => {
    box.addEventListener('click', function(e) { e.stopPropagation(); });
  });
/* === DOWNLOAD GAME === */
let selectedDownloadPlatform = null;
function detectDownloadPlatform() {
  const ua = navigator.userAgent.toLowerCase();
  if (ua.includes("android")) return 'apk';
  if (/iphone|ipad|ipod/.test(ua)) return 'ios';
  return 'pc';
}
function downloadLabel(platform) {
  if (platform === 'apk') return 'APK Android';
  if (platform === 'ios') return 'iOS TestFlight';
  if (platform === 'ipa') return 'IPA iPhone';
  return 'PC Windows';
}
function syncDownloadUI(platform) {
  selectedDownloadPlatform = platform || detectDownloadPlatform();
  document.querySelectorAll('.download-choice').forEach(btn => {
    btn.classList.toggle('recommended', btn.dataset.platform === selectedDownloadPlatform);
  });
  const text = document.getElementById('downloadDeviceText');
  if (text) text.innerHTML = `Thiết bị phù hợp: <strong style="color:#ffd54f">${downloadLabel(selectedDownloadPlatform)}</strong>.`;
}
function downloadGame() {
  syncDownloadUI(selectedDownloadPlatform || detectDownloadPlatform());
  openModal('downloadModal');
}
function closeDownloadModal() {
  closeModal('downloadModal');
}
function selectDownload(platform) {
  syncDownloadUI(platform);
  openModal('downloadModal');
}
function triggerFileDownload(href, filename) {
  const link = document.createElement("a");
  link.href = href;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
function confirmDownload() {
  const platform = selectedDownloadPlatform || detectDownloadPlatform();
  const text = document.getElementById('downloadConfirmText');
  if (text) text.innerHTML = `Bạn muốn tải <strong style="color:#ffd54f">${downloadLabel(platform)}</strong>?`;
  openModal('downloadConfirmModal');
}
function executeDownload() {
  const platform = selectedDownloadPlatform || detectDownloadPlatform();
  if (platform === 'apk') triggerFileDownload(<?= json_encode(tc_download_url('NRO.apk')) ?>, 'NRO.apk');
  else if (platform === 'ios') window.location.href = <?= json_encode($SITE_TESTFLIGHT_URL ?? 'https://testflight.apple.com/') ?>;
  else if (platform === 'ipa') triggerFileDownload(<?= json_encode(tc_download_url('IPA.ipa')) ?>, 'IPA.ipa');
  else triggerFileDownload(<?= json_encode(tc_download_url('PC.rar')) ?>, 'NRO_PC.rar');
  closeModal('downloadConfirmModal');
  closeDownloadModal();
}
window.addEventListener("load", () => {
  selectedDownloadPlatform = detectDownloadPlatform();
  syncDownloadUI(selectedDownloadPlatform);
});
const openTime = new Date(<?= json_encode($SITE_OPEN_TIME ?? '2026-05-25T20:00:00') ?>);
//time server
function formatTime(ms) {
  const sec = Math.floor(ms / 1000);
  const d = Math.floor(sec / 86400);
  const h = Math.floor((sec % 86400) / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = sec % 60;

  if (d > 0) return `${d}d ${h}h ${m}m ${s}s`;
  if (h > 0) return `${h}h ${m}m ${s}s`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
}
function updateServerInfo() {
  const el = document.getElementById("serverInfo");
  if (!el) return;

  const now = new Date();
  const diff = now >= openTime ? now - openTime : openTime - now;

  const time = formatTime(diff);

  if (now >= openTime) {
    el.className = "server-info server-open";
    el.innerHTML = `
      <i class="fa-solid fa-bolt"></i>
      <b>SERVER ĐÃ OPEN</b><br>
      <span class="server-time">Hoạt động: ${time}</span>
    `;
  } else {
    el.className = "server-info server-wait";
    el.innerHTML = `
      <i class="fa-solid fa-clock"></i>
      <b>ĐẾM NGƯỢC MỞ SERVER</b><br>
      <span class="server-time">Còn lại: ${time}</span>
    `;
  }
}
updateServerInfo();
setInterval(updateServerInfo, 1000);




const btnRegister = document.getElementById("btnRegister");
const btnGetSmsCode = document.getElementById("btnGetSmsCode");
const smsCodeDisplay = document.getElementById("smsCodeDisplay");
const regSmsCode = document.getElementById("regSmsCode");
const smsCodeStatus = document.getElementById("smsCodeStatus");
const regPhone = document.getElementById("regPhone");
const smsButtonLabel = document.getElementById("smsButtonLabel");
const smsButtonHint = document.getElementById("smsButtonHint");
let generatedSmsCode = "";
let smsCodePhone = "";
let pendingSmsPhone = "";
let smsRequestPending = false;
let smsCountdownTimer = null;

function setRegisterEnabled(enabled){
  btnRegister.disabled = !enabled;
  btnRegister.style.opacity = enabled ? "1" : ".5";
}

function updateGetSmsButton(){
  const phoneIsValid = isValidPhone(normalizePhone(regPhone.value));
  btnGetSmsCode.disabled = smsRequestPending || !phoneIsValid;
  if (!smsRequestPending) {
    smsButtonHint.textContent = phoneIsValid
      ? "Nhấn để nhận mã"
      : "Nhập đúng số điện thoại";
  }
}

function cancelSmsCountdown(message = ""){
  clearInterval(smsCountdownTimer);
  smsCountdownTimer = null;
  smsRequestPending = false;
  pendingSmsPhone = "";
  smsButtonLabel.textContent = generatedSmsCode ? "Lấy Mã Mới" : "Lấy Mã Xác Thực SMS";
  if (message) {
    smsCodeStatus.textContent = message;
    smsCodeStatus.className = "sms-code-status error";
  }
  updateGetSmsButton();
}

function resetRegisterSms(){
  cancelSmsCountdown();
  generatedSmsCode = "";
  smsCodePhone = "";
  regSmsCode.value = "";
  smsCodeDisplay.hidden = true;
  smsCodeDisplay.textContent = "";
  smsCodeStatus.textContent = "";
  smsCodeStatus.className = "sms-code-status";
  smsButtonLabel.textContent = "Lấy Mã Xác Thực SMS";
  updateGetSmsButton();
  setRegisterEnabled(false);
}

function generateUniqueSixDigitCode(){
  const digits = "0123456789".split("");
  for (let i = digits.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [digits[i], digits[j]] = [digits[j], digits[i]];
  }
  return digits.slice(0, 6).join("");
}

function verifySmsCode(){
  const enteredCode = regSmsCode.value.replace(/\D/g, "").slice(0, 6);
  regSmsCode.value = enteredCode;
  const currentPhone = normalizePhone(regPhone.value);
  const isCorrect = generatedSmsCode !== ""
    && enteredCode === generatedSmsCode
    && currentPhone === smsCodePhone;

  setRegisterEnabled(isCorrect);
  if (!enteredCode) {
    smsCodeStatus.textContent = "";
    smsCodeStatus.className = "sms-code-status";
  } else if (isCorrect) {
    smsCodeStatus.textContent = "✓ Mã xác thực chính xác";
    smsCodeStatus.className = "sms-code-status success";
  } else {
    smsCodeStatus.textContent = enteredCode.length < 6
      ? "Vui lòng nhập đủ 6 chữ số"
      : "Mã xác thực không chính xác";
    smsCodeStatus.className = "sms-code-status error";
  }
}

btnGetSmsCode.addEventListener("click", () => {
  const phone = normalizePhone(regPhone.value);
  if (!isValidPhone(phone)) {
    showMsg("registerModal", "❌ Vui lòng nhập số điện thoại hợp lệ trước!", "error");
    regPhone.focus();
    return;
  }

  clearInterval(smsCountdownTimer);
  generatedSmsCode = "";
  smsCodePhone = "";
  pendingSmsPhone = phone;
  smsRequestPending = true;
  regSmsCode.value = "";
  smsCodeDisplay.hidden = true;
  smsCodeDisplay.textContent = "";
  smsCodeStatus.textContent = "Đang gửi mã xác thực, vui lòng chờ...";
  smsCodeStatus.className = "sms-code-status";
  let secondsRemaining = 5;
  smsButtonLabel.textContent = `Đang gửi mã... ${secondsRemaining}s`;
  smsButtonHint.textContent = "Mã sẽ hiển thị sau ít giây";
  updateGetSmsButton();
  setRegisterEnabled(false);

  smsCountdownTimer = setInterval(() => {
    secondsRemaining--;
    if (secondsRemaining > 0) {
      smsButtonLabel.textContent = `Đang gửi mã... ${secondsRemaining}s`;
      return;
    }

    clearInterval(smsCountdownTimer);
    smsCountdownTimer = null;
    smsRequestPending = false;

    if (normalizePhone(regPhone.value) !== pendingSmsPhone || !isValidPhone(pendingSmsPhone)) {
      pendingSmsPhone = "";
      smsButtonLabel.textContent = "Lấy Mã Xác Thực SMS";
      smsCodeStatus.textContent = "Số điện thoại đã thay đổi, vui lòng lấy mã lại.";
      smsCodeStatus.className = "sms-code-status error";
      updateGetSmsButton();
      return;
    }

    generatedSmsCode = generateUniqueSixDigitCode();
    smsCodePhone = pendingSmsPhone;
    pendingSmsPhone = "";
    smsCodeDisplay.hidden = false;
    smsCodeDisplay.innerHTML = `<span>Mã xác thực SMS</span><strong>${generatedSmsCode}</strong>`;
    smsCodeStatus.textContent = "Mã gồm 6 chữ số khác nhau. Hãy nhập lại bên dưới.";
    smsCodeStatus.className = "sms-code-status";
    smsButtonLabel.textContent = "Lấy Mã Mới";
    updateGetSmsButton();
    regSmsCode.focus();
  }, 1000);
});

regSmsCode.addEventListener("input", verifySmsCode);
regPhone.addEventListener("input", () => {
  if (smsRequestPending && normalizePhone(regPhone.value) !== pendingSmsPhone) {
    cancelSmsCountdown("Số điện thoại đã thay đổi, yêu cầu lấy mã đã được hủy.");
  } else {
    updateGetSmsButton();
  }
  if (generatedSmsCode && normalizePhone(regPhone.value) !== smsCodePhone) {
    smsCodeStatus.textContent = "Số điện thoại đã thay đổi, vui lòng lấy mã mới.";
    smsCodeStatus.className = "sms-code-status error";
  }
  btnRegister.disabled = true;
  btnRegister.style.opacity = ".5";
});








function normalizePhone(phone){
  return phone.trim().replace(/[\s\-.]+/g, "");
}

function isValidPhone(phone){
  return /^0[0-9]{9,14}$/.test(phone);
}

btnRegister.addEventListener("click", () => {
  const user = document.getElementById("regUser").value;
  const pass = document.getElementById("regPass").value;
  const phone = normalizePhone(document.getElementById("regPhone").value);

if (regSmsCode.value !== generatedSmsCode || phone !== smsCodePhone) {
  showMsg("registerModal", "❌ Mã xác thực SMS không hợp lệ!", "error");
  setRegisterEnabled(false);
  return;
}

if (!user || !pass || !phone) {
  showMsg("registerModal", "❌ Vui lòng nhập đầy đủ thông tin!", "error");
  return;
}

if (!isValidPhone(phone)) {
  showMsg("registerModal", "❌ Số điện thoại không hợp lệ!", "error");
  return;
}
  fetch("/sv1/dangky.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/x-www-form-urlencoded"
  },
  body: `username=${encodeURIComponent(user)}&password=${encodeURIComponent(pass)}&phone=${encodeURIComponent(phone)}`
})
.then(res => res.json())
.then(data => {

  // ❌ THẤT BẠI
  if(data.status === "error"){
    showMsg(
      "registerModal",
      "❌ " + data.message,
      "error"
    );

    // ❗ KHÔNG reset form
    return;
  }

  // ✅ THÀNH CÔNG
  showMsg(
    "registerModal",
    "🎉 " + data.message,
    "success",
    "/sv1/trangchu.php"
  );

  // reset form CHỈ khi thành công
  document.getElementById("regUser").value = "";
  document.getElementById("regPass").value = "";
  document.getElementById("regPhone").value = "";

  resetRegisterSms();

});
});
function showMsg(modalId, text, type="success", redirectUrl=null, callback=null){

  const modal = document.getElementById(modalId);
  const box = modal.querySelector(".modal-box");

  let msg = box.querySelector(".form-msg");

  if(!msg){
    msg = document.createElement("div");
    msg.className = "form-msg";
    box.prepend(msg);
  }

  msg.className = "form-msg " + type;
  msg.style.display = "block";

  if(type === "error"){
    msg.innerHTML = text;
    return;
  }

  let time = 3;

  const timer = setInterval(() => {
    msg.innerHTML = `${text}<br><b>Tắt Máy Sau ${time}s...</b>`;
    time--;

    if(time <= 0){
      clearInterval(timer);

      if(callback){
        callback(); // ✅ chạy callback trước
      } 
      else if(redirectUrl){
        window.location.href = redirectUrl;
      }
    }
  }, 1000);
}
document.querySelectorAll('.orbit-icon').forEach(icon => {
  icon.style.cursor = "pointer";
  icon.addEventListener("click", () => {
    window.location.href = "/";
  });
});
function login(){

  const user = document.getElementById("loginUser").value.trim();
  const pass = document.getElementById("loginPass").value.trim();

  if(!user || !pass){
    showMsg("loginModal","❌ Vui lòng nhập đầy đủ!","error");
    return;
  }

  fetch("/sv1/dangnhap.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body:
      "username=" + encodeURIComponent(user) +
      "&password=" + encodeURIComponent(pass)
  })
  .then(res => res.json())
  .then(data => {

    if(data.status === "error"){
      showMsg("loginModal","❌ " + data.message,"error");
      return;
    }

showMsg(
  "loginModal",
  "🔑 Đăng nhập thành công!",
  "success",
  null,
  () => {
    document.getElementById("loginUser").value = "";
    document.getElementById("loginPass").value = "";
    closeModal("loginModal");

    window.location.href = "/sv1/admin/panel.php";
  }
);

  })
  .catch(() => {
    showMsg("loginModal","❌ Lỗi server","error");
  });

}

function forgotSuccess(){
  showMsg(
    "forgotPwModal",
    "📧 Mã xác nhận đã gửi!",
    "success",
    null
  );
}


function changePwSuccess(){
  showMsg(
    "changePwModal",
    "🔄 Đổi mật khẩu thành công!",
    "success",
    "/sv1/trangchu.php"
  );
}

function forgotPassword(){
  const user = document.querySelector("#forgotPwModal input[placeholder='Nhập Tài khoản...']").value;
  const phone = normalizePhone(document.getElementById("fpPhone").value);

  if(!user || !phone){
    showMsg("forgotPwModal","❌ Vui lòng nhập đầy đủ!","error");
    return;
  }

  if(!isValidPhone(phone)){
    showMsg("forgotPwModal","❌ Số điện thoại không hợp lệ!","error");
    return;
  }

  fetch("/sv1/quenmatkhau.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: `username=${encodeURIComponent(user)}&phone=${encodeURIComponent(phone)}`
  })
  .then(res => res.json())
  .then(data => {

    if(data.status === "error"){
      showMsg("forgotPwModal","❌ " + data.message,"error");
      return;
    }

    showMsg(
      "forgotPwModal",
      "📧 " + data.message,
      "success",
      "/sv1/trangchu.php"
    );
  })
  .catch(() => {
    showMsg("forgotPwModal","❌ Lỗi kết nối server","error");
  });
}

function changePassword(){

  const user = document.getElementById("cpUser").value.trim();
  const oldPass = document.getElementById("cpOldPass").value.trim();
  const newPass = document.getElementById("cpNewPass").value.trim();
  const phone = normalizePhone(document.getElementById("cpPhone").value);

  if(!user || !oldPass || !newPass){
    showMsg("changePwModal","❌ Vui lòng nhập đầy đủ!","error");
    return;
  }

  // nếu có nhập số điện thoại thì mới xử lý
  if(phone && !isValidPhone(phone)){
    showMsg("changePwModal","❌ Số điện thoại không hợp lệ!","error");
    return;
  }

  fetch("/sv1/doimatkhau.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body:
      "username=" + encodeURIComponent(user) +
      "&oldpass=" + encodeURIComponent(oldPass) +
      "&newpass=" + encodeURIComponent(newPass) +
      "&phone=" + encodeURIComponent(phone)
  })
  .then(res => res.json())
  .then(data => {

    if(data.status === "error"){
      showMsg("changePwModal","❌ " + data.message,"error");
      return;
    }

    showMsg(
      "changePwModal",
      "🎉 " + data.message,
      "success",
      "/sv1/trangchu.php"
    );

  })
  .catch(() => {
    showMsg("changePwModal","❌ Lỗi server","error");
  });

}


  (function() {
    const icons = document.querySelectorAll('.orbit-icon');
    const total = icons.length;
    const radius = window.innerWidth <= 768 ? 98 : 124;
    let angle = 0;
    const baseAngles = Array.from(icons).map((_, i) => (360 / total) * i);

    function frame(ts) {
      angle = (ts / 18000) * 360;
      icons.forEach((icon, i) => {
        const a = (baseAngles[i] + angle) * Math.PI / 180;
        const x = Math.cos(a) * radius;
        const y = Math.sin(a) * radius;
        icon.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
        icon.style.position = 'absolute';
        icon.style.top = '50%';
        icon.style.left = '50%';
      });
      requestAnimationFrame(frame);
    }
    // Override CSS animation with JS for smooth counter-upright icons
    icons.forEach(icon => { icon.style.animation = 'none'; });
    requestAnimationFrame(frame);
  })();


  function buildBossCard(boss) {
    const mapName = (boss.mapName || 'Chưa xuất hiện').replace(/\s*\(khu\s*\d+\)\s*/i, '');
    const hp = Number(boss.hp || 0);
    const maxHp = Number(boss.maxHp || 0);
    const hpPercent = maxHp > 0 ? Math.max(0, Math.min(100, Math.round((hp / maxHp) * 100))) : 0;
    const isAlive = boss.state === 'Sống';
    const stateIcon = isAlive ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>';
    const stateClass = isAlive ? 'is-alive' : 'is-dead';
    return `
      <div class="boss-card">
        <div class="boss-card__image">
          <span class="boss-card__state-badge ${stateClass}">${stateIcon}</span>
          <img src="../img/account.gif" alt="${boss.name}" onerror="this.src='../img/account.gif'" />
        </div>
        <div>
          <div class="boss-card__name">${boss.name || 'Boss không xác định'}</div>
          <div class="boss-card__hp">HP: ${hp.toLocaleString('vi')}</div>
          <div class="boss-card__map"><i class="fa-solid fa-map-location-dot"></i>: ${mapName}</div>
          <div class="boss-card__bar"><div class="boss-card__bar-fill" style="width:${hpPercent}%"></div></div>
        </div>
      </div>
    `;
  }

  async function updateBossWidget() {
    const container = document.getElementById('bossList');
    if (!container) return;
    try {
      const response = await fetch('/sv1/boss_status.php');
      const data = await response.json();
      if (!Array.isArray(data.bosses)) {
        container.innerHTML = '<div class="boss-card__loading">Không nhận được dữ liệu boss.</div>';
        return;
      }
      if (data.bosses.length === 0) {
        container.innerHTML = '<div class="boss-card__loading">Hiện không có boss main nào.</div>';
        return;
      }
      container.innerHTML = data.bosses.map(buildBossCard).join('');
    } catch (error) {
      container.innerHTML = '<div class="boss-card__loading">Lỗi tải dữ liệu boss.</div>';
      console.error('Boss widget error:', error);
    }
  }
  updateBossWidget();
  setInterval(updateBossWidget, 10000);
function toggleEditForm(id) {
  const form = document.getElementById('editForm-' + id);
  if (form) form.classList.toggle('show');
}
function toggleReplies(id, btn) {
  const box = document.getElementById('replies-' + id);
  if (!box) return;
  const open = box.classList.toggle('show');
  if (btn) btn.innerHTML = btn.innerHTML.replace(open ? 'Hiện' : 'Ẩn', open ? 'Ẩn' : 'Hiện');
}
function toggleReplyForm(id) {
  const form = document.getElementById('replyForm-' + id);
  if (form) form.classList.toggle('show');
}
function toggleCommentFull(id, btn) {
  const content = document.getElementById('commentContent-' + id);
  if (!content) return;
  const open = content.classList.toggle('expanded');
  content.classList.toggle('collapsed', !open);
  if (btn) btn.textContent = open ? '... Ẩn bớt' : '... Xem thêm';
}
function openDeleteCommentModal(id) {
  const input = document.getElementById('deleteCommentId');
  if (input) input.value = id;
  openModal('deleteCommentModal');
}
setInterval(() => {
  const modalOpen = document.querySelector('.modal-overlay.show');
  const activeForm = document.querySelector('.reply-form.show, .edit-form.show');
  const tag = (document.activeElement && document.activeElement.tagName) || '';
  if (!modalOpen && !activeForm && tag !== 'TEXTAREA' && tag !== 'INPUT') {
    window.location.reload();
  }
}, 60000);<?php if ($commentNotice !== ''): ?>
window.addEventListener('load', function forumNoticeModalAutoOpen(){ openModal('forumNoticeModal'); });
<?php endif; ?>
</script>

</body>
</html>





