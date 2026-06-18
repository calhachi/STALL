<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '不正なリクエストです。']);
    exit();
}

$workId = isset($data['workId']) ? (int)$data['workId'] : 0;
$reason = isset($data['reason']) ? (int)$data['reason'] : 0;
$detail = trim((string)($data['detail'] ?? ''));

if ($workId <= 0) {
    echo json_encode(['success' => false, 'message' => '作品IDが不正です。']);
    exit();
}

if ($reason < 1 || $reason > 4) {
    echo json_encode(['success' => false, 'message' => '通報内容を選択してください。']);
    exit();
}

if ($reason === 4 && $detail === '') {
    echo json_encode(['success' => false, 'message' => '詳細を入力してください。']);
    exit();
}

if ($reason !== 4) {
    $detail = null;
}

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare('SELECT 1 FROM works WHERE id = :id');
    $stmt->execute(['id' => $workId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => '作品が存在しません。']);
        exit();
    }

    $checkStmt = $dbh->prepare(
        'SELECT 1 FROM reports WHERE user_id = :userId AND work_id = :workId'
    );
    $checkStmt->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
    if ($checkStmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'この作品は既に通報済みです。']);
        exit();
    }

    $dbh->prepare(
        'INSERT INTO reports (work_id, user_id, reason, detail) VALUES (:workId, :userId, :reason, :detail)'
    )->execute([
        'workId' => $workId,
        'userId' => $_SESSION['userId'],
        'reason' => $reason,
        'detail' => $detail,
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました。']);
}
