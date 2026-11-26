<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Lembur - PT GTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#f4f5f7] text-slate-800 min-h-screen py-6 px-4" x-data="formLembur()">

    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard');
        $tanggalDefault = $defaultDate ?? \Carbon\Carbon::now('Asia/Jakarta')->addDay()->toDateString();
    @endphp

    <div class="max-w-xl mx-auto space-y-4">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 px-5 py-4 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Pengajuan Lembur</h1>
                <p class="text-xs text-slate-500 mt-0.5">PT. Generasi Tirta Abadi</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition">
                Kembali
            </a>
        </div>

        @if (session('success'))
            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs sm:text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-sm sm:text-base font-bold text-slate-900">Form Pengajuan</h2>
                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700" x-text="'Estimasi: ' + estimasiDurasi"></span>
            </div>

            <form action="{{ route('lembur.store') }}" method="POST" class="space-y-3.5">
                @csrf

                <div>
                    <label for="tanggal_lembur" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Rencana Lembur (Otomatis Besok)
                    </label>
                    <input
                        type="date"
                        id="tanggal_lembur"
                        name="tanggal_lembur"
                        value="{{ old('tanggal_lembur', $tanggalDefault) }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="jam_mulai" class="block text-xs font-semibold text-slate-700 mb-1">
                            Jam Mulai
                        </label>
                        <input
                            type="time"
                            id="jam_mulai"
                            name="jam_mulai"
                            x-model="jamMulai"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-600"
                        >
                    </div>

                    <div>
                        <label for="jam_selesai" class="block text-xs font-semibold text-slate-700 mb-1">
                            Jam Selesai
                        </label>
                        <input
                            type="time"
                            id="jam_selesai"
                            name="jam_selesai"
                            x-model="jamSelesai"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-600"
                        >
                    </div>
                </div>

                <div>
                    <label for="keterangan" class="block text-xs font-semibold text-slate-700 mb-1">
                        Keterangan Pekerjaan
                    </label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        rows="3"
                        required
                        placeholder="Tuliskan detail pekerjaan lembur..."
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
                    >{{ old('keterangan') }}</textarea>
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-extrabold tracking-wide uppercase shadow-sm transition cursor-pointer"
                    >
                        KIRIM PENGAJUAN
                    </button>
                </div>
            </form>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200/90 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900">Riwayat Lembur</h2>
                <span class="text-[11px] text-slate-400">Terbaru</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-[11px] uppercase border-b border-slate-200">
                            <th class="py-3 px-4">Tgl Lembur</th>
                            <th class="py-3 px-4">Durasi</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse (($riwayatLembur ?? collect()) as $row)
                            @php
                                $tglRaw = data_get($row, 'tanggal_lembur') ?? data_get($row, 'tanggal') ?? data_get($row, 'tgl_lembur') ?? data_get($row, 'date');
                                $tglText = '-';
                                if (!empty($tglRaw)) {
                                    try {
                                        $tglText = \Carbon\Carbon::parse($tglRaw, 'Asia/Jakarta')->locale('id')->translatedFormat('d M Y');
                                    } catch (\Throwable $e) {
                                        $tglText = (string) $tglRaw;
                                    }
                                }

                                $durasiRaw = data_get($row, 'durasi_jam') ?? data_get($row, 'durasi') ?? data_get($row, 'total_jam') ?? data_get($row, 'lama_lembur');
                                if (is_numeric($durasiRaw)) {
                                    $durasiText = (float) $durasiRaw . ' Jam';
                                } else {
                                    $durasiText = !empty($durasiRaw) ? (string) $durasiRaw : '-';
                                }

                                $statusText = data_get($row, 'status') ?? data_get($row, 'status_validasi') ?? data_get($row, 'status_pengajuan') ?? 'Menunggu';
                                $statusLower = strtolower($statusText);

                                if (str_contains($statusLower, 'setuju') || str_contains($statusLower, 'approv') || str_contains($statusLower, 'valid')) {
                                    $badgeClass = 'bg-emerald-50 text-emerald-700';
                                } elseif (str_contains($statusLower, 'tolak') || str_contains($statusLower, 'reject')) {
                                    $badgeClass = 'bg-rose-50 text-rose-700';
                                } else {
                                    $badgeClass = 'bg-amber-50 text-amber-700';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="py-3 px-4 font-medium text-slate-800">
                                    {{ $tglText }}
                                    @if (data_get($row, 'keterangan') ?? data_get($row, 'alasan') ?? data_get($row, 'pekerjaan'))
                                        <p class="text-[11px] text-slate-400 font-normal mt-0.5 line-clamp-1">
                                            {{ data_get($row, 'keterangan') ?? data_get($row, 'alasan') ?? data_get($row, 'pekerjaan') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-700">
                                    {{ $durasiText }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 px-4 text-center text-slate-400">
                                    Belum ada riwayat pengajuan lembur.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <script>
        function formLembur() {
            return {
                jamMulai: @js(old('jam_mulai', '17:00')),
                jamSelesai: @js(old('jam_selesai', '19:00')),
                get estimasiDurasi() {
                    if (!this.jamMulai || !this.jamSelesai) return '-';
                    const [mH, mM] = this.jamMulai.split(':').map(Number);
                    const [sH, sM] = this.jamSelesai.split(':').map(Number);
                    if ([mH, mM, sH, sM].some(Number.isNaN)) return '-';

                    let startMin = mH * 60 + mM;
                    let endMin = sH * 60 + sM;
                    if (endMin <= startMin) endMin += 24 * 60;

                    const diff = endMin - startMin;
                    const jam = Math.floor(diff / 60);
                    const menit = diff % 60;

                    if (jam > 0 && menit > 0) return `${jam} Jam ${menit} Menit`;
                    if (jam > 0) return `${jam} Jam`;
                    return `${menit} Menit`;
                }
            }
        }
    </script>
</body>
</html>