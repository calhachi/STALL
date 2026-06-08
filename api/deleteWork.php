<?php

require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$workId = $data['workId'] ?? '';

if ($workId === '') {

    echo json_encode([
        'success' => false,
        'message' => '作品IDがありません。'
    ]);

    exit();
}

$dbh = dbConnect();

try {


    $worksStmt = $dbh->prepare('
        SELECT file_name,thumbnail_name 
        FROM works
        WHERE id = :id
        AND user_id = :userId
    ');
    $worksStmt->execute([
        ':id' => $workId,
        ':userId' => $_SESSION['userId']
    ]);
    $worksFile = $worksStmt->fetch(PDO::FETCH_ASSOC);

    if ($worksFile === false) {

        echo json_encode([
            'success' => false,
            'message' => '作品が存在しません。'
        ]);

        exit();
    }
    $worksImagesStmt = $dbh->prepare('
        SELECT image_name 
        FROM works_images
        WHERE work_id = :workId
    ');
    $worksImagesStmt->execute([
        ':workId' => $workId,
    ]);
    $worksImages = $worksImagesStmt->fetchAll(PDO::FETCH_ASSOC);

    $dbh->beginTransaction();

    $deleteStmt = $dbh->prepare('
        DELETE FROM works
        WHERE id = :id
        AND user_id = :userId
    ');

    $deleteStmt->execute([
        ':id' => $workId,
        ':userId' => $_SESSION['userId']
    ]);

    $dbh->commit();

    $path = WORKS_DIR . $worksFile['file_name'];
    if (file_exists($path)) {

        if (!unlink($path)) {
            error_log('削除失敗: ' . $path);
        }
    }

    $path = THUMBNAIL_DIR . $worksFile['thumbnail_name'];
    if (file_exists($path)) {

        if (!unlink($path)) {
            error_log('削除失敗: ' . $path);
        }
    }

    foreach ($worksImages as $image) {
        $path = WORKS_IMAGES_DIR . $image['image_name'];

        if (file_exists($path)) {

            if (!unlink($path)) {
                error_log('削除失敗: ' . $path);
            }
        }
    }


    echo json_encode([
        'success' => true
    ]);
} catch (Exception $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    error_log($e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => '削除に失敗しました。'
    ]);
}
