<?php

namespace App\Console\Commands;

use App\Services\PriorityService;
use Illuminate\Console\Command;

class RecalculatePriorities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'priority:recalculate {--force : Force recalculate including manual priorities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate automatic priorities for all pending projects';

    /**
     * Execute the console command.
     */
    public function handle(PriorityService $priorityService)
    {
        $this->info('🔄 Memulai recalculate priorities...');
        $this->newLine();
        
        $stats = $priorityService->recalculateAll();
        
        $this->info('✅ Selesai!');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Proyek', $stats['total']],
                ['Prioritas Diupdate', $stats['updated']],
                ['Manual Priority (Skipped)', $stats['skipped_manual']],
            ]
        );
        
        return Command::SUCCESS;
    }
}
