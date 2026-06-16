<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/varidateImage.php';

if (($_SESSION['role'] ?? 0) === 0) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$newsCategories = ['お知らせ', 'アップデート', 'イベント'];

$titleError = '';
$categoryError = '';
$bodyError = '';
$imageError = '';

$title    = trim($_POST['title'] ?? '');
$category = $_POST['category'] ?? $newsCategories[0];
$body     = trim($_POST['body'] ?? '');
$image    = $_FILES['image'] ?? [];
$imageName = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($title === '') {
        $titleError = 'タイトルを入力してください。';
    } elseif (mb_strlen($title) > 255) {
        $titleError = 'タイトルは255文字以内で入力してください。';
    }

    if (!in_array($category, $newsCategories, true)) {
        $categoryError = '無効なカテゴリです。';
    }

    if ($body === '') {
        $bodyError = '本文を入力してください。';
    }

    // アイキャッチ画像（任意）- バリデーションのみ、保存はID確定後
    $imageExt = '';
    if (isset($image['error']) && $image['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageError = validateImage($image, 2000000);
        if ($imageError === '') {
            $imageExt = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        }
    }

    if ($titleError === '' && $categoryError === '' && $bodyError === '' && $imageError === '') {
        try {
            $dbh = dbConnect();

            // 先にINSERTしてIDを確定
            $stmt = $dbh->prepare(
                'INSERT INTO news (title, body, image_name, category)
                 VALUES (:title, :body, :image_name, :category)'
            );
            $stmt->execute([
                ':title'      => $title,
                ':body'       => $body,
                ':image_name' => null,
                ':category'   => $category,
            ]);
            $newId = $dbh->lastInsertId();

            // IDが確定したのでファイル名を決めて保存・UPDATE
            if ($imageExt !== '') {
                $imageName = 'news' . $newId . '.' . $imageExt;
                $saveDir = __DIR__ . '/../images/news/';
                if (!is_dir($saveDir)) {
                    mkdir($saveDir, 0755, true);
                }
                if (move_uploaded_file($image['tmp_name'], $saveDir . $imageName)) {
                    $upd = $dbh->prepare('UPDATE news SET image_name = :image_name WHERE id = :id');
                    $upd->execute([':image_name' => $imageName, ':id' => $newId]);
                } else {
                    error_log('news画像保存失敗: images/news/' . $imageName);
                }
            }

            header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
            exit();
        } catch (PDOException $e) {
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
        <div class="mainWindow">
            <h1>ニュース追加画面</h1>
            <form action="" method="post" enctype="multipart/form-data">
                <h2>記事タイトル</h2>
                <?php if ($titleError !== ''): ?>
                    <p><?= h($titleError) ?></p>
                <?php endif; ?>
                <input type="text" name="title" value="<?= h($title) ?>">
                <br>
                <h2>カテゴリ</h2>
                <?php if ($categoryError !== ''): ?>
                    <p><?= h($categoryError) ?></p>
                <?php endif; ?>
                <select name="category">
                    <?php foreach ($newsCategories as $cat): ?>
                        <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                            <?= h($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br>
                <h2>アイキャッチ画像（任意）</h2>
                <?php if ($imageError !== ''): ?>
                    <p><?= h($imageError) ?></p>
                <?php endif; ?>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                <br>
                <h2>本文</h2>
                <?php if ($bodyError !== ''): ?>
                    <p><?= h($bodyError) ?></p>
                <?php endif; ?>
                <textarea name="body" class="descriptionArea" rows="35" cols="70"><?= h($body) ?></textarea>
                <br>
                <button type="submit">投稿</button>
            </form>
        </div>
    </main>
    <script src="../common/script.js"></script>
</body>

</html>