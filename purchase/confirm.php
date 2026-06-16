<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$cartItems = [];
$total     = 0;

try {
    $dbh  = dbConnect();
    $stmt = $dbh->prepare(
        'SELECT c.work_id, w.title, w.thumbnail_name, w.price, u.username
         FROM carts c
         JOIN works w ON c.work_id = w.id
         JOIN users u ON w.user_id = u.id
         WHERE c.user_id = :userId
         ORDER BY c.added_at ASC'
    );
    $stmt->execute(['userId' => $_SESSION['userId']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

if (empty($cartItems)) {
    header('Location: ' . $_ENV['APP_URL'] . '/cart');
    exit();
}

foreach ($cartItems as $item) {
    $total += (int)($item['price'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購入確認 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h2 class="cartHeading">購入確認</h2>
        <p class="confirmLead">以下の内容で購入します。</p>

        <div class="confirmList">
            <?php foreach ($cartItems as $item): ?>
            <div class="cartItem">
                <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($item['work_id']) ?>">
                    <img
                        src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($item['thumbnail_name']) ?>"
                        alt="<?= h($item['title']) ?>"
                        class="cartItemThumb">
                </a>
                <div class="cartItemInfo">
                    <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($item['work_id']) ?>" class="cartItemTitle">
                        <?= h($item['title']) ?>
                    </a>
                    <p class="cartItemAuthor"><?= h($item['username']) ?></p>
                    <p class="cartItemPrice"><?= number_format((int)($item['price'] ?? 0)) ?>円</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cartFooter">
            <a href="<?= h($_ENV['APP_URL']) ?>/cart" class="cartBackLink">カートに戻る</a>
            <p class="cartTotal">合計：<?= number_format($total) ?>円</p>
            <form action="<?= h($_ENV['APP_URL']) ?>/api/checkout.php" method="post">
                <button type="submit" class="cartButton cartProceedButton">購入する</button>
            </form>
        </div>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>
