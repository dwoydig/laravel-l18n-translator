@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Edit: ' . ($languageFiles->firstWhere('filename', $lang)?->name ?? $lang))

@section('content')

<form method="POST" action="{{ route('l18n.storedictionary') }}" id="dict-form">
    @csrf
    <input type="hidden" name="lang" value="{{ $lang }}">

    <div x-data="translationEditor()" x-effect="$store.deeplUsage.selectedChars = selectedChars" class="space-y-3">

        {{-- Controls bar --}}
        <div class="sticky top-0 z-10 bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-2 items-center shadow-sm">

            {{-- Search --}}
            <input
                type="text"
                x-ref="filterInput"
                @input.debounce.250ms="search = $event.target.value"
                placeholder="Filter keys or values…"
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
            <button type="submit" form="dict-form"
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
                        <th class="px-4 py-2.5 font-medium text-gray-600">
                            @php $langFile = $languageFiles->firstWhere('filename', $lang); @endphp
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <span class="text-xl leading-none">{{ $langFile?->flag }}</span>
                                    <span>{{ $langFile?->name ?? $lang }}</span>
                                    <span class="font-normal text-gray-400 font-mono text-xs">{{ $langFile?->basename }}</span>
                                </span>
                                <button type="button"
                                    @click="showOnlySelected ? (showOnlySelected = false) : selectMissing()"
                                    :class="showOnlySelected
                                        ? 'bg-blue-100 text-blue-700 border-blue-200'
                                        : 'text-gray-400 border-gray-200 hover:text-gray-600'"
                                    class="text-xs font-normal px-2 py-0.5 rounded border transition-colors">
                                    <span x-text="showOnlySelected ? 'Show all' : 'Filter missing'"></span>
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody x-ref="tbody" class="divide-y divide-gray-100">
                    @foreach($translations as $entry)
                    <tr
                        x-show="isVisible($el)"
                        @click="if (!$event.target.closest('textarea, a')) toggleRow($el.dataset.key)"
                        :class="rowClass($el)"
                        data-key="{{ $entry['key'] }}"
                        data-original="{{ html_entity_decode($entry['original'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}"
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
                            @if($entry['original'])
                            <div class="text-xs text-gray-400 mb-1 leading-snug">{{ html_entity_decode($entry['original'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</div>
                            @else
                            <div class="text-xs text-red-400 mb-1 font-medium">Missing in {{ $mainLanguage }}</div>
                            @endif
                            <textarea
                                name="dict[{{ $entry['key'] }}]"
                                rows="2"
                                dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
                                autocomplete="off"
                                class="w-full border border-gray-200 rounded px-2 py-1 text-sm resize-y
                                       focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            >{{ html_entity_decode($entry['translation'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</textarea>
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

@if(count($orphaned) > 0)
<form method="POST" action="{{ route('l18n.orphans.adopt') }}" class="mt-4"
      x-data="orphanEditor({{ json_encode(array_keys($orphaned)) }})">
    @csrf
    <input type="hidden" name="lang" value="{{ $lang }}">

    <div class="bg-white border border-amber-300 rounded-lg overflow-hidden">

        @php
            $langName = $languageFiles->firstWhere('filename', $lang)?->name ?? $lang;
            $mainName = $languageFiles->firstWhere('filename', $mainLanguage)?->name ?? $mainLanguage;
        @endphp
        {{-- Header --}}
        <div class="bg-amber-50 border-b border-amber-200 px-4 py-2.5 flex items-center gap-3">
            <span class="text-sm font-medium text-amber-800">
                {{ count($orphaned) }} orphaned {{ count($orphaned) === 1 ? 'key' : 'keys' }}
                — present in {{ $langName }} but missing in {{ $mainName }}
            </span>
            <div class="ml-auto flex gap-2" x-data="{ showHint: false }">
                <div class="relative">
                    <button type="submit"
                        formaction="{{ route('l18n.orphans.remove') }}"
                        @mouseenter="showHint = selected.size === 0"
                        @mouseleave="showHint = false"
                        :disabled="selected.size === 0"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700
                               disabled:opacity-40 disabled:cursor-not-allowed">
                        Remove selected
                    </button>
                    <div x-show="showHint"
                        class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                        Select at least one key.
                    </div>
                </div>
                <button type="submit"
                    :disabled="selected.size === 0"
                    class="px-3 py-1 text-xs bg-amber-600 text-white rounded hover:bg-amber-700
                           disabled:opacity-40 disabled:cursor-not-allowed">
                    Add selected to {{ $mainName }}
                </button>
            </div>
        </div>

        {{-- Table --}}
        <table class="w-full text-sm table-fixed">
            <thead class="bg-gray-50 border-b border-gray-200 text-left">
                <tr>
                    <th class="w-8 px-3 py-2">
                        <input type="checkbox"
                            x-effect="$el.indeterminate = selected.size > 0 && selected.size < allKeys.length; $el.checked = selected.size > 0 && selected.size === allKeys.length"
                            @click="selected.size === allKeys.length ? selectNone() : selectAll()"
                            class="rounded border-gray-300 text-amber-600 cursor-pointer">
                    </th>
                    <th class="px-4 py-2 font-medium text-gray-600 w-1/3">Key</th>
                    <th class="px-4 py-2 font-medium text-gray-600">Value in {{ $langName }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($orphaned as $key => $value)
                <tr @click="if (!$event.target.closest('input')) toggleRow($el.dataset.key)"
                    :class="selected.has($el.dataset.key) ? 'bg-amber-50 cursor-pointer' : 'hover:bg-gray-50/60 cursor-pointer'"
                    data-key="{{ $key }}">
                    <td class="pl-3 py-2 align-top">
                        <input type="checkbox"
                            name="keys[]"
                            value="{{ $key }}"
                            :checked="selected.has($el.closest('tr').dataset.key)"
                            @click.stop="toggleRow($el.closest('tr').dataset.key)"
                            class="mt-0.5 rounded border-gray-300 text-amber-600 cursor-pointer">
                    </td>
                    <td class="px-4 py-2 align-top font-mono text-xs text-gray-700 break-all">{{ $key }}</td>
                    <td class="px-4 py-2 align-top text-gray-600">{{ html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>
@endif
@endsection

@section('scripts')
<script>
function translationEditor() {
    return withMixins({
        busy: false,
        abortController: null,
        noResults: false,

        init() {
            if (new URLSearchParams(location.search).get('filter') === 'missing') {
                this.$nextTick(() => this.selectMissing());
            }
            document.addEventListener('l18n:key-selected', (e) => {
                e.preventDefault();
                this.search = '';
                this.showOnlySelected = false;
                if (this.$refs.filterInput) this.$refs.filterInput.value = '';
                this.$nextTick(() => {
                    const row = [...(this.$refs.tbody?.querySelectorAll('tr') ?? [])]
                        .find(r => r.dataset.key === e.detail);
                    if (!row) return;
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('key-flash');
                    row.addEventListener('animationend', () => row.classList.remove('key-flash'), { once: true });
                });
            });
        },

        rowClass(el) {
            const hasOriginal = el.dataset.original?.trim();
            const isSelected  = this.selected.has(el.dataset.key);
            if (!hasOriginal) {
                return isSelected
                    ? 'bg-red-50 border-l-4 border-red-400 cursor-pointer'
                    : 'bg-red-50/70 border-l-4 border-red-200 cursor-pointer';
            }
            return isSelected ? 'bg-blue-50 cursor-pointer' : 'hover:bg-gray-50/60 cursor-pointer';
        },

        selectMissing() {
            this.selected = new Set(
                this.visibleRows()
                    .filter(r => !r.querySelector('textarea')?.value?.trim())
                    .map(r => r.dataset.key)
            );
            this.showOnlySelected = true;
        },

        async translateSelected() {
            if (!this.selected.size) return;
            const lang = document.querySelector('input[name="lang"]')?.value;
            const targetLang = (TARGET_LANG_MAP || {})[lang] ?? lang.toUpperCase();
            await runTranslationJob(this, signal => this.translatableRows()
                .map(row => () => translateField(row.querySelector('textarea'), row.dataset.original, targetLang, signal)));
        },

        cancelTranslation() {
            this.abortController?.abort();
        },
    }, filterableRowsMixin());
}

function orphanEditor(allKeys = []) {
    return withMixins({
        allKeys: allKeys,

        selectAll() {
            this.selected = new Set(this.allKeys);
        },
    }, keySelectionMixin());
}
</script>
@endsection
