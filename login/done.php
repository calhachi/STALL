<?php
require_once '../common/bootstrap.php';
require_once '../common/dbConnect.php';

$token = $_GET['token'] ?? '';
$isSuccess = 0;
$serverError = 0;
$dbh = null;

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare(
        'SELECT id, username, role
        FROM users
        WHERE register_token=:token'
    );
    $stmt->execute([
        'token' => $token
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user !== false) {

        $dbh->beginTransaction();

        $stmt = $dbh->prepare(
            'UPDATE users 
            SET is_verified=1,
                register_token=NULL
            WHERE register_token=:token
            AND is_verified=0'
        );

        $stmt->execute([
            'token' => $token
        ]);

        session_regenerate_id(true);

        $_SESSION['userId'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $isSuccess = 1;

        $dbh->commit();
    }
} catch (PDOException $e) {
    if ($dbh && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
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
    <title>
        <?= $isSuccess == 1 ? '本登録完了 | STALL' : '登録エラー | STALL'; ?>
    </title>
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

        <?php if ($isSuccess == 1): ?>
            <h1>本登録完了</h1>
            <p>メール認証が完了いたしました。<br>
                本サイトをご利用いただけます。</p>
            <p><a href="<?= $_ENV['APP_URL'] ?>">トップへ</a></p>
        <?php else: ?>
            <p>無効なURLです。</p>
        <?php endif; ?>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>