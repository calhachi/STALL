<?php
require_once __DIR__ . '/function/deleteTempImages.php';

try {

    deleteTempImages();
} catch (Throwable $e) {

    error_log($e->getMessage());
}
