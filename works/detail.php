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
                    sc.name AS subcategory_name,
                    (SELECT COUNT(*) FROM favorite WHERE work_id = w.id) AS favorite_count
             FROM works w
             JOIN users u ON w.user_id = u.id
             LEFT JOIN subcategories sc ON w.subcategory_id = sc.id
             WHERE w.id = :id'
        );
        $stmt->execute(['id' => $workId]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$work) {
            $worksIdError = '作品が存在しません。';
        } elseif ((int)$work['is_hidden'] === 1) {
            $worksIdError = 'この作品は削除されました。';
            $work = null;
        } else {
            $dbh->prepare('UPDATE works SET view_count = view_count + 1 WHERE id = :id')
                ->execute(['id' => $workId]);

            $imgStmt = $dbh->prepare(
                'SELECT image_name, display_order
                 FROM works_images
                 WHERE work_id = :id
                 ORDER BY display_order'
            );
            $imgStmt->execute(['id' => $workId]);
            $workImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            $tagStmt = $dbh->prepare(
                'SELECT t.tag_name
                 FROM works_tag wt
                 JOIN tags t ON wt.tag_id = t.id
                 WHERE wt.work_id = :id'
            );
            $tagStmt->execute(['id' => $workId]);
            $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

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

                <!-- 上段：ギャラリー + 情報 -->
                <div class="workDetailTop">

                    <!-- 左：画像ギャラリー -->
                    <div class="workGallery">
                        <div class="workMainImageWrap">
                            <img
                                src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($work['thumbnail_name']) ?>"
                                alt="<?= h($work['title']) ?>"
                                id="mainDetailImage"
                                class="workMainImg">
                        </div>
                        <?php if (!empty($workImages)): ?>
                        <div class="workTrailersOuter">
                            <button type="button" class="trailerNav" id="trailerPrev">&#8249;</button>
                            <div class="workTrailers">
                                <div class="trailerTrack" id="trailerTrack">
                                    <?php foreach ($workImages as $image): ?>
                                    <img
                                        src="<?= h($_ENV['APP_URL']) ?>/userdata/works-image/<?= h($image['image_name']) ?>"
                                        alt="サンプル画像"
                                        class="trailerThumb"
                                        data-full="<?= h($_ENV['APP_URL']) ?>/userdata/works-image/<?= h($image['image_name']) ?>">
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="button" class="trailerNav" id="trailerNext">&#8250;</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 右：作品情報 -->
                    <div class="workInfo">
                        <span class="categoryBadge"><?= h($categoryNames[$work['category_id']] ?? '不明') ?></span>
                        <p class="detailDate">投稿日：<?= h(date('Y年n月j日', strtotime($work['posted_at']))) ?></p>
                        <h1 class="detailTitle"><?= h($work['title']) ?></h1>
                        <div class="detailAuthor">
                            <?php if (!empty($work['icon_image'])): ?>
                                <img
                                    src="<?= h($_ENV['APP_URL']) ?>/userdata/icon/<?= h($work['icon_image']) ?>"
                                    alt="<?= h($work['username']) ?>"
                                    class="detailAuthorIcon">
                            <?php else: ?>
                                <span class="detailAuthorIconEmpty"></span>
                            <?php endif; ?>
                            <span class="detailAuthorName"><?= h($work['username']) ?></span>
                        </div>
                        <p class="detailPrice">
                            <?php if ($work['price'] === null): ?>
                                無料
                            <?php else: ?>
                                <?= number_format((int)$work['price']) ?>円
                            <?php endif; ?>
                        </p>

                        <div class="workActions">
                            <?php if ($isOwnWork): ?>
                                <a href="<?= h($_ENV['APP_URL']) ?>/mypage/works-detail?id=<?= h($work['id']) ?>"
                                   class="cartButton editButton">作品を編集する</a>
                            <?php else: ?>
                                <?php if (!empty($_SESSION['userId'])): ?>
                                    <?php if ($isPurchased): ?>
                                        <a href="<?= h($_ENV['APP_URL']) ?>/api/download.php?work_id=<?= h($work['id']) ?>"
                                           class="cartButton downloadButton">ダウンロード</a>
                                    <?php else: ?>
                                        <button type="button" class="cartButton"
                                            data-work-id="<?= h($work['id']) ?>">カートに入れる</button>
                                    <?php endif; ?>
                                    <div class="favoriteArea">
                                        <span>お気に入り</span>
                                        <button type="button"
                                            class="favoriteButton <?= $isFavorited ? 'active' : '' ?>"
                                            data-work-id="<?= h($work['id']) ?>">★</button>
                                    </div>
                                    <button type="button" class="reportButton"
                                        data-work-id="<?= h($work['id']) ?>" data-logged-in="1">この作品を通報する</button>
                                <?php else: ?>
                                    <a href="<?= h($_ENV['APP_URL']) ?>/login" class="cartButton">ログインして購入</a>
                                    <div class="favoriteArea">
                                        <span>お気に入り</span>
                                        <button type="button" class="favoriteButton" disabled>★</button>
                                    </div>
                                    <button type="button" class="reportButton"
                                        data-work-id="<?= h($work['id']) ?>" data-logged-in="0">この作品を通報する</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /.workDetailTop -->

                <!-- 下段：タグ・システム情報・説明 -->
                <div class="workDetailBottom">

                    <?php if (!empty($tags)): ?>
                    <div class="detailInfoBlock">
                        <p class="detailInfoLabel">登録タグ</p>
                        <div class="workTags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="tag"><?= h($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ((int)$work['category_id'] === 1): ?>
                        <?php if (!empty($work['subcategory_name'])): ?>
                        <div class="detailInfoBlock">
                            <p class="detailInfoLabel">使用システム</p>
                            <p class="detailInfoValue"><?= h($work['subcategory_name']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($work['min_players'] !== null): ?>
                        <div class="detailInfoBlock">
                            <p class="detailInfoLabel">推奨プレイ人数</p>
                            <p class="detailInfoValue"><?= h($work['min_players']) ?>〜<?= h($work['max_players']) ?>人</p>
                        </div>
                        <?php endif; ?>

                        <?php if ($work['text_hours'] !== null || $work['voice_hours'] !== null): ?>
                        <div class="detailInfoBlock">
                            <p class="detailInfoLabel">推定プレイ時間</p>
                            <?php if ($work['text_hours'] !== null): ?>
                            <p class="detailInfoValue">テキストセッション：<?= h($playTimeLabels[(int)$work['text_hours']] ?? '') ?></p>
                            <?php endif; ?>
                            <?php if ($work['voice_hours'] !== null): ?>
                            <p class="detailInfoValue">ボイスセッション　：<?= h($playTimeLabels[(int)$work['voice_hours']] ?? '') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="workDescription">
                        <?= nl2br(h($work['description'])) ?>
                    </div>
                </div><!-- /.workDetailBottom -->

            </article>
        <?php endif; ?>
    </main>

    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>

    <script>
        // トレーラーギャラリー
        const mainImg    = document.getElementById('mainDetailImage');
        const track      = document.getElementById('trailerTrack');
        const prevBtn    = document.getElementById('trailerPrev');
        const nextBtn    = document.getElementById('trailerNext');
        const thumbs = document.querySelectorAll('.trailerThumb');

        function getScrollAmount() {
            return thumbs[0] ? thumbs[0].offsetWidth + 8 : 150;
        }

        function updateNavState() {
            if (!prevBtn) return;
            prevBtn.classList.toggle('trailerNavInactive', track.scrollLeft <= 0);
        }

        updateNavState();
        track.addEventListener('scroll', updateNavState);

        thumbs.forEach(function(thumb) {
            thumb.addEventListener('click', function() {
                mainImg.src = this.dataset.full;
                thumbs.forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
            });
        });
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                track.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
                if (atEnd) {
                    track.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    track.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
                }
            });
        }
    </script>

    <?php if (!empty($_SESSION['userId']) && !$isOwnWork && $work): ?>
    <script>
        const favBtn = document.querySelector('.favoriteButton');
        if (favBtn) {
            favBtn.addEventListener('click', async function() {
                const workId = this.dataset.workId;
                try {
                    const res = await fetch(`${appUrl}/api/toggleFavorite.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ workId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.classList.toggle('active', data.isFavorited);
                    } else {
                        alert(data.message);
                    }
                } catch (e) {
                    alert('エラーが発生しました。');
                }
            });
        }

        const cartBtn = document.querySelector('.cartButton');
        if (cartBtn && cartBtn.tagName === 'BUTTON') {
            cartBtn.addEventListener('click', async function() {
                const workId = this.dataset.workId;
                this.disabled = true;
                try {
                    const res = await fetch(`${appUrl}/api/addToCart.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ workId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        location.href = `${appUrl}/cart?added=${workId}`;
                    } else {
                        alert(data.message || 'エラーが発生しました。');
                        this.disabled = false;
                    }
                } catch (e) {
                    alert('エラーが発生しました。');
                    this.disabled = false;
                }
            });
        }
    </script>
    <?php endif; ?>
</body>

</html>
