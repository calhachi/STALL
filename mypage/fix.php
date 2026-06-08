<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$usernameError = '';
$emailError = '';
$passError = '';
$newPassError = '';
$iconError = '';
$profileError = '';
$iconUploaded = '';
$isSuccess = '';

try {
    $dbh = dbConnect();

    $stmt = $dbh->prepare(
        'SELECT email,password_hash,icon_image,profile_text
        FROM users
        WHERE id=:id'
    );
    $stmt->execute([
        'id' => $_SESSION['userId']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $pass = $_POST['pass'] ?? '';
    $newPass = $_POST['newpass'] ?? '';
    $newPass2 = $_POST['newPass2'] ?? '';
    $icon = $_FILES['icon'] ?? null;
    $profile = $_POST['profile'] ?? '';

    if (mb_strlen($username) >= 20) {
        $usernameError = 'ユーザーネームは20文字以内で入力してください。';
    }

    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailError = 'メールアドレスが正しくありません。';
        } else {
            $dbh = null;
            try {
                $dbh = dbConnect();

                $stmt = $dbh->prepare(
                    'SELECT id 
                    FROM users 
                    WHERE email = :email
                     AND id != :id'
                );

                $stmt->execute([
                    'email' => $email,
                    'id' => $_SESSION['userId']
                ]);

                if ($stmt->fetch()) {
                    $emailError = '既に登録されているメールアドレスです。';
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                header('Location: ' . $_ENV['APP_URL'] . '/error.php');
                exit();
            }
        }
    }

    if (!password_verify($pass, $user['password_hash'])) {
        $passError = 'パスワードが違います。';
    }
    if ($email !== '') {
        if ($newPass != $newPass2) {
            $newPassError = '入力欄のパスワード2つが一致しませんでした。';
        } elseif (!preg_match('/\A(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{8,20}\z/', $newPass)) {
            $newPassError = 'パスワードは8文字以上20文字以内の半角英数字で入力してください。';
        }
    }


    if ($_FILES['icon']['error'] === 4) {
        $iconName = $user['icon_image'];
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

    if ($profile !== '') {
        if (mb_strlen($profile) >= 300) {
            $profileError = '自己紹介は300文字以内で入力してください。';
        }
    }

    if (
        password_verify($pass, $user['password_hash']) &&
        $usernameError === '' &&
        $emailError === '' &&
        $passError === '' &&
        $iconError === '' &&
        $profileError === ''
    ) {

        if ($username == '') {
            $username = $_SESSION['username'];
        }
        if ($email == '') {
            $email = $user['email'];
        }
        if ($newPass == '') {
            $hashedPass = $user['password_hash'];
        } else {
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
        }

        // 画像がある場合は一時保存
        if ($_FILES['icon']['error'] === 0) {
            move_uploaded_file(
                $_FILES['icon']['tmp_name'],
                __DIR__ . '/../userdata/icon/' . $iconName
            );
            $iconUploaded = 1;
        }

        $dbh = null;

        try {
            $dbh = dbConnect();

            $dbh->beginTransaction();

            $stmt = $dbh->prepare(
                'UPDATE users SET
        username = :username,
        email = :email,
        password_hash = :hashedPass,
        icon_image = :iconImage,
        profile_text = :profileText
    WHERE id = :id'
            );
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'hashedPass' => $hashedPass,
                'iconImage' => $iconName,
                'profileText' => $profile,
                'id' => $_SESSION['userId']
            ]);

            // 古いアイコン削除
            if ($iconUploaded === 1) {
                unlink(__DIR__ . '/../userdata/icon/' . $oldIconName);
            }

            $isSuccess = 1;
            $dbh->commit();
        } catch (PDOException $e) {
            if ($dbh && $dbh->inTransaction()) {
                $dbh->rollBack();
            }
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員情報変更 | STALL</title>
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
        <h1>会員情報確認・変更</h1>
        <?php if ($isSuccess === 1): ?>
            <p><?= '会員情報を変更しました。' ?></p>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            <label for="pass">現在のパスワード（必須）</label>
            <br>
            <p>※半角アルファベットと数字を含む8文字以上20文字以内</p>
            <?php if ($passError != ''): ?>
                <p><?= $passError ?></p>
            <?php endif; ?>
            <input type="password" name="pass" id="pass" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <br>
            <label for="username">ユーザーネーム</label>
            <br>
            <?php if ($usernameError != ''): ?>
                <p><?= $usernameError ?></p>
            <?php endif; ?>
            <input type="text" name="username" id="username" class="chara-limit" data-maxlength="20" value="<?= h($_SESSION['username']) ?>">
            <p class="count"></p>
            <label for="email">メールアドレス</label>
            <br>
            <?php if ($emailError != ''): ?>
                <p><?= $emailError ?></p>
            <?php endif; ?>
            <input type="text" name="email" id="email" value="<?= h($user['email']) ?>">
            <br>
            <label for="newpass">新しいパスワード</label>
            <br>
            <?php if ($newPassError != ''): ?>
                <p><?= $newPassError ?></p>
            <?php endif; ?>
            <input type="password" name="newpass" id="newpass" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <label for="newpass2">新しいパスワード（確認用）</label>
            <br>
            <input type="password" name="newPass2" id="newpass2" class="chara-limit" data-maxlength="20">
            <p class="count"></p>
            <br>
            <p>プロフィールアイコン</p>
            <p>ファイルサイズ1MB以内・対応拡張子：jpg,png,webp<br>
                400px*400px以上の正方形を推奨します。</p>
            <img src="<?= $_ENV['APP_URL'] . '/userdata/icon/' . $user['icon_image'] ?>" id="preview" alt="ユーザーアイコン"><br>
            <?php if ($iconError != ''): ?>
                <p><?= $iconError ?></p>
            <?php endif; ?>
            <input type="file" name="icon" id="icon" accept=".jpg,.jpeg,.png,.webp">
            <br>
            <label for="profile">自己紹介文</label>
            <br>
            <?php if ($profileError != ''): ?>
                <p><?= $profileError ?></p>
            <?php endif; ?>
            <textarea name="profile" id="profile" class="chara-limit" data-maxlength="300"><?= h($user['profile_text']) ?></textarea>
            <p class="count"></p>
            <br>
            <input type="submit" value="変更">
        </form>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>