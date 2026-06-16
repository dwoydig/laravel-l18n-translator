@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Coverage')

@section('content')
@php
    $BLOCKS = 40;
@endphp

{{-- Main language baseline --}}
<div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-center gap-3 mb-4">
    <span class="text-2xl leading-none">{{ $mainFile->flag }}</span>
    <div class="min-w-0">
        <div class="font-semibold text-blue-900">{{ $mainFile->name }}</div>
        <div class="text-xs text-blue-500 font-mono mt-0.5">{{ $mainFile->basename }}</div>
    </div>
    <div class="ml-auto text-sm text-blue-700 font-medium tabular-nums">
        {{ $mainCount }} keys &mdash; source language
    </div>
</div>

@if($stats->isEmpty())
<p class="text-gray-500 text-sm">No other language files found.</p>
@else

{{-- Summary bar --}}
@php
    $total      = $stats->count();
    $complete   = $stats->where('pct', 100)->count();
    $incomplete = $total - $complete;
@endphp
<div class="text-xs text-gray-500 mb-3 flex gap-4">
    <span><span class="font-semibold text-gray-700">{{ $total }}</span> languages checked</span>
    @if($complete)  <span class="text-green-700"><span class="font-semibold">{{ $complete }}</span> complete</span> @endif
    @if($incomplete)<span class="text-red-700"><span class="font-semibold">{{ $incomplete }}</span> incomplete</span>@endif
</div>

<div class="space-y-2">
@foreach($stats->sortBy('pct') as $stat)
@php
    $greenBlocks = $stat['total'] > 0 ? (int) round(($stat['translated'] / $stat['total']) * $BLOCKS) : 0;
    $redBlocks   = $BLOCKS - $greenBlocks;
    $pct         = $stat['pct'];
    $pctColor    = $pct === 100 ? 'text-green-700' : ($pct >= 80 ? 'text-yellow-600' : 'text-red-600');
@endphp
<a href="{{ route('l18n.show', $stat['file']->filename) }}?filter=missing"
   class="block bg-white border border-gray-200 rounded-lg px-4 py-3 hover:border-blue-300 hover:shadow-sm transition-all">

    <div class="flex items-center gap-3 mb-2.5">
        <span class="text-xl leading-none">{{ $stat['file']->flag }}</span>
        <div class="min-w-0 flex-1">
            <span class="font-medium text-gray-900 text-sm">{{ $stat['file']->name }}</span>
            <span class="text-xs text-gray-400 font-mono ml-2">{{ $stat['file']->basename }}</span>
        </div>
        <span class="text-base font-bold tabular-nums {{ $pctColor }}">{{ $pct }}%</span>
    </div>

    {{-- Block visualisation --}}
    <div class="flex gap-px mb-2" title="{{ $stat['translated'] }} translated / {{ $stat['missing'] }} missing">
        @for($i = 0; $i < $greenBlocks; $i++)
        <div class="h-4 flex-1 rounded-sm bg-green-400"></div>
        @endfor
        @for($i = 0; $i < $redBlocks; $i++)
        <div class="h-4 flex-1 rounded-sm bg-red-400"></div>
        @endfor
    </div>

    {{-- Stats --}}
    <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
        <span class="text-green-700 font-medium">&#10003; {{ $stat['translated'] }} translated</span>
        <span class="text-red-600 font-medium">&#10007; {{ $stat['missing'] }} missing</span>
        @if($stat['orphaned'] > 0)
        <span class="text-amber-600 font-medium">&#9888; {{ $stat['orphaned'] }} orphaned</span>
        @endif
    </div>

</a>
@endforeach
</div>
@endif
@endsection
