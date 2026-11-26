<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - PT GTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-[#f4f5f7] text-slate-800 min-h-screen" x-data="manajemenKaryawan()">

    @php
        $dashboardUrl  = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard');
        $karyawanUrl   = \Illuminate\Support\Facades\Route::has('karyawan.index') ? route('karyawan.index') : url('/karyawan');
        $lemburIzinUrl = \Illuminate\Support\Facades\Route::has('lembur.index') ? route('lembur.index') : (\Illuminate\Support\Facades\Route::has('cuti.index') ? route('cuti.index') : url('/lembur'));
        $cutiUrl       = \Illuminate\Support\Facades\Route::has('cuti.index') ? route('cuti.index') : url('/cuti');
    @endphp

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Sidebar Kiri -->
        <aside class="w-full lg:w-56 bg-white border-b lg:border-b-0 lg:border-r border-slate-200 p-4 flex flex-col justify-between shrink-0">
            <div class="space-y-4">
                <div class="flex items-center gap-2.5 px-2.5 py-2 border border-slate-300 rounded-lg bg-slate-50">
                    <div class="w-8 h-8 rounded-md bg-slate-800 text-white font-extrabold text-xs flex items-center justify-center">
                        GTA
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-slate-900 leading-none">logo</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">PT. Generasi Tirta Abadi</p>
                    </div>
                </div>

                <nav class="space-y-1.5 text-xs sm:text-sm font-medium">
                    <a href="{{ $dashboardUrl }}" class="block px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                        dashboard
                    </a>
                    <a href="{{ $karyawanUrl }}" class="block px-3 py-2 rounded-lg border border-slate-800 bg-slate-800 text-white font-semibold transition">
                        karyawan
                    </a>
                    <a href="{{ $lemburIzinUrl }}" class="block px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                        data lembur &amp; izin
                    </a>
                    <a href="{{ $cutiUrl }}" class="block px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                        cuti karyawan
                    </a>
                    <a href="{{ $dashboardUrl }}" class="block px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                        pengaturan
                    </a>
                    <a href="{{ $dashboardUrl }}#riwayat-absensi" class="block px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                        laporan
                    </a>
                </nav>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-200">
                @if (\Illuminate\Support\Facades\Route::has('logout'))
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs sm:text-sm font-semibold text-left transition cursor-pointer">
                            logout
                        </button>
                    </form>
                @endif
            </div>
        </aside>

        <!-- Konten Utama -->
        <main class="flex-1 p-4 sm:p-6 space-y-5">

            <div class="bg-white rounded-xl border border-slate-200 px-5 py-3.5 shadow-sm flex items-center justify-between">
                <h1 class="text-lg sm:text-xl font-bold text-slate-900">Manajemen Karyawan</h1>
                <span class="text-xs text-slate-500">Total: {{ ($karyawans ?? collect())->count() }} Karyawan</span>
            </div>

            @if (session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

                <!-- Form Registrasi Baru -->
                <section class="xl:col-span-5 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900 pb-2.5 mb-4 border-b border-slate-100">
                        Registrasi Baru
                    </h2>

                    <form action="{{ route('karyawan.store') }}" method="POST" class="space-y-3">
                        @csrf

                        <div>
                            <label for="kode_karyawan" class="block text-xs font-semibold text-slate-700 mb-1">Kode Karyawan</label>
                            <input type="text" id="kode_karyawan" name="kode_karyawan" value="{{ old('kode_karyawan') }}" placeholder="Contoh: GTA-001" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                        </div>

                        <div>
                            <label for="nama_lengkap" class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" placeholder="Masukkan nama lengkap" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                        </div>

                        <div>
                            <label for="username" class="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                            <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan username login" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                        </div>

                        <div>
                            <label for="no_telepon" class="block text-xs font-semibold text-slate-700 mb-1">No. Telephone / WA</label>
                            <input type="text" id="no_telepon" name="no_telepon" value="{{ old('no_telepon') }}" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                            <input type="password" id="password" name="password" placeholder="Minimal 4 karakter" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                        </div>

                        <div>
                            <label for="status_kerja" class="block text-xs font-semibold text-slate-700 mb-1">Status Kerja</label>
                            <select id="status_kerja" name="status_kerja" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-700">
                                <option value="Tetap" {{ old('status_kerja') == 'Tetap' ? 'selected' : '' }}>Tetap</option>
                                <option value="Kontrak" {{ old('status_kerja') == 'Kontrak' ? 'selected' : '' }}>Kontrak</option>
                                <option value="Magang" {{ old('status_kerja') == 'Magang' ? 'selected' : '' }}>Magang</option>
                            </select>
                        </div>

                        <div>
                            <label for="divisi" class="block text-xs font-semibold text-slate-700 mb-1">Divisi</label>
                            <input type="text" id="divisi" name="divisi" list="daftar-divisi" value="{{ old('divisi', 'Operasional') }}" placeholder="Pilih atau ketik divisi" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-700">
                            <datalist id="daftar-divisi">
                                @foreach (($divisis ?? collect()) as $div)
                                    @php $labelDiv = data_get($div, 'nama_divisi') ?? data_get($div, 'nama') ?? ''; @endphp
                                    @if ($labelDiv !== '')
                                        <option value="{{ $labelDiv }}"></option>
                                    @endif
                                @endforeach
                                <option value="Operasional"></option>
                                <option value="IT & Sistem"></option>
                                <option value="HRD & Umum"></option>
                                <option value="Keuangan"></option>
                            </datalist>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs sm:text-sm font-semibold shadow-sm transition cursor-pointer">
                                simpan data
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Tabel Daftar Karyawan -->
                <section class="xl:col-span-7 bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <h2 class="text-sm font-bold text-slate-900">Daftar Karyawan</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-600 text-[11px] uppercase border-b border-slate-200">
                                    <th class="py-3 px-3.5">Karyawan</th>
                                    <th class="py-3 px-3.5">Kode</th>
                                    <th class="py-3 px-3.5">Kontak</th>
                                    <th class="py-3 px-3.5">Divisi</th>
                                    <th class="py-3 px-3.5">Status Kerja</th>
                                    <th class="py-3 px-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse (($karyawans ?? collect()) as $item)
                                    @php
                                        $rowId     = data_get($item, 'id') ?? data_get($item, 'id_karyawan');$namaVal   = data_get($item, 'nama_lengkap') ?? data_get($item, 'nama') ?? data_get($item, 'user_name') ?? data_get($item, 'name') ?? '-';
                                        $kodeVal   = data_get($item, 'kode_karyawan') ?? data_get($item, 'kode') ?? data_get($item, 'nip') ?? ('KRY-' . $rowId);$kontakVal = data_get($item, 'no_telepon') ?? data_get($item, 'no_telp') ?? data_get($item, 'telepon') ?? data_get($item, 'no_wa') ?? data_get($item, 'kontak') ?? data_get($item, 'user_email') ?? '-';
                                        $divisiVal = data_get($item, 'nama_divisi_relasi') ?? data_get($item, 'divisi') ?? data_get($item, 'nama_divisi') ?? data_get($item, 'jabatan') ?? 'Operasional';$statusVal = data_get($item, 'status_kerja') ?? data_get($item, 'status') ?? 'Tetap';
                                    @endphp
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="py-3 px-3.5 font-semibold text-slate-900">{{ $namaVal }}</td>
                                        <td class="py-3 px-3.5 font-mono text-xs text-slate-700">{{ $kodeVal }}</td>
                                        <td class="py-3 px-3.5 text-xs text-slate-600">{{ $kontakVal }}</td>
                                        <td class="py-3 px-3.5 text-xs text-slate-700">{{ $divisiVal }}</td>
                                        <td class="py-3 px-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700">
                                                {{ $statusVal }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="openEdit({
                                                        id: @js($rowId),
                                                        kode_karyawan: @js($kodeVal),
                                                        nama_lengkap: @js($namaVal),
                                                        no_telepon: @js($kontakVal),
                                                        divisi: @js($divisiVal),
                                                        status_kerja: @js($statusVal)
                                                    })"
                                                    class="px-2.5 py-1 rounded-md bg-amber-100 hover:bg-amber-200 text-amber-800 text-xs font-semibold transition cursor-pointer"
                                                >
                                                    Edit
                                                </button>

                                                @if (\Illuminate\Support\Facades\Route::has('karyawan.destroy') && $rowId)
                                                    <form action="{{ route('karyawan.destroy', $rowId) }}" method="POST" onsubmit="return confirm('Hapus data karyawan ini?')" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-2.5 py-1 rounded-md bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs font-semibold transition cursor-pointer">
                                                            Hapus
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 px-4 text-center text-slate-400">
                                            Belum ada data karyawan tersimpan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Modal Edit Karyawan -->
    <div