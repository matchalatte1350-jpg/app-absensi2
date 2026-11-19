<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Manajemen Data Karyawan</h2>
                <p class="text-muted">Kelola akun, data pegawai, dan hak akses role sistem</p>
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
                Tambah Karyawan Baru
            </div>
            <div class="card-body">
                <form action="{{ route('karyawan.store') }}" method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control" placeholder="Contoh: Rian Utama" value="{{ old('nama_lengkap') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="kode_karyawan" class="form-label">Kode Karyawan</label>
                            <input type="text" name="kode_karyawan" id="kode_karyawan" class="form-control" placeholder="Contoh: EMP004" value="{{ old('kode_karyawan') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="username" class="form-label">Username Login</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Contoh: rian" value="{{ old('username') }}" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role Akses</label>
                            <select name="role" id="role" class="form-select" required>
                                @foreach(['karyawan' => 'Karyawan', 'kepala_divisi' => 'Kepala Divisi', 'manajer' => 'Manajer', 'admin' => 'Admin'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">Simpan Data Karyawan</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-bold">
                Daftar Pegawai Aktif
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Karyawan</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawans as $index => $karyawan)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $karyawan->kode_karyawan }}</td>
                                    <td>{{ $karyawan->nama_lengkap }}</td>
                                    <td>{{ $karyawan->username }}</td>
                                    <td><span class="badge bg-info text-dark text-uppercase">{{ $karyawan->role }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data karyawan.</td>
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
