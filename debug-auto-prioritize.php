<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

echo "========================================\n";
echo "🔍 DEBUG AUTO-PRIORITIZE\n";
echo "========================================\n\n";

// Get all projects with timers
$projects = \App\Models\Penagihan::whereNotNull('tanggal_mulai')
    ->whereNotNull('estimasi_durasi_hari')
    ->get();

echo "📊 Total projects with timers: {$projects->count()}\n\n";

$threshold = 3; // H-3

echo "🎯 Threshold: {$threshold} hari (H-3)\n";
echo "📅 Hari ini: " . Carbon::now()->format('Y-m-d') . "\n\n";

echo "========================================\n";
echo "DETAIL SETIAP PROYEK:\n";
echo "========================================\n\n";

$eligibleForP2 = [];
$alreadyP2 = [];
$alreadyP1 = [];
$overThreshold = [];
$completed = [];
$noTimer = [];

foreach ($projects as $project) {
    echo "ID: {$project->id}\n";
    echo "Nama: {$project->nama_proyek}\n";
    echo "Prioritas Saat Ini: " . ($project->prioritas ?? 'null') . "\n";
    
    if ($project->tanggal_mulai && $project->estimasi_durasi_hari) {
        $tanggalMulai = Carbon::parse($project->tanggal_mulai);
        $deadline = $tanggalMulai->copy()->addDays($project->estimasi_durasi_hari);
        $daysRemaining = Carbon::now()->diffInDays($deadline, false);
        
        echo "Tanggal Mulai: {$project->tanggal_mulai}\n";
        echo "Estimasi Durasi: {$project->estimasi_durasi_hari} hari\n";
        echo "Deadline: " . $deadline->format('Y-m-d') . "\n";
        echo "Sisa Hari: {$daysRemaining} hari\n";
        
        $isCompleted = $project->isCompleted();
        echo "Status Selesai: " . ($isCompleted ? '✅ Sudah' : '❌ Belum') . "\n";
        
        // Cek eligible untuk P2
        // Logic baru: Include OVERDUE (negatif) dan mendekati deadline (0-3 hari)
        if ($project->prioritas === 1) {
            echo "🔒 Action: SKIP (P1 Manual)\n";
            $alreadyP1[] = $project->nama_proyek;
        } elseif ($daysRemaining <= $threshold && !$isCompleted) {
            // Include overdue (negatif) dan mendekati deadline (0-3 hari)
            if ($project->prioritas === 2) {
                echo "✅ Action: SUDAH P2\n";
                $alreadyP2[] = $project->nama_proyek;
            } else {
                $status = $daysRemaining < 0 ? '🔴 OVERDUE' : '🎯 MENDEKATI DEADLINE';
                echo "{$status} Action: SET P2 (Eligible!)\n";
                $eligibleForP2[] = [
                    'id' => $project->id,
                    'nama' => $project->nama_proyek,
                    'sisa' => $daysRemaining,
                    'status' => $daysRemaining < 0 ? 'overdue' : 'approaching'
                ];
            }
        } elseif ($daysRemaining > $threshold) {
            echo "⏰ Action: SKIP (Masih {$daysRemaining} hari, > threshold)\n";
            $overThreshold[] = $project->nama_proyek;
        } elseif ($isCompleted) {
            echo "✅ Action: SKIP (Sudah selesai)\n";
            $completed[] = $project->nama_proyek;
        }
    } else {
        echo "⚠️ Action: SKIP (Tidak ada timer lengkap)\n";
        $noTimer[] = $project->nama_proyek;
    }
    
    echo "----------------------------------------\n\n";
}

echo "========================================\n";
echo "📈 SUMMARY:\n";
echo "========================================\n\n";

echo "🎯 Eligible untuk SET P2: " . count($eligibleForP2) . "\n";
foreach ($eligibleForP2 as $item) {
    echo "   - [{$item['id']}] {$item['nama']} (H-{$item['sisa']})\n";
}
echo "\n";

echo "✅ Sudah P2: " . count($alreadyP2) . "\n";
foreach ($alreadyP2 as $name) {
    echo "   - {$name}\n";
}
echo "\n";

echo "🔒 Skip (P1 Manual): " . count($alreadyP1) . "\n";
foreach ($alreadyP1 as $name) {
    echo "   - {$name}\n";
}
echo "\n";

echo "⏰ Skip (Over Threshold): " . count($overThreshold) . "\n";
foreach ($overThreshold as $name) {
    echo "   - {$name}\n";
}
echo "\n";

echo "✅ Skip (Sudah Selesai): " . count($completed) . "\n";
foreach ($completed as $name) {
    echo "   - {$name}\n";
}
echo "\n";

echo "⚠️ Skip (Tidak Ada Timer): " . count($noTimer) . "\n";
foreach ($noTimer as $name) {
    echo "   - {$name}\n";
}
echo "\n";

echo "========================================\n";
echo "✅ DEBUG COMPLETED\n";
echo "========================================\n";
