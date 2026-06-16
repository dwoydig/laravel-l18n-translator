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

    <div x-data="missingEditor()" class="space-y-3">

        {{-- Controls bar --}}
        <div class="bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-2 items-center">

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
                    @click="translateSelected()"
                    @mouseenter="showHint = busy || translatableCount === 0"
                    @mouseleave="showHint = false"
                    :disabled="busy || translatableCount === 0"
                    class="px-3 py-1.5 text-sm bg-sky-600 text-white rounded hover:bg-sky-700
                           disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                    <span x-text="busy ? 'Translating…' : 'Translate ' + translatableCount + (translatableCount === 1 ? ' key' : ' keys')"></span>
                </button>
                <div x-show="showHint"
                    class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                    Select at least one row to translate.
                </div>
            </div>
            @endif

            {{-- Save --}}
            <button type="submit" form="missing-form"
                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 font-medium whitespace-nowrap">
                Save
            </button>
        </div>

        {{-- Table --}}
        <div class="bg-white border border-gray-200 rounded-lg">
            <table class="w-full text-sm table-fixed">
                <thead class="bg-gray-50 border-b border-gray-200 text-left">
                    <tr>
                        <th class="w-8 px-3 py-2.5">
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open"
                                    class="w-5 h-5 rounded border border-gray-300 bg-white hover:bg-gray-50 flex items-center justify-center text-gray-500"
                                    title="Select…">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false"
                                    class="absolute left-0 top-full mt-1 z-10 bg-white border border-gray-200 rounded shadow-md text-sm w-28">
                                    <button type="button" @click="selectAll(); open = false"
                                        class="w-full text-left px-3 py-1.5 hover:bg-gray-50">All</button>
                                    <button type="button" @click="selectNone(); open = false"
                                        class="w-full text-left px-3 py-1.5 hover:bg-gray-50">None</button>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-1/3">Key</th>
                        <th class="px-4 py-2.5 font-medium text-gray-600">
                            <div class="flex items-center justify-between">
                                <span>Translation</span>
                                <button type="button"
                                    @click="showOnlySelected = !showOnlySelected"
                                    :disabled="!showOnlySelected && selectedCount === 0"
                                    :class="showOnlySelected
                                        ? 'bg-blue-100 text-blue-700 border-blue-200'
                                        : 'text-gray-400 border-gray-200 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed'"
                                    class="text-xs font-normal px-2 py-0.5 rounded border transition-colors">
                                    <span x-text="showOnlySelected ? 'Show all' : 'Selected only'"></span>
                                </button>
                            </div>
                        </th>
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
@once
    @include('l18n-translator::partials.deepl')
@endonce
<script>
function missingEditor() {
    return {
        search: '',
        busy: false,
        noResults: false,
        showOnlySelected: false,
        selected: new Set(),

        get selectedCount() {
            return this.selected.size;
        },

        get translatableCount() {
            return [...(this.$refs.tbody?.querySelectorAll('tr') ?? [])]
                .filter(r => this.selected.has(r.dataset.key) && r.dataset.original?.trim())
                .length;
        },

        isVisible(el) {
            if (this.showOnlySelected && !this.selected.has(el.dataset.key)) return false;
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase();
            return el.dataset.key?.toLowerCase().includes(q)
                || el.dataset.original?.toLowerCase().includes(q)
                || el.dataset.lang?.toLowerCase().includes(q)
                || el.dataset.langname?.toLowerCase().includes(q)
                || el.querySelector('textarea')?.value?.toLowerCase().includes(q);
        },

        rowClass(el) {
            const isSelected = this.selected.has(el.dataset.key);
            return isSelected ? 'bg-blue-50 cursor-pointer' : 'hover:bg-gray-50/60 cursor-pointer';
        },

        toggleRow(key) {
            const next = new Set(this.selected);
            next.has(key) ? next.delete(key) : next.add(key);
            this.selected = next;
        },

        visibleRows() {
            return [...(this.$refs.tbody?.querySelectorAll('tr') ?? [])]
                .filter(r => r.style.display !== 'none');
        },

        selectAll() {
            this.selected = new Set(this.visibleRows().map(r => r.dataset.key));
        },

        selectNone() {
            this.selected = new Set();
        },

        async translateSelected() {
            if (!this.selected.size) return;
            this.busy = true;
            const tasks = this.visibleRows()
                .filter(row => this.selected.has(row.dataset.key) && row.dataset.original?.trim())
                .map(row => async () => {
                    const ta = row.querySelector('textarea');
                    const lang = row.dataset.lang;
                    const targetLang = (TARGET_LANG_MAP || {})[lang] ?? lang.toUpperCase();
                    try {
                        ta.disabled = true;
                        ta.value = await deeplTranslate(row.dataset.original, targetLang);
                    } catch (err) {
                        ta.style.outline = '2px solid #ef4444';
                        ta.title = err.message;
                    } finally {
                        ta.disabled = false;
                    }
                });
            await runConcurrent(tasks);
            this.busy = false;
        },
    };
}
</script>
@endsection
