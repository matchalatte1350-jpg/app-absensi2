<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Presensi / Absen Harian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-header bg-primary text-white py-3">
                        <h4 class="mb-0"><i class="fa-solid fa-fingerprint me-2"></i>Form Presensi / Absen Harian</h4>
                    </div>
                    <div class="card-body p-4">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="alert alert-info py-2 mb-4">
                            <small><i class="fa-solid fa-user me-1"></i> Login sebagai: <strong>{{ $user->nama_lengkap ?? $user->username ?? 'Karyawan' }}</strong> (Role: {{ ucfirst($user->role ?? '-') }})</small>
                        </div>

                        <form action="{{ route('absensi.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="tanggal" class="form-label fw-bold">Tanggal Absen</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="status_kehadiran" class="form-label fw-bold">Status Kehadiran</label>
                                <select class="form-select" id="status_kehadiran" name="status_kehadiran" required>
                                    @foreach(['Hadir', 'Izin', 'Sakit', 'Cuti'] as $statusKehadiran)
                                        <option value="{{ $statusKehadiran }}" @selected(old('status_kehadiran') === $statusKehadiran)>{{ $statusKehadiran }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="keterangan" class="form-label fw-bold">Keterangan / Catatan (Opsional)</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Masukkan keterangan jika izin/sakit...">{{ old('keterangan') }}</textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
                                </a>
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Kirim Presensi Sekarang
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <h5 class="fw-bold">Riwayat Presensi</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Karyawan</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($absens as $index => $absen)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $absen->karyawan->nama_lengkap ?? '-' }}</td>
                                            <td>{{ $absen->tanggal?->format('Y-m-d') ?? $absen->tanggal }}</td>
                                            <td>{{ $absen->status_kehadiran }}</td>
                                            <td>{{ $absen->keterangan ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat presensi.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
