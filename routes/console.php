<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Notifikasi;
use App\Models\Penagihan;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifikasi:demo {--userId=} {--all-admins}', function () {
    $userId = $this->option('userId');
    $allAdmins = (bool) $this->option('all-admins');

    $recipients = collect();

    if (!empty($userId)) {
        $user = User::query()->where('id_pengguna', (int) $userId)->first();
        if (!$user) {
            $this->error("User dengan id_pengguna={$userId} tidak ditemukan");
            return 1;
        }
        $recipients = collect([$user]);
    } else {
        // Default: seed to all admin & super_admin
        $recipients = User::query()
            ->whereIn('peran', ['super_admin', 'admin'])
            ->get();

        if (!$allAdmins && $recipients->isEmpty()) {
            $this->error('Tidak ada user admin/super_admin untuk diberi notifikasi');
            return 1;
        }
    }

    $projects = Penagihan::query()
        ->where('status', 'pending')
        ->orderByRaw('CASE WHEN tanggal_jatuh_tempo IS NULL THEN 1 ELSE 0 END')
        ->orderBy('tanggal_jatuh_tempo', 'asc')
        ->limit(3)
        ->get([
            'pid',
            'nama_proyek',
            'prioritas',
            'tanggal_jatuh_tempo',
            'status_ct',
            'status_ut',
            'rekap_boq',
            'rekon_material',
            'pelurusan_material',
            'status_procurement',
        ]);

    $fallbackProjects = collect([
        (object) [
            'pid' => 'PID-DEMO-001',
            'nama_proyek' => 'Proyek Demo Tenggat',
            'prioritas' => null,
            'tanggal_jatuh_tempo' => now()->addDays(3)->toDateString(),
            'status_ct' => 'Belum CT',
            'status_ut' => 'Belum UT',
            'rekap_boq' => 'Belum Rekap',
            'rekon_material' => 'Belum Rekon',
            'pelurusan_material' => 'Belum Lurus',
            'status_procurement' => 'Antri Periv',
        ],
        (object) [
            'pid' => 'PID-DEMO-002',
            'nama_proyek' => 'Proyek Demo Prioritas',
            'prioritas' => 1,
            'tanggal_jatuh_tempo' => now()->addDays(7)->toDateString(),
            'status_ct' => 'Sudah CT',
            'status_ut' => 'Belum UT',
            'rekap_boq' => 'Belum Rekap',
            'rekon_material' => 'Belum Rekon',
            'pelurusan_material' => 'Belum Lurus',
            'status_procurement' => 'Antri Periv',
        ],
    ]);

    $selectedProjects = $projects->isNotEmpty() ? $projects : $fallbackProjects;

    $calcProgress = function ($project): int {
        return method_exists($project, 'calculateProgressPercent')
            ? $project->calculateProgressPercent()
            : 0;
    };

    $created = 0;

    foreach ($recipients as $recipient) {
        $recipientId = (int) $recipient->id_pengguna;

        foreach ($selectedProjects as $project) {
            $pid = (string) ($project->pid ?? '');
            $nama = (string) ($project->nama_proyek ?? $pid);
            $progress = $calcProgress($project);

            // 1) Deadline notification demo
            $jenis = 'h_minus_3';
            $exists = Notifikasi::query()
                ->where('id_penerima', $recipientId)
                ->where('jenis_notifikasi', $jenis)
                ->where('referensi_tabel', 'data_proyek')
                ->where('referensi_id', $pid)
                ->exists();

            if (!$exists) {
                Notifikasi::query()->create([
                    'id_penerima' => $recipientId,
                    'judul' => 'Reminder: Tenggat proyek mendekat (H-3)',
                    'isi_notifikasi' => "Proyek {$nama} (PID: {$pid}) mendekati tenggat. Progres saat ini {$progress}%.", 
                    'jenis_notifikasi' => $jenis,
                    'status' => 'terkirim',
                    'prioritas' => 3,
                    'referensi_tabel' => 'data_proyek',
                    'referensi_id' => $pid,
                    'link_terkait' => "/projects/{$pid}",
                    'metadata' => [
                        'pid' => $pid,
                        'nama_proyek' => $nama,
                        'progress_persen' => $progress,
                        'tanggal_jatuh_tempo' => (string) ($project->tanggal_jatuh_tempo ?? null),
                    ],
                    'waktu_dikirim' => now(),
                ]);
                $created++;
            }

            // 2) Priority notification demo (only if project priority set)
            if (!is_null($project->prioritas)) {
                $jenis = 'prioritas_berubah';
                $exists = Notifikasi::query()
                    ->where('id_penerima', $recipientId)
                    ->where('jenis_notifikasi', $jenis)
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', $pid)
                    ->exists();

                if (!$exists) {
                    Notifikasi::query()->create([
                        'id_penerima' => $recipientId,
                        'judul' => 'Pemberitahuan: Proyek prioritas',
                        'isi_notifikasi' => "Proyek {$nama} (PID: {$pid}) ditandai sebagai prioritas. Progres saat ini {$progress}%.", 
                        'jenis_notifikasi' => $jenis,
                        'status' => 'terkirim',
                        'prioritas' => ((int) $project->prioritas) === 1 ? 3 : 2,
                        'referensi_tabel' => 'data_proyek',
                        'referensi_id' => $pid,
                        'link_terkait' => "/projects/{$pid}",
                        'metadata' => [
                            'pid' => $pid,
                            'nama_proyek' => $nama,
                            'progress_persen' => $progress,
                            'prioritas' => (int) $project->prioritas,
                        ],
                        'waktu_dikirim' => now(),
                    ]);
                    $created++;
                }
            }
        }
    }

    $this->info("Selesai. Notifikasi baru dibuat: {$created}");
    return 0;
})->purpose('Buat demo notifikasi proyek agar terlihat di web (admin/super_admin)');

