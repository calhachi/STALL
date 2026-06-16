<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/varidateImage.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$editId = 0;
$isEdit = false;
$errors = [];
$banner = [
    'title'         => '',
    'link_url'      => '',
    'display_order' => 0,
    'start_at'      => '',
    'end_at'        => '',
    'is_active'     => 1,
    'image_path'    => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // 編集モード: DBから既存データを取得してフォームへプリフィル
    $editId = (int)$_GET['id'];
    $isEdit = true;
    try {
        $dbh = dbConnect();
        $stmt = $dbh->prepare('SELECT * FROM banners WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            header('Location: ' . $_ENV['APP_URL'] . '/admin/carousel.php');
            exit();
        }
        $banner = [
            'title'         => $row['title'],
            'link_url'      => $row['link_url'],
            'display_order' => $row['display_order'],
            'start_at'      => $row['start_at'] ? str_replace(' ', 'T', substr($row['start_at'], 0, 16)) : '',
            'end_at'        => $row['end_at']   ? str_replace(' ', 'T', substr($row['end_at'],   0, 16)) : '',
            'is_active'     => $row['is_active'],
            'image_path'    => $row['image_path'],
        ];
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

    $editId = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $isEdit = $editId > 0;

    $title        = trim($_POST['title'] ?? '');
    $linkUrl      = trim($_POST['link_url'] ?? '');
    $displayOrder = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $startAt      = trim($_POST['start_at'] ?? '') ?: null;
    $endAt        = trim($_POST['end_at'] ?? '')   ?: null;
    $isActive     = isset($_POST['is_active']) ? 1 : 0;
    $image        = $_FILES['image'] ?? [];
    $imageExt     = '';

    // バリデーション失敗時にフォームを再描画するための値を保持
    $banner = [
        'title'         => $title,
        'link_url'      => $linkUrl,
        'display_order' => $displayOrder,
        'start_at'      => $_POST['start_at'] ?? '',
        'end_at'        => $_POST['end_at'] ?? '',
        'is_active'     => $isActive,
        'image_path'    => $_POST['current_image_path'] ?? '',
    ];

    if ($title === '') {
        $errors['title'] = 'タイトルを入力してください。';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'タイトルは255文字以内で入力してください。';
    }

    if ($linkUrl !== '' && filter_var($linkUrl, FILTER_VALIDATE_URL) === false) {
        $errors['link_url'] = '有効なURLを入力してください。';
    }

    if ($startAt !== null && $endAt !== null && $startAt >= $endAt) {
        $errors['end_at'] = '公開終了日時は開始日時より後に設定してください。';
    }

    $hasNewImage = isset($image['error']) && $image['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasNewImage) {
        $imageErr = validateImage($image, 3000000);
        if ($imageErr !== '') {
            $errors['image'] = $imageErr;
        } else {
            $imageExt = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        }
    } elseif (!$isEdit) {
        $errors['image'] = 'バナー画像を選択してください。';
    }

    if (empty($errors)) {
        try {
            $dbh = dbConnect();

            if ($isEdit) {
                $chk = $dbh->prepare('SELECT image_path FROM banners WHERE id = :id');
                $chk->execute([':id' => $editId]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);
                if ($existing === false) {
                    header('Location: ' . $_ENV['APP_URL'] . '/admin/carousel.php');
                    exit();
                }

                $stmt = $dbh->prepare(
                    'UPDATE banners
                     SET title=:title, link_url=:link_url, display_order=:display_order,
                         start_at=:start_at, end_at=:end_at, is_active=:is_active
                     WHERE id=:id'
                );
                $stmt->execute([
                    ':title'         => $title,
                    ':link_url'      => $linkUrl,
                    ':display_order' => $displayOrder,
                    ':start_at'      => $startAt,
                    ':end_at'        => $endAt,
                    ':is_active'     => $isActive,
                    ':id'            => $editId,
                ]);

                if ($hasNewImage && $imageExt !== '') {
                    $newImageName = 'banner' . $editId . '.' . $imageExt;
                    $saveDir = BANNER_DIR;
                    if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);
                    if (move_uploaded_file($image['tmp_name'], $saveDir . $newImageName)) {
                        // 拡張子が変わった場合は旧ファイルを削除
                        if ($existing['image_path'] !== '' && $existing['image_path'] !== $newImageName) {
                            $oldPath = $saveDir . $existing['image_path'];
                            if (file_exists($oldPath)) unlink($oldPath);
                        }
                        $dbh->prepare('UPDATE banners SET image_path=:p WHERE id=:id')
                            ->execute([':p' => $newImageName, ':id' => $editId]);
                    } else {
                        error_log('バナー画像保存失敗: images/banners/' . $newImageName);
                    }
                }
            } else {
                // 新規: INSERT→lastInsertId→ファイル保存→UPDATE（newsadd.phpと同パターン）
                $stmt = $dbh->prepare(
                    'INSERT INTO banners (title, link_url, display_order, start_at, end_at, is_active, image_path)
                     VALUES (:title, :link_url, :display_order, :start_at, :end_at, :is_active, :image_path)'
                );
                $stmt->execute([
                    ':title'         => $title,
                    ':link_url'      => $linkUrl,
                    ':display_order' => $displayOrder,
                    ':start_at'      => $startAt,
                    ':end_at'        => $endAt,
                    ':is_active'     => $isActive,
                    ':image_path'    => '',
                ]);
                $newId = $dbh->lastInsertId();

                if ($imageExt !== '') {
                    $imageName = 'banner' . $newId . '.' . $imageExt;
                    $saveDir = BANNER_DIR;
                    if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);
                    if (move_uploaded_file($image['tmp_name'], $saveDir . $imageName)) {
                        $dbh->prepare('UPDATE banners SET image_path=:p WHERE id=:id')
                            ->execute([':p' => $imageName, ':id' => $newId]);
                    } else {
                        error_log('バナー画像保存失敗: images/banners/' . $imageName);
                    }
                }
            }

            header('Location: ' . $_ENV['APP_URL'] . '/admin/carousel.php');
            exit();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            header('Location: ' . $_ENV['APP_URL'] . '/error.php');
            exit();
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = $isEdit ? 'バナー編集' : 'バナー追加';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <style>
        .currentBannerPreview { max-width: 300px; height: auto; display: block; margin-bottom: 0.5rem; }
        .errorText { color: #c0392b; font-size: 0.9em; margin: 0.25rem 0; }
        .formGroup { margin-bottom: 1.2rem; }
        .formGroup label { display: block; font-weight: bold; margin-bottom: 0.25rem; }
    </style>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <h1><?= h($pageTitle) ?></h1>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="edit_id" value="<?= h($editId) ?>">
                <input type="hidden" name="current_image_path" value="<?= h($banner['image_path']) ?>">

                <div class="formGroup">
                    <label for="title">タイトル <span>*</span></label>
                    <?php if (isset($errors['title'])): ?>
                        <p class="errorText"><?= h($errors['title']) ?></p>
                    <?php endif; ?>
                    <input type="text" id="title" name="title" value="<?= h($banner['title']) ?>" maxlength="255">
                </div>

                <div class="formGroup">
                    <label for="image">バナー画像<?= $isEdit ? '（変更する場合のみ選択）' : ' *' ?></label>
                    <?php if (isset($errors['image'])): ?>
                        <p class="errorText"><?= h($errors['image']) ?></p>
                    <?php endif; ?>
                    <?php if ($isEdit && $banner['image_path'] !== ''): ?>
                        <img src="<?= h($_ENV['APP_URL']) ?>/images/banners/<?= h($banner['image_path']) ?>"
                             alt="現在のバナー画像" class="currentBannerPreview">
                        <p>（現在の画像）</p>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <small>jpg / png / webp、3MB以内</small>
                </div>

                <div class="formGroup">
                    <label for="link_url">リンク先URL（任意）</label>
                    <?php if (isset($errors['link_url'])): ?>
                        <p class="errorText"><?= h($errors['link_url']) ?></p>
                    <?php endif; ?>
                    <input type="url" id="link_url" name="link_url"
                           value="<?= h($banner['link_url']) ?>" maxlength="2083" style="width: 100%;">
                </div>

                <div class="formGroup">
                    <label for="display_order">表示順</label>
                    <input type="number" id="display_order" name="display_order"
                           value="<?= h($banner['display_order']) ?>" min="0" step="1">
                    <small>数値が小さいほど先に表示されます</small>
                </div>

                <div class="formGroup">
                    <label for="start_at">公開開始日時（任意）</label>
                    <input type="datetime-local" id="start_at" name="start_at"
                           value="<?= h($banner['start_at']) ?>">
                </div>

                <div class="formGroup">
                    <label for="end_at">公開終了日時（任意）</label>
                    <?php if (isset($errors['end_at'])): ?>
                        <p class="errorText"><?= h($errors['end_at']) ?></p>
                    <?php endif; ?>
                    <input type="datetime-local" id="end_at" name="end_at"
                           value="<?= h($banner['end_at']) ?>">
                </div>

                <div class="formGroup">
                    <label>
                        <input type="checkbox" name="is_active" value="1"
                               <?= $banner['is_active'] ? 'checked' : '' ?>>
                        公開する
                    </label>
                </div>

                <button type="submit"><?= $isEdit ? '更新する' : '登録する' ?></button>
                <a href="<?= h($_ENV['APP_URL']) ?>/admin/carousel.php">キャンセル</a>
            </form>
        </div>
    </main>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
</body>

</html>
