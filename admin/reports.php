<?php
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../common/dbConnect.php';

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ' . $_ENV['APP_URL']);
    exit();
}

$reasonLabels = [
    1 => '第三者の権利を侵害している',
    2 => '過度にグロテスクな画像・公序良俗に反する',
    3 => '利用者が不快になる可能性のある内容',
    4 => 'その他',
];

$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : -1;

$reportsList = [];
try {
    $dbh = dbConnect();

    $sql = 'SELECT r.id, r.work_id, r.reason, r.detail, r.status, r.created_at,
                   w.title AS work_title, w.is_hidden AS work_is_hidden,
                   u.username AS reporter_name
            FROM reports r
            JOIN works w ON r.work_id = w.id
            JOIN users u ON r.user_id = u.id';
    $params = [];
    if ($statusFilter === 0 || $statusFilter === 1) {
        $sql .= ' WHERE r.status = :status';
        $params['status'] = $statusFilter;
    }
    $sql .= ' ORDER BY r.created_at DESC';

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $reportsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>通報管理 | STALL</title>
    <link href="https://cdn.skypack.dev/sanitize.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $_ENV['APP_URL'] ?>/common/style.css">
    <style>
        .reportsTable { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .reportsTable th,
        .reportsTable td { border: 1px solid #CDD3DB; padding: 0.5rem; text-align: left; vertical-align: top; }
        .reportsTable th { background: #eef2f7; }
        .reportStatusDone { color: #467A46; font-weight: bold; }
        .reportStatusPending { color: #c0392b; font-weight: bold; }
    </style>
</head>

<body>
    <?php require COMPONENTS_DIR . 'header.php'; ?>
    <main>
        <div class="mainWindow">
            <h1>通報管理</h1>
            <div class="categoryFilter">
                <a href="?status=-1" class="filterButton <?= $statusFilter === -1 ? 'active' : '' ?>">すべて</a>
                <a href="?status=0" class="filterButton <?= $statusFilter === 0 ? 'active' : '' ?>">未対応</a>
                <a href="?status=1" class="filterButton <?= $statusFilter === 1 ? 'active' : '' ?>">対応済み</a>
            </div>
            <table class="reportsTable">
                <thead>
                    <tr>
                        <th>通報日時</th>
                        <th>対象作品</th>
                        <th>通報者</th>
                        <th>通報内容</th>
                        <th>詳細</th>
                        <th>状態</th>
                        <th>作品の状態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportsList)): ?>
                        <tr>
                            <td colspan="7">通報はありません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportsList as $report): ?>
                            <tr class="reportRow" data-id="<?= h($report['id']) ?>">
                                <td><?= h(substr($report['created_at'], 0, 16)) ?></td>
                                <td>
                                    <a href="<?= h($_ENV['APP_URL']) ?>/works/detail?id=<?= h($report['work_id']) ?>" target="_blank">
                                        <?= h($report['work_title']) ?>
                                    </a>
                                </td>
                                <td><?= h($report['reporter_name']) ?></td>
                                <td><?= h($reasonLabels[(int)$report['reason']] ?? '不明') ?></td>
                                <td><?= $report['detail'] !== null ? h($report['detail']) : '' ?></td>
                                <td class="reportStatusCell <?= (int)$report['status'] === 1 ? 'reportStatusDone' : 'reportStatusPending' ?>">
                                    <?= (int)$report['status'] === 1 ? '対応済み' : '未対応' ?>
                                </td>
                                <td class="<?= (int)$report['work_is_hidden'] === 1 ? 'reportStatusDone' : 'reportStatusPending' ?>">
                                    <?= (int)$report['work_is_hidden'] === 1 ? '削除済み' : '公開中' ?>
                                </td>
                                <td>
                                    <button class="toggleReportStatusButton"
                                        data-id="<?= h($report['id']) ?>"
                                        data-status="<?= h($report['status']) ?>"
                                        data-csrf="<?= h($csrfToken) ?>">
                                        <?= (int)$report['status'] === 1 ? '未対応に戻す' : '対応済みにする' ?>
                                    </button>
                                    <button class="toggleWorkHiddenButton"
                                        data-work-id="<?= h($report['work_id']) ?>"
                                        data-hidden="<?= h($report['work_is_hidden']) ?>"
                                        data-csrf="<?= h($csrfToken) ?>">
                                        <?= (int)$report['work_is_hidden'] === 1 ? '作品を復元する' : '作品を削除する' ?>
                                    </button>
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
        document.querySelectorAll('.toggleReportStatusButton').forEach(function (button) {
            button.addEventListener('click', async function () {
                const id = this.dataset.id;
                const csrf = this.dataset.csrf;
                const newStatus = this.dataset.status === '1' ? 0 : 1;
                try {
                    const res = await fetch('<?= h($_ENV['APP_URL']) ?>/api/updateReportStatus.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reportId: id, status: newStatus, csrf_token: csrf })
                    });
                    const json = await res.json();
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.message || '更新に失敗しました。');
                    }
                } catch (e) {
                    alert('通信エラーが発生しました。');
                }
            });
        });

        document.querySelectorAll('.toggleWorkHiddenButton').forEach(function (button) {
            button.addEventListener('click', async function () {
                const workId = this.dataset.workId;
                const csrf = this.dataset.csrf;
                const newHidden = this.dataset.hidden === '1' ? 0 : 1;

                if (newHidden === 1 && !confirm('この作品を削除（非公開）にしますか？')) return;

                try {
                    const res = await fetch('<?= h($_ENV['APP_URL']) ?>/api/hideWork.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ workId: workId, isHidden: newHidden, csrf_token: csrf })
                    });
                    const json = await res.json();
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.message || '更新に失敗しました。');
                    }
                } catch (e) {
                    alert('通信エラーが発生しました。');
                }
            });
        });
    </script>
</body>

</html>
