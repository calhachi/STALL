<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';
require_once __DIR__ . '/../common/requireLogin.php';
require_once __DIR__ . '/../common/varidateImage.php';

requireLogin();

try {
    $dbh = dbConnect();

    $categoryStmt = $dbh->query('SELECT id, name 
                    FROM categories');
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    $subCategoryStmt = $dbh->query('SELECT id, category_id, name
    FROM subcategories');
    $subCategories = $subCategoryStmt->fetchAll(PDO::FETCH_ASSOC);

    $tagsStmt = $dbh->query('SELECT id, category_id,tag_name
    FROM tags');
    $tags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

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
    } elseif (mb_strlen(trim($worksTitle)) >= 255) {
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

    if ((int)$categoryId === 1) {
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
    if ($work['error'] == 4) {

        $worksError = '作品ファイルをアップロードしてください。';
    } elseif ($work['error'] == 0) {

        $tmpName = $work['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMime = [
            'application/pdf',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'multipart/x-zip',
        ];
        $allowedExt = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip'];

        $ext = strtolower(pathinfo($work['name'], PATHINFO_EXTENSION));

        $worksName = uniqid() . '.' . $ext;

        $originalBaseName = pathinfo($work['name'], PATHINFO_FILENAME);
        $worksOriginalName = $originalBaseName . '.' . $ext;

        $isZip = ($ext === 'zip');

        if (
            !in_array($ext, $allowedExt) ||
            (!$isZip && !in_array($mime, $allowedMime))
        ) {
            $worksError = '.pdf,.txt,.jpg,.png,.gif,.zipファイルのみアップロード可能です。';
        } elseif ($work['size'] > 20000000) {
            $worksError = '20MB以上のファイルはアップロードできません。';
        } elseif ($isZip) {
            $zip = new ZipArchive();
            if ($zip->open($work['tmp_name']) !== true) {
                $worksError = 'ZIPファイルが破損しているか、開けません。';
            } else {
                $allowedZipContents = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'pdf'];
                $totalUncompressed = 0;
                for ($zi = 0; $zi < $zip->numFiles; $zi++) {
                    $entry = $zip->statIndex($zi);
                    if (substr($entry['name'], -1) === '/') continue;
                    $entryExt = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                    if (!in_array($entryExt, $allowedZipContents)) {
                        $worksError = 'ZIP内に許可されていないファイル形式が含まれています（許可：jpg, png, gif, webp, txt, pdf）。';
                        break;
                    }
                    $totalUncompressed += $entry['size'];
                    if ($totalUncompressed > 200 * 1024 * 1024) {
                        $worksError = 'ZIP展開後のサイズが200MBを超えています。';
                        break;
                    }
                }
                $zip->close();
            }
        }

        if ($worksError === '') {
            if (!move_uploaded_file(
                $work['tmp_name'],
                __DIR__ . '/../userdata/temp/' . $worksName
            )) {
                $worksError = '作品ファイル保存に失敗しました。';
            }
        }
    } else {

        $worksError = 'ファイルアップロードに失敗しました。';
    }


    // トレーラー４枚バリデ


    if (
        !isset($worksImage['error'][0]) ||
        $worksImage['error'][0] == 4
    ) {

        $worksImagesError = 'トレーラー画像は最低1枚アップロードしてください。';
    } else {

        $fileCount = count($worksImage['name']);

        if ($fileCount > 4) {

            $worksImagesError =
                'トレーラー画像のアップロードは4枚までです。';
        } else {
            for ($i = 0; $i < $fileCount; $i++) {

                $file = [
                    'name' => $worksImage['name'][$i],
                    'type' => $worksImage['type'][$i],
                    'tmp_name' => $worksImage['tmp_name'][$i],
                    'error' => $worksImage['error'][$i],
                    'size' => $worksImage['size'][$i],
                ];

                $worksImagesError = validateImage($file, 5000000);

                if ($worksImagesError !== '') {
                    break;
                }

                $ext = strtolower(
                    pathinfo($file['name'], PATHINFO_EXTENSION)
                );

                $worksImagesName = uniqid() . '.' . $ext;

                if (!move_uploaded_file(
                    $file['tmp_name'],
                    __DIR__ . '/../userdata/temp/' . $worksImagesName
                )) {

                    $worksImagesError = '画像保存に失敗しました。';
                    break;
                }

                $worksImagesNames[] = $worksImagesName;
            }
        }
    }

    // サムネバリデ
    if (
        !isset($thumbnail['error']) ||
        $thumbnail['error'] == 4
    ) {

        if (!empty($worksImagesNames)) {
            // 1枚目の保存済み画像
            $sourceImageName = $worksImagesNames[0];

            $sourcePath =
                __DIR__ . '/../userdata/temp/' . $sourceImageName;

            // 拡張子取得
            $ext = strtolower(
                pathinfo($sourceImageName, PATHINFO_EXTENSION)
            );

            // サムネ用別名
            $thumbnailName = uniqid() . '.' . $ext;

            $thumbnailPath =
                __DIR__ . '/../userdata/temp/' . $thumbnailName;

            // コピー
            if (!copy($sourcePath, $thumbnailPath)) {

                $thumbnailError =
                    'サムネイル画像コピーに失敗しました。';
            }
        }
    } else {

        $thumbnailError = validateImage($thumbnail, 1000000);

        if ($thumbnailError === '') {

            $ext = strtolower(
                pathinfo($thumbnail['name'], PATHINFO_EXTENSION)
            );

            $thumbnailName = uniqid() . '.' . $ext;

            if (!move_uploaded_file(
                $thumbnail['tmp_name'],
                __DIR__ . '/../userdata/temp/' . $thumbnailName
            )) {

                $thumbnailError = '画像保存に失敗しました。';
            }
        }
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


        $_SESSION['postWork'] = [
            'worksTitle' => $worksTitle,
            'priceType' => $priceType,
            'price' => $price,
            'categoryId' => $categoryId,
            'categoryName' => $categoryMap[$categoryId],
            'subCategoryId' => $subCategoryId,
            'subCategoryName' => $subCategoryMap[$subCategoryId]['name'],
            'selectedTags' => $selectedTags,
            'minPlayers' => $minPlayers,
            'maxPlayers' => $maxPlayers,
            'textHours' => $textHours,
            'voiceHours' => $voiceHours,
            'description' => $description,
            'worksName' => $worksName,
            'worksOriginalName' => $worksOriginalName,
            'worksImagesName' => $worksImagesNames,
            'thumbnailName' => $thumbnailName
        ];

        header('Location: ./post-check.php');
        exit();
    } else {
        $errored = 1;
    }
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作品登録 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>

    <main>
        <h1>作品登録</h1>
        <form action="" method="post" enctype="multipart/form-data">

            <p>作品タイトル</p>
            <?php if ($worksTitleError != ''): ?>
                <p><?= $worksTitleError ?></p>
            <?php endif; ?>
            <input type="text" name="worksTitle" value="<?= h($worksTitle ?? '') ?>">
            <br>

            <p>価格</p>
            <?php if ($priceError != ''): ?>
                <p><?= $priceError ?></p>
            <?php endif; ?>
            <label>
                <input type="radio" name="priceType" value="free"
                    <?= (($priceType ?? '') === '' || ($priceType ?? '') === 'free') ? 'checked' : '' ?>>
                無料
            </label>
            <br>
            <label>
                <input type="radio" name="priceType" value="paid" <?= ($priceType ?? '') === 'paid' ? 'checked' : '' ?>>
                <input type="number" name="price" min="1" value="<?= h($price ?? '') ?>">
                円
            </label>
            <br>

            <p>作品カテゴリ</p>
            <?php if ($categoryError != ''): ?>
                <p><?= $categoryError ?></p>
            <?php endif; ?>

            <select name="categoryId" id="categorySelect">
                <option value="">-----</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>" <?= ($_POST['categoryId'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p>サブカテゴリ</p>
            <?php if ($subCategoryError != ''): ?>
                <p><?= $subCategoryError ?></p>
            <?php endif; ?>

            <select name="subCategoryId" id="subCategorySelect">
                <option value="">-----</option>
            </select>

            <p>登録タグ</p>
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
                <p>プレイ人数</p>
                <?php if ($playersError != ''): ?>
                    <p><?= $playersError ?></p>
                <?php endif; ?>
                <select name="minPlayers">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>" <?= ($minPlayers ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                    <option value="8" <?= ($minPlayers ?? '') == 8 ? 'selected' : '' ?>>8人以上</option>
                </select>
                ~
                <select name="maxPlayers">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>" <?= ($maxPlayers ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                    <option value="8" <?= ($maxPlayers ?? '') == 8 ? 'selected' : '' ?>>8人以上</option>
                </select>
                人
                <br>
                <p>推定プレイ時間</p>
                <?php if ($hoursError != ''): ?>
                    <p><?= $hoursError ?></p>
                <?php endif; ?>
                テキストセッション<select name="textHours">
                    <option value="">なし</option>
                    <option value="0" <?= ($textHours ?? '') == 0 ? 'selected' : '' ?>>1時間未満</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i  ?>" <?= ($textHours ?? '') == $i ? 'selected' : '' ?>><?= $i ?>時間</option>
                    <?php endfor; ?>
                    <option value="10" <?= ($textHours ?? '') == 10 ? 'selected' : '' ?>>10時間以上</option>
                </select>
                <br>
                ボイスセッション<select name="voiceHours">
                    <option value="">なし</option>
                    <option value="0" <?= ($voiceHours ?? '') == 0 ? 'selected' : '' ?>>1時間未満</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i  ?>" <?= ($voiceHours ?? '') == $i ? 'selected' : '' ?>><?= $i ?>時間</option>
                    <?php endfor; ?>
                    <option value="10" <?= ($voiceHours ?? '') == 10 ? 'selected' : '' ?>>10時間以上</option>
                </select>
            </div>

            <p>作品概要</p>
            <?php if ($descriptionError != ''): ?>
                <p><?= $descriptionError ?></p>
            <?php endif; ?>
            <textarea name="description"><?= h($_POST['description'] ?? '') ?></textarea>

            <p>作品ファイルアップロード（対応形式：pdf, txt, jpg, png, gif, zip／20MB以内）</p>
            <?php if ($worksError != ''): ?>
                <p><?= $worksError ?></p>
            <?php endif; ?>
            <?php if (!empty($worksName)): ?>
                <p>現在選択中の作品ファイル名</p>
                <p>※新しいファイルを選択すると上書きされます</p>
                <p><?= h($work['name']) ?></p>
            <?php endif; ?>
            <input type="file" name="work" id="work" accept=".pdf,.txt,.jpg,.jpeg,.png,.gif,.zip">

            <p>トレーラー画像・サンプル画像（四枚まで）</p>
            <?php if ($worksImagesError != ''): ?>
                <p><?= $worksImagesError ?></p>
            <?php endif; ?>
            <?php if (!empty($worksImagesName)): ?>
                <p>現在選択中の画像</p>
                <p>※新しい画像を選択すると上書きされます</p>
                <?php foreach ($worksImagesNames as $imageName): ?>
                    <img
                        src="<?= $_ENV['APP_URL'] . '/userdata/temp/' . h($imageName) ?>"
                        width="200">
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="imageUploader">
                <div class="imagesPreview"></div>
                <input type="file" class="imagesInput" name="worksImage[]" multiple accept="jpg,.jpeg,.png,.webp">
            </div>

            <p>サムネイル（任意）</p>
            <p>ファイルサイズ1MB以内・対応拡張子：jpg,png,webp<br>
                横長（4:3）の画像を推奨します。</p>
            <p>（未指定の場合はトレーラー・サンプル画像の1枚目が使用されます）</p>
            <?php if ($thumbnailError != ''): ?>
                <p><?= $thumbnailError ?></p>
            <?php endif; ?>
            <?php if (!empty($thumbnailName)): ?>
                <p>現在選択中の画像</p>
                <p>※新しい画像を選択すると上書きされます</p>
                <img
                    src="<?= $_ENV['APP_URL'] . '/userdata/temp/' . h($thumbnailName) ?>"
                    width="200">
            <?php endif; ?>
            <div class="imageUploader">
                <div class="imagesPreview"></div>
                <input type="file" name="thumbnail" class="imagesInput" accept=".jpg,.jpeg,.png,webp">
            </div>
            <input type="submit" value="確認画面へ">
        </form>


    </main>
    <script>
        const subCategories = <?= json_encode($subCategories) ?>;
        const tags = <?= json_encode($tags) ?>;

        const selectedSubCategoryId =
            <?= json_encode($subCategoryId ?? '') ?>;
        const selectedTagsId =
            <?= json_encode(array_values($tagIds ?? [])) ?>;
    </script>
    <script src="../common/script.js"></script>
</body>

</html>