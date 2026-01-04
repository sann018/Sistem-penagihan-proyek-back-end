<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CLEANUP FAILED MIGRATION ===\n\n";

// Drop tables if exists
if (Schema::hasTable('log_aktivitas')) {
    Schema::drop('log_aktivitas');
    echo "✅ Dropped log_aktivitas\n";
}

if (Schema::hasTable('aktivitas_sistem')) {
    Schema::drop('aktivitas_sistem');
    echo "✅ Dropped aktivitas_sistem (new)\n";
}

// Restore old table
if (Schema::hasTable('aktivitas_sistem_old')) {
    Schema::rename('aktivitas_sistem_old', 'aktivitas_sistem');
    echo "✅ Restored aktivitas_sistem from aktivitas_sistem_old\n";
}

echo "\n✅ Cleanup complete! Ready to re-run migration.\n";
