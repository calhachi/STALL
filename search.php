<?php
require_once __DIR__ . '/common/bootstrap.php';
require_once __DIR__ . '/common/dbConnect.php';

$keyword    = trim($_GET['keyword'] ?? '');
$filterCat  = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$priceType  = $_GET['price_type'] ?? '';
$minPrice   = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (int)$_GET['min_price'] : null;
$maxPrice   = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int)$_GET['max_price'] : null;
$sortBy     = $_GET['sort'] ?? 'new';

$works      = [];
$categories = [];

try {
    $dbh = dbConnect();

    $catStmt    = $dbh->query('SELECT id, name FROM categories ORDER BY id');
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $conditions = ['w.is_hidden = 0'];
    $params     = [];

    if ($keyword !== '') {
        $conditions[] = '(w.title LIKE :keyword OR u.username LIKE :keyword OR
                         EXISTS (SELECT 1 FROM works_tag wt JOIN tags t ON wt.tag_id = t.id
                                 WHERE wt.work_id = w.id AND t.tag_name LIKE :keyword))';
        $params['keyword'] = '%' . $keyword . '%';
    }
    if ($filterCat > 0) {
        $conditions[] = 'w.category_id = :category_id';
        $params['category_id'] = $filterCat;
    }
    if ($priceType === 'free') {
        $conditions[] = 'w.price IS NULL';
    } elseif ($priceType === 'paid') {
        $conditions[] = 'w.price IS NOT NULL';
        if ($minPrice !== null) {
            $conditions[] = 'w.price >= :min_price';
            $params['min_price'] = $minPrice;
        }
        if ($maxPrice !== null) {
            $conditions[] = 'w.price <= :max_price';
            $params['max_price'] = $maxPrice;
        }
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $orderClause = match ($sortBy) {
        'view'     => 'ORDER BY w.view_count DESC, w.posted_at DESC',
        'favorite' => 'ORDER BY favorite_count DESC, w.posted_at DESC',
        default    => 'ORDER BY w.posted_at DESC',
    };

    $sql = "SELECT w.id, w.title, w.price, w.category_id, w.thumbnail_name,
                   u.username, w.posted_at, w.view_count,
                   (SELECT COUNT(*) FROM favorite WHERE work_id = w.id) AS favorite_count
            FROM works w
            JOIN users u ON w.user_id = u.id
            $whereClause
            $orderClause
            LIMIT 50";

    if (!empty($params)) {
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $dbh->query($sql);
    }
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$categoryNames = [1 => 'シナリオ', 2 => '素材', 3 => 'その他'];
$hasFilter = $keyword !== '' || $filterCat > 0 || $priceType !== '';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作品検索 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h1>作品検索</h1>

        <form action="<?= $_ENV['APP_URL'] ?>/search" method="get" class="searchForm">
            <div>
                <label>キーワード
                    <input type="search" name="keyword" value="<?= h($keyword) ?>" placeholder="タイトル・作者・タグ">
                </label>
            </div>
            <div>
                <label>カテゴリ
                    <select name="category_id">
                        <option value="0">すべて</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat['id']) ?>"
                                <?= $filterCat === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= h($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="priceFilter">
                <span>価格</span>
                <label><input type="radio" name="price_type" value=""
                        <?= $priceType === '' ? 'checked' : '' ?>> すべて</label>
                <label><input type="radio" name="price_type" value="free"
                        <?= $priceType === 'free' ? 'checked' : '' ?>> 無料</label>
                <label><input type="radio" name="price_type" value="paid"
                        <?= $priceType === 'paid' ? 'checked' : '' ?>> 有料</label>
                <span class="paidPriceRange" <?= $priceType !== 'paid' ? 'style="display:none"' : '' ?>>
                    <input type="number" name="min_price" value="<?= h($minPrice ?? '') ?>" min="1" placeholder="下限">円
                    〜
                    <input type="number" name="max_price" value="<?= h($maxPrice ?? '') ?>" min="1" placeholder="上限">円
                </span>
            </div>
            <div class="sortFilter">
                <span>並べ替え</span>
                <label><input type="radio" name="sort" value="new"
                        <?= $sortBy === 'new' ? 'checked' : '' ?>> 新着順</label>
                <label><input type="radio" name="sort" value="view"
                        <?= $sortBy === 'view' ? 'checked' : '' ?>> 閲覧数順</label>
                <label><input type="radio" name="sort" value="favorite"
                        <?= $sortBy === 'favorite' ? 'checked' : '' ?>> お気に入り数順</label>
            </div>
            <button type="submit">絞り込む</button>
        </form>

        <p>クリックで詳細画面に移動します</p>

        <?php if ($hasFilter): ?>
            <p><?= count($works) ?>件見つかりました</p>
        <?php endif; ?>

        <?php if (!empty($works)): ?>
            <div class="rankingList">
                <?php foreach ($works as $work): ?>
                    <div class="worksCardWrap">
                        <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($work['id']) ?>">
                            <div class="worksCardColumn">
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
                        <button type="button" class="reportButton" data-work-id="<?= h($work['id']) ?>"
                            data-logged-in="<?= !empty($_SESSION['userId']) ? '1' : '0' ?>">通報</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($hasFilter): ?>
            <p>該当する作品が見つかりませんでした。</p>
        <?php else: ?>
            <p>キーワードやカテゴリを指定して検索してください。</p>
        <?php endif; ?>
    </main>

    <script>
        document.querySelectorAll('input[name="price_type"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const range = document.querySelector('.paidPriceRange');
                if (range) range.style.display = this.value === 'paid' ? '' : 'none';
            });
        });
    </script>
    <script>
        const appUrl = <?= json_encode($_ENV['APP_URL']) ?>;
    </script>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>