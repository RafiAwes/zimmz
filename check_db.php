<?php
require 'c:/Projects/Laravel/zimmz/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/zimmz/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = \Illuminate\Support\Facades\DB::select('DESC task_services');
foreach ($columns as $column) {
    echo "{$column->Field}: {$column->Null}\n";
}
