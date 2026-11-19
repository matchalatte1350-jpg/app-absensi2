<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pengajuan Lembur - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Pengajuan Lembur Karyawan</h2>
                <p class="text-muted">Fasilitas pengajuan jam kerja tambahan di luar jam reguler</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
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
            <div class="card-header bg-primary text-white fw-bold">
                Form Pengajuan Lembur
            </div>
            <div class="card-body">
                <form action="{{ route('lembur.store') }}" method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="id_user" class="form-label">Pilih Karyawan</label>
                            <select name="id_user" id="id_user" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach($karyawans as $karyawan)
                                    <option value="{{ $karyawan->id_user }}" @selected(old('id_user') == $karyawan->id_user)>{{ $karyawan->nama_lengkap }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tanggal_lembur" class="form-label">Tanggal Rencana Lembur</label>
                            <input type="date" name="tanggal_lembur" id="tanggal_lembur" class="form-control" value="{{ old('tanggal_lembur', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="durasi" class="form-label">Estimasi Durasi</label>
                            <input type="text" name="durasi" id="durasi" class="form-control" placeholder="Contoh: 2 Jam" value="{{ old('durasi') }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan Pekerjaan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" class="form-control" placeholder="Tuliskan detail tugas atau pekerjaan lembur..." required>{{ old('keterangan') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Kirim Pengajuan Lembur</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">
                Riwayat Pengajuan Lembur
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Karyawan</th>
                                <th>Tanggal Lembur</th>
                                <th>Durasi</th>
                                <th>Keterangan</th>
                                <th>Status Persetujuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lemburs as $index => $lembur)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $lembur->karyawan->nama_lengkap ?? '-' }}</td>
                                    <td>{{ $lembur->tanggal_lembur }}</td>
                                    <td>{{ $lembur->durasi }}</td>
                                    <td>{{ $lembur->keterangan }}</td>
                                    <td><span class="badge bg-warning text-dark">{{ $lembur->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat lembur.</td>
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
