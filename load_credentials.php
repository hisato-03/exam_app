<?php
function restore_credentials($envKey) {
    $base64 = getenv($envKey);
    if ($base64) {
        $json = base64_decode($base64);
        file_put_contents(__DIR__ . '/credentials.json', $json);
    }
}
