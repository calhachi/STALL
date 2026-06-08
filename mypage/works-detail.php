<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';

requireLogin();

$worksTitle = trim($_POST['worksTitle'] ?? '');
$priceType = $_POST['priceType'] ?? '';
$price = $_POST['price'] ?? '';
$categoryId = $_POST['categoryId'] ?? '';
$subCategoryId = $_POST['subCategoryId'] ?? '';
$tagIds = array_values(array_filter($_POST['tagIds'] ?? []));
$minPlayers = $_POST['minPlayers'] ?? '';
$maxPlayers = $_POST['maxPlayers'] ?? '';
$textHours = $_POST['textHours'] ?? '';
$voiceHours = $_POST['voiceHours'] ?? '';
$description = trim($_POST['description'] ?? '');
$work = $_FILES['work'] ?? '';
$worksName = '';
$worksOriginalName = '';
$worksImage = $_FILES['worksImage'] ?? [];
$worksImagesNames = [];
$thumbnail = $_FILES['thumbnail'] ?? [];
$thumbnailName = '';
$workId = $_GET['id'];

$categories = '';
$tags = '';
$subCategories = '';

$worksImages = [];
$selectedTagsIds = '';
$deleteImagePaths = [];
$newWorksImages = [];

$worksTitleError = '';
$priceError = '';
$categoryError = '';
$subCategoryError = '';
$playersError = '';
$hoursError = '';
$descriptionError = '';
$worksError = '';
$worksImagesError = '';
$thumbnailError = '';
$errored = '';

$worksIdError = '';


