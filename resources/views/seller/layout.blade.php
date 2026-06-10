<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Seller Portal') — Auto Ride</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <div class="flex items-center gap-8">
            <span class="text-xl font-bold text-blue-600">🏪 Seller Portal</span>
            <div class="hidden md:flex gap-6 text-sm font-medium text-gray-600">
                <a href="{{ route('seller.dashboard') }}" class="hover:text-blue-600 {{ request()->routeIs('seller.dashboard') ? 'text-blue-600' : '' }}">Dashboard</a>
                <a href="{{ route('seller.products') }}" class="hover:text-blue-600 {{ request()->routeIs('seller.products*') ? 'text-blue-600' : '' }}">My Products</a>
                <a href="{{ route('seller.orders') }}" class="hover:text-blue-600 {{ request()->routeIs('seller.orders') ? 'text-blue-600' : '' }}">Orders</a>
            </div>
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600">
            <span>{{ Auth::guard('seller')->user()->name }}</span>
            <form method="POST" action="{{ route('seller.logout') }}">
                @csrf
                <button type="submit" class="text-red-500 hover:text-red-700">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

</body>
</html>
