<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$workId = isset($_GET['work_id']) ? (int)$_GET['work_id'] : 0;

if ($workId <= 0) {
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

try {
    $dbh = dbConnect();

    // 購入済みチェック
    $purchaseStmt = $dbh->prepare(
        'SELECT 1 FROM purchase WHERE user_id = :userId AND work_id = :workId'
    );
    $purchaseStmt->execute(['userId' => (int)$_SESSION['userId'], 'workId' => $workId]);
    if (!$purchaseStmt->fetchColumn()) {
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    // ファイル情報取得
    $workStmt = $dbh->prepare(
        'SELECT file_name, original_name FROM works WHERE id = :id'
    );
    $workStmt->execute(['id' => $workId]);
    $work = $workStmt->fetch(PDO::FETCH_ASSOC);

    if (!$work) {
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    $filePath = WORKS_DIR . $work['file_name'];

    if (!file_exists($filePath)) {
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    $mimeType    = mime_content_type($filePath) ?: 'application/octet-stream';
    $encodedName = rawurlencode($work['original_name']);

    // 出力バッファをクリアしてからファイルを送出
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . $encodedName);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store');

    readfile($filePath);
    exit();

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}
