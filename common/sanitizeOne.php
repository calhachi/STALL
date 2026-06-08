<?php
function sanitizeOne($raw)
{
    return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
}
