<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$worksData = $_SESSION['postWork'] ?? '';
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>投稿内容確認 | STALL</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <header>
        <div class="header">
            <h1><img src="<?= $_ENV['APP_URL'] ?>/images/stall_logo.svg" alt="STALL" id="top"></h1>
            <form action="" method="get" id="top">
                <input type="search"
                    name="keyword"
                    placeholder="タイトル・作者・システムetc">
                <button type="submit">検索</button>
            </form>
            <div>
                <a href="./mypage/index.php"><img src="<?= $_ENV['APP_URL'] ?>/images/mypage_icon.svg" alt="マイページ"></a>
                <a href="./mypage/favorite.php"><img src="<?= $_ENV['APP_URL'] ?>/images/favorite_icon.svg" alt="お気に入り"></a>
                <a href="./cart/index.php"><img src="<?= $_ENV['APP_URL'] ?>/images/cart_icon.svg" alt="カート"></a>
            </div>
        </div>
    </header>

    <?php if ($worksData !== ''): ?>
        <main>
            <p>以下の内容で作品を投稿します。よろしいですか？</p>


            <h4>作品タイトル</h4>
            <p><?= h($worksData['worksTitle'])
                ?></p>

            <h4>作品ファイル名</h4>
            <p><?= h($worksData['worksOriginalName'])
                ?></p>

            <h4>価格</h4>
            <?php if ($worksData['priceType'] === 'free'): ?>
                <p>無料</p>
            <?php else: ?>
                <p><?= h($worksData['price'])
                    ?>円</p>
            <?php endif; ?>

            <h4>作品カテゴリ</h4>
            <p><?= h($worksData['categoryName'])
                ?></p>

            <h4>サブカテゴリ</h4>
            <p><?= h($worksData['subCategoryName'])
                ?></p>

            <h4>登録タグ</h4>
            <p><?php foreach ($worksData['selectedTags'] as $tag): ?>
                    <?= h($tag['tagName']) ?>
                <?php endforeach; ?></p>

            <?php if ($worksData['categoryId'] === 1): ?>
                <h4>プレイ人数</h4>
                <p><?= h($worksData['minPlayers']) ?>～<?= h($worksData['maxPlayers']) ?>人</p>

                <h4>推定プレイ時間</h4>
                <?php if (!empty($worksData['textHours'])): ?>
                    <p>テキストセッション：約<?= h($worksData['textHours']) ?>時間
                        <?= $worksData['textHours'] == 10 ? '以上' : '' ?></p>
                <?php endif; ?>
                <?php if (!empty($worksData['voiceHours'])): ?>
                    <p>ボイスセッション：約<?= h($worksData['voiceHours']) ?>時間
                        <?= $worksData['voiceHours'] == 10 ? '以上' : '' ?></p>
                <?php endif; ?>
            <?php endif; ?>


            <h4>作品概要</h4>
            <p><?= nl2br(h($worksData['description']))
                ?></p>

            <h4>トレーラー画像・サンプル画像</h4>
            <?php foreach ($worksData['worksImagesName'] as $image): ?>
                <img src="<?= h($_ENV['APP_URL']) ?>/userdata/temp/<?= $image ?>" alt="">
            <?php endforeach; ?>

            <h4>サムネイル</h4>
            <img src="<?= h($_ENV['APP_URL']) ?>/userdata/temp/<?= h($worksData['thumbnailName']) ?>" alt="">
            <a href="<?= $_ENV['APP_URL'] . '/mypage/post/' ?>">
                <p>修正する</p>
            </a>
            <a href="<?= $_ENV['APP_URL'] . '/mypage/works/' ?>">
                <p>投稿する</p>
            </a>
        <?php else: ?>
            <p>投稿する内容がありません。</p>
            <p><a href="<?= $_ENV['APP_URL'] ?>/mypage/post">作品投稿画面に戻る</a></p>

        <?php endif; ?>
        </main>
        <script src="../common/script.js"></script>
</body>

</html>