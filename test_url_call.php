<?php

use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $url = Storage::disk('public')->url('test.jpg');
    echo "URL: $url\n";
} catch (\Throwable $e) {
    echo "Caught: " . $e->getMessage() . "\n";
    echo "Class: " . get_class(Storage::disk('public')) . "\n";
}
