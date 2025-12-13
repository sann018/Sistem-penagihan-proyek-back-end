<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penagihan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_proyek',
        'nama_mitra',
        'pid',
        'nomor_po',
        'phase',
        'status_ct',
        'status_ut',
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
        'status_ct' => 'BELUM CT',
        'status_ut' => 'BELUM UT',
        'rekon_material' => 'BELUM REKON',
        'pelurusan_material' => 'BELUM LURUS',
        'status_procurement' => 'ANTRI PERIV',
        'status' => 'pending',
    ];

    protected $table = 'penagihan';

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
