<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/purchase.php';

// ユーザーが決済完了後にcomplete.phpへ戻らなかった場合の保険として、
// Webhook経由でも購入確定を行う（recordPurchaseは二重実行されても安全）。

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$payload   = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $_ENV['STRIPE_WEBHOOK_SECRET']);
} catch (\Exception $e) {
    error_log($e->getMessage());
    http_response_code(400);
    exit();
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    if ($session->payment_status === 'paid') {
        $userId  = (int)$session->metadata->user_id;
        $workIds = array_map('intval', explode(',', $session->metadata->work_ids));

        try {
            $dbh = dbConnect();
            recordPurchase($dbh, $userId, $workIds);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            exit();
        }
    }
}

http_response_code(200);
