<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    use HasFactory;

    protected $table = 'log_aktivitas';
    protected $primaryKey = 'id_log';
    
    const CREATED_AT = 'waktu_kejadian';
    const UPDATED_AT = null;  // Tidak ada updated_at
    
    public $timestamps = false;  // Manual manage timestamp
    
    protected $fillable = [
        'id_pengguna',
        'aksi',
        'deskripsi',
        'path',
        'method',
        'status_code',
        'alamat_ip',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'session_id',
        'negara',
        'kota',
        'metadata',
        'waktu_kejadian',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'waktu_kejadian' => 'datetime',
    ];
    
    /**
     * Relationship: Log belongs to User
     */
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }
    
    /**
     * Scope: Log untuk user tertentu
     */
    public function scopeUntukPengguna($query, $idPengguna)
    {
        return $query->where('id_pengguna', $idPengguna);
    }
    
    /**
     * Scope: Log berdasarkan aksi
     */
    public function scopeAksi($query, $aksi)
    {
        return $query->where('aksi', $aksi);
    }
    
    /**
     * Scope: Log hari ini
     */
    public function scopeHariIni($query)
    {
        return $query->whereDate('waktu_kejadian', today());
    }
    
    /**
     * Scope: Log dalam rentang waktu
     */
    public function scopeRentangWaktu($query, $dari, $sampai)
    {
        return $query->whereBetween('waktu_kejadian', [$dari, $sampai]);
    }
    
    /**
     * Static: Log login
     */
    public static function logLogin($userId, $ip, $userAgent)
    {
        return self::create([
            'id_pengguna' => $userId,
            'aksi' => 'login',
            'alamat_ip' => $ip,
            'user_agent' => $userAgent,
            'waktu_kejadian' => now(),
        ]);
    }
    
    /**
     * Static: Log logout
     */
    public static function logLogout($userId, $ip)
    {
        return self::create([
            'id_pengguna' => $userId,
            'aksi' => 'logout',
            'alamat_ip' => $ip,
            'waktu_kejadian' => now(),
        ]);
    }
}
