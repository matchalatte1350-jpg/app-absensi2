<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbsensiController extends Controller
{
    private ?string $cachedAbsensiTable = null;

    private function resolveAbsensiTable(): ?string
    {
        if ($this->cachedAbsensiTable !== null) {
            return $this->cachedAbsensiTable;
        }

        foreach (['absensis', 'absensi', 'presensis', 'presensi', 'riwayat_absensis', 'riwayat_absensi'] as $candidate) {
            if (Schema::hasTable($candidate)) {
                $this->cachedAbsensiTable = $candidate;
                return $candidate;
            }
        }

        try {
            if (DB::getDriverName() === 'pgsql') {
                $rows = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = current_schema()");
                foreach ($rows as $r) {
                    $name = (string) ($r->tablename ?? '');
                    if (str_contains(strtolower($name), 'absen') || str_contains(strtolower($name), 'presensi')) {
                        $this->cachedAbsensiTable = $name;
                        return $name;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        return null;
    }

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

        if ($table !== 'absensis' && Schema::hasColumn($table, 'id_absensi')) {
            return 'id_absensi';
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
        if ($pk && Schema::hasColumn($table, $pk)) {
            return $query->orderBy($table . '.' . $pk, $direction);
        }

        return $query;
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

    private function resolveUserId(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $userPk = $this->resolvePk('users') ?? 'id';
        $val = data_get($user, $userPk) ?? data_get($user, 'id') ?? Auth::id();

        if ($val !== null && $val !== '') {
            return (int) $val;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', $userPk)) {
            $firstId = DB::table('users')->value($userPk);
            if ($firstId !== null) {
                return (int) $firstId;
            }
        }

        return null;
    }

    private function resolveKaryawanId(?int $userId = null): ?int
    {
        if (!Schema::hasTable('karyawans')) {
            return null;
        }

        $kryPk = $this->resolvePk('karyawans') ?? 'id';
        $targetUserId = $userId ?? $this->resolveUserId();

        if ($targetUserId !== null) {
            foreach (['id_user', 'user_id'] as $uCol) {
                if (Schema::hasColumn('karyawans', $uCol)) {
                    $byUser = DB::table('karyawans')->where($uCol, $targetUserId)->first();
                    if ($byUser) {
                        return (int) data_get($byUser, $kryPk);
                    }
                }
            }
        }

        $user = Auth::user();
        if ($user && !empty($user->email) && Schema::hasColumn('karyawans', 'email')) {
            $byEmail = DB::table('karyawans')->where('email', $user->email)->first();
            if ($byEmail) {
                return (int) data_get($byEmail, $kryPk);
            }
        }

        if ($user && !empty($user->name)) {
            foreach (['nama_lengkap', 'nama', 'name', 'username'] as $col) {
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

    private function resolveForeignKeyValue(string $table, string $column, array $context = [])
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $sql = "SELECT a.attname AS col_name, "
                . "ccu.table_name AS ref_table, "
                . "ccu.column_name AS ref_col, "
                . "a.attnotnull AS is_not_null "
                . "FROM pg_constraint c "
                . "JOIN pg_class t ON c.conrelid = t.oid "
                . "JOIN pg_namespace n ON t.relnamespace = n.oid "
                . "JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(c.conkey) "
                . "JOIN pg_constraint cc ON cc.oid = c.oid "
                . "JOIN pg_class ct ON cc.confrelid = ct.oid "
                . "JOIN pg_attribute ca ON ca.attrelid = ct.oid AND ca.attnum = ANY(cc.confkey) "
                . "JOIN information_schema.constraint_column_usage ccu "
                . "ON ccu.constraint_name = c.conname AND ccu.table_schema = n.nspname "
                . "WHERE n.nspname = current_schema() "
                . "AND t.relname = '" . addslashes($table) . "' "
                . "AND c.contype = 'f' "
                . "AND a.attname = '" . addslashes($column) . "' "
                . "LIMIT 1";

            $fkInfo = DB::selectOne($sql);
            if (!$fkInfo) {
                return null;
            }

            $refTable = (string) ($fkInfo->ref_table ?? '');
            $refCol = (string) ($fkInfo->ref_col ?? '');
            $isNotNull = (bool) ($fkInfo->is_not_null ?? false);

            if ($refTable === '' || $refCol === '' || !Schema::hasTable($refTable) || !Schema::hasColumn($refTable, $refCol)) {
                return $isNotNull ? 1 : null;
            }

            if ($refTable === 'users' && !empty($context['user_id'])) {
                $ctxUser = (int) $context['user_id'];
                if (DB::table('users')->where($refCol, $ctxUser)->exists()) {
                    return $ctxUser;
                }
            }

            if ($refTable === 'karyawans' && !empty($context['karyawan_id'])) {
                $ctxKry = (int) $context['karyawan_id'];
                if (DB::table('karyawans')->where($refCol, $ctxKry)->exists()) {
                    return $ctxKry;
                }
            }

            $existingVal = DB::table($refTable)->value($refCol);
            if ($existingVal !== null) {
                return is_numeric($existingVal) ? (int) $existingVal : $existingVal;
            }

            return $isNotNull ? 1 : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveValidStatusForTable(string $table, string $desiredStatus = 'Hadir'): string
    {
        $cleanDesired = trim($desiredStatus);
        if ($cleanDesired === '') {
            $cleanDesired = 'Hadir';
        }

        $candidates = [
            $cleanDesired,
            strtolower($cleanDesired),
            strtoupper($cleanDesired),
            ucfirst(strtolower($cleanDesired)),
            'Hadir',
            'hadir',
            'HADIR',
            'Masuk',
            'masuk',
            'MASUK',
            'Terlambat',
            'terlambat',
            'TERLAMBAT',
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

                    return (string) ($lowerMap[strtolower($cleanDesired)]
                        ?? $lowerMap['hadir']
                        ?? $lowerMap['masuk']
                        ?? $allowedLiterals[0]);
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
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

                if (!empty($existingStatuses)) {
                    return $existingStatuses[0];
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

                if (str_starts_with($lowerCol, 'id_')) {
                    $fkVal = $this->resolveForeignKeyValue($table, $colName, $context);
                    if ($fkVal !== null) {
                        $payload[$colName] = $fkVal;
                        continue;
                    }
                }

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
                    $payload[$colName] = $context['tanggal'] ?? Carbon::now('Asia/Jakarta')->toDateString();
                } elseif (str_contains($dataType, 'time') && !str_contains($dataType, 'timestamp')) {
                    $payload[$colName] = $context['jam'] ?? Carbon::now('Asia/Jakarta')->format('H:i:s');
                } elseif (str_contains($dataType, 'timestamp')) {
                    $payload[$colName] = $context['now'] ?? Carbon::now('Asia/Jakarta');
                } elseif (str_contains($dataType, 'bool')) {
                    $payload[$colName] = false;
                } elseif (str_contains($lowerCol, 'status')) {
                    $payload[$colName] = $this->resolveValidStatusForTable($table, (string) ($context['status'] ?? 'Hadir'));
                } else {
                    $payload[$colName] = '-';
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        return $payload;
    }

    public function getRiwayatAbsensi(?int $userId = null, ?int $karyawanId = null, int $limit = 30)
    {
        $table = $this->resolveAbsensiTable();
        if (!$table) {
            return collect();
        }

        $uid = $userId ?? $this->resolveUserId();
        $kid = $karyawanId ?? $this->resolveKaryawanId($uid);

        $userCol = $this->findFirstColumn($table, ['id_user', 'user_id']);
        $kryCol = $this->findFirstColumn($table, ['id_karyawan', 'karyawan_id']);
        $tglCol = $this->findFirstColumn($table, ['tanggal', 'tgl_absen', 'tanggal_absen']);

        try {
            $query = DB::table($table);

            $hasFilter = false;
            if (($userCol && $uid !== null) || ($kryCol && $kid !== null)) {
                $query->where(function ($q) use ($userCol, $uid, $kryCol, $kid) {
                    $added = false;
                    if ($userCol && $uid !== null) {
                        $q->where($userCol, $uid);
                        $added = true;
                    }
                    if ($kryCol && $kid !== null) {
                        if ($added) {
                            $q->orWhere($kryCol, $kid);
                        } else {
                            $q->where($kryCol, $kid);
                        }
                    }
                });
                $hasFilter = true;
            }

            if ($tglCol) {
                $query->orderByDesc($tglCol);
            }
            $rows = $this->applyOrder($query, $table, 'desc')->limit($limit)->get();

            if ($rows->isEmpty() && $hasFilter) {
                $fallbackQuery = DB::table($table);
                if ($tglCol) {
                    $fallbackQuery->orderByDesc($tglCol);
                }
                $rows = $this->applyOrder($fallbackQuery, $table, 'desc')->limit($limit)->get();
            }

            return $rows->map(function ($row) {
                $tgl = data_get($row, 'tanggal')
                    ?? data_get($row, 'tgl_absen')
                    ?? data_get($row, 'tanggal_absen')
                    ?? (data_get($row, 'created_at')
                        ? Carbon::parse(data_get($row, 'created_at'))->timezone('Asia/Jakarta')->toDateString()
                        : '-');

                $jamMasuk = data_get($row, 'jam_masuk')
                    ?? data_get($row, 'waktu_masuk')
                    ?? data_get($row, 'check_in')
                    ?? '-';

                $jamPulang = data_get($row, 'jam_pulang')
                    ?? data_get($row, 'waktu_pulang')
                    ?? data_get($row, 'check_out')
                    ?? '-';

                $status = data_get($row, 'status')
                    ?? data_get($row, 'status_absen')
                    ?? 'Hadir';

                data_set($row, 'tanggal_tampil', $tgl);
                data_set($row, 'jam_masuk_tampil', $jamMasuk);
                data_set($row, 'jam_pulang_tampil', $jamPulang);
                data_set($row, 'status_tampil', $status);

                return $row;
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function getAbsensiHariIni(?int $userId = null, ?int $karyawanId = null)
    {
        $table = $this->resolveAbsensiTable();
        if (!$table) {
            return null;
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $uid = $userId ?? $this->resolveUserId();
        $kid = $karyawanId ?? $this->resolveKaryawanId($uid);

        $userCol = $this->findFirstColumn($table, ['id_user', 'user_id']);
        $kryCol = $this->findFirstColumn($table, ['id_karyawan', 'karyawan_id']);
        $tglCol = $this->findFirstColumn($table, ['tanggal', 'tgl_absen', 'tanggal_absen']);

        try {
            $query = DB::table($table);

            if ($tglCol) {
                $query->whereDate($tglCol, $today);
            } elseif (Schema::hasColumn($table, 'created_at')) {
                $query->whereDate('created_at', $today);
            }

            if (($userCol && $uid !== null) || ($kryCol && $kid !== null)) {
                $query->where(function ($q) use ($userCol, $uid, $kryCol, $kid) {
                    $added = false;
                    if ($userCol && $uid !== null) {
                        $q->where($userCol, $uid);
                        $added = true;
                    }
                    if ($kryCol && $kid !== null) {
                        if ($added) {
                            $q->orWhere($kryCol, $kid);
                        } else {
                            $q->where($kryCol, $kid);
                        }
                    }
                });
            }

            $found = $this->applyOrder($query, $table, 'desc')->first();
            if ($found) {
                return $found;
            }

            $fallback = DB::table($table);
            if ($tglCol) {
                $fallback->whereDate($tglCol, $today);
            } elseif (Schema::hasColumn($table, 'created_at')) {
                $fallback->whereDate('created_at', $today);
            }

            return $this->applyOrder($fallback, $table, 'desc')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $userId = $this->resolveUserId();
        $karyawanId = $this->resolveKaryawanId($userId);
        $riwayat = $this->getRiwayatAbsensi($userId, $karyawanId, 50);
        $absenHariIni = $this->getAbsensiHariIni($userId, $karyawanId);

        foreach ([
            'absensi.index',
            'absensi.riwayat',
            'riwayat-absensi',
            'absensi',
        ] as $viewName) {
            if (view()->exists($viewName)) {
                return view($viewName, [
                    'riwayatAbsensi' => $riwayat,
                    'absensis' => $riwayat,
                    'absenHariIni' => $absenHariIni,
                ]);
            }
        }

        $bladeHtml = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has(\'dashboard\') ? route(\'dashboard\') : url(\'/dashboard\');
    @endphp
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Riwayat Absensi Karyawan</h2>
                <p class="text-muted mb-0">PT. Generasi Tirta Abadi</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        @if(session(\'success\'))
            <div class="alert alert-success">{{ session(\'success\') }}</div>
        @endif

        @if(session(\'error\'))
            <div class="alert alert-danger">{{ session(\'error\') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">Daftar Riwayat Presensi</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($riwayatAbsensi ?? collect()) as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->tanggal_tampil ?? \'-\' }}</td>
                                    <td>{{ $row->jam_masuk_tampil ?? \'-\' }}</td>
                                    <td>{{ $row->jam_pulang_tampil ?? \'-\' }}</td>
                                    <td><span class="badge bg-success">{{ $row->status_tampil ?? \'Hadir\' }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat absensi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';

        return Blade::render($bladeHtml, [
            'riwayatAbsensi' => $riwayat,
            'absensis' => $riwayat,
            'absenHariIni' => $absenHariIni,
        ]);
    }

    public function store(Request $request)
    {
        $tipe = strtolower(trim((string) (
            $request->input('tipe')
            ?? $request->input('jenis')
            ?? $request->input('aksi')
            ?? $request->input('action')
            ?? $request->input('status')
            ?? ''
        )));

        if (str_contains($tipe, 'pulang') || str_contains($tipe, 'out')) {
            return $this->pulang($request);
        }

        if (str_contains($tipe, 'masuk') || str_contains($tipe, 'in')) {
            return $this->masuk($request);
        }

        $userId = $this->resolveUserId();
        $karyawanId = $this->resolveKaryawanId($userId);
        $todayRow = $this->getAbsensiHariIni($userId, $karyawanId);

        if ($todayRow) {
            $jamPulang = data_get($todayRow, 'jam_pulang')
                ?? data_get($todayRow, 'waktu_pulang')
                ?? data_get($todayRow, 'check_out');

            if (empty($jamPulang)) {
                return $this->pulang($request);
            }
        }

        return $this->masuk($request);
    }

    public function masuk(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $table = $this->resolveAbsensiTable();
        if (!$table) {
            return back()->with('error', 'Tabel absensi tidak ditemukan di database.');
        }

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $jamNow = $now->format('H:i:s');
        $userId = $this->resolveUserId();
        $karyawanId = $this->resolveKaryawanId($userId);

        $existing = $this->getAbsensiHariIni($userId, $karyawanId);
        if ($existing) {
            return redirect('/dashboard#riwayat-absensi')
                ->with('success', 'Absensi masuk hari ini sudah tercatat pada ' . $jamNow);
        }

        $statusVal = $this->resolveValidStatusForTable($table, $now->format('H:i') > '08:15' ? 'Terlambat' : 'Hadir');
        $payload = [];

        if ($karyawanId !== null) {
            foreach (['id_karyawan', 'karyawan_id'] as $kCol) {
                if (Schema::hasColumn($table, $kCol)) {
                    $payload[$kCol] = $karyawanId;
                    break;
                }
            }
        }

        if ($userId !== null) {
            foreach (['id_user', 'user_id'] as $uCol) {
                if (Schema::hasColumn($table, $uCol)) {
                    $payload[$uCol] = $userId;
                    break;
                }
            }
        }

        $tglCol = $this->findFirstColumn($table, ['tanggal', 'tgl_absen', 'tanggal_absen']);
        if ($tglCol) {
            $payload[$tglCol] = $today;
        }

        $masukCol = $this->findFirstColumn($table, ['jam_masuk', 'waktu_masuk', 'check_in']);
        if ($masukCol) {
            $payload[$masukCol] = $jamNow;
        }

        $statusCol = $this->findFirstColumn($table, ['status', 'status_absen']);
        if ($statusCol) {
            $payload[$statusCol] = $statusVal;
        }

        $payload = $this->fillNotNullColumnsPgsql($table, $payload, [
            'user_id' => $userId,
            'karyawan_id' => $karyawanId,
            'tanggal' => $today,
            'jam' => $jamNow,
            'status' => $statusVal,
            'now' => $now,
        ]);

        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        try {
            $pk = $this->resolvePk($table);
            if (
                DB::getDriverName() === 'pgsql'
                && !empty($pk)
                && Schema::hasColumn($table, $pk)
                && !array_key_exists($pk, $payload)
            ) {
                DB::table($table)->insertGetId($payload, $pk);
            } else {
                DB::table($table)->insert($payload);
            }
        } catch (\Throwable $e) {
            try {
                if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $ignore) {
                // Abaikan
            }

            return back()->with('error', 'Gagal mencatat absensi masuk: ' . $e->getMessage());
        }

        return redirect('/dashboard#riwayat-absensi')
            ->with('success', 'Absensi berhasil dicatat pada ' . $jamNow);
    }

    public function pulang(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $table = $this->resolveAbsensiTable();
        if (!$table) {
            return back()->with('error', 'Tabel absensi tidak ditemukan di database.');
        }

        $now = Carbon::now('Asia/Jakarta');
        $jamNow = $now->format('H:i:s');
        $userId = $this->resolveUserId();
        $karyawanId = $this->resolveKaryawanId($userId);

        $existing = $this->getAbsensiHariIni($userId, $karyawanId);
        if (!$existing) {
            return back()->with('error', 'Silakan absen MASUK terlebih dahulu sebelum absen PULANG.');
        }

        $pk = $this->resolvePk($table) ?? 'id';
        $rowId = data_get($existing, $pk) ?? data_get($existing, 'id');
        $pulangCol = $this->findFirstColumn($table, ['jam_pulang', 'waktu_pulang', 'check_out']);

        if (!$pulangCol) {
            return redirect('/dashboard#riwayat-absensi')
                ->with('success', 'Absensi pulang tercatat pada ' . $jamNow);
        }

        $updatePayload = [$pulangCol => $jamNow];
        if (Schema::hasColumn($table, 'updated_at')) {
            $updatePayload['updated_at'] = $now;
        }

        try {
            DB::table($table)->where($pk, $rowId)->update($updatePayload);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mencatat absensi pulang: ' . $e->getMessage());
        }

        return redirect('/dashboard#riwayat-absensi')
            ->with('success', 'Absensi pulang berhasil dicatat pada ' . $jamNow);
    }
}