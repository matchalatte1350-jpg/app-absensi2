<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan - PT GTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 antialiased min-h-screen">
    @php
        $user = \Illuminate\Support\Facades\Auth::user();
        $namaUser = $user->name ?? $user->nama_lengkap ?? $user->nama ?? 'Karyawan';
        $nowWib = \Carbon\Carbon::now('Asia/Jakarta');
        $todayWib = $nowWib->toDateString();

        $tabelAbsen = null;
        foreach (['absensis', 'absensi', 'presensis', 'presensi', 'riwayat_absensis', 'riwayat_absensi'] as $tbl) {
            if (\Illuminate\Support\Facades\Schema::hasTable($tbl)) {
                $tabelAbsen = $tbl;
                break;
            }
        }

        if (!$tabelAbsen && \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            try {
                $pgTables = \Illuminate\Support\Facades\DB::select("SELECT tablename FROM pg_tables WHERE schemaname = current_schema()");
                foreach ($pgTables as $pt) {
                    $tName = (string) ($pt->tablename ?? '');
                    if (str_contains(strtolower($tName), 'absen') || str_contains(strtolower($tName), 'presensi')) {
                        $tabelAbsen = $tName;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Abaikan
            }
        }

        $formatWaktu = function ($val) {
            if ($val === null || $val === '' || $val === '-') {
                return null;
            }
            $str = trim((string) $val);
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
                return substr($str, 0, 5);
            }
            try {
                return \Carbon\Carbon::parse($str)->timezone('Asia/Jakarta')->format('H:i');
            } catch (\Throwable $e) {
                return $str;
            }
        };

        $riwayatDashboard = collect();
        $sudahMasukHariIni = false;
        $sudahPulangHariIni = false;
        $jamMasukHariIni = null;
        $jamPulangHariIni = null;

        if ($tabelAbsen) {
            try {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing($tabelAbsen);
                $orderCol = null;
                foreach (['tanggal', 'tgl_absen', 'tanggal_absen', 'id', 'id_absensi', 'id_absen', 'created_at'] as $c) {
                    if (in_array($c, $cols, true)) {
                        $orderCol = $c;
                        break;
                    }
                }
                if (!$orderCol && !empty($cols)) {
                    $orderCol = $cols[0];
                }

                $query = \Illuminate\Support\Facades\DB::table($tabelAbsen);
                if ($orderCol) {
                    $query->orderByDesc($orderCol);
                }

                $rows = $query->limit(15)->get();

                $riwayatDashboard = $rows->take(7)->map(function ($row) use ($formatWaktu) {
                    $arr = (array) $row;

                    $tglRaw = null;
                    foreach (['tanggal', 'tgl_absen', 'tanggal_absen', 'tgl'] as $k) {
                        if (!empty($arr[$k])) {
                            $tglRaw = $arr[$k];
                            break;
                        }
                    }
                    if (!$tglRaw && !empty($arr['created_at'])) {
                        try {
                            $tglRaw = \Carbon\Carbon::parse($arr['created_at'])->timezone('Asia/Jakarta')->toDateString();
                        } catch (\Throwable $e) {
                            $tglRaw = (string) $arr['created_at'];
                        }
                    }
                    $tgl = $tglRaw ? substr((string) $tglRaw, 0, 10) : '-';

                    $masukVal = null;
                    foreach (['jam_masuk', 'waktu_masuk', 'check_in', 'masuk', 'jam_absen', 'waktu_absen', 'jam', 'waktu'] as $k) {
                        if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                            $masukVal = $formatWaktu($arr[$k]);
                            if ($masukVal) {
                                break;
                            }
                        }
                    }
                    if (!$masukVal) {
                        foreach ($arr as $colKey => $colVal) {
                            $lowKey = strtolower((string) $colKey);
                            if ((str_contains($lowKey, 'masuk') || str_contains($lowKey, 'in')) && $colVal !== null && $colVal !== '') {
                                $masukVal = $formatWaktu($colVal);
                                if ($masukVal) {
                                    break;
                                }
                            }
                        }
                    }
                    if (!$masukVal && !empty($arr['created_at'])) {
                        $masukVal = $formatWaktu($arr['created_at']);
                    }

                    $pulangVal = null;
                    foreach (['jam_pulang', 'waktu_pulang', 'check_out', 'pulang', 'jam_keluar', 'waktu_keluar'] as $k) {
                        if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                            $pulangVal = $formatWaktu($arr[$k]);
                            if ($pulangVal) {
                                break;
                            }
                        }
                    }
                    if (!$pulangVal) {
                        foreach ($arr as $colKey => $colVal) {
                            $lowKey = strtolower((string) $colKey);
                            if ((str_contains($lowKey, 'pulang') || str_contains($lowKey, 'out') || str_contains($lowKey, 'keluar')) && $colVal !== null && $colVal !== '') {
                                $pulangVal = $formatWaktu($colVal);
                                if ($pulangVal) {
                                    break;
                                }
                            }
                        }
                    }
                    if (!$pulangVal && !empty($arr['updated_at']) && !empty($arr['created_at']) && (string) $arr['updated_at'] !== (string) $arr['created_at']) {
                        $pulangVal = $formatWaktu($arr['updated_at']);
                    }

                    $statusVal = null;
                    foreach (['status', 'status_absen', 'keterangan'] as $k) {
                        if (!empty($arr[$k])) {
                            $statusVal = (string) $arr[$k];
                            break;
                        }
                    }

                    data_set($row, 'tanggal_tampil', $tgl);
                    data_set($row, 'jam_masuk_tampil', $masukVal ?? '-');
                    data_set($row, 'jam_pulang_tampil', $pulangVal ?? '-');
                    data_set($row, 'status_tampil', $statusVal ?? 'Hadir');

                    return $row;
                });

                foreach ($rows as $r) {
                    $a = (array) $r;
                    $rTgl = null;
                    foreach (['tanggal', 'tgl_absen', 'tanggal_absen', 'tgl'] as $tk) {
                        if (!empty($a[$tk])) {
                            $rTgl = substr((string) $a[$tk], 0, 10);
                            break;
                        }
                    }
                    if (!$rTgl && !empty($a['created_at'])) {
                        $rTgl = \Carbon\Carbon::parse($a['created_at'])->timezone('Asia/Jakarta')->toDateString();
                    }

                    if ($rTgl === $todayWib) {
                        $sudahMasukHariIni = true;
                        foreach (['jam_masuk', 'waktu_masuk', 'check_in', 'masuk'] as $mc) {
                            if (!empty($a[$mc]) && $a[$mc] !== '-') {
                                $jamMasukHariIni = $formatWaktu($a[$mc]);
                                break;
                            }
                        }
                        if (!$jamMasukHariIni && !empty($a['created_at'])) {
                            $jamMasukHariIni = $formatWaktu($a['created_at']);
                        }

                        foreach (['jam_pulang', 'waktu_pulang', 'check_out', 'pulang'] as $pc) {
                            if (!empty($a[$pc]) && $a[$pc] !== '-') {
                                $sudahPulangHariIni = true;
                                $jamPulangHariIni = $formatWaktu($a[$pc]);
                                break;
                            }
                        }
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $riwayatDashboard = collect();
            }
        }

        $actionMasuk = \Illuminate\Support\Facades\Route::has('absen.masuk')
            ? route('absen.masuk')
            : (\Illuminate\Support\Facades\Route::has('absensi.masuk') ? route('absensi.masuk') : url('/absen/masuk'));

        $actionPulang = \Illuminate\Support\Facades\Route::has('absen.pulang')
            ? route('absen.pulang')
            : (\Illuminate\Support\Facades\Route::has('absensi.pulang') ? route('absensi.pulang') : url('/absen/pulang'));

        $urlRiwayat = \Illuminate\Support\Facades\Route::has('absen.index')
            ? route('absen.index')
            : (\Illuminate\Support\Facades\Route::has('absensi.index') ? route('absensi.index') : url('/absen'));

        $urlCuti = \Illuminate\Support\Facades\Route::has('cuti.index')
            ? route('cuti.index')
            : url('/cuti');

        $urlLembur = \Illuminate\Support\Facades\Route::has('lembur.index')
            ? route('lembur.index')
            : url('/lembur');

        $urlKaryawan = \Illuminate\Support\Facades\Route::has('karyawan.index')
            ? route('karyawan.index')
            : url('/karyawan');

        $urlLogout = \Illuminate\Support\Facades\Route::has('logout')
            ? route('logout')
            : url('/logout');
    @endphp

    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">PT GTA</h1>
            <p class="text-sm text-gray-500">Sistem Presensi Mobile Karyawan</p>
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Halo, {{ $namaUser }}! 👋</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $nowWib->translatedFormat('d M Y') }}</p>
                    <span class="inline-block mt-2 px-3 py-1 rounded-md bg-gray-100 text-gray-700 text-xs font-mono font-semibold">
                        {{ $nowWib->format('H.i.s') }}
                    </span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center font-bold text-gray-700">
                        {{ strtoupper(substr((string) $namaUser, 0, 1)) }}
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1">Foto Profil</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <form action="{{ $actionMasuk }}" method="POST" class="w-full">
                    @csrf
                    <input type="hidden" name="tipe" value="masuk">
                    <button
                        type="submit"
                        class="w-full py-5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm transition flex flex-col items-center justify-center gap-1 cursor-pointer"
                    >
                        <span class="text-sm font-bold tracking-wide">MASUK</span>
                        <span class="text-xs text-emerald-100">
                            @if($sudahMasukHariIni)
                                Tercatat ({{ $jamMasukHariIni ?? $nowWib->format('H:i') }})
                            @else
                                Belum Absen Hari Ini
                            @endif
                        </span>
                    </button>
                </form>

                <form action="{{ $actionPulang }}" method="POST" class="w-full">
                    @csrf
                    <input type="hidden" name="tipe" value="pulang">
                    <button
                        type="submit"
                        class="w-full py-5 px-4 rounded-xl border transition flex flex-col items-center justify-center gap-1 cursor-pointer {{ $sudahMasukHariIni ? 'bg-amber-500 hover:bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-600 border-gray-200' }}"
                    >
                        <span class="text-sm font-bold tracking-wide">PULANG</span>
                        <span class="text-xs">
                            @if($sudahPulangHariIni)
                                Sudah Pulang ({{ $jamPulangHariIni ?? $nowWib->format('H:i') }})
                            @elseif($sudahMasukHariIni)
                                Siap Absen Pulang
                            @else
                                Klik untuk Absen Pulang
                            @endif
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="{{ $urlRiwayat }}" class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl py-3.5 px-4 text-center text-sm font-semibold text-gray-700 shadow-sm transition">
                Riwayat Absen
            </a>
            <a href="{{ $urlCuti }}" class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl py-3.5 px-4 text-center text-sm font-semibold text-gray-700 shadow-sm transition">
                Cuti Karyawan
            </a>
            <a href="{{ $urlLembur }}" class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl py-3.5 px-4 text-center text-sm font-semibold text-gray-700 shadow-sm transition">
                Ajukan Lembur
            </a>
            <a href="{{ $urlKaryawan }}" class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl py-3.5 px-4 text-center text-sm font-semibold text-gray-700 shadow-sm transition">
                Manajemen Karyawan
            </a>
        </div>

        <form action="{{ $urlLogout }}" method="POST" class="w-full">
            @csrf
            <button type="submit" class="w-full bg-white hover:bg-rose-50 border border-gray-200 rounded-xl py-3 px-4 text-center text-sm font-semibold text-rose-600 shadow-sm transition cursor-pointer">
                Keluar Aplikasi
            </button>
        </form>

        <div id="riwayat-absensi" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">Riwayat Presensi Terakhir</h3>
                <span class="text-xs text-gray-400">7 Hari Terakhir</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            <th class="py-2.5 px-3">Tanggal</th>
                            <th class="py-2.5 px-3">Jam Masuk</th>
                            <th class="py-2.5 px-3">Jam Pulang</th>
                            <th class="py-2.5 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($riwayatDashboard as $row)
                            <tr>
                                <td class="py-2.5 px-3 text-gray-700">{{ $row->tanggal_tampil ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-gray-700">{{ $row->jam_masuk_tampil ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-gray-700">{{ $row->jam_pulang_tampil ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-gray-700">{{ $row->status_tampil ?? 'Hadir' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 px-3 text-center text-gray-400">Belum ada riwayat presensi tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>