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
                'kata_sandi' => Hash::make('telkomakses25'), 
                'peran' => 'super_admin',
            ]
        );

        // Create Admin/Viewer (CRUD proyek)
        User::updateOrCreate(
            ['email' => 'viewer@telkom.co.id'],
            [
                'nama' => 'Admin Viewer',
                'kata_sandi' => Hash::make('viewer123'),
                'peran' => 'viewer',
            ]
        );

        // Create Read Only User (hanya lihat)
        User::updateOrCreate(
            ['email' => 'readonly@telkom.co.id'],
            [
                'nama' => 'User Read Only',
                'kata_sandi' => Hash::make('readonly123'),
                'peran' => 'read_only',
            ]
        );

        $this->command->info('=== Users created successfully! ===');
        $this->command->info('');
        $this->command->info('Super Admin:');
        $this->command->info('  Email: admin@telkom.co.id');
        $this->command->info('  Password: telkomakses25');
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
