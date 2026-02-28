<?php

use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$disk = Storage::disk('public');
echo "Class: " . get_class($disk) . "\n";
echo "Methods:\n";
foreach (get_class_methods($disk) as $method) {
    echo " - $method\n";
}
