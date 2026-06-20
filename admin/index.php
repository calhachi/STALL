<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$successText = '';
$mainCategory = '';
$tag = '';

try {
    $dbh = dbConnect();

    $categorySql = 'SELECT id, name 
                    FROM categories';
    $categoryStmt = $dbh->query($categorySql);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mainCategory = $_POST['mainCategory'] ?? '';
    $subCategoryId = $_POST['subCategoryId'] ?? '';
    $subCategoryName = $_POST['subCategoryName'] ?? '';
    $tagCategoryId = $_POST['tag_category_id'] ?? '';
    $tag = $_POST['tag_name'] ?? '';

    if ($mainCategory !== '') {
        $dbh = null;

        try {

            $dbh = dbConnect();

            $dbh->beginTransaction();

            $stmt = $dbh->prepare(
                'INSERT INTO categories (
name
    )
    VALUES (
        :name
        )'
            );
            $stmt->execute([
                'name' => $mainCategory
            ]);

            $dbh->commit();

            $successText = 'カテゴリ追加成功';
        } catch (Exception $e) {
            if ($dbh && $dbh->inTransaction()) {
                $dbh->rollBack();
            }
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }

    if ($subCategoryId !== '' && $subCategoryName !== '') {
        $dbh = null;

        try {

            $dbh = dbConnect();

            $dbh->beginTransaction();

            $stmt = $dbh->prepare(
                'INSERT INTO subcategories (
            category_id,
name
    )
    VALUES (
    :categoryId,
        :name
        )'
            );
            $stmt->execute([
                'categoryId' => $subCategoryId,
                'name' => $subCategoryName
            ]);

            $dbh->commit();

            $successText = 'サブカテゴリ追加成功';
        } catch (Exception $e) {
            if ($dbh && $dbh->inTransaction()) {
                $dbh->rollBack();
            }
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }

    if (
        $tag !== '' &&
        $tagCategoryId !== ''
    ) {
        $dbh = null;

        try {

            $dbh = dbConnect();

            $dbh->beginTransaction();

            $stmt = $dbh->prepare(
                'INSERT INTO tags (
                category_id,
            tag_name
    )
    VALUES (
    :tagCategoryId,
    :tag
        )'
            );
            $stmt->execute([
                'tagCategoryId' => $tagCategoryId,
                'tag' => $tag
            ]);

            $dbh->commit();

            $successText = 'タグ追加成功';
        } catch (Exception $e) {
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
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <?php if ($successText !== ''): ?>
            <p><?= $successText ?></p>
        <?php endif; ?>
        <form action="" method="post">
            <p>カテゴリ追加</p>
            <input type="text" name="mainCategory">
            <br>
            <p>サブカテゴリ追加</p>
            <select name="subCategoryId">
                <option value="">-----</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>">
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="subCategoryName">

            <p>タグ追加</p>
            <select name="tag_category_id">
                <option value="">-----</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>">
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="tag_name">
            <br>
            <input type="submit" value="追加">
        </form>
        <p><a href="<?= $_ENV['APP_URL'] ?>/admin/news.php">お知らせ管理</a></p>
        <p><a href="<?= $_ENV['APP_URL'] ?>/admin/carousel.php">バナー管理</a></p>
        <p><a href="<?= $_ENV['APP_URL'] ?>/admin/reports.php">通報管理</a></p>
    </main>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>