<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログアウト完了 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <p>ログアウトしました。</p>
        <p><a href="<?= $_ENV['APP_URL'] ?>">トップページへ</a></p>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>