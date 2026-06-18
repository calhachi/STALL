<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    $dbh = null;

    try {
        $dbh = dbConnect();

        $dbh->beginTransaction();

        $dbh->prepare(
            'UPDATE users SET deleted = 1 WHERE id = :id'
        )->execute([
            'id' => $_SESSION['userId']
        ]);

        $dbh->prepare(
            'UPDATE works SET is_hidden = 1 WHERE user_id = :userId'
        )->execute([
            'userId' => $_SESSION['userId']
        ]);

        $dbh->commit();
    } catch (PDOException $e) {
        if ($dbh && $dbh->inTransaction()) {
            $dbh->rollBack();
        }
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }

    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();

    header('Location: ' . $_ENV['APP_URL'] . '/login/login');
    exit();
}

$csrfToken = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント削除 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <h1>アカウント削除</h1>
        <p>この操作は取り消す事ができません！　<br>本当に削除しますか？</p>
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <button type="submit">アカウントを削除する</button>
        </form>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>
