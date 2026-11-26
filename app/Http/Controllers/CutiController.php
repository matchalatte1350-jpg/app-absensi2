<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CutiController extends Controller
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

    private function userExists(?int $userId): bool
    {
        if ($userId === null || !Schema::hasTable('users')) {
            return false;
        }

        $userPk = $this->resolvePk('users') ?? 'id';
        if (!Schema::hasColumn('users', $userPk)) {
            return false;
        }

        return DB::table('users')->where($userPk, $userId)->exists();
    }

    private function resolveValidUserId($selectedId = null, $karyawanRow = null): ?int
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $userPk = $this->resolvePk('users') ?? 'id';

        // 1. Cek ID yang dipilih dari form
        if ($selectedId !== null && $selectedId !== '') {
            $cand = (int) $selectedId;
            if ($this->userExists($cand)) {
                return $cand;
            }
        }

        // 2. Cek kolom id_user / user_id pada data karyawan
        if ($karyawanRow) {
            foreach (['id_user', 'user_id'] as $uCol) {
                $kUser = data_get($karyawanRow, $uCol);
                if ($kUser !== null && $kUser !== '') {
                    $cand = (int) $kUser;
                    if ($this->userExists($cand)) {
                        return $cand;
                    }
                }
            }

            // Cocokkan username / nama karyawan ke tabel users
            $kUsername = data_get($karyawanRow, 'username');
            if ($kUsername && Schema::hasColumn('users', 'username')) {
                $uByName = DB::table('users')->where('username', $kUsername)->first();
                if ($uByName) {
                    return (int) data_get($uByName, $userPk);
                }
            }

            $kNama = data_get($karyawanRow, 'nama_lengkap') ?? data_get($karyawanRow, 'nama');
            if ($kNama) {
                foreach (['name', 'nama', 'nama_lengkap'] as $uNameCol) {
                    if (Schema::hasColumn('users', $uNameCol)) {
                        $uByFullName = DB::table('users')->where($uNameCol, $kNama)->first();
                        if ($uByFullName) {
                            return (int) data_get($uByFullName, $userPk);
                        }
                    }
                }
            }
        }

        // 3. Cek user yang sedang login
        $authUserId = $this->resolveUserId();
        if ($this->userExists($authUserId)) {
            return $authUserId;
        }

        // 4. Fallback ke user pertama yang tersedia di tabel users
        if (Schema::hasColumn('users', $userPk)) {
            $firstUserId = DB::table('users')->value($userPk);
            if ($firstUserId !== null) {
                return (int) $firstUserId;
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
            'Menunggu',
            'menunggu',
            'MENUNGGU',
            'Pending',
            'pending',
            'PENDING',
            'Diajukan',
            'diajukan',
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

            $existingVal = DB::table($refTable)->value($refCol);
            if ($existingVal !== null) {
                return is_numeric($existingVal) ? (int) $existingVal : $existingVal;
            }

            return $isNotNull ? 1 : null;
        } catch (\Throwable $e) {
            return null;
        }
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
                    } elseif (str_contains($lowerCol, 'hari') || str_contains($lowerCol, 'jumlah') || str_contains($lowerCol, 'durasi')) {
                        $payload[$colName] = (int) ($context['jumlah_hari'] ?? 1);
                    } elseif (str_starts_with($lowerCol, 'id_')) {
                        $maxId = DB::table($table)->max($colName);
                        $payload[$colName] = ((int) $maxId) + 1;
                    } else {
                        $payload[$colName] = 1;
                    }
                } elseif (str_contains($dataType, 'date') && !str_contains($dataType, 'time')) {
                    $payload[$colName] = str_contains($lowerCol, 'selesai') || str_contains($lowerCol, 'akhir')
                        ? ($context['tanggal_selesai'] ?? Carbon::now('Asia/Jakarta')->addDays(2)->toDateString())
                        : ($context['tanggal_mulai'] ?? Carbon::now('Asia/Jakarta')->toDateString());
                } elseif (str_contains($dataType, 'timestamp') || str_contains($dataType, 'time')) {
                    $payload[$colName] = $context['now'] ?? Carbon::now('Asia/Jakarta');
                } elseif (str_contains($dataType, 'bool')) {
                    $payload[$colName] = false;
                } elseif (str_contains($lowerCol, 'status')) {
                    $payload[$colName] = $this->resolveValidStatusForTable($table, (string) ($context['status'] ?? 'Menunggu'));
                } elseif (str_contains($lowerCol, 'jenis') || str_contains($lowerCol, 'tipe')) {
                    $payload[$colName] = $context['jenis_cuti'] ?? 'Cuti Sakit';
                } else {
                    $payload[$colName] = $context['alasan'] ?? 'Pengajuan cuti';
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        return $payload;
    }

    private function getKaryawanOptions()
    {
        if (!Schema::hasTable('karyawans')) {
            return collect();
        }

        try {
            $pk = $this->resolvePk('karyawans') ?? 'id';
            $namaCol = $this->findFirstColumn('karyawans', ['nama_lengkap', 'nama', 'name', 'username']);
            $userFkCol = $this->findFirstColumn('karyawans', ['id_user', 'user_id']);
            $rows = $this->applyOrder(DB::table('karyawans'), 'karyawans', 'asc')->get();

            return $rows->map(function ($row) use ($pk, $namaCol, $userFkCol) {
                $idVal = data_get($row, $pk) ?? data_get($row, 'id');
                $rawUserId = ($userFkCol ? data_get($row, $userFkCol) : null)
                    ?? data_get($row, 'id_user')
                    ?? data_get($row, 'user_id');

                $validUserId = $this->resolveValidUserId($rawUserId, $row) ?? $idVal;

                $namaVal = ($namaCol ? data_get($row, $namaCol) : null)
                    ?? data_get($row, 'nama_lengkap')
                    ?? data_get($row, 'nama')
                    ?? data_get($row, 'name')
                    ?? data_get($row, 'username')
                    ?? ('Karyawan #' . $idVal);

                return (object) [
                    'id' => $idVal,
                    'id_karyawan' => $idVal,
                    'id_user' => $validUserId,
                    'user_id' => $validUserId,
                    'nama_lengkap' => $namaVal,
                    'nama' => $namaVal,
                ];
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function getRiwayatCuti($karyawanOptions)
    {
        if (!Schema::hasTable('cutis')) {
            return collect();
        }

        $namaMapById = [];
        $namaMapByUser = [];
        foreach ($karyawanOptions as $kry) {
            $namaMapById[(string) $kry->id] = (string) $kry->nama_lengkap;
            if ($kry->id_user !== null) {
                $namaMapByUser[(string) $kry->id_user] = (string) $kry->nama_lengkap;
            }
        }

        try {
            $rows = $this->applyOrder(DB::table('cutis'), 'cutis', 'desc')->limit(50)->get();

            return $rows->map(function ($row) use ($namaMapById, $namaMapByUser) {
                $kryId = data_get($row, 'id_karyawan') ?? data_get($row, 'karyawan_id');
                $usrId = data_get($row, 'id_user') ?? data_get($row, 'user_id');

                $namaKaryawan = data_get($row, 'nama_lengkap')
                    ?? data_get($row, 'nama_karyawan')
                    ?? data_get($row, 'nama')
                    ?? ($kryId !== null ? ($namaMapById[(string) $kryId] ?? null) : null)
                    ?? ($usrId !== null ? ($namaMapByUser[(string) $usrId] ?? null) : null)
                    ?? '-';

                if (!data_get($row, 'nama_lengkap')) {
                    data_set($row, 'nama_lengkap', $namaKaryawan);
                }

                return $row;
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function renderCutiView($cutis, $karyawans)
    {
        foreach ([
            'cuti.index',
            'cuti',
            'pengajuan-cuti',
        ] as $viewName) {
            if (view()->exists($viewName)) {
                return view($viewName, [
                    'cutis' => $cutis,
                    'riwayatCuti' => $cutis,
                    'karyawans' => $karyawans,
                ]);
            }
        }

        $bladeHtml = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan & Data Cuti Karyawan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has(\'dashboard\') ? route(\'dashboard\') : url(\'/dashboard\');
        $storeUrl = \Illuminate\Support\Facades\Route::has(\'cuti.store\') ? route(\'cuti.store\') : url(\'/cuti\');
        $today = \Carbon\Carbon::now(\'Asia/Jakarta\')->toDateString();
        $plusTwo = \Carbon\Carbon::now(\'Asia/Jakarta\')->addDays(2)->toDateString();
    @endphp
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Pengajuan & Data Cuti Karyawan</h2>
                <p class="text-muted mb-0">Kelola permohonan cuti dan izin ketidakhadiran karyawan</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        @if(session(\'success\'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session(\'success\') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session(\'error\'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session(\'error\') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-primary text-white fw-bold">Form Pengajuan Cuti</div>
            <div class="card-body">
                <form action="{{ $storeUrl }}" method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="form-label">Pilih Karyawan</label>
                            <select name="id_user" class="form-select">
                                @foreach(($karyawans ?? collect()) as $kry)
                                    <option value="{{ $kry->id_user }}" {{ (string) old(\'id_user\') === (string) $kry->id_user ? \'selected\' : \'\' }}>{{ $kry->nama_lengkap }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="form-label">Jenis Cuti</label>
                            <select name="jenis_cuti" class="form-select">
                                @foreach([\'Cuti Sakit\', \'Cuti Tahunan\', \'Cuti Alasan Penting\', \'Izin Khusus\'] as $jc)
                                    <option value="{{ $jc }}" {{ old(\'jenis_cuti\', \'Cuti Sakit\') === $jc ? \'selected\' : \'\' }}>{{ $jc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" value="{{ old(\'tanggal_mulai\', $today) }}" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" value="{{ old(\'tanggal_selesai\', $plusTwo) }}" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alasan Cuti</label>
                        <textarea name="alasan" rows="3" class="form-control" placeholder="Contoh: mau berobat" required>{{ old(\'alasan\') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Kirim Pengajuan Cuti</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">Riwayat Pengajuan Cuti</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Karyawan</th>
                                <th>Jenis Cuti</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Alasan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($cutis ?? collect()) as $index => $row)
                                @php
                                    $namaK = data_get($row, \'nama_lengkap\') ?? data_get($row, \'nama_karyawan\') ?? \'-\';
                                    $jenisK = data_get($row, \'jenis_cuti\') ?? data_get($row, \'jenis\') ?? \'-\';
                                    $mulaiK = data_get($row, \'tanggal_mulai\') ?? data_get($row, \'tgl_mulai\') ?? \'-\';
                                    $selesaiK = data_get($row, \'tanggal_selesai\') ?? data_get($row, \'tgl_selesai\') ?? \'-\';
                                    $alasanK = data_get($row, \'alasan\') ?? data_get($row, \'keterangan\') ?? \'-\';
                                    $statusK = data_get($row, \'status\') ?? \'Menunggu\';
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $namaK }}</td>
                                    <td>{{ $jenisK }}</td>
                                    <td>{{ $mulaiK }}</td>
                                    <td>{{ $selesaiK }}</td>
                                    <td>{{ $alasanK }}</td>
                                    <td><span class="badge bg-warning text-dark">{{ $statusK }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan cuti.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

        return Blade::render($bladeHtml, [
            'cutis' => $cutis,
            'riwayatCuti' => $cutis,
            'karyawans' => $karyawans,
        ]);
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $karyawans = $this->getKaryawanOptions();
        $cutis = $this->getRiwayatCuti($karyawans);

        return $this->renderCutiView($cutis, $karyawans);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Schema::hasTable('cutis')) {
            return back()->withInput()->with('error', 'Tabel cutis belum tersedia di database.');
        }

        $validated = $request->validate([
            'id_user' => ['nullable'],
            'user_id' => ['nullable'],
            'id_karyawan' => ['nullable'],
            'karyawan_id' => ['nullable'],
            'jenis_cuti' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tgl_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now('Asia/Jakarta');
        $tanggalMulai = trim((string) ($validated['tanggal_mulai'] ?? $validated['tgl_mulai'] ?? $now->toDateString()));
        $tanggalSelesai = trim((string) ($validated['tanggal_selesai'] ?? $validated['tgl_selesai'] ?? $now->copy()->addDays(2)->toDateString()));
        $jenisCuti = trim((string) ($validated['jenis_cuti'] ?? $validated['jenis'] ?? 'Cuti Sakit'));
        $alasan = trim((string) ($validated['alasan'] ?? $validated['keterangan'] ?? 'Pengajuan cuti'));

        $jumlahHari = 1;
        try {
            $d1 = Carbon::parse($tanggalMulai)->startOfDay();
            $d2 = Carbon::parse($tanggalSelesai)->startOfDay();
            if ($d2->lessThan($d1)) {
                $d2 = $d1->copy();
                $tanggalSelesai = $d2->toDateString();
            }
            $jumlahHari = max(1, $d1->diffInDays($d2) + 1);
        } catch (\Throwable $e) {
            $jumlahHari = 1;
        }

        $selectedId = $validated['id_user'] ?? $validated['user_id'] ?? $validated['id_karyawan'] ?? $validated['karyawan_id'] ?? null;

        $karyawanRow = null;
        if ($selectedId !== null && $selectedId !== '' && Schema::hasTable('karyawans')) {
            $kryPk = $this->resolvePk('karyawans') ?? 'id';
            $karyawanRow = DB::table('karyawans')->where($kryPk, (int) $selectedId)->first();
            if (!$karyawanRow) {
                foreach (['id_user', 'user_id'] as $uCol) {
                    if (Schema::hasColumn('karyawans', $uCol)) {
                        $karyawanRow = DB::table('karyawans')->where($uCol, (int) $selectedId)->first();
                        if ($karyawanRow) {
                            break;
                        }
                    }
                }
            }
        }

        $userId = $this->resolveValidUserId($selectedId, $karyawanRow);

        $karyawanId = $validated['id_karyawan'] ?? $validated['karyawan_id'] ?? null;
        $karyawanId = ($karyawanId !== null && $karyawanId !== '') ? (int) $karyawanId : null;
        if ($karyawanId === null && $karyawanRow) {
            $kryPk = $this->resolvePk('karyawans') ?? 'id';
            $karyawanId = (int) data_get($karyawanRow, $kryPk);
        }
        if ($karyawanId === null) {
            $karyawanId = $this->resolveKaryawanId($userId);
        }

        $validStatus = $this->resolveValidStatusForTable('cutis', 'Menunggu');
        $payload = [];

        if ($karyawanId !== null) {
            foreach (['id_karyawan', 'karyawan_id'] as $kCol) {
                if (Schema::hasColumn('cutis', $kCol)) {
                    $payload[$kCol] = $karyawanId;
                    break;
                }
            }
        }

        if ($userId !== null) {
            foreach (['id_user', 'user_id'] as $uCol) {
                if (Schema::hasColumn('cutis', $uCol)) {
                    $payload[$uCol] = $userId;
                    break;
                }
            }
        }

        $jenisCol = $this->findFirstColumn('cutis', ['jenis_cuti', 'jenis', 'tipe_cuti']);
        if ($jenisCol) {
            $payload[$jenisCol] = $jenisCuti;
        }

        $mulaiCol = $this->findFirstColumn('cutis', ['tanggal_mulai', 'tgl_mulai', 'dari_tanggal']);
        if ($mulaiCol) {
            $payload[$mulaiCol] = $tanggalMulai;
        }

        $selesaiCol = $this->findFirstColumn('cutis', ['tanggal_selesai', 'tgl_selesai', 'sampai_tanggal']);
        if ($selesaiCol) {
            $payload[$selesaiCol] = $tanggalSelesai;
        }

        $hariCol = $this->findFirstColumn('cutis', ['jumlah_hari', 'lama_cuti', 'total_hari', 'durasi']);
        if ($hariCol) {
            $payload[$hariCol] = $jumlahHari;
        }

        $alasanCol = $this->findFirstColumn('cutis', ['alasan', 'keterangan', 'keperluan']);
        if ($alasanCol) {
            $payload[$alasanCol] = $alasan;
        }

        $statusCol = $this->findFirstColumn('cutis', ['status', 'status_pengajuan']);
        if ($statusCol) {
            $payload[$statusCol] = $validStatus;
        }

        $payload = $this->fillNotNullColumnsPgsql('cutis', $payload, [
            'user_id' => $userId,
            'karyawan_id' => $karyawanId,
            'jenis_cuti' => $jenisCuti,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'jumlah_hari' => $jumlahHari,
            'alasan' => $alasan,
            'status' => $validStatus,
            'now' => $now,
        ]);

        if (Schema::hasColumn('cutis', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn('cutis', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        try {
            $pk = $this->resolvePk('cutis');
            if (
                DB::getDriverName() === 'pgsql'
                && !empty($pk)
                && Schema::hasColumn('cutis', $pk)
                && !array_key_exists($pk, $payload)
            ) {
                DB::table('cutis')->insertGetId($payload, $pk);
            } else {
                DB::table('cutis')->insert($payload);
            }
        } catch (\Throwable $e) {
            try {
                if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $ignore) {
                // Abaikan
            }

            return back()->withInput()->with('error', 'Gagal menyimpan pengajuan cuti: ' . $e->getMessage());
        }

        $redirectTarget = \Illuminate\Support\Facades\Route::has('cuti.index')
            ? redirect()->route('cuti.index')
            : redirect('/cuti');

        return $redirectTarget->with('success', 'Pengajuan cuti berhasil dikirim.');
    }
}