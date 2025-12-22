<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penagihan extends Model
{
    use HasFactory;

    protected $table = 'penagihan';
    
    /**
     * Nama kolom timestamp custom
     */
    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';
    const DELETED_AT = 'dihapus_pada';

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
}
