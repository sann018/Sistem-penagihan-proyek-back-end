<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Nama tabel database
     */
    protected $table = 'pengguna';

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
        'nik',
        'kata_sandi',
        'peran',
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
