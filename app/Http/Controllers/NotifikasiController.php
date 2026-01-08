<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Models\Penagihan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    private const FINAL_PROCUREMENT_STATUSES = [
        'sekuler ttd',
        'scan dokumen mitra',
        'otw reg',
    ];
    /**
     * List notifikasi untuk user login.
     * Query params:
     * - status: pending|terkirim|dibaca|gagal
     * - jenis: jenis_notifikasi
     * - page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Auto-generate project alerts for admin/super_admin
        $this->syncProjectNotificationsForUser($user);

        $query = Notifikasi::query()
            ->where('id_penerima', $user->id_pengguna)
            ->orderByDesc('waktu_dibuat')
            ->orderByDesc('id_notifikasi');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('jenis')) {
            $query->where('jenis_notifikasi', $request->string('jenis')->toString());
        }

        $perPage = (int) $request->get('per_page', 15);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    private function syncProjectNotificationsForUser($user): void
    {
        if (!$user || !in_array($user->role, ['super_admin', 'admin'], true)) {
            return;
        }

        $userId = (int) $user->id_pengguna;

        // Cleanup notifikasi lama untuk proyek yang sudah selesai / tidak pending
        $stalePids = Notifikasi::query()
            ->where('id_penerima', $userId)
            ->where('referensi_tabel', 'data_proyek')
            ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_5', 'h_minus_3', 'h_minus_1', 'jatuh_tempo', 'prioritas_berubah'])
            ->pluck('referensi_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($stalePids)) {
            $cleanupProjects = Penagihan::query()
                ->whereIn('pid', $stalePids)
                ->get([
                    'pid',
                    'status',
                    'status_ct',
                    'status_ut',
                    'rekap_boq',
                    'rekon_material',
                    'pelurusan_material',
                    'status_procurement',
                ]);

            // Hapus notifikasi yang referensi proyeknya sudah tidak ada.
            // Tanpa ini, notifikasi bisa "nyangkut" terus walau proyek dihapus.
            $foundPids = $cleanupProjects->pluck('pid')->map(fn ($v) => (string) $v)->all();
            $missingPids = array_values(array_diff(array_map('strval', $stalePids), $foundPids));
            if (!empty($missingPids)) {
                Notifikasi::query()
                    ->where('id_penerima', $userId)
                    ->where('referensi_tabel', 'data_proyek')
                    ->whereIn('referensi_id', $missingPids)
                    ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_5', 'h_minus_3', 'h_minus_1', 'jatuh_tempo', 'prioritas_berubah'])
                    ->delete();
            }

            foreach ($cleanupProjects as $p) {
                $proc = strtolower(trim((string) ($p->status_procurement ?? '')));
                $isFinalProcurement = in_array($proc, self::FINAL_PROCUREMENT_STATUSES, true);

                $shouldRemove = ($p->status ?? null) !== 'pending'
                    || (method_exists($p, 'isCompleted') && $p->isCompleted())
                    || (method_exists($p, 'calculateProgressPercent') && $p->calculateProgressPercent() >= 100)
                    || $isFinalProcurement;

                if ($shouldRemove) {
                    Notifikasi::query()
                        ->where('id_penerima', $userId)
                        ->where('referensi_tabel', 'data_proyek')
                        ->where('referensi_id', (string) $p->pid)
                        ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_5', 'h_minus_3', 'h_minus_1', 'jatuh_tempo', 'prioritas_berubah'])
                        ->delete();
                }
            }
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        $h5 = $today->copy()->addDays(5)->endOfDay();

        $projects = Penagihan::query()
            ->where('status', 'pending')
            ->where(function ($q) use ($today, $h5) {
                // Overdue or due within 5 days (by explicit due date)
                $q->where(function ($qq) use ($today, $h5) {
                    $qq->whereNotNull('tanggal_jatuh_tempo')
                        ->where(function ($qqq) use ($today, $h5) {
                            $qqq->whereBetween('tanggal_jatuh_tempo', [$today, $h5])
                                ->orWhere('tanggal_jatuh_tempo', '<', $today);
                        });
                })
                // Or projects that can compute deadline from start date + estimate
                ->orWhere(function ($qq) {
                    $qq->whereNotNull('tanggal_mulai')
                        ->whereNotNull('estimasi_durasi_hari');
                })
                // Or priority projects
                ->orWhereNotNull('prioritas');
            })
            ->get([
                'pid',
                'nama_proyek',
                'prioritas',
                'tanggal_mulai',
                'estimasi_durasi_hari',
                'tanggal_jatuh_tempo',
                'status',
                'status_ct',
                'status_ut',
                'rekap_boq',
                'rekon_material',
                'pelurusan_material',
                'status_procurement',
            ]);

        foreach ($projects as $project) {
            $progress = method_exists($project, 'calculateProgressPercent')
                ? $project->calculateProgressPercent()
                : 0;

            $proc = strtolower(trim((string) ($project->status_procurement ?? '')));
            $isFinalProcurement = in_array($proc, self::FINAL_PROCUREMENT_STATUSES, true);

            // Jika proyek sudah selesai, hapus notifikasi terkait (jangan buat lagi)
            if (
                ($project->status ?? null) !== 'pending'
                || (method_exists($project, 'isCompleted') && $project->isCompleted())
                || $progress >= 100
                || $isFinalProcurement
            ) {
                Notifikasi::query()
                    ->where('id_penerima', $userId)
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', (string) $project->pid)
                    ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_5', 'h_minus_3', 'h_minus_1', 'jatuh_tempo', 'prioritas_berubah'])
                    ->delete();
                continue;
            }

            $pid = (string) $project->pid;
            $nama = (string) ($project->nama_proyek ?? $pid);

            // Deadline alerts
            $due = null;
            if ($project->tanggal_jatuh_tempo) {
                $due = Carbon::parse($project->tanggal_jatuh_tempo)->startOfDay();
            } elseif ($project->tanggal_mulai && $project->estimasi_durasi_hari) {
                $due = Carbon::parse($project->tanggal_mulai)
                    ->addDays((int) $project->estimasi_durasi_hari)
                    ->startOfDay();
            }

            if ($due) {
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
                    $prioritas = 1;
                    $judul = 'Peringatan: Proyek melewati jatuh tempo';
                    $isi = "Proyek {$nama} (PID: {$pid}) sudah melewati jatuh tempo ({$dueLabel}). Progres saat ini {$progressLabel}. Segera selesaikan proyek ini.";
                } elseif ($days <= 1) {
                    $jenis = 'h_minus_1';
                    $prioritas = 1;
                    $judul = 'Reminder: Tenggat proyek sangat dekat (H-1)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}. Segera percepat penyelesaian.";
                } elseif ($days <= 3) {
                    $jenis = 'h_minus_3';
                    $prioritas = 1;
                    $judul = 'Reminder: Tenggat proyek mendekat (H-3)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}. Pastikan progres sesuai target.";
                } elseif ($days <= 5) {
                    $jenis = 'h_minus_5';
                    $prioritas = 2;
                    $judul = 'Reminder: Tenggat proyek (H-5)';
                    $isi = "Proyek {$nama} (PID: {$pid}) jatuh tempo {$whenLabel} ({$dueLabel}). Progres saat ini {$progressLabel}.";
                }

                if ($jenis) {
                    $this->upsertProjectNotification(
                        $userId,
                        $jenis,
                        $pid,
                        $judul,
                        $isi,
                        (int) $prioritas,
                        [
                            'pid' => $pid,
                            'nama_proyek' => $nama,
                            'progress_persen' => $progress,
                            'days_to_deadline' => $days,
                            'tanggal_jatuh_tempo' => $due->toDateString(),
                        ]
                    );
                }
            } else {
                // Jika tidak ada tanggal jatuh tempo (dan tidak bisa dihitung), hapus notifikasi deadline yang mungkin sudah ada.
                Notifikasi::query()
                    ->where('id_penerima', $userId)
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', $pid)
                    ->whereIn('jenis_notifikasi', ['h_minus_7', 'h_minus_5', 'h_minus_3', 'h_minus_1', 'jatuh_tempo'])
                    ->delete();
            }

            // Priority project alerts
            if (!is_null($project->prioritas)) {
                $priorityLevel = (int) $project->prioritas;
                $progressLabel = $progress . '%';
                $this->upsertProjectNotification(
                    $userId,
                    'prioritas_berubah',
                    $pid,
                    'Pemberitahuan: Proyek prioritas',
                    "Proyek {$nama} (PID: {$pid}) ditandai sebagai prioritas. Progres saat ini {$progressLabel}. Mohon diprioritaskan penyelesaiannya.",
                    $priorityLevel === 1 ? 3 : 2,
                    [
                        'pid' => $pid,
                        'nama_proyek' => $nama,
                        'progress_persen' => $progress,
                        'prioritas' => $priorityLevel,
                    ]
                );
            } else {
                // Jika prioritas sudah dibatalkan, hapus notifikasi prioritas yang mungkin masih tersisa.
                Notifikasi::query()
                    ->where('id_penerima', $userId)
                    ->where('referensi_tabel', 'data_proyek')
                    ->where('referensi_id', $pid)
                    ->where('jenis_notifikasi', 'prioritas_berubah')
                    ->delete();
            }
        }
    }

    private function upsertProjectNotification(
        int $userId,
        string $jenis,
        string $pid,
        string $judul,
        string $isi,
        int $prioritas,
        array $metadata
    ): void {
        $existing = Notifikasi::query()
            ->where('id_penerima', $userId)
            ->where('jenis_notifikasi', $jenis)
            ->where('referensi_tabel', 'data_proyek')
            ->where('referensi_id', $pid)
            ->first();

        if ($existing) {
            $updateData = [
                'judul' => $judul,
                'isi_notifikasi' => $isi,
                'prioritas' => $prioritas,
                'link_terkait' => "/projects/{$pid}",
                'metadata' => $metadata,
            ];

            // Jangan reset status dibaca saat user buka halaman notifikasi.
            if ($existing->status !== 'dibaca') {
                $updateData['status'] = 'terkirim';
                $updateData['waktu_dikirim'] = now();
            }

            $existing->update($updateData);
            return;
        }

        Notifikasi::query()->create([
            'id_penerima' => $userId,
            'judul' => $judul,
            'isi_notifikasi' => $isi,
            'jenis_notifikasi' => $jenis,
            'status' => 'terkirim',
            'prioritas' => $prioritas,
            'referensi_tabel' => 'data_proyek',
            'referensi_id' => $pid,
            'link_terkait' => "/projects/{$pid}",
            'metadata' => $metadata,
            'waktu_dikirim' => now(),
        ]);
    }

    /**
     * Mark notifikasi as dibaca.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notif = Notifikasi::query()
            ->where('id_notifikasi', $id)
            ->where('id_penerima', $user->id_pengguna)
            ->first();

        if (!$notif) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan',
            ], 404);
        }

        if ($notif->status !== 'dibaca') {
            $notif->markAsDibaca();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca',
            'data' => $notif,
        ]);
    }

    /**
     * Soft delete notifikasi.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notif = Notifikasi::query()
            ->where('id_notifikasi', $id)
            ->where('id_penerima', $user->id_pengguna)
            ->first();

        if (!$notif) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan',
            ], 404);
        }

        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi dihapus',
        ]);
    }
}
