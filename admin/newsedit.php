<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/varidateImage.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$newsCategories = ['お知らせ', 'アップデート', 'イベント'];

$editId = 0;
$errors = [];
$news = [
    'title'      => '',
    'category'   => $newsCategories[0],
    'body'       => '',
    'image_name' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $editId = (int)($_GET['id'] ?? 0);
    if ($editId <= 0) {
        header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
        exit();
    }
    try {
        $dbh = dbConnect();
        $stmt = $dbh->prepare('SELECT id, title, category, body, image_name FROM news WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
            exit();
        }
        $news = $row;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }

    $editId = (int)($_POST['edit_id'] ?? 0);
    if ($editId <= 0) {
        header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
        exit();
    }

    $title       = trim($_POST['title'] ?? '');
    $category    = $_POST['category'] ?? '';
    $body        = trim($_POST['body'] ?? '');
    $image       = $_FILES['image'] ?? [];
    $deleteImage = isset($_POST['delete_image']);
    $imageExt    = '';

    // バリデーション失敗時の再描画用に値を保持
    $news = [
        'title'      => $title,
        'category'   => $category,
        'body'       => $body,
        'image_name' => $_POST['current_image_name'] ?: null,
    ];

    if ($title === '') {
        $errors['title'] = 'タイトルを入力してください。';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'タイトルは255文字以内で入力してください。';
    }

    if (!in_array($category, $newsCategories, true)) {
        $errors['category'] = '無効なカテゴリです。';
    }

    if ($body === '') {
        $errors['body'] = '本文を入力してください。';
    }

    $hasNewImage = isset($image['error']) && $image['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasNewImage) {
        $imageErr = validateImage($image, 2000000);
        if ($imageErr !== '') {
            $errors['image'] = $imageErr;
        } else {
            $imageExt = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        }
    }

    if (empty($errors)) {
        try {
            $dbh = dbConnect();

            $chk = $dbh->prepare('SELECT image_name FROM news WHERE id = :id');
            $chk->execute([':id' => $editId]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existing === false) {
                header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
                exit();
            }

            $saveDir      = __DIR__ . '/../images/news/';
            $newImageName = $existing['image_name'];

            if ($hasNewImage && $imageExt !== '') {
                // 新画像アップロード
                $newImageName = 'news' . $editId . '.' . $imageExt;
                if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);
                if (move_uploaded_file($image['tmp_name'], $saveDir . $newImageName)) {
                    // 拡張子が変わった場合は旧ファイルを削除
                    if ($existing['image_name'] !== null && $existing['image_name'] !== $newImageName) {
                        $oldPath = $saveDir . $existing['image_name'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                } else {
                    error_log('news画像保存失敗: images/news/' . $newImageName);
                    $newImageName = $existing['image_name'];
                }
            } elseif ($deleteImage && $existing['image_name'] !== null) {
                // 画像削除チェックボックスが有効
                $oldPath = $saveDir . $existing['image_name'];
                if (file_exists($oldPath)) unlink($oldPath);
                $newImageName = null;
            }

            $stmt = $dbh->prepare(
                'UPDATE news SET title=:title, category=:category, body=:body, image_name=:image_name
                 WHERE id=:id'
            );
            $stmt->execute([
                ':title'      => $title,
                ':category'   => $category,
                ':body'       => $body,
                ':image_name' => $newImageName,
                ':id'         => $editId,
            ]);

            header('Location: ' . $_ENV['APP_URL'] . '/admin/news.php');
            exit();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お知らせ編集 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <style>
        .errorText { color: #c0392b; font-size: 0.9em; margin: 0.25rem 0; }
        .formGroup { margin-bottom: 1.2rem; }
        .formGroup label { display: block; font-weight: bold; margin-bottom: 0.25rem; }
    </style>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <h1>お知らせ編集</h1>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="edit_id" value="<?= h($editId) ?>">
                <input type="hidden" name="current_image_name" value="<?= h($news['image_name'] ?? '') ?>">

                <div class="formGroup">
                    <label for="title">記事タイトル</label>
                    <?php if (isset($errors['title'])): ?>
                        <p class="errorText"><?= h($errors['title']) ?></p>
                    <?php endif; ?>
                    <input type="text" id="title" name="title" value="<?= h($news['title']) ?>">
                </div>

                <div class="formGroup">
                    <label for="category">カテゴリ</label>
                    <?php if (isset($errors['category'])): ?>
                        <p class="errorText"><?= h($errors['category']) ?></p>
                    <?php endif; ?>
                    <select id="category" name="category">
                        <?php foreach ($newsCategories as $cat): ?>
                            <option value="<?= h($cat) ?>" <?= $news['category'] === $cat ? 'selected' : '' ?>>
                                <?= h($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formGroup">
                    <label>アイキャッチ画像</label>
                    <?php if (isset($errors['image'])): ?>
                        <p class="errorText"><?= h($errors['image']) ?></p>
                    <?php endif; ?>
                    <?php if ($news['image_name'] !== null): ?>
                        <img src="<?= h($_ENV['APP_URL']) ?>/images/news/<?= h($news['image_name']) ?>"
                             alt="現在のアイキャッチ" class="contentImage" style="margin-bottom: 0.5rem;">
                        <label>
                            <input type="checkbox" name="delete_image" value="1">
                            この画像を削除する
                        </label>
                        <br>
                    <?php endif; ?>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <small>新しい画像を選択すると上書きされます（jpg / png / webp、2MB以内）</small>
                </div>

                <div class="formGroup">
                    <label for="body">本文</label>
                    <?php if (isset($errors['body'])): ?>
                        <p class="errorText"><?= h($errors['body']) ?></p>
                    <?php endif; ?>
                    <textarea id="body" name="body" class="descriptionArea" rows="35" cols="70"><?= h($news['body']) ?></textarea>
                </div>

                <button type="submit">更新する</button>
                <a href="<?= h($_ENV['APP_URL']) ?>/admin/news.php">キャンセル</a>
            </form>
        </div>
    </main>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>
