<?php

namespace App\Models;

use App\Enums\ProjectPriorityLevel;
use App\Enums\ProjectPrioritySource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Penagihan extends Model
{
    use HasFactory;

    protected $table = 'data_proyek';
    
    /**
     * Primary key custom - menggunakan pid
     */
    protected $primaryKey = 'pid';
    
    /**
     * Tipe primary key bukan auto increment integer
     */
    public $incrementing = false;
    
    /**
     * Tipe data primary key adalah string
     */
    protected $keyType = 'string';
    
    /**
     * Nama kolom timestamp custom
     */
    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';
    const DELETED_AT = 'dihapus_pada';
    
    /**
     * Model events untuk auto-clear cache
     */
    protected static function boot()
    {
        parent::boot();

        // [🔐 MITRA_ACCESS] Enforce data isolation untuk akun mitra.
        // Policy:
        // - Role "mitra" tidak digunakan lagi.
        // - Akun mitra menggunakan role=viewer + field pengguna.mitra terisi.
        // - Jika pengguna.mitra = "Telkom Akses" maka boleh akses semua data.
        // Filtering dilakukan di backend dan tidak bergantung pada request parameter.
        static::addGlobalScope('mitra_access', function (Builder $builder) {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();
            $userRole = $user->role ?? $user->peran;

            // Hanya viewer yang perlu dibatasi berdasarkan mapping mitra.
            if ($userRole !== 'viewer') {
                return;
            }

            $mitra = is_string($user->mitra ?? null) ? trim((string) $user->mitra) : '';
            if ($mitra === '') return;

            // Special-case: Telkom Akses boleh akses semua mitra.
            // Accept variants like "PT Telkom Akses" / "PT. Telkom Akses".
            if (preg_match('/telkom\s*akses/i', $mitra) === 1) return;

            // Map user.mitra ke kolom data_proyek.nama_mitra
            $builder->where('nama_mitra', $mitra);
        });
        
        // Clear cache saat data berubah
        static::saved(function () {
            Cache::forget('card_statistics');
        });
        
        static::deleted(function () {
            Cache::forget('card_statistics');
        });
    }

    protected $fillable = [
        'nama_proyek',
        'nama_mitra',
        'pid',
        'jenis_po',
        'nomor_po',
        'phase',
        'status_ct',
        'status_ut',
        'rekap_boq',
        'rekon_nilai',
        'rekon_material',
        'pelurusan_material',
        'status_procurement',
        'prioritas',
        'prioritas_updated_at',
        // Kolom prioritas baru
        'priority_level',
        'priority_source',
        'priority_reason',
        'priority_score',
        'priority_updated_by',
        'estimasi_durasi_hari',
        'tanggal_mulai',
        'status',
        'tanggal_invoice',
        'tanggal_jatuh_tempo',
        'catatan',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_mulai' => 'date',
        'estimasi_durasi_hari' => 'integer',
        'rekon_nilai' => 'decimal:2',
        // Cast ke string saja (enum akan dihandle manual di accessor)
        'priority_level' => 'string',
        'priority_source' => 'string',
        'priority_score' => 'integer',
    ];

    protected $attributes = [
        'status_ct' => 'Belum CT',
        'status_ut' => 'Belum UT',
        'rekap_boq' => 'Belum Rekap',
        'rekon_material' => 'Belum Rekon',
        'pelurusan_material' => 'Belum Lurus',
        'status_procurement' => 'Antri Periv',
        'status' => 'pending',
    ];

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && 
               $this->tanggal_jatuh_tempo < now();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope untuk proyek dengan prioritas (versi baru - enum based)
     * Urutkan berdasarkan score (lower = higher priority) dan tanggal mulai
     */
    public function scopePrioritized($query)
    {
        return $query->whereNotNull('priority_level')
                     ->where('priority_level', '!=', ProjectPriorityLevel::NONE->value)
                     ->orderBy('priority_score', 'desc')
                     ->orderBy('tanggal_mulai', 'asc');
    }

    /**
     * Scope untuk proyek dengan prioritas manual (set oleh user)
     */
    public function scopeManualPriority($query)
    {
        return $query->where('priority_source', ProjectPrioritySource::MANUAL->value);
    }

    /**
     * Scope untuk proyek dengan prioritas auto (otomatis dari sistem)
     */
    public function scopeAutoPriority($query)
    {
        return $query->whereIn('priority_source', [
            ProjectPrioritySource::AUTO_DEADLINE->value,
            ProjectPrioritySource::AUTO_OVERDUE->value,
            ProjectPrioritySource::AUTO_BLOCKED->value,
        ]);
    }
    
    /**
     * Scope untuk prioritas berdasarkan level
     */
    public function scopeByPriorityLevel($query, ProjectPriorityLevel $level)
    {
        return $query->where('priority_level', $level->value);
    }
    
    /**
     * Check apakah prioritas bisa diubah manual
     */
    public function canChangePriority(): bool
    {
        if (!$this->priority_source) {
            return true; // Belum ada prioritas, bisa di-set
        }
        
        return $this->priority_source->canOverride();
    }
    
    /**
     * Helper method untuk cek high priority
     */
    public function isHighPriority(): bool
    {
        if (!$this->priority_level || $this->priority_level === 'none') {
            return false;
        }
        
        try {
            $level = ProjectPriorityLevel::from($this->priority_level);
            return in_array($level, [
                ProjectPriorityLevel::CRITICAL,
                ProjectPriorityLevel::HIGH,
            ]);
        } catch (\ValueError $e) {
            return false;
        }
    }
    
    /**
     * Helper method untuk cek critical priority
     */
    public function isCritical(): bool
    {
        if (!$this->priority_level) {
            return false;
        }
        
        try {
            $level = ProjectPriorityLevel::from($this->priority_level);
            return $level === ProjectPriorityLevel::CRITICAL;
        } catch (\ValueError $e) {
            return false;
        }
    }
    
    /**
     * Get priority level as enum (helper method)
     */
    public function getPriorityLevelEnum(): ?ProjectPriorityLevel
    {
        if (!$this->priority_level || $this->priority_level === 'none') {
            return null;
        }
        
        try {
            return ProjectPriorityLevel::from($this->priority_level);
        } catch (\ValueError $e) {
            return null;
        }
    }
    
    /**
     * Get priority source as enum (helper method)
     */
    public function getPrioritySourceEnum(): ?ProjectPrioritySource
    {
        if (!$this->priority_source) {
            return null;
        }
        
        try {
            return ProjectPrioritySource::from($this->priority_source);
        } catch (\ValueError $e) {
            return null;
        }
    }

    /**
     * Check apakah proyek sudah selesai penuh (semua status hijau)
     */
    public function isCompleted(): bool
    {
        return $this->calculateProgressPercent() >= 100;
    }

    /**
     * Hitung progress proyek (0-100) berdasarkan 6 step utama.
     * Dibuat case-insensitive dan mengikuti opsi UI.
     */
    public function calculateProgressPercent(): int
    {
        $steps = [
            ['status_ct', ['Sudah CT']],
            ['status_ut', ['Sudah UT']],
            ['rekap_boq', ['Sudah Rekap']],
            ['rekon_material', ['Sudah Rekon']],
            ['pelurusan_material', ['Sudah Lurus']],
            // Selesai procurement: OTW Reg atau Sekuler TTD (sesuai UI)
            ['status_procurement', ['OTW Reg', 'Sekuler TTD']],
        ];

        $done = 0;
        foreach ($steps as [$field, $targets]) {
            $value = $this->{$field} ?? null;
            if ($this->valueMatchesAnyTarget($value, $targets)) {
                $done++;
            }
        }

        $total = count($steps);
        if ($total <= 0) return 0;

        return (int) round(($done / $total) * 100);
    }

    private function valueMatchesAnyTarget($value, array $targets): bool
    {
        $normalized = $this->normalizeProgressValue($value);
        if ($normalized === '') return false;

        foreach ($targets as $target) {
            if ($normalized === $this->normalizeProgressValue($target)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeProgressValue($value): string
    {
        if (!is_string($value)) return '';
        return strtolower(trim($value));
    }

    /**
     * Hitung sisa hari sampai deadline
     */
    public function getDaysUntilDeadline(): ?int
    {
        if (!$this->tanggal_mulai || !$this->estimasi_durasi_hari) {
            return null;
        }

        $deadline = \Carbon\Carbon::parse($this->tanggal_mulai)
                        ->addDays($this->estimasi_durasi_hari);
        $now = now();

        return $now->diffInDays($deadline, false);
    }
}
