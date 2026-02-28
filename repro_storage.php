<?php

use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $disk = Storage::disk('public');
    echo "Disk type: " . get_class($disk) . "\n";
    if (method_exists($disk, 'url')) {
        echo "url() method exists.\n";
        echo "Example URL: " . $disk->url('test.jpg') . "\n";
    } else {
        echo "url() method DOES NOT exist.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
