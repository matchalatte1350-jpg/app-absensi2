<?php

$storagePath = getenv('LARAVEL_STORAGE_PATH') ?: '/tmp/storage';

foreach ([
    $storagePath.'/framework/views',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/cache/locks',
    $storagePath.'/framework/sessions',
    $storagePath.'/logs',
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

require __DIR__.'/../public/index.php';
