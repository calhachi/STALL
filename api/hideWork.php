<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '権限がありません。']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '不正なリクエストです。']);
    exit();
}

$workId   = isset($data['workId']) ? (int)$data['workId'] : 0;
$isHidden = isset($data['isHidden']) ? (int)$data['isHidden'] : -1;

if ($workId <= 0 || ($isHidden !== 0 && $isHidden !== 1)) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です。']);
    exit();
}

try {
    $dbh = dbConnect();
    $dbh->prepare('UPDATE works SET is_hidden = :isHidden WHERE id = :id')
        ->execute(['isHidden' => $isHidden, 'id' => $workId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '更新に失敗しました。']);
}
