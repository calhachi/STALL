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

    // 作品の存在確認と投稿者チェック
    $ownerStmt = $dbh->prepare('SELECT user_id FROM works WHERE id = :id');
    $ownerStmt->execute(['id' => $workId]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($ownerId === false) {
        echo json_encode(['success' => false, 'message' => '作品が存在しません。']);
        exit();
    }

    if ((int)$ownerId === (int)$_SESSION['userId']) {
        echo json_encode(['success' => false, 'message' => '自分の作品はお気に入りできません。']);
        exit();
    }

    // 既にお気に入り済みか確認
    $checkStmt = $dbh->prepare(
        'SELECT 1 FROM favorite WHERE user_id = :userId AND work_id = :workId'
    );
    $checkStmt->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
    $alreadyFavorited = (bool)$checkStmt->fetchColumn();

    if ($alreadyFavorited) {
        $dbh->prepare('DELETE FROM favorite WHERE user_id = :userId AND work_id = :workId')
            ->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
        echo json_encode(['success' => true, 'isFavorited' => false]);
    } else {
        $dbh->prepare('INSERT INTO favorite (user_id, work_id) VALUES (:userId, :workId)')
            ->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
        echo json_encode(['success' => true, 'isFavorited' => true]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました。']);
}
