<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

const PER_PAGE = 5;

try {
    $dbh = dbConnect();

    $totalCount = (int)$dbh->query('SELECT COUNT(*) FROM news')->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / PER_PAGE));

    // 最新=1ページ目（降順）
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $totalPages]]);
    if ($page === false || $page === null) {
        $page = 1;
    }

    $offset = ($page - 1) * PER_PAGE;
    $stmt = $dbh->prepare(
        'SELECT id, title, body, image_name, category, created_at
         FROM news
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

function newsExcerpt(string $text, int $len = 80): string
{
    $plain = strip_tags($text);
    if (mb_strlen($plain) <= $len) {
        return h($plain);
    }
    return h(mb_substr($plain, 0, $len)) . '…';
}

$baseUrl = $_ENV['APP_URL'] . '/news/news-list.php';
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お知らせ一覧 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h1>お知らせ一覧</h1>

        <div class="mainWindow">

            <?php if (empty($newsList)): ?>
                <p>お知らせはまだありません。</p>
            <?php else: ?>
                <?php foreach ($newsList as $item): ?>
                    <a href="<?= $_ENV['APP_URL'] ?>/news/index.php?id=<?= (int)$item['id'] ?>" class="newsCardLink">
                        <div class="newsCard">
                            <p class="newsCardDate"><?= h((new DateTime($item['created_at']))->format('Y-m-d')) ?></p>
                            <div class="newsCardBody">
                                <?php if ($item['image_name'] !== null): ?>
                                    <img src="<?= $_ENV['APP_URL'] ?>/images/news/<?= h($item['image_name']) ?>" alt="" class="newsCardImage">
                                <?php endif; ?>
                                <div class="newsCardText">
                                    <p class="cardCategoryIcon"><?= h($item['category']) ?></p>
                                    <h2 class="newsCardTitle"><?= h($item['title']) ?></h2>
                                    <p class="newsCardExcerpt"><?= newsExcerpt($item['body']) ?></p>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <nav class="pagination" aria-label="ページナビゲーション">
                    <?php if ($page > 1): ?>
                        <a href="<?= $baseUrl ?>?page=1" class="pageLink">最新</a>
                        <a href="<?= $baseUrl ?>?page=<?= $page - 1 ?>" class="pageLink">‹</a>
                    <?php else: ?>
                        <span class="pageLink disabled">最新</span>
                        <span class="pageLink disabled">‹</span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i === $page): ?>
                            <span class="pageLink current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $baseUrl ?>?page=<?= $i ?>" class="pageLink"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $baseUrl ?>?page=<?= $page + 1 ?>" class="pageLink">›</a>
                        <a href="<?= $baseUrl ?>?page=<?= $totalPages ?>" class="pageLink">最古</a>
                    <?php else: ?>
                        <span class="pageLink disabled">›</span>
                        <span class="pageLink disabled">最古</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        </div>
    </main>
    <footer>
        <a href="<?= $_ENV['APP_URL'] ?>#top"><img src="<?= $_ENV['APP_URL'] ?>/images/top_button.svg" alt="ページトップへ" id="backToTop"></a>
    </footer>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>