<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "🔍 DEBUG DATA PENAGIHAN\n";
echo "========================================\n\n";

// Get total count
$total = \App\Models\Penagihan::count();
echo "📊 Total data di database: {$total}\n\n";

// Get all projects
$projects = \App\Models\Penagihan::orderBy('id')->get();

echo "========================================\n";
echo "DETAIL SEMUA PROYEK:\n";
echo "========================================\n\n";

foreach ($projects as $project) {
    echo "ID: {$project->id}\n";
    echo "Nama: {$project->nama_proyek}\n";
    echo "PID: {$project->pid}\n";
    echo "Prioritas: " . ($project->prioritas ?? 'null') . "\n";
    echo "Dibuat: {$project->created_at}\n";
    echo "----------------------------------------\n";
}

echo "\n========================================\n";
echo "📈 SUMMARY:\n";
echo "========================================\n\n";

echo "Total Proyek: {$total}\n";
echo "Dengan Prioritas P1: " . \App\Models\Penagihan::where('prioritas', 1)->count() . "\n";
echo "Dengan Prioritas P2: " . \App\Models\Penagihan::where('prioritas', 2)->count() . "\n";
echo "Tanpa Prioritas: " . \App\Models\Penagihan::whereNull('prioritas')->count() . "\n";

echo "\n========================================\n";
echo "✅ DEBUG COMPLETED\n";
echo "========================================\n";