Artisan::command('notifikasi:daily-reminder', function () {
    $this->info('=== DAILY PROJECT REMINDER ===');
    $this->info('Memproses notifikasi proyek harian...');

    // Get all admin & super_admin
    $admins = User::query()
        ->whereIn('peran', ['super_admin', 'admin'])
        ->get();

    if ($admins->isEmpty()) {
        $this->warn('Tidak ada admin/super_admin untuk diberi notifikasi');
        return 0;
    }

    $this->info("Target penerima: {$admins->count()} admin/super_admin");

    // Get pending projects
    $projects = Penagihan::query()
        ->where('status', 'pending')
        ->get([
            'pid',
            'nama_proyek',
            'prioritas',
            'tanggal_jatuh_tempo',
            'status_ct',
            'status_ut',
            'rekap_boq',
            'rekon_material',
            'pelurusan_material',
            'status_procurement',
        ]);

    $this->info("Proyek pending: {$projects->count()}");

    $calcProgress = function ($project): int {
        $steps = [
            ['status_ct', 'Sudah CT'],
            ['status_ut', 'Sudah UT'],
            ['rekap_boq', 'Sudah Rekap'],
            ['rekon_material', 'Sudah Rekon'],
            ['pelurusan_material', 'Sudah Lurus'],
            ['status_procurement', 'OTW Reg'],
        ];

        $done = 0;
        foreach ($steps as [$field, $target]) {
            if (($project->{$field} ?? null) === $target) {
                $done++;
            }
        }

        return (int) round(($done / count($steps)) * 100);
    };

    $today = now()->startOfDay();
    $updated = 0;
    $created = 0;
    $deleted = 0;

    foreach ($admins as $admin) {
        $adminId = (int) $admin->id_pengguna;

        foreach ($projects as $project) {
            $pid = (string) $project->pid;
            $nama = (string) ($project->nama_proyek ?? $pid);
            $progress = $calcProgress($project);

            // Delete notifikasi jika proyek sudah 100%
            if ($progress >= 100) {
                $deletedCount = Notifikasi::query()
                    ->where('id_penerima', $adminId)
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', $pid)
                    ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_3', 'h_minus_1', 'jatuh_tempo', 'prioritas_berubah'])
                    ->delete();
                $deleted += $deletedCount;
                continue;
            }

            // Process deadline notifications
            if ($project->tanggal_jatuh_tempo) {
                $due = \Carbon\Carbon::parse($project->tanggal_jatuh_tempo)->startOfDay();
                $days = $today->diffInDays($due, false);

                $dueLabel = $due->format('d M Y');
                $progressLabel = $progress . '%';

                $whenLabel = null;
                if ($days < 0) {
                    $whenLabel = 'terlewat ' . abs($days) . ' hari';
                } elseif ($days === 0) {
                    $whenLabel = 'hari ini';
                } elseif ($days === 1) {
                    $whenLabel = 'besok';
                } else {
                    $whenLabel = 'dalam ' . $days . ' hari';
                }

                $jenis = null;
                $prioritas = null;
                $judul = null;
                $isi = null;

                if ($days < 0) {
                    $jenis = 'jatuh_tempo';
                    $prioritas = 4;
                    $judul = 'Peringatan: Proyek melewati jatuh tempo';
                    $isi = "Proyek {$nama} (PID: {$pid}) sudah melewati jatuh tempo ({$dueLabel}). Progres saat ini {$progressLabel}. Segera selesaikan proyek ini.";
                } elseif ($days <= 1) {
                    $jenis = 'h_minus_1';
                    $prioritas = 4;
                    $judul = 'Reminder: Tenggat proyek sangat dekat (H-1)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}. Segera percepat penyelesaian.";
                } elseif ($days <= 3) {
                    $jenis = 'h_minus_3';
                    $prioritas = 3;
                    $judul = 'Reminder: Tenggat proyek mendekat (H-3)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}. Pastikan progres sesuai target.";
                } elseif ($days <= 7) {
                    $jenis = 'h_minus_7';
                    $prioritas = 2;
                    $judul = 'Reminder: Tenggat proyek (H-7)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}.";
                }

                if ($jenis) {
                    // Update existing or create new
                    $existing = Notifikasi::query()
                        ->where('id_penerima', $adminId)
                        ->where('jenis_notifikasi', $jenis)
                        ->where('referensi_tabel', 'data_proyek')
                        ->where('referensi_id', $pid)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'judul' => $judul,
                            'isi_notifikasi' => $isi,
                            'prioritas' => $prioritas,
                            'metadata' => [
                                'pid' => $pid,
                                'nama_proyek' => $nama,
                                'progress_persen' => $progress,
                                'days_to_deadline' => $days,
                                'tanggal_jatuh_tempo' => $due->toDateString(),
                            ],
                            'status' => 'terkirim',
                            'waktu_dikirim' => now(),
                        ]);
                        $updated++;
                    } else {
                        Notifikasi::query()->create([
                            'id_penerima' => $adminId,
                            'judul' => $judul,
                            'isi_notifikasi' => $isi,
                            'jenis_notifikasi' => $jenis,
                            'status' => 'terkirim',
                            'prioritas' => $prioritas,
                            'referensi_tabel' => 'data_proyek',
                            'referensi_id' => $pid,
                            'link_terkait' => "/projects/{$pid}",
                            'metadata' => [
                                'pid' => $pid,
                                'nama_proyek' => $nama,
                                'progress_persen' => $progress,
                                'days_to_deadline' => $days,
                                'tanggal_jatuh_tempo' => $due->toDateString(),
                            ],
                            'waktu_dikirim' => now(),
                        ]);
                        $created++;
                    }
                }
            }

            // Process priority notifications
            if (!is_null($project->prioritas)) {
                $priorityLevel = (int) $project->prioritas;
                $progressLabel = $progress . '%';

                $existing = Notifikasi::query()
                    ->where('id_penerima', $adminId)
                    ->where('jenis_notifikasi', 'prioritas_berubah')
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', $pid)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'isi_notifikasi' => "Proyek {$nama} (PID: {$pid}) ditandai sebagai prioritas. Progres saat ini {$progressLabel}. Mohon diprioritaskan penyelesaiannya.",
                        'metadata' => [
                            'pid' => $pid,
                            'nama_proyek' => $nama,
                            'progress_persen' => $progress,
                            'prioritas' => $priorityLevel,
                        ],
                        'status' => 'terkirim',
                    ]);
                    $updated++;
                } else {
                    Notifikasi::query()->create([
                        'id_penerima' => $adminId,
                        'judul' => 'Pemberitahuan: Proyek prioritas',
                        'isi_notifikasi' => "Proyek {$nama} (PID: {$pid}) ditandai sebagai prioritas. Progres saat ini {$progressLabel}. Mohon diprioritaskan penyelesaiannya.",
                        'jenis_notifikasi' => 'prioritas_berubah',
                        'status' => 'terkirim',
                        'prioritas' => $priorityLevel === 1 ? 3 : 2,
                        'referensi_tabel' => 'data_proyek',
                        'referensi_id' => $pid,
                        'link_terkait' => "/projects/{$pid}",
                        'metadata' => [
                            'pid' => $pid,
                            'nama_proyek' => $nama,
                            'progress_persen' => $progress,
                            'prioritas' => $priorityLevel,
                        ],
                        'waktu_dikirim' => now(),
                    ]);
                    $created++;
                }
            }
        }
    }

    $this->info("✅ Selesai!");
    $this->info("  - Notifikasi baru: {$created}");
    $this->info("  - Notifikasi diupdate: {$updated}");
    $this->info("  - Notifikasi dihapus (proyek selesai): {$deleted}");

    return 0;
})->purpose('Reminder harian untuk proyek pending (auto-update progress)');
