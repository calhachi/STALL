<?php

function deleteTempImages()
{

    $tempDir = __DIR__ . '/../userdata/temp/';

    $files = glob($tempDir . '*');

    foreach ($files as $file) {

        if (!is_file($file)) {
            continue;
        }

        // 24時間以上前
        if (filemtime($file) < time() - 86400) {
            unlink($file);
        }
    }
}
