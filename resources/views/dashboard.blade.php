<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">Dashboard Sistem Absensi & Gaji</h1>
                <p class="text-muted mb-0">PT Generasi Tirta Abadi</p>
            </div>
            <div>
                <span class="badge bg-primary px-3 py-2">{{ ucfirst($user->role ?? 'Tamu') }}</span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline ms-2">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Logout</button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card p-3 shadow-sm border-0">
                    <div class="text-muted">Total Karyawan</div>
                    <div class="h4 fw-bold">{{ $totalKaryawan }} Orang</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm border-0">
                    <div class="text-muted">Absen Masuk Hari Ini</div>
                    <div class="h4 fw-bold">{{ $totalAbsenHariIni }} Orang</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm border-0">
                    <div class="text-muted">Cuti Menunggu</div>
                    <div class="h4 fw-bold">{{ $totalCutiPending }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 shadow-sm border-0">
                    <div class="text-muted">Lembur Menunggu</div>
                    <div class="h4 fw-bold">{{ $totalLemburPending }}</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 p-4">
            <h5 class="fw-bold mb-3">Menu Utama & Navigasi Sistem</h5>

            <div class="row g-3">
                <div class="col-md-4">
                    <a href="{{ route('absen.index') }}" class="btn btn-outline-primary w-100 p-3 text-start fw-bold">
                        Form Presensi / Absen Harian
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('lembur.index') }}" class="btn btn-outline-success w-100 p-3 text-start fw-bold">
                        Pengajuan & Riwayat Lembur
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('gaji.index') }}" class="btn btn-outline-secondary w-100 p-3 text-start fw-bold">
                        Laporan Gaji & Kehadiran
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="{{ route('karyawan.index') }}" class="btn btn-outline-success w-100 p-3 text-start">
                        <div class="fw-bold">Manajemen Karyawan</div>
                        <small class="text-muted">Kelola daftar pegawai dan hak akses</small>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="{{ route('cuti.index') }}" class="btn btn-outline-primary w-100 p-3 text-start">
                        <div class="fw-bold">Manajemen Cuti</div>
                        <small class="text-muted">Pengajuan dan riwayat cuti karyawan</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
