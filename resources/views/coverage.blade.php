@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Coverage')

@section('content')
@php
    $BLOCKS = 40;
    $eligibleLangs = $stats->where('missing', '>', 0)->pluck('file.filename')->values();
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

<div @if(config('l18n-translator.deepl.enabled')) x-data="coverageSelector()" @endif class="space-y-3">

    {{-- Summary / controls bar --}}
    @php
        $total      = $stats->count();
        $complete   = $stats->where('pct', 100)->count();
        $incomplete = $total - $complete;
    @endphp
    <div class="bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-3 items-center">
        <div class="text-xs text-gray-500 flex gap-4">
            <span><span class="font-semibold text-gray-700">{{ $total }}</span> languages checked</span>
            @if($complete)  <span class="text-green-700"><span class="font-semibold">{{ $complete }}</span> complete</span> @endif
            @if($incomplete)<span class="text-red-700"><span class="font-semibold">{{ $incomplete }}</span> incomplete</span>@endif
        </div>

        @if(config('l18n-translator.deepl.enabled') && $eligibleLangs->isNotEmpty())
        <div class="ml-auto flex flex-wrap gap-2 items-center">
            <button type="button" @click="selectAll()" class="text-xs text-blue-600 hover:underline">Select all incomplete</button>
            <button type="button" @click="selectNone()" class="text-xs text-gray-500 hover:underline">Select none</button>

            @include('l18n-translator::partials.deepl-usage')

            <div class="relative" x-data="{ showHint: false }">
                <button type="button"
                    @click="translateSelected()"
                    @mouseenter="showHint = selected.size === 0 || $store.deeplUsage.overBudget"
                    @mouseleave="showHint = false"
                    :disabled="selected.size === 0 || $store.deeplUsage.overBudget"
                    class="px-3 py-1.5 text-sm bg-sky-600 text-white rounded hover:bg-sky-700
                           disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                    <span x-text="selected.size === 0
                        ? 'Translate'
                        : `Translate ${selected.size} ${selected.size === 1 ? 'language' : 'languages'} (${selectedChars.toLocaleString()} chars)`"></span>
                </button>
                <div x-show="showHint"
                    class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                    <span x-show="$store.deeplUsage.overBudget">Selected characters exceed remaining DeepL budget.</span>
                    <span x-show="!$store.deeplUsage.overBudget">Select at least one incomplete language.</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="space-y-2" @if(config('l18n-translator.deepl.enabled')) x-effect="$store.deeplUsage.selectedChars = selectedChars" @endif>
    @foreach($stats->sortBy('pct') as $stat)
    @php
        $greenBlocks = $stat['total'] > 0 ? (int) round(($stat['translated'] / $stat['total']) * $BLOCKS) : 0;
        $redBlocks   = $BLOCKS - $greenBlocks;
        $pct         = $stat['pct'];
        $pctColor    = $pct === 100 ? 'text-green-700' : ($pct >= 80 ? 'text-yellow-600' : 'text-red-600');
        $canSelect   = config('l18n-translator.deepl.enabled') && $stat['missing'] > 0;
        $cardClass   = 'block bg-white border border-gray-200 rounded-lg px-4 py-3 hover:border-blue-300 hover:shadow-sm transition-all';
    @endphp
    @if($canSelect)
    <div
        @click="if (!$event.target.closest('input')) window.location = '{{ route('l18n.show', $stat['file']->filename) }}?filter=missing'"
        data-coverage-lang="{{ $stat['file']->filename }}"
        data-missing-chars="{{ $stat['missingChars'] }}"
        class="{{ $cardClass }} cursor-pointer">
        @include('l18n-translator::partials.coverage-card-body')
    </div>
    @else
    <a href="{{ route('l18n.show', $stat['file']->filename) }}?filter=missing" class="{{ $cardClass }}">
        @include('l18n-translator::partials.coverage-card-body')
    </a>
    @endif
    @endforeach
    </div>
</div>
@endif
@endsection

@section('scripts')
@if(config('l18n-translator.deepl.enabled'))
@once
    @include('l18n-translator::partials.deepl')
    @include('l18n-translator::partials.row-selection')
@endonce
<script>
function coverageSelector() {
    return withMixins({
        selectAll() {
            this.selected = new Set(@json($eligibleLangs));
        },

        get selectedChars() {
            return [...document.querySelectorAll('[data-coverage-lang]')]
                .filter(el => this.selected.has(el.dataset.coverageLang))
                .reduce((sum, el) => sum + Number(el.dataset.missingChars || 0), 0);
        },

        translateSelected() {
            if (!this.selected.size) return;
            window.location.href = '{{ route('l18n.missing') }}?langs=' + [...this.selected].join(',');
        },
    }, keySelectionMixin());
}
</script>
@endif
@endsection
