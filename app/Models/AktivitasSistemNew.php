<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AktivitasSistem extends Model
{
    use HasFactory;

    protected $table = 'aktivitas_sistem';
    protected $primaryKey = 'id_aktivitas';
    
    const CREATED_AT = 'waktu_kejadian';
    const UPDATED_AT = null;  // Tidak ada updated_at
    
    public $timestamps = false;  // Manual manage timestamp
    
    protected $fillable = [
        'id_pengguna',
        'aksi',
        'tabel_target',
        'id_target',
        'nama_target',
        'detail_perubahan',
        'keterangan',
        'alamat_ip',
        'user_agent',
        'waktu_kejadian',
    ];
    
    protected $casts = [
        'detail_perubahan' => 'array',
        'waktu_kejadian' => 'datetime',
    ];
    
    /**
     * Relationship: Aktivitas belongs to User
     */
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }
    
    /**
     * Scope: Aktivitas untuk user tertentu
     */
    public function scopeUntukPengguna($query, $idPengguna)
    {
        return $query->where('id_pengguna', $idPengguna);
    }
    
    /**
     * Scope: Aktivitas berdasarkan aksi
     */
    public function scopeAksi($query, $aksi)
    {
        return $query->where('aksi', $aksi);
    }
    
    /**
     * Scope: Aktivitas pada tabel tertentu
     */
    public function scopeTabel($query, $tabel)
    {
        return $query->where('tabel_target', $tabel);
    }
    
    /**
     * Scope: Aktivitas untuk record tertentu
     */
    public function scopeUntukRecord($query, $tabel, $id)
    {
        return $query->where('tabel_target', $tabel)
                    ->where('id_target', $id);
    }
    
    /**
     * Scope: Aktivitas hari ini
     */
    public function scopeHariIni($query)
    {
        return $query->whereDate('waktu_kejadian', today());
    }
    
    /**
     * Static: Log CRUD operation
     */
    public static function logCrud($userId, $aksi, $tabel, $id, $nama = null, $detail = null, $keterangan = null)
    {
        return self::create([
            'id_pengguna' => $userId,
            'aksi' => $aksi,
            'tabel_target' => $tabel,
            'id_target' => $id,
            'nama_target' => $nama,
            'detail_perubahan' => $detail,
            'keterangan' => $keterangan,
            'alamat_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'waktu_kejadian' => now(),
        ]);
    }
    
    /**
     * Static: Log tambah proyek
     */
    public static function logTambahProyek($userId, $pid, $namaProyek)
    {
        return self::logCrud($userId, 'tambah_proyek', 'data_proyek', $pid, $namaProyek);
    }
    
    /**
     * Static: Log ubah proyek
     */
    public static function logUbahProyek($userId, $pid, $namaProyek, $detail)
    {
        return self::logCrud($userId, 'ubah_proyek', 'data_proyek', $pid, $namaProyek, $detail);
    }
    
    /**
     * Static: Log hapus proyek
     */
    public static function logHapusProyek($userId, $pid, $namaProyek)
    {
        return self::logCrud($userId, 'hapus_proyek', 'data_proyek', $pid, $namaProyek);
    }
}
