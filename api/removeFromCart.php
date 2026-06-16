<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$workId = isset($data['workId']) ? (int)$data['workId'] : 0;

if ($workId <= 0) {
    echo json_encode(['success' => false, 'message' => '作品IDが不正です。']);
    exit();
}

try {
    $dbh = dbConnect();

    $dbh->prepare('DELETE FROM carts WHERE user_id = :userId AND work_id = :workId')
        ->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました。']);
}
