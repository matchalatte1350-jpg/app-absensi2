<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KaryawanController extends Controller
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

    private function resolveValidRoleForTable(string $table, string $desiredRole): string
    {
        $cleanDesired = trim($desiredRole);
        if ($cleanDesired === '') {
            $cleanDesired = 'karyawan';
        }

        $candidates = [
            $cleanDesired,
            strtolower($cleanDesired),
            strtoupper($cleanDesired),
            ucfirst(strtolower($cleanDesired)),
            'karyawan',
            'KARYAWAN',
            'Karyawan',
            'staff',
            'STAFF',
            'Staff',
            'pegawai',
            'PEGAWAI',
            'user',
            'USER',
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
                    if (stripos($def, 'role') === false) {
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
                        ?? $lowerMap['karyawan']
                        ?? $lowerMap['staff']
                        ?? $lowerMap['pegawai']
                        ?? $lowerMap['user']
                        ?? $allowedLiterals[0];

                    return (string) $mapped;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal membaca constraint
        }

        try {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'role')) {
                $existingRoles = DB::table($table)
                    ->whereNotNull('role')
                    ->pluck('role')
                    ->map(fn ($r) => (string) $r)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($existingRoles as $existing) {
                    if (strtolower($existing) === strtolower($cleanDesired)) {
                        return $existing;
                    }
                }

                foreach (['karyawan', 'staff', 'pegawai', 'user'] as $fallbackKey) {
                    foreach ($existingRoles as $existing) {
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

            $existingVal = DB::table($refTable)->value($refCol);
            if ($existingVal !== null) {
                return is_numeric($existingVal) ? (int) $existingVal : $existingVal;
            }

            if (!$isNotNull) {
                return null;
            }

            // Jika tabel referensi kosong tapi FK wajib NOT NULL, buat 1 record default aman
            $refPayload = $this->fillNotNullColumnsPgsql($refTable, [], [
                'nama' => 'Umum',
                'now' => $context['now'] ?? Carbon::now('Asia/Jakarta'),
                'now_date' => $context['now_date'] ?? Carbon::now('Asia/Jakarta')->toDateString(),
            ]);

            foreach (['nama_divisi', 'nama', 'name', 'judul', 'label', 'kode_divisi', 'kode'] as $titleCol) {
                if (Schema::hasColumn($refTable, $titleCol) && !array_key_exists($titleCol, $refPayload)) {
                    $refPayload[$titleCol] = str_contains($titleCol, 'kode') ? 'DIV01' : 'Umum';
                }
            }

            if (Schema::hasColumn($refTable, 'created_at') && !array_key_exists('created_at', $refPayload)) {
                $refPayload['created_at'] = $context['now'] ?? Carbon::now('Asia/Jakarta');
            }
            if (Schema::hasColumn($refTable, 'updated_at') && !array_key_exists('updated_at', $refPayload)) {
                $refPayload['updated_at'] = $context['now'] ?? Carbon::now('Asia/Jakarta');
            }

            if (empty($refPayload)) {
                $refPayload[$refCol] = 1;
            }

            DB::table($refTable)->insert($refPayload);
            $createdVal = DB::table($refTable)->value($refCol);

            return $createdVal !== null ? (is_numeric($createdVal) ? (int) $createdVal : $createdVal) : 1;
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

                if (str_starts_with($lowerCol, 'id_') && !str_contains($lowerCol, 'user') && !str_contains($lowerCol, 'karyawan')) {
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
                    $payload[$colName] = $context['now_date'] ?? Carbon::now('Asia/Jakarta')->toDateString();
                } elseif (str_contains($dataType, 'timestamp') || str_contains($dataType, 'time')) {
                    $payload[$colName] = $context['now'] ?? Carbon::now('Asia/Jakarta');
                } elseif (str_contains($dataType, 'bool')) {
                    $payload[$colName] = false;
                } elseif (str_contains($lowerCol, 'email')) {
                    $baseSlug = Str::slug((string) ($context['username'] ?? $context['nama'] ?? 'karyawan'), '.');
                    if ($baseSlug === '') {
                        $baseSlug = 'karyawan.' . Str::lower(Str::random(5));
                    }
                    $payload[$colName] = $baseSlug . '@ptgta.local';
                } elseif (str_contains($lowerCol, 'role')) {
                    $payload[$colName] = $this->resolveValidRoleForTable($table, (string) ($context['role'] ?? 'karyawan'));
                } elseif (str_contains($lowerCol, 'pass')) {
                    $payload[$colName] = $context['password_hash'] ?? Hash::make('123456');
                } elseif (str_contains($lowerCol, 'nama') || $lowerCol === 'name') {
                    $payload[$colName] = $context['nama'] ?? 'Karyawan Baru';
                } elseif (str_contains($lowerCol, 'user')) {
                    $payload[$colName] = $context['username'] ?? ('user_' . Str::lower(Str::random(5)));
                } elseif (str_contains($lowerCol, 'kode')) {
                    $payload[$colName] = $context['kode'] ?? ('EMP' . rand(100, 999));
                } else {
                    $payload[$colName] = '-';
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal membaca metadata
        }

        return $payload;
    }

    private function getKaryawanList()
    {
        $hasKaryawans = Schema::hasTable('karyawans');
        $hasUsers = Schema::hasTable('users');

        if ($hasKaryawans && $hasUsers) {
            $userFk = $this->findFirstColumn('karyawans', ['id_user', 'user_id']);
            $userPk = $this->resolvePk('users');

            if ($userFk && $userPk) {
                try {
                    $query = DB::table('karyawans')
                        ->leftJoin('users', 'karyawans.' . $userFk, '=', 'users.' . $userPk);

                    $selects = ['karyawans.*'];
                    foreach (['username', 'email', 'role', 'name', 'nama', 'kode_karyawan'] as $uCol) {
                        if (Schema::hasColumn('users', $uCol) && !Schema::hasColumn('karyawans', $uCol)) {
                            $selects[] = 'users.' . $uCol . ' as user_' . $uCol;
                        } elseif (Schema::hasColumn('users', $uCol)) {
                            $selects[] = 'users.' . $uCol;
                        }
                    }

                    return $this->applyOrder($query->select($selects), 'karyawans', 'desc')->get();
                } catch (\Throwable $e) {
                    // Fallback ke query tunggal
                }
            }
        }

        if ($hasKaryawans) {
            try {
                return $this->applyOrder(DB::table('karyawans'), 'karyawans', 'desc')->get();
            } catch (\Throwable $e) {
                return collect();
            }
        }

        if ($hasUsers) {
            try {
                return $this->applyOrder(DB::table('users'), 'users', 'desc')->get();
            } catch (\Throwable $e) {
                return collect();
            }
        }

        return collect();
    }

    private function renderKaryawanView($karyawans)
    {
        foreach ([
            'karyawan.index',
            'manajemen',
            'karyawan.manajemen',
            'karyawan',
        ] as $viewName) {
            if (view()->exists($viewName)) {
                return view($viewName, [
                    'karyawans' => $karyawans,
                ]);
            }
        }

        $bladeHtml = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has(\'dashboard\') ? route(\'dashboard\') : url(\'/dashboard\');
        $storeUrl = \Illuminate\Support\Facades\Route::has(\'karyawan.store\') ? route(\'karyawan.store\') : url(\'/karyawan\');
        $roles = [
            \'karyawan\' => \'Karyawan\',
            \'kepala_divisi\' => \'Kepala Divisi\',
            \'manajer\' => \'Manajer\',
            \'admin\' => \'Admin\',
        ];
    @endphp
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Manajemen Data Karyawan</h2>
                <p class="text-muted mb-0">Kelola akun, data pegawai, dan hak akses role sistem</p>
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
            <div class="card-header bg-primary text-white fw-bold">Tambah Karyawan Baru</div>
            <div class="card-body">
                <form action="{{ $storeUrl }}" method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control" placeholder="Contoh: Rian Utama" value="{{ old(\'nama_lengkap\') }}" required>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="kode_karyawan" class="form-label">Kode Karyawan</label>
                            <input type="text" name="kode_karyawan" id="kode_karyawan" class="form-control" placeholder="Contoh: EMP004" value="{{ old(\'kode_karyawan\') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="username" class="form-label">Username Login</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Contoh: rian" value="{{ old(\'username\') }}" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="role" class="form-label">Role Akses</label>
                            <select name="role" id="role" class="form-select" required>
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" {{ old(\'role\', \'karyawan\') === $value ? \'selected\' : \'\' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 4-6 karakter" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">Simpan Data Karyawan</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">Daftar Pegawai Aktif</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Karyawan</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($karyawans ?? collect()) as $index => $karyawan)
                                @php
                                    $kodeVal = data_get($karyawan, \'kode_karyawan\') ?? data_get($karyawan, \'kode\') ?? \'-\';
                                    $namaVal = data_get($karyawan, \'nama_lengkap\') ?? data_get($karyawan, \'nama\') ?? data_get($karyawan, \'name\') ?? data_get($karyawan, \'user_nama\') ?? \'-\';
                                    $userVal = data_get($karyawan, \'username\') ?? data_get($karyawan, \'user_username\') ?? \'-\';
                                    $roleVal = data_get($karyawan, \'role\') ?? data_get($karyawan, \'user_role\') ?? \'karyawan\';
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $kodeVal }}</td>
                                    <td>{{ $namaVal }}</td>
                                    <td>{{ $userVal }}</td>
                                    <td><span class="badge bg-info text-dark text-uppercase">{{ $roleVal }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data karyawan.</td>
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

        return Blade::render($bladeHtml, ['karyawans' => $karyawans]);
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return $this->renderKaryawanView($this->getKaryawanList());
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'kode_karyawan' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        $nama = trim($validated['nama_lengkap']);
        $kode = trim($validated['kode_karyawan']);
        $username = trim($validated['username']);
        $roleInput = trim($validated['role']);
        $passwordHash = Hash::make($validated['password']);
        $now = Carbon::now('Asia/Jakarta');

        $hasUsers = Schema::hasTable('users');
        $hasKaryawans = Schema::hasTable('karyawans');

        if (!$hasUsers && !$hasKaryawans) {
            return back()->withInput()->with('error', 'Tabel users dan karyawans tidak ditemukan di database.');
        }

        if ($hasUsers) {
            if (Schema::hasColumn('users', 'username') && DB::table('users')->where('username', $username)->exists()) {
                return back()->withInput()->with('error', 'Username "' . $username . '" sudah digunakan. Pakai username lain ya.');
            }
            $userKodeCol = $this->findFirstColumn('users', ['kode_karyawan', 'kode']);
            if ($userKodeCol && DB::table('users')->where($userKodeCol, $kode)->exists()) {
                return back()->withInput()->with('error', 'Kode karyawan "' . $kode . '" sudah terdaftar. Pakai kode lain.');
            }
        }

        if ($hasKaryawans) {
            $kodeCol = $this->findFirstColumn('karyawans', ['kode_karyawan', 'kode']);
            if ($kodeCol && DB::table('karyawans')->where($kodeCol, $kode)->exists()) {
                return back()->withInput()->with('error', 'Kode karyawan "' . $kode . '" sudah digunakan. Pakai kode lain.');
            }
        }

        try {
            $userId = null;

            if ($hasUsers) {
                $userPk = $this->resolvePk('users') ?? 'id';
                $userRole = $this->resolveValidRoleForTable('users', $roleInput);
                $userPayload = [];

                if (Schema::hasColumn('users', 'username')) {
                    $userPayload['username'] = $username;
                }
                if (Schema::hasColumn('users', 'name')) {
                    $userPayload['name'] = $nama;
                } elseif (Schema::hasColumn('users', 'nama')) {
                    $userPayload['nama'] = $nama;
                } elseif (Schema::hasColumn('users', 'nama_lengkap')) {
                    $userPayload['nama_lengkap'] = $nama;
                }
                $userKodeCol = $this->findFirstColumn('users', ['kode_karyawan', 'kode']);
                if ($userKodeCol) {
                    $userPayload[$userKodeCol] = $kode;
                }
                if (Schema::hasColumn('users', 'email')) {
                    $slug = Str::slug($username ?: $nama, '.');
                    $userPayload['email'] = ($slug ?: 'user') . '.' . Str::lower(Str::random(4)) . '@ptgta.local';
                }
                if (Schema::hasColumn('users', 'role')) {
                    $userPayload['role'] = $userRole;
                }
                if (Schema::hasColumn('users', 'password')) {
                    $userPayload['password'] = $passwordHash;
                }

                $userPayload = $this->fillNotNullColumnsPgsql('users', $userPayload, [
                    'nama' => $nama,
                    'username' => $username,
                    'kode' => $kode,
                    'role' => $userRole,
                    'password_hash' => $passwordHash,
                    'now' => $now,
                    'now_date' => $now->toDateString(),
                ]);

                if (Schema::hasColumn('users', 'created_at')) {
                    $userPayload['created_at'] = $now;
                }
                if (Schema::hasColumn('users', 'updated_at')) {
                    $userPayload['updated_at'] = $now;
                }

                if (
                    DB::getDriverName() === 'pgsql'
                    && !empty($userPk)
                    && Schema::hasColumn('users', $userPk)
                    && !array_key_exists($userPk, $userPayload)
                ) {
                    $insertedId = DB::table('users')->insertGetId($userPayload, $userPk);
                    $userId = $insertedId !== null ? (int) $insertedId : null;
                } else {
                    DB::table('users')->insert($userPayload);
                    if (!empty($userPayload[$userPk])) {
                        $userId = (int) $userPayload[$userPk];
                    } elseif (Schema::hasColumn('users', 'username')) {
                        $found = DB::table('users')->where('username', $username)->first();
                        if ($found) {
                            $userId = (int) data_get($found, $userPk);
                        }
                    }
                }
            }

            if ($hasKaryawans) {
                $kryPk = $this->resolvePk('karyawans') ?? 'id';
                $kryRole = $this->resolveValidRoleForTable('karyawans', $roleInput);
                $kryPayload = [];

                if ($userId !== null) {
                    foreach (['id_user', 'user_id'] as $uFk) {
                        if (Schema::hasColumn('karyawans', $uFk)) {
                            $kryPayload[$uFk] = $userId;
                            break;
                        }
                    }
                }

                $kodeCol = $this->findFirstColumn('karyawans', ['kode_karyawan', 'kode']);
                if ($kodeCol) {
                    $kryPayload[$kodeCol] = $kode;
                }

                $namaCol = $this->findFirstColumn('karyawans', ['nama_lengkap', 'nama', 'name']);
                if ($namaCol) {
                    $kryPayload[$namaCol] = $nama;
                }

                if (Schema::hasColumn('karyawans', 'username')) {
                    $kryPayload['username'] = $username;
                }
                if (Schema::hasColumn('karyawans', 'role')) {
                    $kryPayload['role'] = $kryRole;
                }
                if (Schema::hasColumn('karyawans', 'password')) {
                    $kryPayload['password'] = $passwordHash;
                }

                $kryPayload = $this->fillNotNullColumnsPgsql('karyawans', $kryPayload, [
                    'user_id' => $userId,
                    'nama' => $nama,
                    'username' => $username,
                    'kode' => $kode,
                    'role' => $kryRole,
                    'password_hash' => $passwordHash,
                    'now' => $now,
                    'now_date' => $now->toDateString(),
                ]);

                if (Schema::hasColumn('karyawans', 'created_at')) {
                    $kryPayload['created_at'] = $now;
                }
                if (Schema::hasColumn('karyawans', 'updated_at')) {
                    $kryPayload['updated_at'] = $now;
                }

                if (
                    DB::getDriverName() === 'pgsql'
                    && !empty($kryPk)
                    && Schema::hasColumn('karyawans', $kryPk)
                    && !array_key_exists($kryPk, $kryPayload)
                ) {
                    DB::table('karyawans')->insertGetId($kryPayload, $kryPk);
                } else {
                    DB::table('karyawans')->insert($kryPayload);
                }
            }
        } catch (\Throwable $e) {
            try {
                if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $ignore) {
                // Abaikan
            }

            return back()->withInput()->with('error', 'Gagal menyimpan data karyawan: ' . $e->getMessage());
        }

        $redirectTarget = \Illuminate\Support\Facades\Route::has('karyawan.index')
            ? redirect()->route('karyawan.index')
            : redirect('/karyawan');

        return $redirectTarget->with('success', 'Data karyawan "' . $nama . '" berhasil disimpan.');
    }
}