@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Coverage')

@section('content')
@php
    $eligibleLangs = $stats->where('missing', '>', 0)->pluck('file.filename')->values();
    $total      = $stats->count();
    $complete   = $stats->where('pct', 100)->count();
    $incomplete = $total - $complete;
    $deepl      = config('l18n-translator.deepl.enabled');
@endphp

<div @if($deepl) x-data="coverageSelector()" @endif class="space-y-3">

    {{-- Controls bar --}}
    <div class="bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-3 items-center">
        <div class="text-xs text-gray-500 flex gap-4">
            <span><span class="font-semibold text-gray-700">{{ $total }}</span> languages</span>
            @if($complete)  <span class="text-green-700"><span class="font-semibold">{{ $complete }}</span> complete</span>@endif
            @if($incomplete)<span class="text-red-700"><span class="font-semibold">{{ $incomplete }}</span> incomplete</span>@endif
        </div>

        @if($deepl && $eligibleLangs->isNotEmpty())
        <div class="ml-auto flex flex-wrap gap-2 items-center">
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

    {{-- Table --}}
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden"
         @if($deepl) x-effect="$store.deeplUsage.selectedChars = selectedChars" @endif>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-left">
                <tr>
                    @if($deepl)
                    <th class="w-8 px-3 py-2.5">
                        @if($eligibleLangs->isNotEmpty())
                        <input type="checkbox"
                            x-effect="$el.indeterminate = selectedCount > 0 && selectedCount < {{ $eligibleLangs->count() }}; $el.checked = selectedCount > 0 && selectedCount === {{ $eligibleLangs->count() }}"
                            @click="selectedCount === {{ $eligibleLangs->count() }} ? selectNone() : selectAll()"
                            class="rounded border-gray-300 text-blue-600 cursor-pointer">
                        @endif
                    </th>
                    @endif
                    <th class="px-4 py-2.5 font-medium text-gray-600 w-1/4">Language</th>
                    <th class="px-4 py-2.5 font-medium text-gray-600">Progress</th>
                    <th class="px-4 py-2.5 font-medium text-gray-600 w-40 text-right">
                        @if($deepl && $eligibleLangs->isNotEmpty())
                        <button type="button"
                            @click="selectedCount === {{ $eligibleLangs->count() }} ? selectNone() : selectAll()"
                            :class="selectedCount === {{ $eligibleLangs->count() }}
                                ? 'bg-blue-100 text-blue-700 border-blue-200'
                                : 'text-gray-400 border-gray-200 hover:text-gray-600'"
                            class="text-xs font-normal px-2 py-0.5 rounded border transition-colors whitespace-nowrap">
                            <span x-text="selectedCount === {{ $eligibleLangs->count() }} ? 'Deselect all' : 'Select incomplete'"></span>
                        </button>
                        @endif
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">

                {{-- Source language row --}}
                <tr class="bg-blue-50 hover:bg-blue-50/80">
                    @if($deepl)<td class="px-3 py-3"></td>@endif
                    <td class="px-4 py-3">
                        <a href="{{ route('l18n.show', $mainIso) }}" class="flex items-center gap-2 group">
                            <span class="text-xl leading-none">{{ $mainFile->flag }}</span>
                            <div>
                                <div class="font-medium text-blue-900 group-hover:underline flex items-center gap-1.5">
                                    {{ $mainFile->name }}
                                    <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded font-normal">source</span>
                                </div>
                                <div class="font-mono text-xs text-blue-400 mt-0.5">{{ $mainFile->basename }}</div>
                            </div>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-blue-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-400 h-2 rounded-full w-full"></div>
                            </div>
                            <span class="text-xs font-bold tabular-nums w-10 text-right text-blue-400">100%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="font-semibold text-blue-700">{{ $mainCount }}</span>
                        <span class="text-xs text-blue-400 ml-1">keys</span>
                    </td>
                </tr>

                {{-- Per-language rows --}}
                @foreach($stats->sortBy('pct') as $stat)
                @php
                    $pct      = $stat['pct'];
                    $canSelect = $deepl && $stat['missing'] > 0;
                    $pctColor = $pct === 100 ? 'text-green-700' : ($pct >= 80 ? 'text-yellow-600' : 'text-red-600');
                    $barColor = $pct === 100 ? 'bg-green-500' : ($pct >= 80 ? 'bg-yellow-400' : 'bg-red-400');
                @endphp
                <tr
                    @if($canSelect)
                        @click="if (!$event.target.closest('input,a')) window.location = '{{ route('l18n.show', $stat['file']->filename) }}?filter=missing'"
                        data-coverage-lang="{{ $stat['file']->filename }}"
                        data-missing-chars="{{ $stat['missingChars'] }}"
                        :class="selected.has('{{ $stat['file']->filename }}') ? 'bg-blue-50 cursor-pointer' : 'hover:bg-gray-50/60 cursor-pointer'"
                    @else
                        class="hover:bg-gray-50/60"
                    @endif
                >
                    @if($deepl)
                    <td class="pl-3 py-3 align-middle">
                        @if($canSelect)
                        <input type="checkbox"
                            @click.stop
                            @change="toggleRow('{{ $stat['file']->filename }}')"
                            :checked="selected.has('{{ $stat['file']->filename }}')"
                            class="rounded border-gray-300 text-blue-600 cursor-pointer">
                        @endif
                    </td>
                    @endif
                    <td class="px-4 py-3">
                        <a href="{{ route('l18n.show', $stat['file']->filename) }}{{ $stat['missing'] > 0 ? '?filter=missing' : '' }}"
                           @click.stop
                           class="flex items-center gap-2 group">
                            <span class="text-xl leading-none">{{ $stat['file']->flag }}</span>
                            <div>
                                <div class="font-medium text-gray-900 group-hover:underline text-sm">{{ $stat['file']->name }}</div>
                                <div class="font-mono text-xs text-gray-400 mt-0.5">{{ $stat['file']->basename }}</div>
                            </div>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div class="{{ $barColor }} h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-xs font-bold tabular-nums w-10 text-right {{ $pctColor }}">{{ $pct }}%</span>
                        </div>
                        <div class="flex flex-wrap gap-x-3 mt-1 text-xs">
                            <span class="text-green-600" title="Translated">&#10003; {{ $stat['translated'] }}</span>
                            <span class="text-red-500" title="Missing{{ $stat['missing'] > 0 ? ' (' . number_format($stat['missingChars']) . ' chars)' : '' }}">&#10007; {{ $stat['missing'] }}@if($stat['missing'] > 0)<span class="text-gray-400 ml-0.5">({{ number_format($stat['missingChars']) }} chars)</span>@endif</span>
                            @if($stat['orphaned'] > 0)
                            <span class="text-amber-500" title="Orphaned — present in this language but missing in source">&#9888; {{ $stat['orphaned'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="font-medium text-gray-700">{{ $stat['total'] }}</span>
                        <span class="text-xs text-gray-400 ml-1">keys</span>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>

</div>
@endsection

@section('scripts')
@if($deepl)
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
