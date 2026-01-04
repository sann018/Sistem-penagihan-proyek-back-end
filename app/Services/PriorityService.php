<?php

namespace App\Services;

use App\Models\Penagihan;
use App\Enums\ProjectPriorityLevel;
use App\Enums\ProjectPrioritySource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Priority Management Service
 * 
 * Centralized service untuk mengatur prioritas proyek dengan business logic yang jelas.
 * 
 * Features:
 * - Auto-calculate priority based on multiple factors
 * - Manual priority override
 * - Priority history tracking
 * - Smart priority rules engine
 */
class PriorityService
{
    /**
     * Set manual priority untuk proyek
     */
    public function setManualPriority(
        Penagihan $project,
        ProjectPriorityLevel $level,
        int $userId,
        ?string $reason = null
    ): bool {
        $oldLevel = $project->priority_level;
        $oldSource = $project->priority_source;
        
        DB::beginTransaction();
        try {
            $project->update([
                'priority_level' => $level->value,
                'priority_source' => ProjectPrioritySource::MANUAL->value,
                'priority_reason' => $reason ?? "Set manual oleh user",
                'priority_updated_at' => now(),
                'priority_updated_by' => $userId,
            ]);
            
            // Log perubahan
            Log::info('[PRIORITY] Manual priority set', [
                'pid' => $project->pid,
                'project' => $project->nama_proyek,
                'old_level' => $oldLevel,
                'new_level' => $level->value,
                'user_id' => $userId,
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PRIORITY] Failed to set manual priority', [
                'pid' => $project->pid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Calculate dan set automatic priority untuk satu proyek
     */
    public function calculateAutoPriority(Penagihan $project): ProjectPriorityLevel
    {
        // Skip jika sudah ada manual priority
        if ($project->priority_source === ProjectPrioritySource::MANUAL->value) {
            return ProjectPriorityLevel::from($project->priority_level ?? ProjectPriorityLevel::NONE->value);
        }
        
        $factors = $this->analyzePriorityFactors($project);
        $level = $this->determineLevel($factors);
        $source = $this->determineSource($factors);
        
        // Update hanya jika ada perubahan
        if ($project->priority_level !== $level->value || $project->priority_source !== $source->value) {
            $project->update([
                'priority_level' => $level->value,
                'priority_source' => $source->value,
                'priority_reason' => $factors['reason'],
                'priority_score' => $factors['score'],
                'priority_updated_at' => now(),
            ]);
            
            Log::info('[PRIORITY] Auto priority calculated', [
                'pid' => $project->pid,
                'level' => $level->value,
                'score' => $factors['score'],
                'reason' => $factors['reason'],
            ]);
        }
        
        return $level;
    }
    
    /**
     * Analyze berbagai faktor untuk menentukan prioritas
     */
    private function analyzePriorityFactors(Penagihan $project): array
    {
        $score = 0;
        $reasons = [];
        $dominantSource = ProjectPrioritySource::SYSTEM;
        
        // Factor 1: Deadline proximity
        if ($project->tanggal_jatuh_tempo) {
            $daysUntilDeadline = now()->diffInDays($project->tanggal_jatuh_tempo, false);
            
            if ($daysUntilDeadline < 0) {
                // Overdue - Critical
                $score += 100;
                $reasons[] = "Lewat deadline " . abs($daysUntilDeadline) . " hari";
                $dominantSource = ProjectPrioritySource::AUTO_OVERDUE;
            } elseif ($daysUntilDeadline <= 1) {
                // H-1 - Critical
                $score += 90;
                $reasons[] = "Deadline besok";
                $dominantSource = ProjectPrioritySource::AUTO_DEADLINE;
            } elseif ($daysUntilDeadline <= 3) {
                // H-3 - High
                $score += 70;
                $reasons[] = "Deadline dalam 3 hari";
                $dominantSource = ProjectPrioritySource::AUTO_DEADLINE;
            } elseif ($daysUntilDeadline <= 7) {
                // H-7 - Medium
                $score += 50;
                $reasons[] = "Deadline dalam 7 hari";
                $dominantSource = ProjectPrioritySource::AUTO_DEADLINE;
            }
        }
        
        // Factor 2: Progress vs Time (keterlambatan progres)
        $progress = $this->calculateProgress($project);
        $expectedProgress = $this->calculateExpectedProgress($project);
        
        if ($expectedProgress > 0) {
            $progressGap = $expectedProgress - $progress;
            
            if ($progressGap >= 30) {
                // Sangat tertinggal
                $score += 40;
                $reasons[] = "Progres tertinggal {$progressGap}%";
            } elseif ($progressGap >= 15) {
                // Cukup tertinggal
                $score += 20;
                $reasons[] = "Progres kurang {$progressGap}%";
            }
        }
        
        // Factor 3: Stuck/Blocked (tidak ada update lama)
        if ($project->diperbarui_pada) {
            $daysSinceUpdate = now()->diffInDays($project->diperbarui_pada);
            
            if ($daysSinceUpdate >= 7 && $progress < 100) {
                $score += 30;
                $reasons[] = "Tidak ada update {$daysSinceUpdate} hari";
                if ($dominantSource === ProjectPrioritySource::SYSTEM) {
                    $dominantSource = ProjectPrioritySource::AUTO_BLOCKED;
                }
            }
        }
        
        // Factor 4: Phase (phase awal lebih penting untuk dimonitor)
        if (in_array($project->status_ct, ['Belum CT']) && $score > 0) {
            $score += 10;
            $reasons[] = "Phase awal (CT belum)";
        }
        
        return [
            'score' => $score,
            'reason' => empty($reasons) ? 'Normal' : implode(', ', $reasons),
            'source' => $dominantSource,
            'progress' => $progress,
            'expected_progress' => $expectedProgress,
        ];
    }
    
    /**
     * Determine priority level dari score
     */
    private function determineLevel(array $factors): ProjectPriorityLevel
    {
        $score = $factors['score'];
        
        if ($score >= 90) {
            return ProjectPriorityLevel::CRITICAL;
        }
        
        if ($score >= 60) {
            return ProjectPriorityLevel::HIGH;
        }
        
        if ($score >= 30) {
            return ProjectPriorityLevel::MEDIUM;
        }
        
        if ($score > 0) {
            return ProjectPriorityLevel::LOW;
        }
        
        return ProjectPriorityLevel::NONE;
    }
    
    /**
     * Determine priority source
     */
    private function determineSource(array $factors): ProjectPrioritySource
    {
        return $factors['source'];
    }
    
    /**
     * Calculate progress percentage (0-100)
     */
    private function calculateProgress(Penagihan $project): int
    {
        return method_exists($project, 'calculateProgressPercent')
            ? $project->calculateProgressPercent()
            : 0;
    }
    
    /**
     * Calculate expected progress berdasarkan waktu yang sudah lewat
     */
    private function calculateExpectedProgress(Penagihan $project): int
    {
        if (!$project->tanggal_mulai || !$project->estimasi_durasi_hari) {
            return 0;
        }
        
        $start = Carbon::parse($project->tanggal_mulai);
        $end = $start->copy()->addDays($project->estimasi_durasi_hari);
        $now = now();
        
        if ($now->lt($start)) {
            return 0; // Belum mulai
        }
        
        if ($now->gte($end)) {
            return 100; // Seharusnya sudah selesai
        }
        
        $totalDays = $start->diffInDays($end);
        $daysPassed = $start->diffInDays($now);
        
        return (int) round(($daysPassed / $totalDays) * 100);
    }
    
    /**
     * Clear priority (set ke NONE)
     */
    public function clearPriority(Penagihan $project, int $userId, ?string $reason = null): bool
    {
        DB::beginTransaction();
        try {
            $project->update([
                'priority_level' => ProjectPriorityLevel::NONE->value,
                'priority_source' => null,
                'priority_reason' => $reason ?? "Prioritas dihapus",
                'priority_score' => 0,
                'priority_updated_at' => now(),
                'priority_updated_by' => $userId,
            ]);
            
            Log::info('[PRIORITY] Priority cleared', [
                'pid' => $project->pid,
                'user_id' => $userId,
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PRIORITY] Failed to clear priority', [
                'pid' => $project->pid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Recalculate priorities untuk semua proyek pending
     */
    public function recalculateAll(): array
    {
        $projects = Penagihan::query()
            ->where('status', 'pending')
            ->get();
        
        $stats = [
            'total' => $projects->count(),
            'updated' => 0,
            'skipped_manual' => 0,
        ];
        
        foreach ($projects as $project) {
            if ($project->priority_source === ProjectPrioritySource::MANUAL->value) {
                $stats['skipped_manual']++;
                continue;
            }
            
            $oldLevel = $project->priority_level;
            $this->calculateAutoPriority($project);
            
            if ($oldLevel !== $project->priority_level) {
                $stats['updated']++;
            }
        }
        
        Log::info('[PRIORITY] Recalculate all completed', $stats);
        
        return $stats;
    }
}
