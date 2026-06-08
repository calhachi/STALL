<?php
require_once __DIR__ . '/./common/bootstrap.php';
require_once __DIR__ . '/./common/dbConnect.php';

?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STALL | TRPGシナリオ販売サイト</title>
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
    <main>
        <?php if (!empty($_SESSION['username'])): ?>
            <p>ようこそ、<?= $_SESSION['username']  ?>さん</p>
        <?php else: ?>
            <p>ようこそ、ゲストさん</p>
        <?php endif; ?>

        <p>トップページ</p>

    </main>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>