<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$purchasedTitles = $_SESSION['purchaseComplete'] ?? [];
unset($_SESSION['purchaseComplete']);

if (empty($purchasedTitles)) {
    header('Location: ' . $_ENV['APP_URL'] . '/');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購入完了 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="purchaseCompleteWrap">
            <p class="purchaseCompleteIcon">&#10003;</p>
            <h2 class="purchaseCompleteTitle">購入が完了しました</h2>
            <p class="purchaseCompleteLead">以下の作品をご購入いただきました。</p>
            <ul class="purchasedTitleList">
                <?php foreach ($purchasedTitles as $item): ?>
                    <li>
                        <span><?= h($item['title']) ?></span>
                        <a href="<?= h($_ENV['APP_URL']) ?>/api/download.php?work_id=<?= h($item['work_id']) ?>"
                           class="cartButton downloadButton ordersDownloadButton">ダウンロード</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="purchaseCompleteActions">
                <a href="<?= h($_ENV['APP_URL']) ?>/mypage" class="cartButton cartProceedButton">マイページへ</a>
                <a href="<?= h($_ENV['APP_URL']) ?>/" class="cartBackLink">作品を探す</a>
            </div>
        </div>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>
