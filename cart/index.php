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
        'SELECT c.work_id, c.added_at, w.title, w.thumbnail_name, w.price, u.username
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

$addedTitle = '';
if (!empty($cartItems)) {
    foreach ($cartItems as $item) {
        $total += (int)($item['price'] ?? 0);
    }
    $addedId = isset($_GET['added']) ? (int)$_GET['added'] : 0;
    if ($addedId > 0) {
        foreach ($cartItems as $item) {
            if ((int)$item['work_id'] === $addedId) {
                $addedTitle = $item['title'];
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カート | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h2 class="cartHeading">カート</h2>
        <div id="cartMessage">
            <?php if ($addedTitle !== ''): ?>
                <p class="cartAddedMessage">「<?= h($addedTitle) ?>」がカートに追加されました。</p>
            <?php endif; ?>
        </div>
        <?php if (empty($cartItems)): ?>
            <p id="cartEmpty">カートに作品がありません。</p>
        <?php else: ?>
            <div id="cartList">
                <?php foreach ($cartItems as $item): ?>
                <div class="cartItem" data-work-id="<?= h($item['work_id']) ?>" data-price="<?= (int)($item['price'] ?? 0) ?>">
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
                        <p class="cartItemPrice">
                            <?= $item['price'] === null ? '無料' : number_format((int)$item['price']) . '円' ?>
                        </p>
                    </div>
                    <button type="button" class="cartRemoveButton" data-work-id="<?= h($item['work_id']) ?>">カートから削除</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="cartFooter" id="cartFooter">
                <p class="cartTotal">合計：<span id="cartTotalPrice"><?= number_format($total) ?></span>円</p>
                <a href="<?= h($_ENV['APP_URL']) ?>/purchase/confirm" class="cartButton cartProceedButton">購入手続きへ</a>
            </div>
        <?php endif; ?>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
    <script>
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.cartItem').forEach(function(item) {
                total += parseInt(item.dataset.price || 0);
            });
            const el = document.getElementById('cartTotalPrice');
            if (el) el.textContent = total.toLocaleString();
        }

        document.querySelectorAll('.cartRemoveButton').forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const workId = this.dataset.workId;
                const item   = document.querySelector(`.cartItem[data-work-id="${workId}"]`);
                const title  = item ? item.querySelector('.cartItemTitle').textContent.trim() : '';
                this.disabled = true;
                try {
                    const res  = await fetch(`${appUrl}/api/removeFromCart.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ workId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (item) item.remove();
                        updateTotal();

                        const msgDiv = document.getElementById('cartMessage');
                        const p = document.createElement('p');
                        p.className = 'cartAddedMessage';
                        p.textContent = `「${title}」をカートから削除しました。`;
                        msgDiv.innerHTML = '';
                        msgDiv.appendChild(p);

                        if (document.querySelectorAll('.cartItem').length === 0) {
                            document.getElementById('cartList').remove();
                            document.getElementById('cartFooter').remove();
                            const empty = document.createElement('p');
                            empty.id = 'cartEmpty';
                            empty.textContent = 'カートに作品がありません。';
                            document.querySelector('main').appendChild(empty);
                        }
                    } else {
                        alert(data.message || 'エラーが発生しました。');
                        this.disabled = false;
                    }
                } catch (e) {
                    alert('エラーが発生しました。');
                    this.disabled = false;
                }
            });
        });
    </script>
</body>

</html>
