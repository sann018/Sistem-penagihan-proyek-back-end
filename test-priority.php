<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "🧪 TESTING PRIORITY SYSTEM\n";
echo "========================================\n\n";

// 1. Count total projects
$total = \App\Models\Penagihan::count();
echo "✅ Total projects: {$total}\n";

// 2. Count prioritized projects
$prioritized = \App\Models\Penagihan::prioritized()->count();
echo "✅ Prioritized projects: {$prioritized}\n";

// 3. Count manual priority
$manual = \App\Models\Penagihan::manualPriority()->count();
echo "✅ Manual priority (P1): {$manual}\n";

// 4. Count auto priority
$auto = \App\Models\Penagihan::autoPriority()->count();
echo "✅ Auto priority (P2): {$auto}\n\n";

// 5. Check projects with timers
$withTimers = \App\Models\Penagihan::whereNotNull('tanggal_mulai')
    ->whereNotNull('estimasi_durasi_hari')
    ->count();
echo "📊 Projects with timers: {$withTimers}\n\n";

// 6. Test isCompleted() method
$completed = \App\Models\Penagihan::whereRaw('LOWER(status_ct) = ?', ['sudah ct'])
    ->whereRaw('LOWER(status_ut) = ?', ['sudah ut'])
    ->whereRaw('LOWER(rekap_boq) = ?', ['sudah rekap'])
    ->whereRaw('LOWER(rekon_material) = ?', ['sudah rekon'])
    ->whereRaw('LOWER(pelurusan_material) = ?', ['sudah lurus'])
    ->whereRaw('LOWER(status_procurement) = ?', ['otw reg'])
    ->count();
echo "✅ Completed projects (6 conditions): {$completed}\n\n";

// 7. Show sample prioritized projects
echo "========================================\n";
echo "📋 SAMPLE PRIORITIZED PROJECTS\n";
echo "========================================\n";

$samples = \App\Models\Penagihan::prioritized()->take(5)->get(['id', 'nama_proyek', 'prioritas']);
foreach ($samples as $project) {
    $label = $project->prioritas === 1 ? '🔥 P1' : ($project->prioritas === 2 ? '⚠️ P2' : '-');
    echo "{$label} - [{$project->id}] {$project->nama_proyek}\n";
}

if ($samples->isEmpty()) {
    echo "No prioritized projects yet.\n";
}

echo "\n========================================\n";
echo "✅ TEST COMPLETED\n";
echo "========================================\n";
