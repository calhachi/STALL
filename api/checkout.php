<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $_ENV['APP_URL'] . '/cart');
    exit();
}

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare(
        'SELECT c.work_id, w.title
         FROM carts c
         JOIN works w ON c.work_id = w.id
         WHERE c.user_id = :userId'
    );
    $stmt->execute(['userId' => $_SESSION['userId']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        header('Location: ' . $_ENV['APP_URL'] . '/cart');
        exit();
    }

    // 購入済みの作品を除外
    $workIds      = array_column($cartItems, 'work_id');
    $placeholders = implode(',', array_fill(0, count($workIds), '?'));
    $purchasedStmt = $dbh->prepare(
        "SELECT work_id FROM purchase WHERE user_id = ? AND work_id IN ($placeholders)"
    );
    $purchasedStmt->execute(array_merge([(int)$_SESSION['userId']], $workIds));
    $purchasedIds = $purchasedStmt->fetchAll(PDO::FETCH_COLUMN);

    $purchasableItems = array_values(array_filter($cartItems, function ($item) use ($purchasedIds) {
        return !in_array($item['work_id'], $purchasedIds);
    }));

    $dbh->beginTransaction();

    $insertStmt = $dbh->prepare(
        'INSERT INTO purchase (user_id, work_id) VALUES (:userId, :workId)'
    );
    foreach ($purchasableItems as $item) {
        $insertStmt->execute(['userId' => (int)$_SESSION['userId'], 'workId' => $item['work_id']]);
    }

    $dbh->prepare('DELETE FROM carts WHERE user_id = :userId')
        ->execute(['userId' => (int)$_SESSION['userId']]);

    $dbh->commit();

    // ↓ Stripe差し替え時はここまでの処理をStripe決済成功後のWebhookに移す

    $_SESSION['purchaseComplete'] = array_map(function ($item) {
        return ['work_id' => $item['work_id'], 'title' => $item['title']];
    }, $purchasableItems);

    header('Location: ' . $_ENV['APP_URL'] . '/purchase/complete');
    exit();

} catch (PDOException $e) {
    if (isset($dbh) && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}
