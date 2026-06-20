<?php

// Stripeの決済成功（complete.php / Webhook）の両方から呼ばれる購入確定処理。
// 二重実行されても安全なように、未購入のwork_idのみINSERTする。
function recordPurchase(PDO $dbh, int $userId, array $workIds): array
{
    if (empty($workIds)) {
        return [];
    }

    $placeholders  = implode(',', array_fill(0, count($workIds), '?'));
    $purchasedStmt = $dbh->prepare(
        "SELECT work_id FROM purchase WHERE user_id = ? AND work_id IN ($placeholders)"
    );
    $purchasedStmt->execute(array_merge([$userId], $workIds));
    $alreadyPurchased = $purchasedStmt->fetchAll(PDO::FETCH_COLUMN);

    $newWorkIds = array_values(array_diff($workIds, $alreadyPurchased));

    if (!empty($newWorkIds)) {
        $dbh->beginTransaction();

        $insertStmt = $dbh->prepare(
            'INSERT INTO purchase (user_id, work_id) VALUES (:userId, :workId)'
        );
        foreach ($newWorkIds as $workId) {
            $insertStmt->execute(['userId' => $userId, 'workId' => $workId]);
        }

        $deletePlaceholders = implode(',', array_fill(0, count($newWorkIds), '?'));
        $dbh->prepare(
            "DELETE FROM carts WHERE user_id = ? AND work_id IN ($deletePlaceholders)"
        )->execute(array_merge([$userId], $newWorkIds));

        $dbh->commit();
    }

    return $newWorkIds;
}
