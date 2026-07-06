@extends('partner.layout')
@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')

@section('content')

{{-- ── Period Filter ────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-5 py-4 mb-5 flex items-center gap-3 flex-wrap">
    <span class="text-sm font-semibold text-slate-600">Period:</span>
    @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
        <a href="{{ route('partner.reports', ['days' => $d]) }}"
           class="px-4 py-1.5 rounded-xl text-sm font-medium transition-colors
                  {{ $days == $d
                      ? 'text-white shadow-sm'
                      : 'text-slate-600 bg-slate-100 hover:bg-slate-200' }}"
           @if($days == $d) style="background:linear-gradient(135deg,#e63946,#c1121f)" @endif>
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ── Metric Cards ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center">
        <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Total Orders</p>
        <p class="text-3xl font-extrabold text-slate-800 mb-0">{{ $total }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center">
        <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Delivered</p>
        <p class="text-3xl font-extrabold text-emerald-600 mb-0">{{ $delivered }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center">
        <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Cancelled</p>
        <p class="text-3xl font-extrabold text-red-500 mb-0">{{ $cancelled }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center">
        <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Success Rate</p>
        <p class="text-3xl font-extrabold text-blue-600 mb-0">{{ $successRate }}%</p>
    </div>
</div>

<div class="row g-4">

    {{-- ── Bar Chart ────────────────────────────────────────────── --}}
    <div class="col-lg-8">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2 mb-4">
                <i class="fas fa-chart-bar text-blue-500"></i>
                Daily Orders — Last {{ $days }} days
            </h3>
            @if($daily->isEmpty())
                <div class="text-center text-slate-400 py-12">
                    <i class="fas fa-chart-bar fa-2x mb-2 d-block text-slate-200"></i>
                    No data for this period.
                </div>
            @else
            @php $maxTotal = $daily->max('total') ?: 1; @endphp
            <div class="flex items-end gap-1" style="height:140px">
                @foreach($daily as $row)
                @php $pct = max(round(($row->total / $maxTotal) * 100), 4); @endphp
                <div class="flex-1 relative group" style="height:100%;display:flex;align-items:flex-end">
                    <div class="w-full rounded-t-md cursor-pointer"
                         style="height:{{ $pct }}%;background:linear-gradient(to top,#3b82f6,#93c5fd);min-width:4px">
                    </div>
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10">
                        <div class="bg-slate-800 text-white text-xs rounded-lg px-2 py-1 whitespace-nowrap shadow-lg">
                            {{ \Carbon\Carbon::parse($row->date)->format('d M') }}<br>
                            {{ $row->total }} orders / {{ $row->delivered }} done
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2">
                <span class="text-xs text-slate-400">{{ $daily->first()->date ?? '' }}</span>
                <span class="text-xs text-slate-400">{{ $daily->last()->date ?? '' }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Right column ─────────────────────────────────────────── --}}
    <div class="col-lg-4 d-flex flex-column gap-4">

        {{-- Avg Delivery Time --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-2">Avg. Delivery Time</p>
            @if($avgMinutes)
                <p class="text-3xl font-extrabold text-slate-800 mb-0">{{ $avgMinutes }} <span class="text-lg font-semibold text-slate-400">min</span></p>
                <p class="text-slate-400 text-xs mt-1">≈ {{ round($avgMinutes/60,1) }} hr</p>
            @else
                <p class="text-slate-400 py-2 mb-0 text-sm">No completed data</p>
            @endif
        </div>

        {{-- Breakdown --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex-1">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-3">Breakdown</p>
            @if($total > 0)
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-600">Delivered</span>
                    <span class="font-semibold text-emerald-600">{{ $delivered }} ({{ round($delivered/$total*100,1) }}%)</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full" style="height:6px">
                    <div class="bg-emerald-500 rounded-full" style="height:6px;width:{{ round($delivered/$total*100) }}%"></div>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-600">Cancelled</span>
                    <span class="font-semibold text-red-500">{{ $cancelled }} ({{ round($cancelled/$total*100,1) }}%)</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full" style="height:6px">
                    <div class="bg-red-500 rounded-full" style="height:6px;width:{{ round($cancelled/$total*100) }}%"></div>
                </div>
            </div>
            @else
                <p class="text-slate-400 text-sm mb-0">No orders in this period.</p>
            @endif
        </div>
    </div>
</div>

@endsection
