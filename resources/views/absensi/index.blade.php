<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - PT Generasi Tirta Abadi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 antialiased min-h-screen">
    @php
        $listAbsen = isset($absens) ? $absens : (isset($riwayatAbsensi) ? $riwayatAbsensi : (isset($absensis) ? $absensis : collect()));

        $isKosong = empty($listAbsen);
        if (!$isKosong && is_object($listAbsen) && method_exists($listAbsen, 'isEmpty')) {
            $isKosong = $listAbsen->isEmpty();
        }

        if ($isKosong) {
            try {
                $tblIdx = null;
                foreach (['absensis', 'absensi', 'presensis', 'presensi', 'riwayat_absensis', 'riwayat_absensi'] as $t) {
                    if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                        $tblIdx = $t;
                        break;
                    }
                }

                if (!$tblIdx && \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                    $pgTables = \Illuminate\Support\Facades\DB::select("SELECT tablename FROM pg_tables WHERE schemaname = current_schema()");
                    foreach ($pgTables as $pt) {
                        $tName = (string) ($pt->tablename ?? '');
                        $lowName = strtolower($tName);
                        if (str_contains($lowName, 'absen') || str_contains($lowName, 'presensi')) {
                            $tblIdx = $tName;
                            break;
                        }
                    }
                }

                if ($tblIdx) {
                    $qIdx = \Illuminate\Support\Facades\DB::table($tblIdx);
                    foreach (['tanggal', 'tgl_absen', 'tanggal_absen', 'id', 'id_absensi', 'id_absen', 'created_at'] as $oc) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn($tblIdx, $oc)) {
                            $qIdx->orderByDesc($oc);
                            break;
                        }
                    }
                    $listAbsen = $qIdx->limit(50)->get();
                } else {
                    $listAbsen = collect();
                }
            } catch (\Throwable $e) {
                $listAbsen = collect();
            }
        }

        $dashboardUrl = \Illuminate\Support\Facades\Route::has('dashboard')
            ? route('dashboard')
            : url('/dashboard');
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Riwayat Absensi Karyawan</h1>
                <p class="text-sm text-gray-500">PT. Generasi Tirta Abadi</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="px-4 py-2 rounded-xl bg-white hover:bg-gray-50 border border-gray-200 text-sm font-semibold text-gray-700 shadow-sm transition">
                Kembali ke Dashboard
            </a>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-900">Daftar Riwayat Presensi</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <th class="py-3 px-4">No</th>
                            <th class="py-3 px-4">Nama Karyawan</th>
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Jam Masuk</th>
                            <th class="py-3 px-4">Jam Pulang</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($listAbsen as $index => $absen)
                            @php
                                $namaKry = data_get($absen, 'karyawan.nama_lengkap');
                                if (!$namaKry) { $namaKry = data_get($absen, 'karyawan.nama'); }
                                if (!$namaKry) { $namaKry = data_get($absen, 'nama_lengkap'); }
                                if (!$namaKry) { $namaKry = data_get($absen, 'nama_karyawan'); }
                                if (!$namaKry) { $namaKry = data_get($absen, 'nama'); }
                                if (!$namaKry) { $namaKry = optional(\Illuminate\Support\Facades\Auth::user())->name; }
                                if (!$namaKry) { $namaKry = '-'; }

                                $tglVal = data_get($absen, 'tanggal');
                                if (!$tglVal) { $tglVal = data_get($absen, 'tgl_absen'); }
                                if (!$tglVal) { $tglVal = data_get($absen, 'tanggal_absen'); }
                                if (!$tglVal) { $tglVal = data_get($absen, 'created_at'); }
                                if (is_object($tglVal) && method_exists($tglVal, 'format')) {
                                    $tglVal = $tglVal->format('Y-m-d');
                                }
                                $tglVal = trim((string) ($tglVal ?: '-'));
                                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $tglVal)) {
                                    $tglVal = substr($tglVal, 0, 10);
                                }

                                $masukVal = data_get($absen, 'jam_masuk');
                                if (!$masukVal) { $masukVal = data_get($absen, 'waktu_masuk'); }
                                if (!$masukVal) { $masukVal = data_get($absen, 'check_in'); }
                                if (!$masukVal) { $masukVal = data_get($absen, 'masuk'); }
                                if (is_object($masukVal) && method_exists($masukVal, 'format')) {
                                    $masukVal = $masukVal->format('H:i:s');
                                }
                                $masukVal = trim((string) ($masukVal ?: '-'));

                                $pulangVal = data_get($absen, 'jam_pulang');
                                if (!$pulangVal) { $pulangVal = data_get($absen, 'waktu_pulang'); }
                                if (!$pulangVal) { $pulangVal = data_get($absen, 'check_out'); }
                                if (!$pulangVal) { $pulangVal = data_get($absen, 'pulang'); }
                                if (is_object($pulangVal) && method_exists($pulangVal, 'format')) {
                                    $pulangVal = $pulangVal->format('H:i:s');
                                }
                                $pulangVal = trim((string) ($pulangVal ?: '-'));

                                $statusVal = data_get($absen, 'status_kehadiran');
                                if (!$statusVal) { $statusVal = data_get($absen, 'status'); }
                                if (!$statusVal) { $statusVal = data_get($absen, 'status_absen'); }
                                if (!$statusVal) { $statusVal = 'Hadir'; }

                                $ketVal = data_get($absen, 'keterangan');
                                if (!$ketVal) { $ketVal = data_get($absen, 'catatan'); }
                                if (!$ketVal) { $ketVal = '-'; }
                            @endphp
                            <tr class="hover:bg-gray-50/70">
                                <td class="py-3 px-4 text-gray-600">{{ $index + 1 }}</td>
                                <td class="py-3 px-4 font-medium text-gray-800">{{ $namaKry }}</td>
                                <td class="py-3 px-4 text-gray-700">{{ $tglVal }}</td>
                                <td class="py-3 px-4 text-gray-700">{{ $masukVal }}</td>
                                <td class="py-3 px-4 text-gray-700">{{ $pulangVal }}</td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                        {{ $statusVal }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-600">{{ $ketVal }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center text-gray-400">
                                    Belum ada riwayat presensi tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>