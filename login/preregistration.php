<?php
require_once '../common/bootstrap.php';
require_once '../common/dbConnect.php';

if (!isset($_SESSION['registration'])) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}
$userData = $_SESSION['registration'];

$registerToken = bin2hex(random_bytes(32));

$dbh = null;

try {

    $dbh = dbConnect();

    $dbh->beginTransaction();

    $stmt = $dbh->prepare(
        'INSERT INTO users (
        username,
        email,
        password_hash,
        icon_image,
        profile_text,
        register_token
    )
    VALUES (
        :username,
        :email,
        :passwordHash,
        :iconImage,
        :profileText,
        :registerToken
        )'
    );
    $stmt->execute([
        'username' => $userData['username'],
        'email' => $userData['email'],
        'passwordHash' => $userData['hashedPass'],
        'iconImage' => $userData['iconName'],
        'profileText' => $userData['profile'],
        'registerToken' => $registerToken
    ]);

    $registerUrl = $_ENV['APP_URL'] . "/login/done.php?token={$registerToken}";
    $body = <<<EOT
            {$userData['username']}様

            会員登録ありがとうございます。

            以下URLをクリックして本登録を完了してください。

            {$registerUrl}


            このメールにお心あたりがない場合は誠に恐れ入りますが
            破棄していただきますようお願いいたします。

            発行元：STALL  https://saka.gloomy.jp/portfolio/stall
            (c)2026 scenallion All Rights Reserved.
            EOT;

    $title = '仮登録完了のお知らせ ';
    $header = 'From:info@saka.gloomy.jp';
    mb_language('japanese');
    mb_internal_encoding('UTF-8');
    $resultCustomer = mb_send_mail($userData['email'], $title, $body, $header);

    if ($resultCustomer === false) {
        throw new Exception('mail send failed');
    }

    $dbh->commit();

    unset($_SESSION['registration']);
} catch (Exception $e) {
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
    <title>仮登録完了 | STALL</title>
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
        <h1>仮登録完了</h1>
        <p>入力されたメールアドレス宛に本登録用のURL付きメールを送信しました。<br>
            ご確認ください。</p>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>