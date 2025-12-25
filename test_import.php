<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    // Test database connection
    $db = $app->make('db');
    $penagihan_count = $db->table('penagihan')->count();
    echo "Current Penagihan count: " . $penagihan_count . "\n";
    
    // Test model
    $model = new \App\Models\Penagihan();
    echo "Model table: " . $model->getTable() . "\n";
    echo "Model fillable: " . json_encode($model->getFillable()) . "\n";
    
    // Check if jenis_po column exists
    $columns = $db->getSchemaBuilder()->getColumnListing('penagihan');
    echo "Table columns: " . json_encode($columns) . "\n";
    
    if (in_array('jenis_po', $columns)) {
        echo "✅ jenis_po column EXISTS\n";
    } else {
        echo "❌ jenis_po column MISSING!\n";
    }
    
    if (in_array('rekap_boq', $columns)) {
        echo "✅ rekap_boq column EXISTS\n";
    } else {
        echo "❌ rekap_boq column MISSING!\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
