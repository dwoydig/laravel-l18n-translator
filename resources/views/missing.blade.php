@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', count($missing) . ' missing translation' . (count($missing) === 1 ? '' : 's') . ' across ' . $langCount . ' language' . ($langCount === 1 ? '' : 's'))

@section('content')

@if(count($missing) === 0)
<div class="bg-white border border-green-200 rounded-lg px-6 py-10 text-center">
    <div class="text-4xl mb-3">✅</div>
    <div class="text-gray-700 font-medium">All translations are complete.</div>
</div>
@else

<form method="POST" action="{{ route('l18n.missing.save') }}" id="missing-form">
    @csrf

    <div x-data="missingEditor()" x-effect="$store.deeplUsage.selectedChars = selectedChars" class="space-y-3">

        {{-- Controls bar --}}
        <div class="sticky top-0 z-10 bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-2 items-center shadow-sm">

            {{-- Search --}}
            <input
                type="text"
                @input.debounce.250ms="search = $event.target.value"
                placeholder="Filter keys, values or languages…"
                class="flex-1 min-w-48 border border-gray-300 rounded px-3 py-1.5 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500"
            >

            {{-- DeepL --}}
            @if(config('l18n-translator.deepl.enabled'))
            <div class="relative" x-data="{ showHint: false }">
                <button type="button"
                    @click="busy ? cancelTranslation() : translateSelected()"
                    @mouseenter="showHint = !busy && (translatableCount === 0 || $store.deeplUsage.overBudget)"
                    @mouseleave="showHint = false"
                    :disabled="!busy && (translatableCount === 0 || $store.deeplUsage.overBudget)"
                    :class="busy ? 'bg-gray-600 hover:bg-gray-700' : 'bg-sky-600 hover:bg-sky-700'"
                    class="px-3 py-1.5 text-sm text-white rounded
                           disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                    <span x-text="busy ? 'Cancel' : 'Translate ' + translatableCount + (translatableCount === 1 ? ' key' : ' keys')"></span>
                </button>
                <div x-show="showHint"
                    class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                    <span x-show="$store.deeplUsage.overBudget">Selected characters exceed remaining DeepL budget.</span>
                    <span x-show="!$store.deeplUsage.overBudget">Select at least one row to translate.</span>
                </div>
            </div>
            @endif

            {{-- Save --}}
            <button type="submit" form="missing-form"
                :disabled="busy"
                :title="busy ? 'Cannot save while a translation job is running' : ''"
                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 font-medium whitespace-nowrap
                       disabled:opacity-40 disabled:cursor-not-allowed">
                Save
            </button>
        </div>

        {{-- Table --}}
        <div class="bg-white border border-gray-200 rounded-lg">
            <table class="w-full text-sm table-fixed">
                <thead class="bg-gray-50 border-b border-gray-200 text-left">
                    <tr>
                        <th class="w-8 px-3 py-2.5">
                            <input type="checkbox"
                                x-effect="$el.indeterminate = selectedCount > 0 && selectedCount < visibleRows().length; $el.checked = selectedCount > 0 && selectedCount === visibleRows().length"
                                @click="selectedCount === visibleRows().length ? selectNone() : selectAll()"
                                class="rounded border-gray-300 text-blue-600 cursor-pointer">
                        </th>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-1/3">Key</th>
                        <th class="px-4 py-2.5 font-medium text-gray-600">Translation</th>
                    </tr>
                </thead>
                <tbody x-ref="tbody" class="divide-y divide-gray-100">
                    @foreach($missing as $entry)
                    <tr
                        x-show="isVisible($el)"
                        @click="if (!$event.target.closest('textarea, a')) toggleRow($el.dataset.key)"
                        :class="rowClass($el)"
                        data-key="{{ $entry['lang'] }}::{{ $entry['key'] }}"
                        data-lang="{{ $entry['lang'] }}"
                        data-original="{{ html_entity_decode($entry['original'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}"
                        data-langname="{{ $entry['langName'] }}"
                    >
                        <td class="pl-3 py-2 align-top">
                            <input type="checkbox"
                                :checked="selected.has($el.closest('tr').dataset.key)"
                                @click.stop="toggleRow($el.closest('tr').dataset.key)"
                                class="mt-1 rounded border-gray-300 text-blue-600 cursor-pointer">
                        </td>
                        <td class="px-4 py-2 align-top w-1/3">
                            <a href="{{ route('l18n.editstrings') }}?key={{ urlencode($entry['key']) }}"
                               @click.stop
                               class="text-blue-600 hover:underline font-mono text-xs break-all leading-relaxed"
                               title="Edit in all languages">{{ $entry['key'] }}</a>
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div class="text-xs text-gray-400 mb-1 leading-snug">
                                <span class="font-medium text-gray-500">{{ $entry['langFlag'] }} {{ $entry['langName'] }} ({{ $entry['lang'] }})</span>
                                @if($entry['original'])
                                · {{ html_entity_decode($entry['original'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
                                @endif
                            </div>
                            <textarea
                                name="dict[{{ $entry['lang'] }}][{{ $entry['key'] }}]"
                                rows="2"
                                dir="{{ $entry['langRtl'] ? 'rtl' : 'ltr' }}"
                                class="w-full border border-gray-200 rounded px-2 py-1 text-sm resize-y
                                       focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            ></textarea>
                            <p class="translate-error mt-1 text-xs text-red-600 hidden"></p>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div x-show="noResults" class="py-10 text-center text-gray-400 text-sm">
                No matching translations.
            </div>
        </div>

    </div>
</form>

@endif
@endsection

@section('scripts')
<script>
function missingEditor() {
    return withMixins({
        busy: false,
        abortController: null,
        noResults: false,

        init() {
            const langs = new URLSearchParams(location.search).get('langs');
            if (langs) {
                const langSet = new Set(langs.split(','));
                this.$nextTick(() => {
                    this.selected = new Set(
                        this.visibleRows()
                            .filter(r => langSet.has(r.dataset.lang))
                            .map(r => r.dataset.key)
                    );
                    this.showOnlySelected = true;
                });
            }
        },

        rowClass(el) {
            const isSelected = this.selected.has(el.dataset.key);
            return isSelected ? 'bg-blue-50 cursor-pointer' : 'hover:bg-gray-50/60 cursor-pointer';
        },

        async translateSelected() {
            if (!this.selected.size) return;
            await runTranslationJob(this, signal => this.translatableRows()
                .map(row => () => {
                    const targetLang = (TARGET_LANG_MAP || {})[row.dataset.lang] ?? row.dataset.lang.toUpperCase();
                    return translateField(row.querySelector('textarea'), row.dataset.original, targetLang, signal);
                }));
        },

        cancelTranslation() {
            this.abortController?.abort();
        },
    }, filterableRowsMixin(['lang', 'langname']));
}
</script>
@endsection
