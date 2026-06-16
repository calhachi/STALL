<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$newsList = [];
try {
    $dbh = dbConnect();
    $stmt = $dbh->query(
        'SELECT id, title, category, image_name, created_at
         FROM news
         ORDER BY created_at DESC'
    );
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>お知らせ管理 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <style>
        .newsAdminThumb { width: 80px; height: 60px; object-fit: cover; display: block; }
        .newsAdminTable { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .newsAdminTable th,
        .newsAdminTable td { border: 1px solid #CDD3DB; padding: 0.5rem; text-align: left; vertical-align: middle; }
        .newsAdminTable th { background: #eef2f7; }
        .noImage { color: #999; font-size: 0.85em; }
    </style>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <div class="moreTextFlex">
                <h1>お知らせ管理</h1>
                <a href="<?= h($_ENV['APP_URL']) ?>/admin/newsadd.php">新規追加</a>
            </div>
            <table class="newsAdminTable">
                <thead>
                    <tr>
                        <th>画像</th>
                        <th>タイトル</th>
                        <th>カテゴリ</th>
                        <th>投稿日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($newsList)): ?>
                        <tr>
                            <td colspan="5">お知らせが登録されていません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($newsList as $item): ?>
                            <tr class="newsRow" data-id="<?= h($item['id']) ?>">
                                <td>
                                    <?php if ($item['image_name'] !== null): ?>
                                        <img src="<?= h($_ENV['APP_URL']) ?>/images/news/<?= h($item['image_name']) ?>"
                                             alt="" class="newsAdminThumb">
                                    <?php else: ?>
                                        <span class="noImage">なし</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= h($_ENV['APP_URL']) ?>/news/index.php?id=<?= h($item['id']) ?>">
                                        <?= h($item['title']) ?>
                                    </a>
                                </td>
                                <td><?= h($item['category']) ?></td>
                                <td><?= h(substr($item['created_at'], 0, 16)) ?></td>
                                <td>
                                    <a href="<?= h($_ENV['APP_URL']) ?>/admin/newsedit.php?id=<?= h($item['id']) ?>">編集</a>
                                    <button class="deleteNewsButton"
                                            data-id="<?= h($item['id']) ?>"
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
        document.querySelectorAll('.deleteNewsButton').forEach(function (button) {
            button.addEventListener('click', async function () {
                if (!confirm('このお知らせを削除しますか？')) return;
                const id = this.dataset.id;
                const csrf = this.dataset.csrf;
                try {
                    const res = await fetch('<?= h($_ENV['APP_URL']) ?>/api/deleteNews.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ newsId: id, csrf_token: csrf })
                    });
                    const json = await res.json();
                    if (json.success) {
                        this.closest('.newsRow').remove();
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
