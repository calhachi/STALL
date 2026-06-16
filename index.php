<?php
require_once __DIR__ . '/common/bootstrap.php';
require_once __DIR__ . '/common/dbConnect.php';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$validCategories = [0 => '全て', 1 => 'シナリオ', 2 => '素材', 3 => 'その他'];

$works = [];
$rankingWorks = [];
$latestNews = [];
$banners = [];
$categoryNames = [1 => 'シナリオ', 2 => '素材', 3 => 'その他'];

try {
    $dbh = dbConnect();

    // 作品一覧（カテゴリフィルタ付き）
    if ($categoryId > 0 && isset($validCategories[$categoryId])) {
        $worksStmt = $dbh->prepare(
            'SELECT w.id, w.title, w.price, w.category_id, w.thumbnail_name,
                    u.username, w.posted_at,
                    (SELECT COUNT(*) FROM favorite WHERE work_id = w.id) AS favorite_count
             FROM works w
             JOIN users u ON w.user_id = u.id
             WHERE w.category_id = :category_id
             ORDER BY w.posted_at DESC
             LIMIT 20'
        );
        $worksStmt->execute(['category_id' => $categoryId]);
    } else {
        $worksStmt = $dbh->query(
            'SELECT w.id, w.title, w.price, w.category_id, w.thumbnail_name,
                    u.username, w.posted_at,
                    (SELECT COUNT(*) FROM favorite WHERE work_id = w.id) AS favorite_count
             FROM works w
             JOIN users u ON w.user_id = u.id
             ORDER BY w.posted_at DESC
             LIMIT 20'
        );
    }
    $works = $worksStmt->fetchAll(PDO::FETCH_ASSOC);

    // 閲覧数ランキング（上位5件）
    $rankingStmt = $dbh->query(
        'SELECT w.id, w.title, w.price, w.category_id, w.thumbnail_name,
                u.username, w.view_count
         FROM works w
         JOIN users u ON w.user_id = u.id
         ORDER BY w.view_count DESC, w.posted_at DESC
         LIMIT 5'
    );
    $rankingWorks = $rankingStmt->fetchAll(PDO::FETCH_ASSOC);

    // 公開中バナー（表示順昇順）
    $bannerStmt = $dbh->prepare(
        'SELECT id, title, image_path, link_url
         FROM banners
         WHERE is_active = 1
           AND (start_at IS NULL OR start_at <= NOW())
           AND (end_at   IS NULL OR end_at   >= NOW())
         ORDER BY display_order ASC, id ASC'
    );
    $bannerStmt->execute();
    $banners = $bannerStmt->fetchAll(PDO::FETCH_ASSOC);

    // お知らせ最新3件（CLAUDE.md: newsクエリは他クエリより後ろに配置）
    $newsStmt = $dbh->query(
        'SELECT id, title, created_at FROM news ORDER BY created_at DESC LIMIT 3'
    );
    $latestNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STALL | TRPGシナリオ販売サイト</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <?php if (!empty($banners)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <?php endif; ?>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <?php if (!empty($banners)): ?>
        <div class="swiper topCarousel">
            <div class="swiper-wrapper">
                <?php foreach ($banners as $bnr): ?>
                <div class="swiper-slide">
                    <?php if ($bnr['link_url'] !== ''): ?>
                    <a href="<?= h($bnr['link_url']) ?>">
                        <img src="<?= h($_ENV['APP_URL']) ?>/images/banners/<?= h($bnr['image_path']) ?>"
                             alt="<?= h($bnr['title']) ?>">
                    </a>
                    <?php else: ?>
                    <img src="<?= h($_ENV['APP_URL']) ?>/images/banners/<?= h($bnr['image_path']) ?>"
                         alt="<?= h($bnr['title']) ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
        <?php endif; ?>

        <section>
            <h2>お知らせ</h2>
            <?php foreach ($latestNews as $newsItem): ?>
                <p><a href="<?= h($_ENV['APP_URL']) ?>/news?id=<?= h($newsItem['id']) ?>"><?= h(substr($newsItem['created_at'], 0, 10)) ?>　<?= h($newsItem['title']) ?></a></p>
            <?php endforeach; ?>
            <p><a href="<?= h($_ENV['APP_URL']) ?>/news/news-list.php">お知らせ一覧</a></p>
        </section>

        <?php if (!empty($rankingWorks)): ?>
            <section class="center">
                <div class="moreTextFlex">
                    <h2>閲覧数ランキング</h2>
                    <p><a href="">もっと見る</a></p>
                </div>
                <ol class="rankingList">
                    <?php foreach ($rankingWorks as $rank): ?>
                        <li class="worksCardColumn">
                            <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($rank['id']) ?>">
                                <div>
                                    <img src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($rank['thumbnail_name']) ?>"
                                        alt="" class="thumbnailImage">
                                    <div class="noMargin">
                                        <p class="cardCategoryIcon"><?= h($categoryNames[$rank['category_id']] ?? '不明') ?></p>
                                    </div>
                                    <div class="cardDescription">
                                        <p class="cardTitle"><?= h($rank['title']) ?></p>
                                        <p><?= h($rank['username']) ?></p>
                                        <p><?= $rank['price'] === null ? '無料' : h($rank['price']) . '円' ?></p>
                                        <p>閲覧数: <?= h($rank['view_count']) ?></p>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
        <section>
            <h2>お知らせ</h2>
        </section>
        <section>
            <h2>新着作品一覧</h2>
            <div class="categoryFilter">
                <?php foreach ($validCategories as $id => $name): ?>
                    <a href="<?= h($_ENV['APP_URL']) ?>?category=<?= $id ?>"
                        class="filterButton <?= $categoryId === $id ? 'active' : '' ?>">
                        <?= h($name) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <p>クリックで詳細画面に移動します</p>
            <?php if (!empty($works)): ?>
                <div class="worksList">
                    <?php foreach ($works as $work): ?>
                        <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($work['id']) ?>">
                            <div class="worksCard">
                                <img src="<?= h($_ENV['APP_URL']) ?>/userdata/thumbnail/<?= h($work['thumbnail_name']) ?>"
                                    alt="" class="thumbnailImage">
                                <div>
                                    <p><?= h($categoryNames[$work['category_id']] ?? '不明') ?></p>
                                    <p><?= h($work['title']) ?></p>
                                    <p><?= h($work['username']) ?></p>
                                    <p><?= $work['price'] === null ? '無料' : h($work['price']) . '円' ?></p>
                                    <p>♡ <?= h($work['favorite_count']) ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>まだ作品がありません。</p>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
    <?php if (!empty($banners)): ?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        new Swiper('.topCarousel', {
            loop: true,
            autoplay: { delay: 4000, disableOnInteraction: false },
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        });
    </script>
    <?php endif; ?>
</body>

</html>