<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$banners = [];
try {
    $dbh = dbConnect();
    $stmt = $dbh->query(
        'SELECT id, title, image_path, display_order, is_active, start_at, end_at
         FROM banners
         ORDER BY display_order ASC, id ASC'
    );
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ' . $_ENV['APP_URL'] . '/error.php');
    exit();
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>バナー管理 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <style>
        .bannerThumb { width: 120px; height: auto; display: block; }
        .bannerTable { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .bannerTable th, .bannerTable td { border: 1px solid #CDD3DB; padding: 0.5rem; text-align: left; }
        .bannerTable th { background: #eef2f7; }
        .statusBadge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; }
        .statusActive { background: #d4edda; color: #155724; }
        .statusInactive { background: #f8d7da; color: #721c24; }
    </style>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <div class="moreTextFlex">
                <h1>バナー管理</h1>
                <a href="<?= h($_ENV['APP_URL']) ?>/admin/carouseladd.php">新規追加</a>
            </div>
            <table class="bannerTable">
                <thead>
                    <tr>
                        <th>画像</th>
                        <th>タイトル</th>
                        <th>表示順</th>
                        <th>公開状態</th>
                        <th>公開期間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($banners)): ?>
                        <tr>
                            <td colspan="6">バナーが登録されていません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr class="bannerRow" data-id="<?= h($banner['id']) ?>">
                                <td>
                                    <img src="<?= h($_ENV['APP_URL']) ?>/images/banners/<?= h($banner['image_path']) ?>"
                                         alt="" class="bannerThumb">
                                </td>
                                <td><?= h($banner['title']) ?></td>
                                <td><?= h($banner['display_order']) ?></td>
                                <td>
                                    <span class="statusBadge <?= $banner['is_active'] ? 'statusActive' : 'statusInactive' ?>">
                                        <?= $banner['is_active'] ? '公開' : '非公開' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $banner['start_at'] ? h(substr($banner['start_at'], 0, 16)) : '─' ?>
                                    〜
                                    <?= $banner['end_at'] ? h(substr($banner['end_at'], 0, 16)) : '─' ?>
                                </td>
                                <td>
                                    <a href="<?= h($_ENV['APP_URL']) ?>/admin/carouseladd.php?id=<?= h($banner['id']) ?>">編集</a>
                                    <button class="deleteBannerButton"
                                            data-id="<?= h($banner['id']) ?>"
                                            data-csrf="<?= h($csrfToken) ?>">削除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><a href="<?= h($_ENV['APP_URL']) ?>/admin/index.php">← 管理トップへ戻る</a></p>
        </div>
    </main>
    <script src="<?= $_ENV['APP_URL'] ?>/common/script.js"></script>
    <script>
        document.querySelectorAll('.deleteBannerButton').forEach(function (button) {
            button.addEventListener('click', async function () {
                if (!confirm('このバナーを削除しますか？')) return;
                const id = this.dataset.id;
                const csrf = this.dataset.csrf;
                try {
                    const res = await fetch('<?= h($_ENV['APP_URL']) ?>/api/deleteBanner.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ bannerId: id, csrf_token: csrf })
                    });
                    const json = await res.json();
                    if (json.success) {
                        this.closest('.bannerRow').remove();
                    } else {
                        alert(json.message || '削除に失敗しました。');
                    }
                } catch (e) {
                    alert('通信エラーが発生しました。');
                }
            });
        });
    </script>
</body>

</html>
