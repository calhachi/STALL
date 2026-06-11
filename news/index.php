<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

try {
    $dbh = dbConnect();
    $stmt = $dbh->prepare(
        'SELECT title, body, image_name, category, created_at, updated_at
         FROM news WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

if (!$news) {
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

function formatDate($datetime)
{
    return (new DateTime($datetime))->format('Y年m月d日　H:i:s');
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($news['title']) ?> | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <h1><?= h($news['title']) ?></h1>
            <p><?= h($news['category']) ?></p>
            <p><?= formatDate($news['created_at']) ?></p>
            <?php if ($news['updated_at'] !== $news['created_at']): ?>
                <p>更新日時：<?= formatDate($news['updated_at']) ?></p>
            <?php endif; ?>

            <?php if ($news['image_name'] !== null): ?>
                <img src="<?= $_ENV['APP_URL'] ?>/images/news/<?= h($news['image_name']) ?>" alt="">
            <?php endif; ?>

            <p><?= nl2br(h($news['body'])) ?></p>
        </div>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>