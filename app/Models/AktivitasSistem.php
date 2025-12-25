<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AktivitasSistem extends Model
{
    use HasFactory;

    protected $table = 'aktivitas_sistem';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'pengguna_id',
        'nama_pengguna',
        'aksi',
        'tipe',
        'deskripsi',
        'tabel_yang_diubah',
        'id_record_yang_diubah',
        'data_sebelum',
        'data_sesudah',
        'ip_address',
        'user_agent',
        'waktu_aksi',
    ];

    protected $casts = [
        'data_sebelum' => 'array',
        'data_sesudah' => 'array',
        'waktu_aksi' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to User
     */
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    /**
     * Scope for filtering by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('tipe', $type);
    }

    /**
     * Scope for filtering by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('pengguna_id', $userId);
    }

    /**
     * Scope for filtering by table
     */
    public function scopeByTable($query, $table)
    {
        return $query->where('tabel_yang_diubah', $table);
    }

    /**
     * Scope for recent activities
     */
    public function scopeRecent($query)
    {
        return $query->with('pengguna')->orderBy('waktu_aksi', 'desc');
    }
}
