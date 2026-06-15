@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Edit: ' . ($availableLanguages[$lang] ?? $lang))

@section('content')

{{-- Language switcher --}}
<div class="flex flex-wrap gap-1.5 mb-4">
    @foreach($availableLanguages as $iso => $name)
    <a href="{{ route('l18n.show', $iso) }}"
       class="px-3 py-1 text-sm rounded-full border
           {{ $iso === $lang
               ? 'bg-blue-600 border-blue-600 text-white font-medium'
               : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
        {{ $name }}
    </a>
    @endforeach
</div>

<form method="POST" action="{{ route('l18n.storedictionary') }}" id="dict-form">
    @csrf
    <input type="hidden" name="lang" value="{{ $lang }}">

    <div x-data="translationEditor()" class="space-y-3">

        {{-- Controls bar --}}
        <div class="bg-white border border-gray-200 rounded-lg px-3 py-2.5 flex flex-wrap gap-3 items-center">
            <input
                type="text"
                @input.debounce.250ms="search = $event.target.value"
                placeholder="Filter keys or values…"
                class="flex-1 min-w-48 border border-gray-300 rounded px-3 py-1.5 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer select-none">
                <input type="checkbox" x-model="missingOnly" class="rounded border-gray-300">
                Missing only
            </label>
            @if(config('l18n-translator.deepl.enabled'))
            <button type="button" @click="translateMissing()" :disabled="busy"
                class="px-3 py-1.5 text-sm bg-sky-600 text-white rounded hover:bg-sky-700
                       disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="busy ? 'Translating…' : 'DeepL: fill missing'"></span>
            </button>
            @endif
            <button type="submit" form="dict-form"
                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 font-medium">
                Save
            </button>
            <a href="{{ route('l18n.tmx', $lang) }}"
               class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                ↓ TMX
            </a>
        </div>

        {{-- Table --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <thead class="bg-gray-50 border-b border-gray-200 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-1/4">Key</th>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-5/12">{{ $mainLanguage }}</th>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-5/12">{{ $lang }}</th>
                    </tr>
                </thead>
                <tbody x-ref="tbody" class="divide-y divide-gray-100">
                    @foreach($translations as $entry)
                    <tr
                        x-show="isVisible($el)"
                        data-key="{{ $entry->key }}"
                        data-original="{{ $entry->original }}"
                        class="hover:bg-gray-50/60"
                    >
                        <td class="px-4 py-2 align-top w-1/4">
                            <a href="{{ route('l18n.editstrings', ['key' => $entry->key]) }}"
                               class="text-blue-600 hover:underline font-mono text-xs break-all leading-relaxed"
                               title="Edit in all languages">{{ $entry->key }}</a>
                        </td>
                        <td class="px-4 py-2 align-top text-gray-700 w-5/12 whitespace-pre-wrap break-words">{{ $entry->original }}</td>
                        <td class="px-4 py-2 align-top w-5/12">
                            <textarea
                                name="dict[{{ $entry->key }}]"
                                rows="2"
                                class="w-full border border-gray-200 rounded px-2 py-1 text-sm resize-y
                                       focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            >{{ $entry->translation }}</textarea>
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
@endsection

@section('scripts')
<script>
function translationEditor() {
    return {
        search: '',
        missingOnly: false,
        busy: false,
        noResults: false,

        isVisible(el) {
            if (this.missingOnly) {
                const ta = el.querySelector('textarea');
                if (ta && ta.value.trim() !== '') return false;
            }
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase();
            return el.dataset.key?.toLowerCase().includes(q)
                || el.dataset.original?.toLowerCase().includes(q)
                || el.querySelector('textarea')?.value?.toLowerCase().includes(q);
        },

        checkNoResults() {
            this.$nextTick(() => {
                const rows = this.$refs.tbody?.querySelectorAll('tr') ?? [];
                this.noResults = [...rows].every(r => r.style.display === 'none');
            });
        },

        async translateMissing() {
            this.busy = true;
            const lang = document.querySelector('input[name="lang"]')?.value;
            const targetLang = (TARGET_LANG_MAP || {})[lang];
            if (!targetLang) {
                alert('No DeepL language mapping configured for: ' + lang);
                this.busy = false;
                return;
            }
            const rows = this.$refs.tbody?.querySelectorAll('tr') ?? [];
            for (const row of rows) {
                const ta = row.querySelector('textarea');
                if (!ta || ta.value.trim()) continue;
                const original = row.dataset.original;
                if (!original?.trim()) continue;
                try {
                    ta.disabled = true;
                    ta.value = await deeplTranslate(original, targetLang);
                } catch (err) {
                    ta.style.outline = '2px solid #ef4444';
                    ta.title = err.message;
                } finally {
                    ta.disabled = false;
                }
            }
            this.busy = false;
        },
    };
}
</script>
@endsection
