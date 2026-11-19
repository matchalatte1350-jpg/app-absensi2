<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Gaji & Kehadiran - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Laporan Gaji & Kehadiran</h2>
                <p class="text-muted">Rekapitulasi rincian gaji, kehadiran, dan tunjangan karyawan</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">
                Rekapitulasi Gaji & Kehadiran Karyawan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Karyawan</th>
                                <th>Periode</th>
                                <th>Kehadiran (Hari)</th>
                                <th>Jam Lembur</th>
                                <th>Total Gaji</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gajis as $index => $gaji)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $gaji->karyawan->nama_lengkap ?? '-' }}</td>
                                    <td>{{ $gaji->periode }}</td>
                                    <td>{{ $gaji->hari_hadir }}</td>
                                    <td>{{ $gaji->jam_lembur }}</td>
                                    <td>Rp {{ number_format($gaji->total_gaji, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-success">{{ $gaji->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada data gaji.</td>
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
</html>
