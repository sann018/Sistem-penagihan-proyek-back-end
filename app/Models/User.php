<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Nama tabel database
     */
    protected $table = 'pengguna';
    
    /**
     * Primary key custom
     */
    protected $primaryKey = 'id_pengguna';

    /**
     * Nama kolom timestamp custom
     */
    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'username',
        'jobdesk',
        'mitra',
        'nomor_hp',
        'kata_sandi',
        'peran',
        'foto',
        'terakhir_login_pada',
        'email_terverifikasi_pada',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'kata_sandi',
        'token_ingat',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_terverifikasi_pada' => 'datetime',
            'kata_sandi' => 'hashed',
            'terakhir_login_pada' => 'datetime',
        ];
    }

    /**
     * Get password attribute accessor
     */
    public function getAuthPassword()
    {
        return $this->kata_sandi;
    }

    /**
     * Get remember token column name
     */
    public function getRememberTokenName()
    {
        return 'token_ingat';
    }

    /**
     * Kirim notifikasi reset password dengan link menuju frontend (SPA).
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * Get id attribute accessor (alias untuk id_pengguna)
     * Untuk compatibility dengan frontend dan Laravel standard
     */
    public function getIdAttribute()
    {
        return $this->id_pengguna;
    }

    /**
     * Get role attribute accessor (alias untuk peran)
     */
    public function getRoleAttribute()
    {
        return $this->peran;
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->peran === 'super_admin';
    }

    /**
     * Check if user is viewer
     */
    public function isViewer(): bool
    {
        return $this->peran === 'viewer';
    }

    /**
     * Check if user has permission to modify data
     */
    public function canModify(): bool
    {
        return $this->isSuperAdmin();
    }
}
