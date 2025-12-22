<?php

namespace Database\Seeders;

use App\Models\AktivitasSistem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::where('email', 'admin@telkom.co.id')->first();
        $viewer = User::where('email', 'viewer@telkom.co.id')->first();

        if (!$superAdmin || !$viewer) {
            return;
        }

        // Sample activity logs
        $activities = [
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Login',
                'tipe' => 'login',
                'deskripsi' => 'Super Admin berhasil login ke sistem',
                'tabel_yang_diubah' => null,
                'id_record_yang_diubah' => null,
                'waktu_aksi' => now()->subDays(2)->subHours(5),
            ],
            [
                'pengguna_id' => $viewer->id,
                'nama_pengguna' => $viewer->nama,
                'aksi' => 'Tambah Proyek',
                'tipe' => 'create',
                'deskripsi' => 'Menambahkan proyek baru "Website Monitoring System"',
                'tabel_yang_diubah' => 'penagihan',
                'id_record_yang_diubah' => 1,
                'data_sesudah' => json_encode([
                    'nama_proyek' => 'Website Monitoring System',
                    'status' => 'BELUM CT'
                ]),
                'waktu_aksi' => now()->subDays(2)->subHours(4),
            ],
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Edit Proyek',
                'tipe' => 'edit',
                'deskripsi' => 'Mengubah status proyek menjadi "SUDAH CT"',
                'tabel_yang_diubah' => 'penagihan',
                'id_record_yang_diubah' => 1,
                'data_sebelum' => json_encode(['status_ct' => 'BELUM CT']),
                'data_sesudah' => json_encode(['status_ct' => 'SUDAH CT']),
                'waktu_aksi' => now()->subDays(2)->subHours(3),
            ],
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Tambah User',
                'tipe' => 'create',
                'deskripsi' => 'Menambahkan user baru dengan email test@example.com',
                'tabel_yang_diubah' => 'pengguna',
                'id_record_yang_diubah' => 3,
                'data_sesudah' => json_encode([
                    'nama' => 'Test User',
                    'email' => 'test@example.com',
                    'peran' => 'viewer'
                ]),
                'waktu_aksi' => now()->subDays(2)->subHours(2),
            ],
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Update Profile',
                'tipe' => 'edit',
                'deskripsi' => 'Mengubah informasi profil diri sendiri',
                'tabel_yang_diubah' => 'pengguna',
                'id_record_yang_diubah' => $superAdmin->id,
                'data_sebelum' => json_encode(['nama' => 'Admin']),
                'data_sesudah' => json_encode(['nama' => 'Super Admin']),
                'waktu_aksi' => now()->subDays(1)->subHours(3),
            ],
            [
                'pengguna_id' => $viewer->id,
                'nama_pengguna' => $viewer->nama,
                'aksi' => 'Login',
                'tipe' => 'login',
                'deskripsi' => 'User Admin Viewer berhasil login ke sistem',
                'tabel_yang_diubah' => null,
                'id_record_yang_diubah' => null,
                'waktu_aksi' => now()->subDays(1)->subHours(2),
            ],
            [
                'pengguna_id' => $viewer->id,
                'nama_pengguna' => $viewer->nama,
                'aksi' => 'Edit Proyek',
                'tipe' => 'edit',
                'deskripsi' => 'Mengubah nilai rekon proyek Website Monitoring System',
                'tabel_yang_diubah' => 'penagihan',
                'id_record_yang_diubah' => 1,
                'data_sebelum' => json_encode(['rekon_nilai' => 25000000]),
                'data_sesudah' => json_encode(['rekon_nilai' => 30000000]),
                'waktu_aksi' => now()->subDays(1)->subHour(),
            ],
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Hapus User',
                'tipe' => 'delete',
                'deskripsi' => 'Menghapus user dengan email readonly@telkom.co.id',
                'tabel_yang_diubah' => 'pengguna',
                'id_record_yang_diubah' => 4,
                'data_sebelum' => json_encode([
                    'nama' => 'User Read Only',
                    'email' => 'readonly@telkom.co.id'
                ]),
                'waktu_aksi' => now()->subHours(10),
            ],
            [
                'pengguna_id' => $superAdmin->id,
                'nama_pengguna' => $superAdmin->nama,
                'aksi' => 'Login',
                'tipe' => 'login',
                'deskripsi' => 'Super Admin berhasil login ke sistem',
                'tabel_yang_diubah' => null,
                'id_record_yang_diubah' => null,
                'waktu_aksi' => now()->subHours(5),
            ],
            [
                'pengguna_id' => $viewer->id,
                'nama_pengguna' => $viewer->nama,
                'aksi' => 'Tambah Proyek',
                'tipe' => 'create',
                'deskripsi' => 'Menambahkan proyek baru "Mobile App Development"',
                'tabel_yang_diubah' => 'penagihan',
                'id_record_yang_diubah' => 2,
                'data_sesudah' => json_encode([
                    'nama_proyek' => 'Mobile App Development',
                    'status' => 'BELUM CT'
                ]),
                'waktu_aksi' => now()->subHours(2),
            ],
        ];

        foreach ($activities as $activity) {
            AktivitasSistem::create($activity);
        }

        $this->command->info('Activity logs seeded successfully!');
    }
}
