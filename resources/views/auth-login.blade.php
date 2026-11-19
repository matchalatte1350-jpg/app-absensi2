<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PT Generasi Tirta Abadi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.98);
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            border-color: #2563eb;
        }
        .btn-primary {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: #2563eb;
            border: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card login-card p-4 p-md-5">
                    
                    <div class="text-center mb-4">
                        <h4 class="fw-bold text-dark mb-1">Selamat Datang</h4>
                        <p class="text-muted small">Sistem Manajemen PT Generasi Tirta Abadi</p>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success py-2 small mb-3">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger py-2 small mb-3">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('login.process') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" value="{{ old('username') }}" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 shadow-sm">
                            Masuk ke Dashboard
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>