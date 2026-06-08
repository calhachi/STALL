<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireGuest.php';

requireGuest();

$emailError = '';
$passError = '';

// 本ページからPOSTしたら二周目でバリデーション
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($email === '') {
        $emailError = 'メールアドレスが入力されていません。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = 'メールアドレスが正しくありません。';
    }

    if ($pass == '') {
        $passError = 'パスワードが入力されていません。';
    } elseif (!preg_match('/\A(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{8,20}\z/', $pass)) {
        $passError = 'パスワードは8文字以上20文字以内の半角英数字で入力してください。';
    }

    if ($emailError == '' && $passError == '') {
        // ログイン処理
        try {
            $dbh = dbConnect();

            $stmt = $dbh->prepare(
                'SELECT id, username, role,password_hash
        FROM users
        WHERE email=:email'
            );
            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user === false) {
                $emailError = '登録されていないメールアドレスです。';
            } else {
                $hashedPass = $user['password_hash'];

                if (!password_verify($pass, $hashedPass)) {
                    $passError = 'メールアドレスとパスワードが一致しません。';
                } else {
                    $_SESSION['userId'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }

    if ($emailError == '' && $passError == '') {
        // 要ログインページから飛ばされた場合そこに戻す
        if (!empty($_SESSION['checkPoint'])) {
            $redirectUrl = $_SESSION['checkPoint'];
            unset($_SESSION['checkPoint']);

            header('Location: ' . $redirectUrl);
            exit();
        }

        header('Location: ' . $_ENV['APP_URL']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン | STALL</title>
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
        <h1>ログイン</h1>
        <p>本サイトの利用にはログインが必要です。</p>
        <form action="" method="post">
            <p>メールアドレス</p>
            <?php if ($emailError != ''): ?>
                <p><?= $emailError ?></p>
            <?php endif; ?>
            <input type="text" name="email">
            <p>パスワード</p>
            <p>（8文字以上20文字以内の半角英数字）</p>
            <?php if ($passError != ''): ?>
                <p><?= $passError ?></p>
            <?php endif; ?>
            <input type="password" name="pass">
            <input type="submit" name="login" value="ログイン">
        </form>
        <p><a href="<?= $_ENV['APP_URL'] . '/login/registration' ?>">新規登録</a></p>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>