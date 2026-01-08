<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        User::updateOrCreate(
            ['email' => 'admin@telkom.co.id'],
            [
                'nama' => 'Super Admin',
                'username' => 'admin01',
                'kata_sandi' => Hash::make('@Admintelkom25'), 
                'peran' => 'super_admin',
                'email_terverifikasi_pada' => now(),
            ]
        );

        // Create Admin/Viewer (CRUD proyek)
        User::updateOrCreate(
            ['email' => 'viewer@telkom.co.id'],
            [
                'nama' => 'Admin Viewer',
                'username' => 'viewer01',
                'kata_sandi' => Hash::make('viewer123'),
                'peran' => 'viewer',
                'email_terverifikasi_pada' => now(),
            ]
        );

        // Create Read Only User (hanya lihat)
        User::updateOrCreate(
            ['email' => 'readonly@telkom.co.id'],
            [
                'nama' => 'User Read Only',
                'username' => 'readonly01',
                'kata_sandi' => Hash::make('readonly123'),
                // Role legacy "read_only" tidak digunakan. Gunakan viewer (read-only akses).
                'peran' => 'viewer',
                'email_terverifikasi_pada' => now(),
            ]
        );

        $this->command->info('=== Users created successfully! ===');
        $this->command->info('');
        $this->command->info('Super Admin:');
        $this->command->info('  Email: admin@telkom.co.id');
        $this->command->info('  Username: admin01');
        $this->command->info('  Password: @Admintelkom25');
        $this->command->info('');
        $this->command->info('Admin/Viewer (CRUD Proyek):');
        $this->command->info('  Email: viewer@telkom.co.id');
        $this->command->info('  Password: viewer123');
        $this->command->info('');
        $this->command->info('Read Only (Lihat Saja):');
        $this->command->info('  Email: readonly@telkom.co.id');
        $this->command->info('  Password: readonly123');
    }
}
