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

$reportId = isset($data['reportId']) ? (int)$data['reportId'] : 0;
$status   = isset($data['status']) ? (int)$data['status'] : -1;

if ($reportId <= 0 || ($status !== 0 && $status !== 1)) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です。']);
    exit();
}

try {
    $dbh = dbConnect();
    $dbh->prepare('UPDATE reports SET status = :status WHERE id = :id')
        ->execute(['status' => $status, 'id' => $reportId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '更新に失敗しました。']);
}
