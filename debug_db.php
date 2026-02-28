<?php
require 'c:/Projects/Laravel/zimmz/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/zimmz/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \App\Models\TaskService::create([
        'user_id' => 1,
        'task' => 'Test',
        'price' => '10',
        'status' => 'new'
    ]);
    echo "Success";
} catch (\Exception $e) {
    echo $e->getMessage();
}
