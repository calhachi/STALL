<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

try {
    $dbh = dbConnect();

    $sql = 'SELECT icon_image, created_at
        FROM users
        WHERE id=:id';
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        'id' => $_SESSION['userId']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $iconImage = $user['icon_image'];
    $createdAt = date('Y年n月j日', strtotime($user['created_at']));
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}


?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?> <main>
        <h1>マイページ</h1>
        <div class="menu">
            <section>
                <h2>会員情報</h2>
                <img src="<?= $_ENV['APP_URL'] ?>/userdata/icon/<?= h($iconImage) ?> " alt="" class="user_icon">
                <p><?= h($_SESSION['username']) ?></p>
                <p>登録日：<?= h($createdAt) ?></p>
            </section>
            <div class="flex-row">
                <section>
                    <h2>アカウントメニュー</h2>
                    <ul>
                        <li><a href="<?= $_ENV['APP_URL'] ?>/mypage/fix">会員情報変更</a></li>
                        <li><a href="<?= $_ENV['APP_URL'] ?>/mypage/orders">購入履歴</a></li>
                        <li><a href="<?= $_ENV['APP_URL'] ?>/mypage/favorite">お気に入り一覧</a></li>
                        <li>
                            <form action="<?= $_ENV['APP_URL'] ?>/login/logout" method="post">
                                <button type="submit">ログアウト</button>
                            </form>
                        </li>
                    </ul>
                </section>
                <section>
                    <h2>作品管理</h2>
                    <ul>
                        <li><a href="<?= $_ENV['APP_URL'] ?>/mypage/post">新規投稿</a></li>
                        <li><a href="<?= $_ENV['APP_URL'] ?>/mypage/works">投稿作品一覧</a></li>
                    </ul>
                </section>
            </div>
        </div>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>