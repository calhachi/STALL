<?php
session_start();

// ↓のDotenv前提Composer読み込み
require __DIR__ . '/../vendor/autoload.php';
// .env読み込み用
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();


// 作品関連ファイルURL短縮用
define('TEMP_DIR', __DIR__ . '/../userdata/temp/');
define('WORKS_DIR', __DIR__ . '/../userdata/works/');
define('THUMBNAIL_DIR', __DIR__ . '/../userdata/thumbnail/');
define('WORKS_IMAGES_DIR', __DIR__ . '/../userdata/works-image/');
define('COMPONENTS_DIR', __DIR__ . '/components/');
define('BANNER_DIR', __DIR__ . '/../images/banners/');



function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 開発中のみ有効にする（本番公開時はコメントアウト）
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
