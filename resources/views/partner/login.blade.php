<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Login — ROTEH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center min-h-screen" style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%)">

<div class="w-full max-w-sm mx-4">

    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

        {{-- Header --}}
        <div class="px-8 pt-8 pb-6 text-center" style="background:linear-gradient(135deg,#e63946,#c1121f)">
            <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-handshake text-white text-2xl"></i>
            </div>
            <h1 class="text-white text-2xl font-bold tracking-tight">Partner Portal</h1>
            <p class="text-red-100 text-sm mt-1">ROTEH Delivery Management</p>
        </div>

        {{-- Body --}}
        <div class="px-8 py-8">

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show py-2 mb-4 text-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i>{{ $errors->first() }}
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show py-2 mb-4 text-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('partner.login.post') }}" class="space-y-5">
                @csrf

                {{-- Login --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Phone Number or Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="fas fa-user text-sm"></i>
                        </span>
                        <input type="text" name="login"
                               class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:border-transparent @error('login') border-red-400 @enderror"
                               style="--tw-ring-color:#e63946"
                               placeholder="012 345 678 or email@example.com"
                               value="{{ old('login') }}" required autofocus>
                    </div>
                    @error('login')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" id="pwd"
                               class="w-full pl-9 pr-10 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                               style="--tw-ring-color:#e63946"
                               placeholder="Enter password" required>
                        <button type="button" onclick="togglePwd()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                            <i class="fas fa-eye text-sm" id="pwd-icon"></i>
                        </button>
                    </div>
                </div>

                {{-- Remember --}}
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember"
                           class="w-4 h-4 rounded border-slate-300 text-red-600">
                    <label for="remember" class="text-sm text-slate-600 cursor-pointer">Remember me</label>
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-xl text-white font-semibold text-sm transition-all active:scale-95"
                        style="background:linear-gradient(135deg,#e63946,#c1121f)">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-slate-400 text-xs mt-5">
        Need an account? Contact your AutoRide account manager.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    var inp = document.getElementById('pwd');
    var ico = document.getElementById('pwd-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        ico.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
