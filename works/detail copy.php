<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

$workId      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$work        = null;
$workImages  = [];
$tags        = [];
$isFavorited = false;
$isPurchased = false;
$worksIdError = '';

if ($workId <= 0) {
    $worksIdError = '不正なアクセスです。';
} else {
    try {
        $dbh = dbConnect();

        $stmt = $dbh->prepare(
            'SELECT w.*, u.username, u.icon_image,
                    (SELECT COUNT(*) FROM favorite WHERE work_id = w.id) AS favorite_count
             FROM works w
             JOIN users u ON w.user_id = u.id
             WHERE w.id = :id'
        );
        $stmt->execute(['id' => $workId]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$work) {
            $worksIdError = '作品が存在しません。';
        } else {
            // 閲覧数カウントアップ
            $dbh->prepare('UPDATE works SET view_count = view_count + 1 WHERE id = :id')
                ->execute(['id' => $workId]);

            // 作品画像
            $imgStmt = $dbh->prepare(
                'SELECT image_name, display_order
                 FROM works_images
                 WHERE work_id = :id
                 ORDER BY display_order'
            );
            $imgStmt->execute(['id' => $workId]);
            $workImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            // タグ
            $tagStmt = $dbh->prepare(
                'SELECT t.tag_name
                 FROM works_tag wt
                 JOIN tags t ON wt.tag_id = t.id
                 WHERE wt.work_id = :id'
            );
            $tagStmt->execute(['id' => $workId]);
            $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

            // ログイン中ユーザーのお気に入り・購入済みチェック
            if (!empty($_SESSION['userId'])) {
                $favStmt = $dbh->prepare(
                    'SELECT 1 FROM favorite WHERE user_id = :userId AND work_id = :workId'
                );
                $favStmt->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
                $isFavorited = (bool)$favStmt->fetchColumn();

                $purchaseStmt = $dbh->prepare(
                    'SELECT 1 FROM purchase WHERE user_id = :userId AND work_id = :workId'
                );
                $purchaseStmt->execute(['userId' => $_SESSION['userId'], 'workId' => $workId]);
                $isPurchased = (bool)$purchaseStmt->fetchColumn();
            }
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }
}

$isOwnWork     = !empty($_SESSION['userId']) && !empty($work) && (int)$_SESSION['userId'] === (int)$work['user_id'];
$categoryNames = [1 => 'シナリオ', 2 => '素材', 3 => 'その他'];
$playTimeLabels = [
    0 => '1時間未満',
    1 => '1時間',
    2 => '2時間',
    3 => '3時間',
    4 => '4時間',
    5 => '5時間',
    6 => '6時間',
    7 => '7時間',
    8 => '8時間',
    9 => '9時間',
    10 => '10時間以上',
];
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $work ? h($work['title']) . ' | STALL' : '作品詳細 | STALL' ?></title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <?php if ($worksIdError !== ''): ?>
            <p><?= h($worksIdError) ?></p>
            <p><a href="<?= h($_ENV['APP_URL']) ?>">トップへ</a></p>
        <?php else: ?>
            <article class="workDetail">

                <img src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($work['thumbnail_name']) ?>"
                    alt="<?= h($work['title']) ?>" class="thumbnailImage">

                <h1><?= h($work['title']) ?></h1>

                <div class="workMeta">
                    <p>カテゴリ: <?= h($categoryNames[$work['category_id']] ?? '不明') ?></p>
                    <p>投稿者: <?= h($work['username']) ?></p>
                    <p>投稿日: <?= h(date('Y年n月j日', strtotime($work['posted_at']))) ?></p>
                    <p>閲覧数: <?= h($work['view_count']) ?></p>
                    <p>♡ <?= h($work['favorite_count']) ?></p>
                </div>

                <?php if (!empty($tags)): ?>
                    <div class="workTags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag"><?= h($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ((int)$work['category_id'] === 1): ?>
                    <div class="trpgInfo">
                        <?php if ($work['min_players'] !== null): ?>
                            <p>プレイ人数: <?= h($work['min_players']) ?>〜<?= h($work['max_players']) ?>人</p>
                        <?php endif; ?>
                        <?php if ($work['text_hours'] !== null): ?>
                            <p>テキストセッション: <?= h($playTimeLabels[(int)$work['text_hours']] ?? '') ?></p>
                        <?php endif; ?>
                        <?php if ($work['voice_hours'] !== null): ?>
                            <p>ボイスセッション: <?= h($playTimeLabels[(int)$work['voice_hours']] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="workPrice">
                    <?php if ($work['price'] === null): ?>
                        <p class="price free">無料</p>
                    <?php else: ?>
                        <p class="price"><?= h($work['price']) ?>円</p>
                    <?php endif; ?>
                </div>

                <div class="workActions">
                    <?php if ($isOwnWork): ?>
                        <a href="<?= h($_ENV['APP_URL']) ?>/mypage/works-detail?id=<?= h($work['id']) ?>">
                            <button type="button">作品を編集する</button>
                        </a>
                    <?php else: ?>
                        <?php if (!empty($_SESSION['userId'])): ?>
                            <?php if ($isPurchased): ?>
                                <button type="button" class="downloadButton" data-work-id="<?= h($work['id']) ?>">
                                    ダウンロード
                                </button>
                            <?php else: ?>
                                <button type="button" class="cartButton" data-work-id="<?= h($work['id']) ?>">
                                    カートに入れる
                                </button>
                            <?php endif; ?>
                            <button type="button"
                                class="favoriteButton <?= $isFavorited ? 'active' : '' ?>"
                                data-work-id="<?= h($work['id']) ?>">
                                <?= $isFavorited ? '♡ お気に入り済み' : '♡ お気に入り' ?>
                            </button>
                        <?php else: ?>
                            <p><a href="<?= h($_ENV['APP_URL']) ?>/login">ログインして購入・お気に入り登録</a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <section>
                    <h2>作品説明</h2>
                    <div class="workDescription">
                        <?= nl2br(h($work['description'])) ?>
                    </div>
                </section>

                <?php if (!empty($workImages)): ?>
                    <section>
                        <h2>サンプル画像</h2>
                        <div class="workImages">
                            <?php foreach ($workImages as $image): ?>
                                <img src="<?= h($_ENV['APP_URL']) ?>/userdata/works-image/<?= h($image['image_name']) ?>"
                                    alt="サンプル画像<?= h($image['display_order']) ?>"
                                    class="workSampleImage">
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </article>
        <?php endif; ?>
    </main>

    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
    <?php if (!empty($_SESSION['userId']) && !$isOwnWork && $work): ?>
        <script>
            const favBtn = document.querySelector('.favoriteButton');
            if (favBtn) {
                favBtn.addEventListener('click', async function() {
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
                        if (data.success) {
                            this.classList.toggle('active', data.isFavorited);
                            this.textContent = data.isFavorited ? '♡ お気に入り済み' : '♡ お気に入り';
                        } else {
                            alert(data.message);
                        }
                    } catch (e) {
                        alert('エラーが発生しました。');
                    }
                });
            }

            const cartBtn = document.querySelector('.cartButton');
            if (cartBtn) {
                cartBtn.addEventListener('click', function() {
                    alert('カート機能は準備中です。');
                });
            }
        </script>
    <?php endif; ?>
</body>

</html>