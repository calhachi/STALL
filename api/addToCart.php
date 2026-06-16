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

    $ownerStmt = $dbh->prepare('SELECT user_id FROM works WHERE id = :id');
    $ownerStmt->execute(['id' => $workId]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($ownerId === false) {
        echo json_encode(['success' => false, 'message' => '作品が存在しません。']);
        exit();
    }

    if ((int)$ownerId === (int)$_SESSION['userId']) {
        echo json_encode(['success' => false, 'message' => '自分の作品はカートに入れられません。']);
        exit();
    }

    $dbh->prepare('INSERT IGNORE INTO carts (user_id, work_id) VALUES (:userId, :workId)')
        ->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました。']);
}
