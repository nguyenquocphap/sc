<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/sv1/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Invalid request");
}

if (!isset($_SESSION['account_id'])) {
    exit("Not login");
}

require_csrf_token();

$stmt = $pdo->prepare("SELECT is_admin FROM account WHERE id = ?");
$stmt->execute([$_SESSION['account_id']]);

$me = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$me || (int)$me['is_admin'] !== 1) {
    exit("Khong co quyen");
}

function reset_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function reset_delete_if_exists(PDO $pdo, string $table): void {
    if (!reset_table_exists($pdo, $table)) {
        return;
    }

    $safeTable = str_replace('`', '``', $table);
    $pdo->exec("DELETE FROM `$safeTable`");
}

try {
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM super_rank");

    $pdo->exec("DELETE FROM player");

    $pdo->exec("DELETE FROM account WHERE is_admin = 0");

    reset_delete_if_exists($pdo, 'history_items_diemdanh');
    $pdo->exec("DELETE FROM history_transaction");
    $pdo->exec("DELETE FROM clan");
    reset_delete_if_exists($pdo, 'shop_ky_gui');

    $pdo->commit();

    file_put_contents(
        __DIR__ . '/reset_log.txt',
        date('Y-m-d H:i:s') .
        " | ADMIN ID: " . $_SESSION['account_id'] .
        " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
        PHP_EOL,
        FILE_APPEND
    );

    echo "RESET THANH CONG";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[admin reset] ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}
