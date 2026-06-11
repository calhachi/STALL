<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$favorites = [];

try {
    $dbh  = dbConnect();
    $stmt = $dbh->prepare(
        'SELECT w.id, w.title, w.price, w.category_id, w.thumbnail_name,
                u.username, f.created_at
         FROM favorite f
         JOIN works w ON f.work_id = w.id
         JOIN users u ON w.user_id = u.id
         WHERE f.user_id = :userId
         ORDER BY f.created_at DESC'
    );
    $stmt->execute(['userId' => $_SESSION['userId']]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

$categoryNames = [1 => 'シナリオ', 2 => '素材', 3 => 'その他'];
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お気に入り一覧 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h1>お気に入り一覧</h1>
        <?php if (!empty($favorites)): ?>
            <p>クリックで詳細画面に移動します</p>
            <div class="worksList">
                <?php foreach ($favorites as $fav): ?>
                    <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($fav['id']) ?>">
                        <div class="worksCard">
                            <img src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($fav['thumbnail_name']) ?>"
                                alt="" class="thumbnailImage">
                            <div>
                                <p><?= h($categoryNames[$fav['category_id']] ?? '不明') ?></p>
                                <p><?= h($fav['title']) ?></p>
                                <p><?= h($fav['username']) ?></p>
                                <p><?= $fav['price'] === null ? '無料' : h($fav['price']) . '円' ?></p>
                                <p>お気に入り登録日: <?= h(date('Y年n月j日', strtotime($fav['created_at']))) ?></p>
                            </div>
                            <button type="button"
                                class="removeFavoriteButton"
                                data-work-id="<?= h($fav['id']) ?>">
                                お気に入り解除
                            </button>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>お気に入り登録した作品はありません。</p>
        <?php endif; ?>
    </main>

    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
    <script>
        document.querySelectorAll('.removeFavoriteButton').forEach(function(btn) {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!confirm('お気に入りを解除しますか？')) return;
                const workId = this.dataset.workId;
                try {
                    const res = await fetch(`${appUrl}/api/toggleFavorite.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            workId
                        })
                    });
                    const data = await res.json();
                    if (data.success && !data.isFavorited) {
                        this.closest('.worksCard').remove();
                    }
                } catch (e) {
                    alert('エラーが発生しました。');
                }
            });
        });
    </script>
</body>

</html>