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

$newsId = isset($data['newsId']) ? (int)$data['newsId'] : 0;

if ($newsId <= 0) {
    echo json_encode(['success' => false, 'message' => 'IDが無効です。']);
    exit();
}

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare('SELECT image_name FROM news WHERE id = :id');
    $stmt->execute([':id' => $newsId]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($news === false) {
        echo json_encode(['success' => false, 'message' => 'お知らせが存在しません。']);
        exit();
    }

    $dbh->beginTransaction();
    $del = $dbh->prepare('DELETE FROM news WHERE id = :id');
    $del->execute([':id' => $newsId]);
    $dbh->commit();

    if ($news['image_name'] !== null) {
        $imagePath = __DIR__ . '/../images/news/' . $news['image_name'];
        if (file_exists($imagePath) && !unlink($imagePath)) {
            error_log('news画像削除失敗: ' . $imagePath);
        }
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if (isset($dbh) && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '削除に失敗しました。']);
}
