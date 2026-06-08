<?php
require_once '../common/bootstrap.php';
require_once '../common/dbConnect.php';

if (!isset($_SESSION['registration'])) {
    header('Location: ' . $_ENV['APP_URL'] . '/login/registration');
    exit();
}
$userData = $_SESSION['registration'];

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登録情報確認 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
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
        <form action="preregistration.php" method="post">
            <h1>登録情報確認</h1>
            <h4>ユーザーネーム</h4>
            <?= h($userData['username']) ?>
            <h4>メールアドレス</h4>
            <?= h($userData['email']) ?>
            <h4>パスワード</h4>
            <p><?= str_repeat('●', $userData['passLength']) ?></p>
            <p>プロフィールアイコン</p>
            <img src="<?= $_ENV['APP_URL'] . '/userdata/icon/' . $userData['iconName'] ?>" alt="ユーザーアイコン" id="aImagePreview">
            <p>自己紹介文</p>
            <?= h($userData['profile']) ?>
            <br>
            <p>上記の内容で登録します。</p>
            <input type="button" onclick="history.back()" value="戻る">
            <input type="submit" value="登録" style="width: 50px;">
        </form>
    </main>
    <script src="common/script.js"></script>
</body>

</html>