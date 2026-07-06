<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Login | AutoRide</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1c1c2e 0%, #2d1b3d 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 420px; width: 100%; }
        .login-header { background: linear-gradient(135deg, #e63946, #c1121f); border-radius: 16px 16px 0 0; padding: 32px; text-align: center; color: #fff; }
        .login-header h2 { font-weight: 800; margin: 0; letter-spacing: -0.5px; }
        .login-body { padding: 32px; }
        .btn-login { background: linear-gradient(135deg, #e63946, #c1121f); border: none; color: #fff; font-weight: 700; padding: 12px; border-radius: 8px; font-size: 1rem; }
        .btn-login:hover { background: linear-gradient(135deg, #c1121f, #a00f19); color: #fff; }
        .form-control:focus { border-color: #e63946; box-shadow: 0 0 0 0.2rem rgba(230,57,70,0.15); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="login-card card">
                <div class="login-header">
                    <div style="font-size:2.5rem; margin-bottom:8px;"><i class="fas fa-handshake"></i></div>
                    <h2>Partner Portal</h2>
                    <p class="mb-0 mt-1 opacity-75" style="opacity:.75; font-size:.9rem;">AutoRide Delivery Management</p>
                </div>
                <div class="login-body">
                    @if($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger py-2">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('partner.login.post') }}">
                        @csrf
                        <div class="form-group">
                            <label class="font-weight-600 small">Phone Number or Email</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" name="login" class="form-control"
                                       placeholder="0XX XXX XXXX or email@example.com"
                                       value="{{ old('login') }}" required autofocus>
                            </div>
                            @error('login')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600 small">Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" name="password" class="form-control"
                                       placeholder="Enter password" required>
                            </div>
                        </div>
                        <div class="form-group d-flex justify-content-between align-items-center">
                            <label class="mb-0">
                                <input type="checkbox" name="remember"> <small>Remember me</small>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-login btn-block">
                            <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-white-50 mt-3 small">
                Need an account? Contact your AutoRide account manager.
            </p>
        </div>
    </div>
</div>
</body>
</html>
