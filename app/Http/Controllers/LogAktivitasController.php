<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogAktivitasController extends Controller
{
    /**
     * List log aktivitas akses/navigasi.
     * SUPER ADMIN only (enforced by route middleware).
     * Query params:
     * - aksi
     * - search (nama pengguna, ip, deskripsi, path)
     * - page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('log_aktivitas as l')
            ->leftJoin('pengguna as p', 'l.id_pengguna', '=', 'p.id_pengguna')
            ->select([
                'l.id_log',
                'l.id_pengguna',
                DB::raw('p.nama as nama_pengguna'),
                'p.foto as foto_profile',
                'l.aksi',
                'l.deskripsi',
                'l.path',
                'l.method',
                'l.status_code',
                'l.alamat_ip',
                'l.user_agent',
                'l.device_type',
                'l.browser',
                'l.os',
                'l.waktu_kejadian',
            ])
            ->orderByDesc('l.waktu_kejadian')
            ->orderByDesc('l.id_log');

        if ($request->filled('aksi')) {
            $query->where('l.aksi', $request->string('aksi')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('p.nama', 'like', "%{$search}%")
                    ->orWhere('l.alamat_ip', 'like', "%{$search}%")
                    ->orWhere('l.deskripsi', 'like', "%{$search}%")
                    ->orWhere('l.path', 'like', "%{$search}%")
                    ->orWhere('l.aksi', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 20);
        $items = $query->paginate($perPage);

        $normalized = collect($items->items())->map(function ($item) {
            if (!empty($item->foto_profile)) {
                $item->foto_profile = url('storage/' . $item->foto_profile);
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $normalized,
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
}
