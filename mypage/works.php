<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$works = [];
$isSuccess = 0;
$categoryNames = [
    1 => 'シナリオ',
    2 => '素材',
    3 => 'その他'
];

if (isset($_SESSION['postWork'])) {

    $worksData = $_SESSION['postWork'];

    $dbh = null;

    try {
        $dbh = dbConnect();

        $dbh->beginTransaction();

        $worksInsertStmt = $dbh->prepare(
            'INSERT INTO works (
        user_id,
        title,
        price,
        category_id,
        subcategory_id,
        min_players,
        max_players,
        text_hours,
        voice_hours,
        description,
        file_name,
        original_name,
        thumbnail_name
    )
    VALUES (
        :userid,
        :title,
        :price,
        :category_id,
        :subcategory_id,
        :min_players,
        :max_players,
        :text_hours,
        :voice_hours,
        :description,
        :file_name,
        :original_name,
        :thumbnail_name
        )'
        );
        $worksInsertStmt->execute([
            'userid' => $_SESSION['userId'],
            'title' => $worksData['worksTitle'],
            'price' => $worksData['price'],
            'category_id' => $worksData['categoryId'],
            'subcategory_id' => $worksData['subCategoryId'],
            'min_players' => $worksData['minPlayers'],
            'max_players' => $worksData['maxPlayers'],
            'text_hours' => $worksData['textHours'] === ''
                ? null
                : $worksData['textHours'],
            'voice_hours' => $worksData['voiceHours'] === ''
                ? null
                : $worksData['voiceHours'],
            'description' => $worksData['description'],
            'file_name' => $worksData['worksName'],
            'original_name' => $worksData['worksOriginalName'],
            'thumbnail_name' => $worksData['thumbnailName']
        ]);

        $workId = $dbh->lastInsertId();

        $worksImageInsertStmt = $dbh->prepare(
            'INSERT INTO works_images (
        work_id,
        image_name,
        display_order
    )
    VALUES (
        :work_id,
        :image_name,
        :display_order
        )'
        );

        foreach ($worksData['worksImagesName'] as $index => $imageName) {
            $worksImageInsertStmt->execute([
                'work_id' => $workId,
                'image_name' => $imageName,
                'display_order' => $index + 1,
            ]);
        }


        $worksTagsInsertStmt = $dbh->prepare(
            'INSERT INTO works_tag (
        work_id,
        tag_id
    )
    VALUES (
        :work_id,
        :tag_id
        )'
        );

        foreach ($worksData['selectedTags'] as $index => $tagsId) {
            $worksTagsInsertStmt->execute([
                'work_id' => $workId,
                'tag_id' => $tagsId['id']
            ]);
        }

        $isSuccess = 1;
        unset($_SESSION['postWork']);
        $dbh->commit();

        // 一時保存tempから正式フォルダに移動
        try {
            rename(
                TEMP_DIR . $worksData['worksName'],
                WORKS_DIR . $worksData['worksName']
            );

            rename(
                TEMP_DIR . $worksData['thumbnailName'],
                THUMBNAIL_DIR . $worksData['thumbnailName']
            );

            foreach ($worksData['worksImagesName'] as $imageName) {

                rename(
                    TEMP_DIR . $imageName,
                    WORKS_IMAGES_DIR . $imageName
                );
            }
        } catch (RuntimeException $e) {

            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    } catch (PDOException $e) {
        if ($dbh && $dbh->inTransaction()) {
            $dbh->rollBack();
        }
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }
}

try {
    $dbh = dbConnect();

    $worksStmt = $dbh->prepare(
        'SELECT id,title,category_id,thumbnail_name,posted_at
        FROM works
        WHERE user_id=:userid
        ORDER BY posted_at DESC'
    );
    $worksStmt->execute([
        'userid' => $_SESSION['userId']
    ]);

    $works = $worksStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}


?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>投稿作品一覧 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?> <main>
        <?php if ($isSuccess === 1): ?>
            <p>投稿に成功しました。</p>
        <?php endif; ?>
        <?php if (!empty($works)): ?>
            <h1>投稿作品一覧</h1>
            <p>クリックで詳細画面に移動します</p>
            <?php foreach ($works as $work): ?>
                <a href="<?= h($_ENV['APP_URL']) ?>/mypage/works-detail.php?id=<?= h($work['id']) ?>">
                    <div class="worksCard">
                        <div><img src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($work['thumbnail_name']) ?>" alt="" class="thumbnailImage"></div>
                        <div>
                            <p><?= h($categoryNames[$work['category_id']] ?? '不明') ?></p>
                            <p><?= h($work['title']) ?></p>
                            <p><?= h($work['posted_at']) ?></p>
                        </div>

                        <div>
                            <p class="deleteButton" data-work-id="<?= h($work['id']) ?>">削除</p>
                        </div>
                    </div>
                </a>

            <?php endforeach; ?>
        <?php else: ?>
            <p>作品がありません。</p>
        <?php endif; ?>
    </main>
    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= h($_ENV['APP_URL']) ?>/common/script.js"></script>
</body>

</html>