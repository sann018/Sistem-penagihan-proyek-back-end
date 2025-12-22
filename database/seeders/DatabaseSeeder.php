<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Penagihan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call SuperAdminSeeder to create super admin user
        $this->call(SuperAdminSeeder::class);

        // Call ActivitySeeder to create sample activity logs
        $this->call(ActivitySeeder::class);

        // Create test user with viewer role
        User::create([
            'nama' => 'Test User',
            'email' => 'test@example.com',
            'kata_sandi' => Hash::make('password123'),
            'peran' => 'viewer',
        ]);

        // Create sample invoices
        Penagihan::create([
            'nama_proyek' => 'E-Commerce Platform',
            'nama_mitra' => 'Tech Corp Inc',
            'pid' => 'PID-001',
            'nomor_po' => 'PO-2024-001',
            'phase' => 'Phase 1',
            'status_ct' => 'BELUM CT',
            'status_ut' => 'BELUM UT',
            'rekon_nilai' => 25000000,
            'rekon_material' => 'BELUM REKON',
            'pelurusan_material' => 'BELUM LURUS',
            'status_procurement' => 'ANTRI PERIV',
            'status' => 'paid',
            'tanggal_invoice' => '2024-01-15',
            'tanggal_jatuh_tempo' => '2024-02-15',
            'catatan' => 'Full payment received',
        ]);

        Penagihan::create([
            'nama_proyek' => 'Mobile App Development',
            'nama_mitra' => 'Digital Solutions Ltd',
            'pid' => 'PID-002',
            'nomor_po' => 'PO-2024-002',
            'phase' => 'Phase 2',
            'status_ct' => 'BELUM CT',
            'status_ut' => 'BELUM UT',
            'rekon_nilai' => 35000000,
            'rekon_material' => 'BELUM REKON',
            'pelurusan_material' => 'BELUM LURUS',
            'status_procurement' => 'ANTRI PERIV',
            'status' => 'pending',
            'tanggal_invoice' => '2024-02-01',
            'tanggal_jatuh_tempo' => '2024-03-01',
            'catatan' => 'Awaiting payment',
        ]);

        Penagihan::create([
            'nama_proyek' => 'System Integration',
            'nama_mitra' => 'Future Tech Co',
            'pid' => 'PID-003',
            'nomor_po' => 'PO-2024-003',
            'phase' => 'Phase 3',
            'status_ct' => 'BELUM CT',
            'status_ut' => 'BELUM UT',
            'rekon_nilai' => 50000000,
            'rekon_material' => 'BELUM REKON',
            'pelurusan_material' => 'BELUM LURUS',
            'status_procurement' => 'ANTRI PERIV',
            'status' => 'overdue',
            'tanggal_invoice' => '2024-01-20',
            'tanggal_jatuh_tempo' => '2024-02-20',
            'catatan' => 'Payment overdue',
        ]);

        Penagihan::create([
            'nama_proyek' => 'Cloud Migration',
            'nama_mitra' => 'Global Systems',
            'pid' => 'PID-004',
            'nomor_po' => 'PO-2024-004',
            'phase' => 'Phase 4',
            'status_ct' => 'BELUM CT',
            'status_ut' => 'BELUM UT',
            'rekon_nilai' => 45000000,
            'rekon_material' => 'BELUM REKON',
            'pelurusan_material' => 'BELUM LURUS',
            'status_procurement' => 'ANTRI PERIV',
            'status' => 'pending',
            'tanggal_invoice' => '2024-03-01',
            'tanggal_jatuh_tempo' => '2024-04-01',
            'catatan' => 'Cloud infrastructure project',
        ]);

        Penagihan::create([
            'nama_proyek' => 'Website Redesign',
            'nama_mitra' => 'Creative Agency',
            'pid' => 'PID-005',
            'nomor_po' => 'PO-2024-005',
            'phase' => 'Phase 5',
            'status_ct' => 'BELUM CT',
            'status_ut' => 'BELUM UT',
            'rekon_nilai' => 20000000,
            'rekon_material' => 'BELUM REKON',
            'pelurusan_material' => 'BELUM LURUS',
            'status_procurement' => 'ANTRI PERIV',
            'status' => 'cancelled',
            'tanggal_invoice' => '2024-02-15',
            'tanggal_jatuh_tempo' => '2024-03-15',
            'catatan' => 'Project cancelled by client',
        ]);
    }
}

