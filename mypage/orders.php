<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$orders = [];

try {
    $dbh  = dbConnect();
    $stmt = $dbh->prepare(
        'SELECT p.work_id, p.purchased_at, w.title, w.thumbnail_name, w.price, u.username
         FROM purchase p
         JOIN works w ON p.work_id = w.id
         JOIN users u ON w.user_id = u.id
         WHERE p.user_id = :userId
         ORDER BY p.purchased_at DESC'
    );
    $stmt->execute(['userId' => $_SESSION['userId']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>購入済み作品 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h2 class="cartHeading">購入済み作品</h2>
        <?php if (empty($orders)): ?>
            <p>購入済みの作品がありません。</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <div class="cartItem">
                <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($order['work_id']) ?>">
                    <img
                        src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($order['thumbnail_name']) ?>"
                        alt="<?= h($order['title']) ?>"
                        class="cartItemThumb">
                </a>
                <div class="cartItemInfo">
                    <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($order['work_id']) ?>" class="cartItemTitle">
                        <?= h($order['title']) ?>
                    </a>
                    <p class="cartItemAuthor"><?= h($order['username']) ?></p>
                    <p class="cartItemPrice">
                        <?= $order['price'] === null ? '無料' : number_format((int)$order['price']) . '円' ?>
                    </p>
                </div>
                <a href="<?= h($_ENV['APP_URL']) ?>/api/download.php?work_id=<?= h($order['work_id']) ?>"
                   class="cartButton downloadButton ordersDownloadButton">ダウンロード</a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>
