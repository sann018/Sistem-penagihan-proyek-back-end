<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notifikasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';
    
    const CREATED_AT = 'waktu_dibuat';
    const UPDATED_AT = null;  // Tidak ada updated_at
    const DELETED_AT = 'dihapus_pada';
    
    protected $fillable = [
        'id_penerima',
        'judul',
        'isi_notifikasi',
        'jenis_notifikasi',
        'status',
        'link_terkait',
        'referensi_tabel',
        'referensi_id',
        'metadata',
        'prioritas',
        'waktu_dikirim',
        'waktu_dibaca',
        'waktu_kadaluarsa',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'waktu_dibuat' => 'datetime',
        'waktu_dikirim' => 'datetime',
        'waktu_dibaca' => 'datetime',
        'waktu_kadaluarsa' => 'datetime',
        'dihapus_pada' => 'datetime',
    ];
    
    /**
     * Relationship: Notifikasi belongs to User (Penerima)
     */
    public function penerima()
    {
        return $this->belongsTo(User::class, 'id_penerima', 'id_pengguna');
    }
    
    /**
     * Scope: Notifikasi belum dibaca
     */
    public function scopeBelumDibaca($query)
    {
        return $query->where('status', 'terkirim');
    }
    
    /**
     * Scope: Notifikasi sudah dibaca
     */
    public function scopeSudahDibaca($query)
    {
        return $query->where('status', 'dibaca');
    }
    
    /**
     * Scope: Notifikasi untuk user tertentu
     */
    public function scopeUntukPengguna($query, $idPengguna)
    {
        return $query->where('id_penerima', $idPengguna);
    }
    
    /**
     * Scope: Notifikasi berdasarkan jenis
     */
    public function scopeJenis($query, $jenis)
    {
        return $query->where('jenis_notifikasi', $jenis);
    }
    
    /**
     * Mark notifikasi as dibaca
     */
    public function markAsDibaca()
    {
        $this->update([
            'status' => 'dibaca',
            'waktu_dibaca' => now(),
        ]);
    }
    
    /**
     * Mark notifikasi as terkirim
     */
    public function markAsTerkirim()
    {
        $this->update([
            'status' => 'terkirim',
            'waktu_dikirim' => now(),
        ]);
    }
    
    /**
     * Check if notifikasi expired
     */
    public function isExpired()
    {
        if (!$this->waktu_kadaluarsa) {
            return false;
        }
        
        return now()->greaterThan($this->waktu_kadaluarsa);
    }
}
