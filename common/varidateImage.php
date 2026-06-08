<?php
function validateImage($file, $maxSize)
{
    if ($file['error'] !== 0) {
        return '画像アップロードに失敗しました。';
    }

    $tmpName = $file['tmp_name'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (
        !in_array($mime, $allowed) ||
        !in_array($ext, $allowedExt)
    ) {
        return 'jpg,png,webpファイルのみアップロード可能です。';
    }

    if ($file['size'] > $maxSize) {

        $maxMb = $maxSize / 1000000;

        return $maxMb . 'MB以上の画像はアップロードできません。';
    }

    return '';
}
