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

$bannerId = isset($data['bannerId']) ? (int)$data['bannerId'] : 0;

if ($bannerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'バナーIDが無効です。']);
    exit();
}

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare('SELECT image_path FROM banners WHERE id = :id');
    $stmt->execute([':id' => $bannerId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($banner === false) {
        echo json_encode(['success' => false, 'message' => 'バナーが存在しません。']);
        exit();
    }

    $dbh->beginTransaction();

    $del = $dbh->prepare('DELETE FROM banners WHERE id = :id');
    $del->execute([':id' => $bannerId]);

    $dbh->commit();

    if ($banner['image_path'] !== '') {
        $imagePath = BANNER_DIR . $banner['image_path'];
        if (file_exists($imagePath) && !unlink($imagePath)) {
            error_log('バナー画像削除失敗: ' . $imagePath);
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
