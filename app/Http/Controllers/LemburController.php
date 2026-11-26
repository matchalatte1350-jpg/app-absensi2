<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LemburController extends Controller
{
    private function resolvePk(string $table): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        if (Schema::hasColumn($table, 'id')) {
            return 'id';
        }

        $singular = rtrim($table, 's');
        $customPk = 'id_' . $singular;
        if (Schema::hasColumn($table, $customPk)) {
            return $customPk;
        }

        $columns = Schema::getColumnListing($table);
        foreach ($columns as $col) {
            if (str_starts_with($col, 'id')) {
                return $col;
            }
        }

        return $columns[0] ?? null;
    }

    private function applyOrder($query, string $table, string $direction = 'desc')
    {
        $pk = $this->resolvePk($table);
        if ($pk) {
            return $query->orderBy($table . '.' . $pk, $direction);
        }

        return $query;
    }

    private function resolveUserId(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $userPk = $this->resolvePk('users') ?? 'id';
        $val = data_get($user, $userPk) ?? data_get($user, 'id') ?? Auth::id();

        return $val !== null ? (int) $val : null;
    }

    private function resolveKaryawanId(): ?int
    {
        $user = Auth::user();
        if (!$user || !Schema::hasTable('karyawans')) {
            return null;
        }

        $kryPk = $this->resolvePk('karyawans') ?? 'id';
        $userId = $this->resolveUserId();

        if ($userId !== null) {
            foreach (['id_user', 'user_id'] as $uCol) {
                if (Schema::hasColumn('karyawans', $uCol)) {
                    $byUser = DB::table('karyawans')->where($uCol, $userId)->first();
                    if ($byUser) {
                        return (int) data_get($byUser, $kryPk);
                    }
                }
            }
        }

        if (!empty($user->email) && Schema::hasColumn('karyawans', 'email')) {
            $byEmail = DB::table('karyawans')->where('email', $user->email)->first();
            if ($byEmail) {
                return (int) data_get($byEmail, $kryPk);
            }
        }

        if (!empty($user->name)) {
            foreach (['nama_lengkap', 'nama', 'name'] as $col) {
                if (Schema::hasColumn('karyawans', $col)) {
                    $byName = DB::table('karyawans')->where($col, $user->name)->first();
                    if ($byName) {
                        return (int) data_get($byName, $kryPk);
                    }
                }
            }
        }

        return null;
    }

    private function findFirstColumn(string $table, array $candidates): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    private function resolveValidStatusForTable(string $table, string $desiredStatus = 'Menunggu'): string
    {
        $cleanDesired = trim($desiredStatus);
        if ($cleanDesired === '') {
            $cleanDesired = 'Menunggu';
        }

        $candidates = [
            $cleanDesired,
            strtolower($cleanDesired),
            strtoupper($cleanDesired),
            ucfirst(strtolower($cleanDesired)),
            'menunggu',
            'Menunggu',
            'MENUNGGU',
            'pending',
            'Pending',
            'PENDING',
            'diajukan',
            'Diajukan',
            'DIAJUKAN',
        ];

        try {
            if (Schema::hasTable($table) && DB::getDriverName() === 'pgsql') {
                $sql = "SELECT pg_get_constraintdef(c.oid) AS def "
                    . "FROM pg_constraint c "
                    . "JOIN pg_class t ON c.conrelid = t.oid "
                    . "JOIN pg_namespace n ON t.relnamespace = n.oid "
                    . "WHERE n.nspname = current_schema() "
                    . "AND t.relname = '" . addslashes($table) . "' "
                    . "AND c.contype = 'c'";

                $checks = DB::select($sql);
                $allowedLiterals = [];

                foreach ($checks as $chk) {
                    $def = (string) ($chk->def ?? '');
                    if (stripos($def, 'status') === false) {
                        continue;
                    }
                    if (preg_match_all("/'([^']+)'/", $def, $matches)) {
                        foreach ($matches[1] as $lit) {
                            $allowedLiterals[] = $lit;
                        }
                    }
                }

                if (!empty($allowedLiterals)) {
                    foreach ($candidates as $cand) {
                        if (in_array($cand, $allowedLiterals, true)) {
                            return $cand;
                        }
                    }

                    $lowerMap = [];
                    foreach ($allowedLiterals as $lit) {
                        $lowerMap[strtolower($lit)] = $lit;
                    }

                    $mapped = $lowerMap[strtolower($cleanDesired)]
                        ?? $lowerMap['menunggu']
                        ?? $lowerMap['pending']
                        ?? $lowerMap['diajukan']
                        ?? $allowedLiterals[0];

                    return (string) $mapped;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal membaca constraint
        }

        try {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'status')) {
                $existingStatuses = DB::table($table)
                    ->whereNotNull('status')
                    ->pluck('status')
                    ->map(fn ($s) => (string) $s)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($existingStatuses as $existing) {
                    if (strtolower($existing) === strtolower($cleanDesired)) {
                        return $existing;
                    }
                }

                foreach (['menunggu', 'pending', 'diajukan'] as $fallbackKey) {
                    foreach ($existingStatuses as $existing) {
                        if (str_contains(strtolower($existing), $fallbackKey)) {
                            return $existing;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        return $cleanDesired;
    }

    private function fillNotNullColumnsPgsql(string $table, array $payload, array $context = []): array
    {
        try {
            if (!Schema::hasTable($table) || DB::getDriverName() !== 'pgsql') {
                return $payload;
            }

            $sql = 'SELECT column_name, data_type FROM information_schema.columns '
                . 'WHERE table_schema = current_schema() '
                . "AND table_name = '" . addslashes($table) . "' "
                . "AND is_nullable = 'NO' "
                . 'AND column_default IS NULL '
                . "AND is_identity = 'NO'";

            $notNullCols = DB::select($sql);

            foreach ($notNullCols as $colInfo) {
                $colName = (string) ($colInfo->column_name ?? '');
                if ($colName === '' || array_key_exists($colName, $payload)) {
                    continue;
                }

                $dataType = strtolower((string) ($colInfo->data_type ?? ''));
                $lowerCol = strtolower($colName);

                if (
                    str_contains($dataType, 'int') ||
                    str_contains($dataType, 'numeric') ||
                    str_contains($dataType, 'decimal') ||
                    str_contains($dataType, 'double') ||
                    str_contains($dataType, 'real')
                ) {
                    if (str_contains($lowerCol, 'user') && !empty($context['user_id'])) {
                        $payload[$colName] = (int) $context['user_id'];
                    } elseif (str_contains($lowerCol, 'karyawan') && !empty($context['karyawan_id'])) {
                        $payload[$colName] = (int) $context['karyawan_id'];
                    } elseif (str_starts_with($lowerCol, 'id_')) {
                        $maxId = DB::table($table)->max($colName);
                        $payload[$colName] = ((int) $maxId) + 1;
                    } else {
                        $payload[$colName] = 0;
                    }
                } elseif (str_contains($dataType, 'date') && !str_contains($dataType, 'time')) {
                    $payload[$colName] = $context['tanggal_lembur'] ?? Carbon::now('Asia/Jakarta')->addDay()->toDateString();
                } elseif (str_contains($dataType, 'timestamp') || str_contains($dataType, 'time')) {
                    $payload[$colName] = $context['now'] ?? Carbon::now('Asia/Jakarta');
                } elseif (str_contains($dataType, 'bool')) {
                    $payload[$colName] = false;
                } elseif (str_contains($lowerCol, 'status')) {
                    $payload[$colName] = $this->resolveValidStatusForTable($table, (string) ($context['status'] ?? 'Menunggu'));
                } else {
                    $payload[$colName] = $context['keterangan'] ?? '-';
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal membaca metadata
        }

        return $payload;
    }

    private function computeDurasiJam(string $jamMulai, string $jamSelesai): int
    {
        try {
            $start = Carbon::createFromFormat('H:i', substr($jamMulai, 0, 5));
            $end = Carbon::createFromFormat('H:i', substr($jamSelesai, 0, 5));

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            $minutes = $start->diffInMinutes($end);
            $hours = (int) ceil($minutes / 60);

            return max(1, $hours);
        } catch (\Throwable $e) {
            return 2;
        }
    }

    private function renderLemburView($riwayatLembur, string $defaultTanggal)
    {
        foreach ([
            'lembur-index',
            'lembur.index',
            'lembur.create',
            'lembur',
        ] as $viewName) {
            if (view()->exists($viewName)) {
                return view($viewName, [
                    'riwayatLembur' => $riwayatLembur,
                    'lemburs' => $riwayatLembur,
                    'defaultTanggal' => $defaultTanggal,
                ]);
            }
        }

        $bladeHtml = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Lembur - PT Generasi Tirta Abadi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f4f5f7] text-slate-800 min-h-screen py-6 px-4">
    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has(\'dashboard\') ? route(\'dashboard\') : url(\'/dashboard\');
        $storeUrl = \Illuminate\Support\Facades\Route::has(\'lembur.store\') ? route(\'lembur.store\') : url(\'/lembur\');
    @endphp
    <div class="max-w-3xl mx-auto space-y-5">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 px-5 py-4 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Pengajuan Lembur</h1>
                <p class="text-xs text-slate-500 mt-0.5">PT. Generasi Tirta Abadi</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition">Kembali</a>
        </div>

        @if(session(\'success\'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-medium">{{ session(\'success\') }}</div>
        @endif

        @if(session(\'error\'))
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs sm:text-sm font-medium">{{ session(\'error\') }}</div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-5 sm:p-6">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Form Pengajuan</h2>
            <form action="{{ $storeUrl }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Rencana Lembur</label>
                    <input type="date" name="tanggal_lembur" value="{{ old(\'tanggal_lembur\', $defaultTanggal) }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jam Mulai</label>
                        <input type="time" name="jam_mulai" value="{{ old(\'jam_mulai\', \'17:00\') }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jam Selesai</label>
                        <input type="time" name="jam_selesai" value="{{ old(\'jam_selesai\', \'19:00\') }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan Pekerjaan</label>
                    <textarea name="keterangan" rows="3" required placeholder="Contoh: menyelesaikan laporan bulanan" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm">{{ old(\'keterangan\') }}</textarea>
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-extrabold tracking-wide uppercase transition">KIRIM PENGAJUAN</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Riwayat Pengajuan Lembur</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-[11px] uppercase border-b border-slate-200">
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Durasi</th>
                            <th class="py-3 px-4">Keterangan</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse(($riwayatLembur ?? collect()) as $item)
                            @php
                                $tglRaw = data_get($item, \'tanggal_lembur\') ?? data_get($item, \'tanggal\') ?? data_get($item, \'tgl_lembur\') ?? \'-\';
                                $durasiVal = data_get($item, \'durasi\') ?? data_get($item, \'durasi_jam\') ?? data_get($item, \'jumlah_jam\') ?? \'-\';
                                $ketVal = data_get($item, \'keterangan\') ?? data_get($item, \'alasan\') ?? data_get($item, \'pekerjaan\') ?? \'-\';
                                $statusVal = data_get($item, \'status\') ?? \'menunggu\';
                            @endphp
                            <tr>
                                <td class="py-3 px-4">{{ $tglRaw }}</td>
                                <td class="py-3 px-4">{{ $durasiVal }}</td>
                                <td class="py-3 px-4">{{ $ketVal }}</td>
                                <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">{{ $statusVal }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 px-4 text-center text-slate-400">Belum ada pengajuan lembur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>';

        return Blade::render($bladeHtml, [
            'riwayatLembur' => $riwayatLembur,
            'lemburs' => $riwayatLembur,
            'defaultTanggal' => $defaultTanggal,
        ]);
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $riwayat = collect();
        if (Schema::hasTable('lemburs')) {
            try {
                $riwayat = $this->applyOrder(DB::table('lemburs'), 'lemburs', 'desc')->limit(50)->get();
            } catch (\Throwable $e) {
                $riwayat = collect();
            }
        }

        $defaultTanggal = Carbon::now('Asia/Jakarta')->addDay()->toDateString();

        return $this->renderLemburView($riwayat, $defaultTanggal);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Schema::hasTable('lemburs')) {
            return back()->withInput()->with('error', 'Tabel lemburs belum tersedia di database.');
        }

        $validated = $request->validate([
            'tanggal_lembur' => ['nullable', 'date'],
            'tanggal' => ['nullable', 'date'],
            'jam_mulai' => ['nullable', 'string', 'max:20'],
            'jam_selesai' => ['nullable', 'string', 'max:20'],
            'durasi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now('Asia/Jakarta');
        $tanggalLembur = trim((string) ($validated['tanggal_lembur'] ?? $validated['tanggal'] ?? $now->copy()->addDay()->toDateString()));
        $jamMulai = trim((string) ($validated['jam_mulai'] ?? '17:00'));
        $jamSelesai = trim((string) ($validated['jam_selesai'] ?? '19:00'));
        $keterangan = trim((string) ($validated['keterangan'] ?? $validated['alasan'] ?? 'Lembur pekerjaan'));
        $durasiJam = $this->computeDurasiJam($jamMulai, $jamSelesai);
        $durasiText = trim((string) ($validated['durasi'] ?? ($durasiJam . ' Jam')));

        $userId = $this->resolveUserId();
        $karyawanId = $this->resolveKaryawanId();
        $validStatus = $this->resolveValidStatusForTable('lemburs', 'Menunggu');

        $payload = [];

        if ($karyawanId !== null) {
            foreach (['id_karyawan', 'karyawan_id'] as $kCol) {
                if (Schema::hasColumn('lemburs', $kCol)) {
                    $payload[$kCol] = $karyawanId;
                    break;
                }
            }
        }

        if ($userId !== null) {
            foreach (['id_user', 'user_id'] as $uCol) {
                if (Schema::hasColumn('lemburs', $uCol)) {
                    $payload[$uCol] = $userId;
                    break;
                }
            }
        }

        $tglCol = $this->findFirstColumn('lemburs', ['tanggal_lembur', 'tanggal', 'tgl_lembur']);
        if ($tglCol) {
            $payload[$tglCol] = $tanggalLembur;
        }

        $jamMulaiCol = $this->findFirstColumn('lemburs', ['jam_mulai', 'waktu_mulai', 'mulai']);
        if ($jamMulaiCol) {
            $payload[$jamMulaiCol] = $jamMulai;
        }

        $jamSelesaiCol = $this->findFirstColumn('lemburs', ['jam_selesai', 'waktu_selesai', 'selesai']);
        if ($jamSelesaiCol) {
            $payload[$jamSelesaiCol] = $jamSelesai;
        }

        $durasiCol = $this->findFirstColumn('lemburs', ['durasi', 'durasi_jam', 'jumlah_jam', 'lama_lembur']);
        if ($durasiCol) {
            $colType = '';
            try {
                if (DB::getDriverName() === 'pgsql') {
                    $colInfo = DB::selectOne(
                        "SELECT data_type FROM information_schema.columns "
                        . "WHERE table_schema = current_schema() AND table_name = 'lemburs' AND column_name = '" . addslashes($durasiCol) . "'"
                    );
                    $colType = strtolower((string) ($colInfo->data_type ?? ''));
                }
            } catch (\Throwable $e) {
                $colType = '';
            }

            if (str_contains($colType, 'int') || str_contains($colType, 'numeric') || str_contains($colType, 'decimal')) {
                $payload[$durasiCol] = $durasiJam;
            } else {
                $payload[$durasiCol] = $durasiText;
            }
        }

        $ketCol = $this->findFirstColumn('lemburs', ['keterangan', 'alasan', 'pekerjaan', 'deskripsi']);
        if ($ketCol) {
            $payload[$ketCol] = $keterangan;
        }

        $statusCol = $this->findFirstColumn('lemburs', ['status', 'status_pengajuan']);
        if ($statusCol) {
            $payload[$statusCol] = $validStatus;
        }

        $payload = $this->fillNotNullColumnsPgsql('lemburs', $payload, [
            'user_id' => $userId,
            'karyawan_id' => $karyawanId,
            'tanggal_lembur' => $tanggalLembur,
            'keterangan' => $keterangan,
            'status' => $validStatus,
            'now' => $now,
        ]);

        if (Schema::hasColumn('lemburs', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn('lemburs', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        try {
            $pk = $this->resolvePk('lemburs');
            if (
                DB::getDriverName() === 'pgsql'
                && !empty($pk)
                && Schema::hasColumn('lemburs', $pk)
                && !array_key_exists($pk, $payload)
            ) {
                DB::table('lemburs')->insertGetId($payload, $pk);
            } else {
                DB::table('lemburs')->insert($payload);
            }
        } catch (\Throwable $e) {
            try {
                if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $ignore) {
                // Abaikan
            }

            return back()->withInput()->with('error', 'Gagal menyimpan pengajuan lembur: ' . $e->getMessage());
        }

        $redirectTarget = \Illuminate\Support\Facades\Route::has('lembur.index')
            ? redirect()->route('lembur.index')
            : redirect('/lembur');

        return $redirectTarget->with('success', 'Pengajuan lembur berhasil dikirim.');
    }
}