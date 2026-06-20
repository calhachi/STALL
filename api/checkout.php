<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';
require_once __DIR__ . '/../common/purchase.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $_ENV['APP_URL'] . '/cart');
    exit();
}

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare(
        'SELECT c.work_id, w.title, w.price
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

    if (empty($purchasableItems)) {
        header('Location: ' . $_ENV['APP_URL'] . '/cart');
        exit();
    }

    $total = array_sum(array_column($purchasableItems, 'price'));

    // 合計0円（無料配布など）はStripeを通さずその場で購入確定する
    if ($total <= 0) {
        recordPurchase($dbh, (int)$_SESSION['userId'], array_column($purchasableItems, 'work_id'));

        $_SESSION['purchaseComplete'] = array_map(function ($item) {
            return ['work_id' => $item['work_id'], 'title' => $item['title']];
        }, $purchasableItems);

        header('Location: ' . $_ENV['APP_URL'] . '/purchase/complete');
        exit();
    }

    // JPYはゼロ・デシマル通貨のため unit_amount はそのまま金額（円）を指定する
    $lineItems = array_map(function ($item) {
        return [
            'price_data' => [
                'currency'     => 'jpy',
                'product_data' => ['name' => $item['title']],
                'unit_amount'  => (int)$item['price'],
            ],
            'quantity' => 1,
        ];
    }, $purchasableItems);

    $session = \Stripe\Checkout\Session::create([
        'mode'        => 'payment',
        'line_items'  => $lineItems,
        'success_url' => $_ENV['APP_URL'] . '/purchase/complete?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $_ENV['APP_URL'] . '/purchase/confirm',
        'metadata'    => [
            'user_id'  => $_SESSION['userId'],
            'work_ids' => implode(',', array_column($purchasableItems, 'work_id')),
        ],
    ]);

    header('Location: ' . $session->url);
    exit();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}
