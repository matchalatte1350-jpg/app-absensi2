<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Cuti Karyawan - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    @php
        $dashboardUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard');
        $storeUrl = \Illuminate\Support\Facades\Route::has('cuti.store') ? route('cuti.store') : url('/cuti');
        $firstKaryawanUserId = optional(($karyawans ?? collect())->first())->id_user ?? '';
    @endphp

    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Pengajuan & Data Cuti Karyawan</h2>
                <p class="text-muted">Kelola permohonan cuti dan izin ketidakhadiran karyawan</p>
            </div>
            <a href="{{ $dashboardUrl }}" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
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
                Form Pengajuan Cuti
            </div>
            <div class="card-body">
                <form action="{{ $storeUrl }}" method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="id_user" class="form-label">Pilih Karyawan</label>
                            <select name="id_user" id="id_user" class="form-select">
                                <option value="">-- Otomatis / Pilih Karyawan --</option>
                                @foreach(($karyawans ?? collect()) as $karyawan)
                                    @php
                                        $valUser = $karyawan->id_user ?? $karyawan->id ?? '';
                                    @endphp
                                    <option value="{{ $valUser }}" @selected((string) old('id_user', $firstKaryawanUserId) === (string) $valUser)>
                                        {{ $karyawan->nama_lengkap ?? $karyawan->nama ?? 'Karyawan' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="jenis_cuti" class="form-label">Jenis Cuti</label>
                            <select name="jenis_cuti" id="jenis_cuti" class="form-select" required>
                                @foreach(['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Alasan Penting'] as $jenisCuti)
                                    <option value="{{ $jenisCuti }}" @selected(old('jenis_cuti', 'Cuti Sakit') === $jenisCuti)>{{ $jenisCuti }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="{{ old('tanggal_mulai', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control" value="{{ old('tanggal_selesai', date('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="alasan" class="form-label">Alasan Cuti</label>
                        <textarea name="alasan" id="alasan" rows="3" class="form-control" placeholder="Tuliskan keterangan atau alasan pengajuan cuti..." required>{{ old('alasan') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Kirim Pengajuan Cuti</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">
                Riwayat Pengajuan Cuti
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Karyawan</th>
                                <th>Jenis Cuti</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Alasan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($cutis ?? collect()) as $index => $cuti)
                                @php
                                    $namaKaryawan = data_get($cuti, 'nama_lengkap')
                                        ?? data_get($cuti, 'karyawan.nama_lengkap')
                                        ?? data_get($cuti, 'nama_karyawan')
                                        ?? '-';
                                    $jenisVal = data_get($cuti, 'jenis_cuti') ?? data_get($cuti, 'jenis') ?? '-';
                                    $mulaiVal = data_get($cuti, 'tanggal_mulai') ?? data_get($cuti, 'tgl_mulai') ?? '-';
                                    $selesaiVal = data_get($cuti, 'tanggal_selesai') ?? data_get($cuti, 'tgl_selesai') ?? '-';
                                    $alasanVal = data_get($cuti, 'alasan') ?? data_get($cuti, 'keterangan') ?? '-';
                                    $statusVal = data_get($cuti, 'status') ?? 'Menunggu';
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $namaKaryawan }}</td>
                                    <td>{{ $jenisVal }}</td>
                                    <td>{{ $mulaiVal }}</td>
                                    <td>{{ $selesaiVal }}</td>
                                    <td>{{ $alasanVal }}</td>
                                    <td><span class="badge bg-warning text-dark">{{ $statusVal }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada riwayat cuti.</td>
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