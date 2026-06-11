<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

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
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <p>サーバーとの通信でエラーが発生しました。<br>
            時間を置いてから再度お試しください。</p>
        <p><a href="<?= $_ENV['APP_URL'] ?>">トップページへ</a></p>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>