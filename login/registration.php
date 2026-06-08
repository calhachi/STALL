<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireGuest.php';

requireGuest();

unset($_SESSION['checkPoint']);

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$pass = $_POST['pass'] ?? '';
$pass2 = $_POST['pass2'] ?? '';
$icon = $_FILES['icon'] ?? '';
$profile = $_POST['profile'] ?? '';
$iconName = '';

$usernameError = '';
$emailError = '';
$passError = '';
$iconError = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($username == '') {
        $usernameError = 'ユーザーネームが入力されていません。';
    } elseif (mb_strlen($username) >= 16) {
        $usernameError = 'ユーザーネームは15文字以内で入力してください。';
    }

    if ($email == '') {
        $emailError = 'メールアドレスが入力されていません。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = 'メールアドレスが正しくありません。';
    } else {
        $dbh = null;
        try {
            $dbh = dbConnect();

            $sql = 'SELECT email 
                        FROM users 
        WHERE email=:email';
            $stmt = $dbh->prepare(
                'SELECT email 
                        FROM users 
        WHERE email=:email'
            );

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user !== false) {
                $emailError = '既に登録されているメールアドレスです。';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . 'error.php');
            exit();
        }
    }

    if ($pass == '' || $pass2 == '') {
        $passError = 'パスワードが入力されていません。';
    } elseif ($pass != $pass2) {
        $passError = '入力欄のパスワード2つが一致しませんでした。';
    } elseif (!preg_match('/\A(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{8,20}\z/', $pass)) {
        $passError = 'パスワードは8文字以上20文字以内の半角英数字で入力してください。';
    }

    if ($_FILES['icon']['error'] === 4) {
        $iconName =  'default_icon.png';
    } elseif ($_FILES['icon']['error'] === 0) {

        $oldIconName = $user['icon_image'];
        $tmpName = $_FILES['icon']['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        $ext = strtolower(pathinfo($icon['name'], PATHINFO_EXTENSION));
        $iconName = uniqid() . '.' . $ext;

        if (
            !in_array($mime, $allowed) ||
            !in_array($ext, $allowedExt)
        ) {
            $iconError = 'jpg,png,webpファイルのみアップロード可能です。';
        } elseif ($icon['size'] > 1000000) {
            $iconError = '1MB以上の画像はアップロードできません。';
        }
    } else {

        $iconError = 'ファイルアップロードに失敗しました。';
    }
    if ($profile != '') {
        if (mb_strlen($profile) >= 300) {
            $profileError = '自己紹介は300文字以内で入力してください。';
        }
    }

    if (
        $usernameError === '' &&
        $emailError === '' &&
        $passError === '' &&
        $iconError === '' &&
        $profileError === ''
    ) {

        $_SESSION['registration'] = [
            'username' => $username,
            'email' => $email,
            'hashedPass' => password_hash($pass, PASSWORD_DEFAULT),
            'passLength' => mb_strlen($pass),
            'profile' => $profile,
            'iconName' => $iconName
        ];

        // 画像がある場合は一時保存
        if ($_FILES['icon']['error'] === 0) {
            move_uploaded_file(
                $_FILES['icon']['tmp_name'],
                __DIR__ . '/../userdata/icon/' . $iconName
            );
        }

        header('Location: ./check.php');
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規会員登録 | STALL</title>
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
        <h1>新規会員登録</h1>
        <form action="" method="post" enctype="multipart/form-data">
            <label for="username">ユーザーネーム</label>
            <br>
            <?php if ($usernameError != ''): ?>
                <p><?= $usernameError ?></p>
            <?php endif; ?>
            <input type="text" name="username" id="username" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <br>
            <label for="email">メールアドレス</label>
            <br>
            <?php if ($emailError != ''): ?>
                <p><?= $emailError ?></p>
            <?php endif; ?>
            <input type="text" name="email" id="email" placeholder="example@example.com">
            <br>
            <label for="pass">パスワード</label>
            <br>
            <p>※半角アルファベットと数字を含む8文字以上20文字以内</p>
            <?php if ($passError != ''): ?>
                <p><?= $passError ?></p>
            <?php endif; ?>
            <input type="password" name="pass" id="pass" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <br>
            <label for="pass2">パスワード（確認用）</label>
            <br>
            <input type="password" name="pass2" id="pass2" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <br>
            <p>プロフィールアイコン</p>
            <p>ファイルサイズ1MB以内・対応拡張子：jpg,png,webp<br>
                400px*400px以上の正方形を推奨します。</p>
            <img src="<?= $_ENV['APP_URL'] . '/userdata/icon/default_icon.png' ?>" id="aImagePreview" alt="デフォルトアイコン"><br>
            <?php if ($iconError != ''): ?>
                <p><?= $iconError ?></p>
            <?php endif; ?>
            <input type="file" name="icon" id="aImageInput" accept=".jpg,.jpeg,.png,.webp">
            <br>
            <label for="profile">自己紹介文</label>
            <br>
            <?php if ($profileError != ''): ?>
                <p><?= $profileError ?></p>
            <?php endif; ?>
            <textarea name="profile" id="profile" class="chara-limit" data-maxlength="300">よろしくお願いします。</textarea>
            <p class="count"></p>
            <br>
            <input type="submit" value="確認画面へ">
        </form>


    </main>
    <script src="../common/script.js"></script>
</body>

</html>