if (!isset($workId) || !is_numeric($workId)) {
    $worksIdError = '不正なアクセスです。';
} else {

    $dbh = null;

    try {

        $dbh = dbConnect();

        $categoryStmt = $dbh->query('SELECT id, name 
                                    FROM categories');
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

        $subCategoryStmt = $dbh->query('SELECT id, category_id, name
                                        FROM subcategories');
        $subCategories = $subCategoryStmt->fetchAll(PDO::FETCH_ASSOC);

        $tagsStmt = $dbh->query(
            'SELECT id, category_id, tag_name
            FROM tags'
        );
        $tags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);

        $oldWorksStmt = $dbh->prepare(
            'SELECT file_name, original_name, thumbnail_name
            FROM works
            WHERE id = :id'
        );
        $oldWorksStmt->execute([
            'id' => $workId
        ]);
        $oldWorks = $oldWorksStmt->fetch(PDO::FETCH_ASSOC);

        $categoryMap = array_column($categories, 'name', 'id');

        $subCategoryMap = [];
        foreach ($subCategories as $subCategory) {

            $subCategoryMap[$subCategory['id']] = [
                'name' => $subCategory['name'],
                'categoryId' => $subCategory['category_id']
            ];
        }

        $selectedTags = [];
        foreach ($tags as $tag) {
            if (in_array($tag['id'], $tagIds)) {
                $selectedTags[] = [
                    'id' => $tag['id'],
                    'tagName' => $tag['tag_name']
                ];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (trim($worksTitle) == '') {
                $worksTitleError = '作品タイトルが入力されていません。';
            } elseif (mb_strlen(trim($worksTitle)) > 255) {
                $worksTitleError = '作品タイトルは255文字以内で入力してください。';
            }

            if ($priceType === 'paid' && $price === '') {
                $priceError = '有料作品にする場合は価格を入力してください。';
            }

            if ($priceType === 'free') {
                $price = null;
            }

            if ($categoryId == '') {
                $categoryError = 'カテゴリを選択してください。';
            } elseif (!isset($categoryMap[$categoryId])) {
                $categoryError = '存在しないカテゴリです。';
            }

            if ($subCategoryId == '') {
                $subCategoryError = 'サブカテゴリを選択してください。';
            } elseif (!isset($subCategoryMap[$subCategoryId])) {
                $subCategoryError = '存在しないサブカテゴリです。';
            } elseif ($subCategoryMap[$subCategoryId]['categoryId'] != $categoryId) {
                $subCategoryError = 'カテゴリとサブカテゴリの組み合わせが不正です。';
            }

            if ($categoryId === 1) {
                if (
                    !ctype_digit($minPlayers) ||
                    !ctype_digit($maxPlayers) ||
                    $minPlayers < 1 ||
                    $minPlayers > 8 ||
                    $maxPlayers < 1 ||
                    $maxPlayers > 8
                ) {
                    $playersError = 'プレイ人数が不正です。';
                } elseif ($minPlayers > $maxPlayers) {
                    $playersError = '最大人数は最小人数以上にしてください。';
                }

                if ($textHours === '' && $voiceHours === '') {

                    $hoursError = 'いずれかのプレイ時間を設定してください。';
                } elseif (
                    ($textHours !== '' && (!ctype_digit($textHours) || $textHours < 0 || $textHours > 10)) ||
                    ($voiceHours !== '' && (!ctype_digit($voiceHours) || $voiceHours < 0 || $voiceHours > 10))
                ) {

                    $hoursError = 'プレイ時間が不正です。';
                }
            }

            if (trim($description) === '') {
                $descriptionError = '作品概要を入力してください。';
            } elseif (mb_strlen($description) > 5000) {
                $descriptionError = '作品概要は5000文字以内で入力してください。';
            }


            // 作品ファイルバリデと名付け
            if ($work['error'] == 0) {

                $tmpName = $work['tmp_name'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                $allowed = ['application/pdf', 'text/plain'];
                $allowedExt = ['pdf', 'txt'];

                $ext = strtolower(pathinfo($work['name'], PATHINFO_EXTENSION));

                $worksName = uniqid() . '.' . $ext;

                $originalBaseName = pathinfo($work['name'], PATHINFO_FILENAME);
                $worksOriginalName = $originalBaseName . '.' . $ext;

                if (
                    !in_array($mime, $allowed) ||
                    !in_array($ext, $allowedExt)
                ) {

                    $worksError = '.pdf,.txtファイルのみアップロード可能です。';
                } elseif ($work['size'] > 20000000) {

                    $worksError = '20MB以上のファイルはアップロードできません。';
                } else {

                    if (!move_uploaded_file(
                        $work['tmp_name'],
                        TEMP_DIR . $worksName
                    )) {
                        $worksError = '作品ファイル保存に失敗しました。';
                    }
                }
            } else {
                $worksName = $oldWorks['file_name'];
                $worksOriginalName = $oldWorks['original_name'];
            }


            // トレーラー４枚バリデ
            if (!empty($_FILES['worksImage']['name'])) {

                foreach ($_FILES['worksImage']['error'] as $displayOrder => $error) {

                    // 未選択
                    if ($error === 4) {
                        continue;
                    }

                    $file = [
                        'name' => $_FILES['worksImage']['name'][$displayOrder],
                        'type' => $_FILES['worksImage']['type'][$displayOrder],
                        'tmp_name' => $_FILES['worksImage']['tmp_name'][$displayOrder],
                        'error' => $_FILES['worksImage']['error'][$displayOrder],
                        'size' => $_FILES['worksImage']['size'][$displayOrder],
                    ];

                    // バリデーション
                    $worksImagesError = validateImage($file, 5000000);

                    if ($worksImagesError !== '') {
                        break;
                    }

                    // 拡張子
                    $ext = strtolower(
                        pathinfo($file['name'], PATHINFO_EXTENSION)
                    );

                    // 保存名
                    $newImageName = uniqid() . '.' . $ext;

                    // 保存
                    if (!move_uploaded_file(
                        $file['tmp_name'],
                        TEMP_DIR . $newImageName
                    )) {

                        $worksImagesError = '画像保存に失敗しました。';
                        break;
                    }

                    // 後でUPDATEする用
                    $worksImagesNames[] = [
                        'display_order' => $displayOrder,
                        'image_name' => $newImageName,
                    ];
                }
            }

            // サムネバリデ
            if ($thumbnail['error'] == 0) {


                $thumbnailError = validateImage($thumbnail, 1000000);

                if ($thumbnailError === '') {

                    $ext = strtolower(
                        pathinfo($thumbnail['name'], PATHINFO_EXTENSION)
                    );

                    $thumbnailName = uniqid() . '.' . $ext;

                    if (!move_uploaded_file(
                        $thumbnail['tmp_name'],
                        TEMP_DIR . $thumbnailName
                    )) {

                        $thumbnailError = '画像保存に失敗しました。';
                    }
                }
            } else {
                $thumbnailName = $oldWorks['thumbnail_name'];
            }

            if (
                $worksTitleError === '' &&
                $priceError === '' &&
                $categoryError === '' &&
                $subCategoryError === '' &&
                $descriptionError === '' &&
                $playersError === '' &&
                $hoursError === '' &&
                $worksError === '' &&
                $worksImagesError === '' &&
                $thumbnailError === ''
            ) {


                $dbh->beginTransaction();

                $worksUpdateStmt = $dbh->prepare(
                    'UPDATE works SET
                            title=:title,
                            price=:price,
                            category_id=:category_id,
                            subcategory_id=:subcategory_id,
                            min_players=:min_players,
                            max_players=:max_players,
                            text_hours=:text_hours,
                            voice_hours=:voice_hours,
                            description=:description,
                            file_name=:file_name,
                            original_name=:original_name,
                            thumbnail_name=:thumbnail_name
                            WHERE id=:id'
                );

                $worksUpdateStmt->execute([
                    'id' => $workId,
                    'title' => $worksTitle,
                    'price' => $price,
                    'category_id' => $categoryId,
                    'subcategory_id' => $subCategoryId,
                    'min_players' => $minPlayers,
                    'max_players' => $maxPlayers,
                    'text_hours' => $textHours === ''
                        ? null
                        : $textHours,
                    'voice_hours' => $voiceHours === ''
                        ? null
                        : $voiceHours,
                    'description' => $description,
                    'file_name' => $worksName,
                    'original_name' => $worksOriginalName,
                    'thumbnail_name' => $thumbnailName
                ]);


                if (!empty($worksImagesNames)) {

                    // 古い画像取得
                    $worksImageSelectStmt = $dbh->prepare(
                        'SELECT image_name, display_order
                        FROM works_images
                        WHERE work_id = :worksId'
                    );

                    $worksImageSelectStmt->execute([
                        'worksId' => $workId
                    ]);

                    $oldWorksImages = $worksImageSelectStmt->fetchAll(PDO::FETCH_ASSOC);

                    // UPDATE
                    $worksImageUpdateStmt = $dbh->prepare(
                        'UPDATE works_images
                        SET image_name = :imageName
                        WHERE work_id = :worksId
                        AND display_order = :displayOrder'
                    );

                    foreach ($worksImagesNames as $image) {

                        // DB更新
                        $worksImageUpdateStmt->execute([
                            'worksId' => $workId,
                            'imageName' => $image['image_name'],
                            'displayOrder' => $image['display_order'],
                        ]);

                        // 古い画像削除用配列作る
                        foreach ($oldWorksImages as $oldImage) {

                            if (
                                $oldImage['display_order']
                                == $image['display_order']
                            ) {

                                $deleteImagePaths[] =
                                    WORKS_IMAGES_DIR . $oldImage['image_name'];

                                break;
                            }
                        }
                    }
                }

                $worksTagDeleteStmt = $dbh->prepare(
                    'DELETE FROM works_tag
                    WHERE work_id = :id'
                );
                $worksTagDeleteStmt->execute([
                    'work_id' => $workId
                ]);


                $worksTagInsertStmt = $dbh->prepare(
                    'INSERT INTO works_tag (
                                 work_id,
                                 tag_id
                                )
                    VALUES (
                                :work_id,
                                :tag_id
                                )'
                );

                foreach ($tagIds as $index => $tagsId) {
                    $worksTagInsertStmt->execute([
                        'work_id' => $workId,
                        'tag_id' => $tagsId
                    ]);
                }

                $isSuccess = 1;
                $dbh->commit();

                if ($worksName != $oldWorks['file_name']) {
                    if (!rename(
                        TEMP_DIR . $worksName,
                        WORKS_DIR . $worksName
                    )) {
                        error_log(
                            '移動失敗: '
                                . TEMP_DIR . $worksName
                                . ' -> '
                                . WORKS_DIR . $worksName
                        );
                    } else {
                        $path = WORKS_DIR . $oldWorks['file_name'];
                        if (file_exists($path)) {

                            if (!unlink($path)) {
                                error_log('削除失敗: ' . $path);
                            }
                        }
                    }
                }

                if ($thumbnailName != $oldWorks['thumbnail_name']) {
                    if (!rename(
                        TEMP_DIR . $thumbnailName,
                        THUMBNAIL_DIR . $thumbnailName
                    )) {
                        error_log(
                            '移動失敗: '
                                . TEMP_DIR . $thumbnailName
                                . ' -> '
                                . THUMBNAIL_DIR . $thumbnailName
                        );
                    } else {
                        $path = THUMBNAIL_DIR . $oldWorks['thumbnail_name'];
                        if (file_exists($path)) {

                            if (!unlink($path)) {
                                error_log('削除失敗: ' . $path);
                            }
                        }
                    }
                }

                if (!empty($worksImagesNames)) {

                    $allRenamed = true;

                    foreach ($worksImagesNames as $image) {

                        if (!rename(
                            TEMP_DIR . $image['image_name'],
                            WORKS_IMAGES_DIR . $image['image_name']
                        )) {

                            error_log('移動失敗: ' . $image['image_name']);
                            $allRenamed = false;
                        }
                    }

                    if ($allRenamed) {

                        foreach ($deleteImagePaths as $path) {

                            if (file_exists($path)) {

                                if (!unlink($path)) {
                                    error_log('削除失敗: ' . $path);
                                }
                            }
                        }
                    }
                }
            }

            // 更新後データ取得
            $newWorksStmt = $dbh->prepare(
                'SELECT *
                    FROM works
                    WHERE id = :id'
            );
            $newWorksStmt->execute([
                'id' => $workId
            ]);
            $newWork = $newWorksStmt->fetch(PDO::FETCH_ASSOC);

            if (!$newWork) {
                $worksIdError = '作品が存在しません';
            }
            $newWorksImagesStmt = $dbh->prepare(
                'SELECT image_name,display_order
                    FROM works_images
                    WHERE work_id = :id
                    ORDER BY display_order'
            );
            $newWorksImagesStmt->execute([
                'id' => $workId
            ]);
            $newWorksImages = $newWorksImagesStmt->fetchAll(PDO::FETCH_ASSOC);

            $newWorksTagsStmt = $dbh->prepare(
                'SELECT tag_id
                    FROM works_tag
                    WHERE work_id = :id'
            );
            $newWorksTagsStmt->execute([
                'id' => $workId
            ]);
            $selectedTagsIds = $newWorksTagsStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        error_log($e->getMessage());
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作品詳細 | STALL</title>
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
        <?php if ($worksIdError != ''): ?>
            <p><?= $worksIdError ?></p>
        <?php else: ?>
            <h1>作品詳細</h1>
            <form action="" method="post" enctype="multipart/form-data">
                <h3>タイトル</h3>
                <?php if ($worksTitleError != ''): ?>
                    <p><?= $worksTitleError ?></p>
                <?php endif; ?>
                <input type="text" name="worksTitle" value="<?= h($newWork['title']) ?>">
                <br>

                <h3>価格</h3>
                <?php if ($priceError != ''): ?>
                    <p><?= $priceError ?></p>
                <?php endif; ?>
                <label>
                    <input type="radio" name="priceType" value="free"
                        <?= $newWork['price'] === null  ? 'checked' : '' ?>>
                    無料
                </label>
                <br>
                <label>
                    <input type="radio" name="priceType" value="paid" <?= $newWork['price'] !== null ? 'checked' : '' ?>>
                    <input type="number" name="price" min="1" value="<?= h($newWork['price']) ?>">
                    円
                </label>
                <br>

                <h3>作品カテゴリ</h3>
                <?php if ($categoryError != ''): ?>
                    <p><?= $categoryError ?></p>
                <?php endif; ?>

                <select name="categoryId" id="categorySelect">
                    <option value="">-----</option>

                    <?php foreach ($categories as $category): ?>
                        <option value="<?= h($category['id']) ?>" <?= $newWork['category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <h3>サブカテゴリ</h3>
                <?php if ($subCategoryError != ''): ?>
                    <p><?= $subCategoryError ?></p>
                <?php endif; ?>

                <select name="subCategoryId" id="subCategorySelect">
                    <option value="">-----</option>
                </select>

                <h3>登録タグ</h3>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <select name="tagIds[]" class="tagSelect">
                        <option value="">-----</option>

                        <?php foreach ($tags as $tag): ?>
                            <option value="<?= h($tag['id']) ?>" <?= ($tagIds[$i] ?? '') == $tag['id'] ? 'selected' : '' ?>>
                                <?= h($tag['tag_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                <?php endfor; ?>

                <div id="trpgOnlyFields">
                    <h3>プレイ人数</h3>
                    <?php if ($playersError != ''): ?>
                        <p><?= $playersError ?></p>
                    <?php endif; ?>
                    <select name="minPlayers">
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option value="<?= $i ?>" <?= $newWork['min_players'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                        <option value="8" <?= $newWork['min_players']  == 8 ? 'selected' : '' ?>>8人以上</option>
                    </select>
                    ~
                    <select name="maxPlayers">
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option value="<?= $i ?>" <?= $newWork['max_players'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                        <option value="8" <?= $newWork['max_players'] == 8 ? 'selected' : '' ?>>8人以上</option>
                    </select>
                    人
                    <br>
                    <h3>推定プレイ時間</h3>
                    <?php if ($hoursError != ''): ?>
                        <p><?= $hoursError ?></p>
                    <?php endif; ?>
                    テキストセッション<select name="textHours">
                        <option value="">なし</option>
                        <option value="0" <?= $newWork['text_hours'] == 0 ? 'selected' : '' ?>>1時間未満</option>
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i  ?>" <?= $newWork['text_hours'] == $i ? 'selected' : '' ?>><?= $i ?>時間</option>
                        <?php endfor; ?>
                        <option value="10" <?= $newWork['text_hours'] == 10 ? 'selected' : '' ?>>10時間以上</option>
                    </select>
                    <br>
                    ボイスセッション<select name="voiceHours">
                        <option value="">なし</option>
                        <option value="0" <?= $newWork['voice_hours'] == 0 ? 'selected' : '' ?>>1時間未満</option>
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i  ?>" <?= $newWork['voice_hours'] == $i ? 'selected' : '' ?>><?= $i ?>時間</option>
                        <?php endfor; ?>
                        <option value="10" <?= $newWork['voice_hours'] == 10 ? 'selected' : '' ?>>10時間以上</option>
                    </select>
                </div>

                <h3>作品概要</h3>
                <?php if ($descriptionError != ''): ?>
                    <p><?= $descriptionError ?></p>
                <?php endif; ?>
                <textarea name="description"><?= h($newWork['description']) ?></textarea>

                <h3>作品ファイル差し替え</h3>
                <?php if ($worksError != ''): ?>
                    <p><?= $worksError ?></p>
                <?php endif; ?>
                <input type="file" name="work" id="work" accept=".pdf,.txt">

                <h3>トレーラー画像・サンプル画像（四枚まで）</h3>

                <?php if ($worksImagesError != ''): ?>
                    <p><?= h($worksImagesError) ?></p>
                <?php endif; ?>

                <p>現在選択中の画像</p>
                <p>※選択した画像だけ差し替えされます</p>

                <?php foreach ($newWorksImages as $image): ?>

                    <div>
                        <p><?= $image['display_order'] ?>枚目</p>
                        <div class="imageUploader">
                            <img class="currentImage"
                                src="<?= $_ENV['APP_URL'] ?>/userdata/works-image/<?= h($image['image_name']) ?>"
                                width="200">

                            <div class="imagesPreview"></div>


                            <input
                                type="file" class="imagesInput"
                                name="worksImage[<?= $image['display_order'] ?>]"
                                accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                <?php endforeach; ?>
                <h3>サムネイル追加</h3>
                <p>ファイルサイズ1MB以内・対応拡張子：jpg,png,webp<br>
                    横長（4:3）の画像を推奨します。</p>
                <p>（未指定の場合はトレーラー・サンプル画像の1枚目が使用されます）</p>
                <?php if ($thumbnailError != ''): ?>
                    <p><?= $thumbnailError ?></p>
                <?php endif; ?>
                <p>現在選択中の画像</p>
                <p>※新しい画像を選択すると上書きされます</p>
                <img
                    src="<?= $_ENV['APP_URL'] ?>/userdata/thumbnail/<?= h($newWork['thumbnail_name']) ?> "
                    width="200">
                <div class="imageUploader">
                    <div class="imagesPreview"></div>
                    <input type="file" name="thumbnail" class="imagesInput" accept=".jpg,.jpeg,.png,webp">
                </div>
                <input type="submit" value="変更する">
            </form>

        <?php endif; ?>
    </main>
    <script>
        const subCategories = <?= json_encode($subCategories) ?>;
        const tags = <?= json_encode($tags) ?>;

        const selectedSubCategoryId =
            <?= json_encode($newWork['subcategory_id'] ?? '') ?>;
        const selectedTagsId =
            <?= json_encode($selectedTagsIds) ?>;
    </script>
    <script src="../common/script.js"></script>
</body>

</html>