<?php

function requireLogin()
{
    if (!isset($_SESSION['userId'])) {
        $_SESSION['checkPoint'] = $_SERVER['REQUEST_URI'];

        header('Location: ' . $_ENV['APP_URL'] . '/login');
        exit();
    }
}
