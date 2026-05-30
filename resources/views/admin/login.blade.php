<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | {{ config('app.name', 'AutoRide') }}</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }

        /* orb glow */
        .orb {
            position: absolute;
            border-radius: 9999px;
            filter: blur(90px);
            opacity: 0.3;
            pointer-events: none;
        }

        /* grid overlay */
        .grid-bg {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* floating animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(-1deg); }
            50%       { transform: translateY(-14px) rotate(1deg); }
        }
        .float { animation: float 5s ease-in-out infinite; }

        /* glass card */
        .glass {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.10);
        }

        /* form input */
        .field {
            width: 100%;
            padding: 0.72rem 1rem 0.72rem 2.6rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #1e293b;
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field:focus {
            border-color: #e63946;
            box-shadow: 0 0 0 4px rgba(230,57,70,0.10);
        }
        .field::placeholder { color: #94a3b8; }
        .field.pw { padding-right: 2.6rem; }

        .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.8rem;
            pointer-events: none;
            transition: color .2s;
        }
        .field-wrap:focus-within .field-icon { color: #e63946; }

        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.8rem;
            cursor: pointer;
            transition: color .2s;
        }
        .toggle-pw:hover { color: #64748b; }

        /* badge pill */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            color: #94a3b8;
        }
    </style>
</head>

<body class="flex h-screen bg-slate-100 overflow-hidden">

{{-- ════════════════════ LEFT PANEL ════════════════════ --}}
<div class="hidden md:flex flex-col justify-between relative overflow-hidden w-1/2 min-h-screen p-12"
     style="background: linear-gradient(145deg,#0f0f1a 0%,#1c1c2e 50%,#2d1b3d 100%);">

    {{-- decorative orbs --}}
    <div class="orb" style="width:440px;height:440px;background:#e63946;top:-130px;left:-110px;"></div>
    <div class="orb" style="width:300px;height:300px;background:#7c3aed;bottom:-80px;right:-60px;"></div>
    <div class="orb" style="width:180px;height:180px;background:#2563eb;top:55%;left:52%;transform:translate(-50%,-50%);"></div>
    <div class="grid-bg"></div>

    {{-- Brand --}}
    <div class="relative z-10 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
             style="background:linear-gradient(135deg,#e63946,#c1121f);">
            <i class="fas fa-car-side text-white text-sm"></i>
        </div>
        <div>
            <div class="text-white text-lg font-bold leading-none">AutoRide</div>
            <div class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color:#e63946;">Admin Panel</div>
        </div>
    </div>

    {{-- Hero --}}
    <div class="relative z-10 flex-1 flex flex-col justify-center py-10">
        <h2 class="text-white text-3xl xl:text-4xl font-extrabold leading-tight mb-4">
            Manage your ride<br>platform with ease.
        </h2>
        <p class="text-sm leading-relaxed mb-10 max-w-xs" style="color:#94a3b8;">
            Full control over users, drivers, vehicles, deliveries &amp; real‑time insights — all from one secure panel.
        </p>

        {{-- Stat cards --}}
        <div class="flex flex-col gap-3 max-w-xs float">
            <div class="glass rounded-2xl flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:rgba(230,57,70,0.18);">
                    <i class="fas fa-users text-sm" style="color:#e63946;"></i>
                </div>
                <div class="flex-1">
                    <div class="text-white font-bold text-lg leading-none">2,400+</div>
                    <div class="text-xs mt-0.5" style="color:#64748b;">Registered Users</div>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                      style="background:rgba(34,197,94,0.15);color:#4ade80;">↑ 12%</span>
            </div>

            <div class="glass rounded-2xl flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:rgba(124,58,237,0.18);">
                    <i class="fas fa-route text-sm" style="color:#a78bfa;"></i>
                </div>
                <div class="flex-1">
                    <div class="text-white font-bold text-lg leading-none">8,900+</div>
                    <div class="text-xs mt-0.5" style="color:#64748b;">Total Rides</div>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                      style="background:rgba(34,197,94,0.15);color:#4ade80;">↑ 8%</span>
            </div>

            <div class="glass rounded-2xl flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:rgba(37,99,235,0.18);">
                    <i class="fas fa-charging-station text-sm" style="color:#60a5fa;"></i>
                </div>
                <div class="flex-1">
                    <div class="text-white font-bold text-lg leading-none">340+</div>
                    <div class="text-xs mt-0.5" style="color:#64748b;">Charging Stations</div>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                      style="background:rgba(34,197,94,0.15);color:#4ade80;">↑ 5%</span>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="relative z-10 text-xs" style="color:#475569;">
        &copy; {{ date('Y') }} AutoRide. All rights reserved.
    </div>
</div>

{{-- ════════════════════ RIGHT PANEL ════════════════════ --}}
<div class="flex items-center justify-center w-full md:w-1/2 min-h-screen p-6 overflow-y-auto bg-slate-50">
    <div class="w-full max-w-md">

        {{-- Mobile brand --}}
        <div class="flex md:hidden items-center justify-center gap-2 mb-8">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                 style="background:linear-gradient(135deg,#e63946,#c1121f);">
                <i class="fas fa-car-side text-white text-sm"></i>
            </div>
            <span class="text-slate-800 text-xl font-bold">AutoRide</span>
        </div>

        {{-- Heading --}}
        <div class="mb-8">
            <h1 class="text-slate-800 text-2xl font-extrabold mb-1">Welcome back 👋</h1>
            <p class="text-slate-500 text-sm">Sign in to your admin account to continue.</p>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="mb-5 flex items-start gap-2 rounded-xl p-4 text-sm"
                 style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;">
                <i class="fas fa-circle-exclamation mt-0.5 shrink-0"></i>
                <ul class="list-none m-0 p-0 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf

            {{-- Email --}}
            <div class="mb-5">
                <label for="email" class="block text-xs font-semibold uppercase tracking-widest mb-2"
                       style="color:#64748b;">Email Address</label>
                <div class="field-wrap relative">
                    <i class="fas fa-envelope field-icon"></i>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="field"
                        value="{{ old('email') }}"
                        placeholder="admin@example.com"
                        required
                        autofocus
                        autocomplete="email"
                    >
                </div>
            </div>

            {{-- Password --}}
            <div class="mb-5">
                <label for="password" class="block text-xs font-semibold uppercase tracking-widest mb-2"
                       style="color:#64748b;">Password</label>
                <div class="field-wrap relative">
                    <i class="fas fa-lock field-icon"></i>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="field pw"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <span class="toggle-pw" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </span>
                </div>
            </div>

            {{-- Remember --}}
            <div class="flex items-center justify-between mb-7">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="remember" id="remember"
                           {{ old('remember') ? 'checked' : '' }}
                           class="w-4 h-4 rounded cursor-pointer"
                           style="accent-color:#e63946;">
                    <span class="text-sm" style="color:#64748b;">Remember me for 30 days</span>
                </label>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 text-white text-sm font-semibold rounded-xl py-3 transition-all duration-200"
                    style="background:linear-gradient(135deg,#e63946,#c1121f);box-shadow:0 4px 16px rgba(230,57,70,0.35);"
                    onmouseover="this.style.opacity='.88';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'">
                <i class="fas fa-arrow-right-to-bracket"></i>
                Sign In to Dashboard
            </button>

            {{-- Divider --}}
            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-slate-200"></div>
                <span class="text-xs text-slate-400">Secure access only</span>
                <div class="flex-1 h-px bg-slate-200"></div>
            </div>

            {{-- Trust badges --}}
            <div class="flex items-center justify-center gap-5 flex-wrap">
                <span class="pill"><i class="fas fa-shield-halved" style="color:#4ade80;"></i> SSL Encrypted</span>
                <span class="pill"><i class="fas fa-lock" style="color:#60a5fa;"></i> Role‑Protected</span>
                <span class="pill"><i class="fas fa-user-shield" style="color:#a78bfa;"></i> Admin Only</span>
            </div>
        </form>

        {{-- Footer --}}
        <p class="text-center text-xs mt-10" style="color:#94a3b8;">
            &copy; {{ date('Y') }} AutoRide &mdash; Admin Panel v1.0
        </p>
    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
</body>
</html>
