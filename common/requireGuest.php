<?php

function requireGuest()
{
    if (isset($_SESSION['userId'])) {

        header('Location: ' . $_ENV['APP_URL']);
        exit();
    }
}
