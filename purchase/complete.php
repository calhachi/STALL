<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';
require_once __DIR__ . '/../common/purchase.php';

requireLogin();

$sessionId = $_GET['session_id'] ?? '';

$purchasedTitles = [];

// 合計0円購入はStripeを通さないため、checkout.phpがセッションに直接渡した結果を表示する
if ($sessionId === '') {
    $purchasedTitles = $_SESSION['purchaseComplete'] ?? [];
    unset($_SESSION['purchaseComplete']);

    if (empty($purchasedTitles)) {
        header('Location: ' . $_ENV['APP_URL'] . '/');
        exit();
    }
} else {
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    try {
        $checkoutSession = \Stripe\Checkout\Session::retrieve($sessionId);

        if (
            $checkoutSession->payment_status !== 'paid'
            || (int)$checkoutSession->metadata->user_id !== (int)$_SESSION['userId']
        ) {
            header('Location: ' . $_ENV['APP_URL'] . '/');
            exit();
        }

        $workIds = array_map('intval', explode(',', $checkoutSession->metadata->work_ids));

        $dbh = dbConnect();
        recordPurchase($dbh, (int)$_SESSION['userId'], $workIds);

        $placeholders = implode(',', array_fill(0, count($workIds), '?'));
        $titleStmt    = $dbh->prepare(
            "SELECT id AS work_id, title FROM works WHERE id IN ($placeholders)"
        );
        $titleStmt->execute($workIds);
        $purchasedTitles = $titleStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    if (empty($purchasedTitles)) {
        header('Location: ' . $_ENV['APP_URL'] . '/');
        exit();
    }
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
