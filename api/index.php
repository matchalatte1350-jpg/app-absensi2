<?php

if (!is_dir('/tmp/views')) {
    mkdir('/tmp/views', 0755, true);
}
if (!is_dir('/tmp/storage/framework/sessions')) {
    mkdir('/tmp/storage/framework/sessions', 0755, true);
    mkdir('/tmp/storage/framework/views', 0755, true);
    mkdir('/tmp/storage/framework/cache', 0755, true);
}

require __DIR__ . '/../public/index.php';