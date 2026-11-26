<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PT. Generasi Tirta Abadi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f172a] min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl grid grid-cols-1 md:grid-cols-2 border border-slate-700/60">

        <!-- Panel Kiri -->
        <div class="bg-gradient-to-br from-[#8c5332] via-[#6d3d23] to-[#4a2715] text-white p-8 sm:p-10 flex flex-col items-center justify-center text-center">
            <div class="w-20 h-20 rounded-2xl bg-amber-100/15 border border-amber-200/30 flex items-center justify-center mb-5 shadow-inner">
                <svg class="w-11 h-11 text-amber-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1m-6 4h1m4 0h1m-6 4h6" />
                </svg>
            </div>

            <h2 class="text-xl sm:text-2xl font-bold tracking-wide text-amber-50">
                PT. Generasi Tirta Abadi
            </h2>
            <p class="text-xs sm:text-sm text-amber-100/85 mt-2 max-w-xs leading-relaxed">
                Sistem Informasi Presensi &amp; Manajemen Karyawan
            </p>
        </div>

        <!-- Panel Kanan -->
        <div class="bg-[#26292e] text-white p-8 sm:p-10 flex flex-col justify-center">
            <h1 class="text-center text-lg sm:text-xl font-bold tracking-widest uppercase text-slate-100 mb-6">
                LOGIN
            </h1>

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-rose-500/15 border border-rose-400/40 text-rose-200 text-xs sm:text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.process') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="{{ old('username') }}"
                        placeholder="USERNAME"
                        required
                        autofocus
                        class="w-full px-4 py-3 rounded-lg bg-slate-100 text-slate-900 placeholder-slate-400 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-amber-400 transition"
                    >
                </div>

                <div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="PASSWORD"
                        required
                        class="w-full px-4 py-3 rounded-lg bg-slate-100 text-slate-900 placeholder-slate-400 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-amber-400 transition"
                    >
                </div>

                <div class="pt-2 flex justify-center">
                    <button
                        type="submit"
                        class="w-full sm:w-56 py-2.5 px-6 rounded-md bg-[#c96f4b] hover:bg-[#b55e3b] text-white text-xs sm:text-sm font-bold tracking-wider uppercase shadow-md transition cursor-pointer"
                    >
                        MASUK SEKARANG
                    </button>
                </div>
            </form>
        </div>

    </div>

</body>
</html>