<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$d = DB::table('discoms')->insertGetId([
    'code' => 'SBPDCL',
    'name' => 'SBPDCL',
    'short_name' => 'SBPDCL',
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$c = DB::table('circles')->insertGetId([
    'discom_id' => $d,
    'code' => 'GAYA',
    'name' => 'Gaya',
    'short_name' => 'Gaya',
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$div = DB::table('divisions')->insertGetId([
    'circle_id' => $c,
    'code' => 'GAYA-RURAL',
    'name' => 'Gaya Rural',
    'short_name' => 'Gaya Rural',
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

$sd = DB::table('subdivisions')->insertGetId([
    'division_id' => $div,
    'code' => 'TEKARI',
    'name' => 'Tekari',
    'short_name' => 'Tekari',
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('sections')->insertGetId([
    'subdivision_id' => $sd,
    'code' => 'KONCH',
    'name' => 'Konch',
    'short_name' => 'Konch',
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "done";
