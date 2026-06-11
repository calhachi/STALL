<?php

function requireLogin()
{
    if (!isset($_SESSION['userId'])) {
        $_SESSION['checkPoint'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $_ENV['APP_URL'] . '/login');
        exit();
    }

    // 最終ログインから7日以上経過でタイムアウト
    if (isset($_SESSION['lastLogin']) && time() - $_SESSION['lastLogin'] > 7 * 24 * 3600) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['checkPoint'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $_ENV['APP_URL'] . '/login');
        exit();
    }
}
