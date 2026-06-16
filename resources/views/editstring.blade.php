@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', $isNew ? 'Add New String to all languages:' : 'Edit: ' . ($key ?? ''))

@section('content')
<div x-data="editStringForm()">

    <form method="POST"
          action="{{ $isNew ? route('l18n.appendtotranslation') : route('l18n.updatealltranslations') }}"
          id="editstring-form"
          @submit.prevent="validateAndSave()">
        @csrf

        {{-- Controls bar --}}
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4">
            <label for="key" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                Translation key <span class="font-normal normal-case text-gray-400">(used in templates as <code class="font-mono">__('key')</code>)</span>
            </label>
            <div class="flex gap-2 items-center">
                <div class="flex-1">
                    <input
                        type="text"
                        id="key"
                        name="key"
                        value="{{ $key ?? '' }}"
                        {{ !$isNew ? 'readonly' : '' }}
                        @if($isNew) @input="keyValue = $event.target.value.trim(); dirty = true" @endif
                        :class="showError('key') ? 'border-red-400 bg-red-50 focus:ring-red-400' : 'border-gray-300 focus:ring-blue-500 {{ !$isNew ? 'bg-gray-50 text-gray-500 cursor-default' : '' }}'"
                        class="w-full border rounded px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-2"
                    >
                    <p x-show="showError('key')" class="mt-1 text-xs text-red-500">Translation key is required.</p>
                </div>

                @if(config('l18n-translator.deepl.enabled'))
                <div class="relative" x-data="{ showHint: false }">
                    <button type="button"
                        @click="attemptTranslate()"
                        @mouseenter="showHint = !canTranslate"
                        @mouseleave="showHint = false"
                        :disabled="busy || !canTranslate"
                        class="px-3 py-1.5 text-sm bg-sky-600 text-white rounded hover:bg-sky-700
                               disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                        <span x-text="busy ? 'Translating…' : 'Translate'"></span>
                    </button>
                    <div x-show="showHint"
                        class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                        <span x-text="translateHint"></span>
                    </div>
                </div>
                @endif

                <div class="relative" x-data="{ showHint: false }">
                    <button type="submit"
                        @mouseenter="showHint = !canSave"
                        @mouseleave="showHint = false"
                        :disabled="!canSave"
                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 font-medium
                               disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                        Save
                    </button>
                    <div x-show="showHint"
                        class="absolute right-0 top-full mt-1 z-10 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                        <span x-text="saveHint"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per-language rows --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-medium text-gray-600 w-1/4">Language</th>
                        <th class="px-4 py-2.5 font-medium text-gray-600">Translation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($languageFiles as $file)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-3 align-top">
                            <div class="flex items-center gap-2">
                                <span class="text-xl leading-none">{{ $file->flag }}</span>
                                <div>
                                    <div class="font-medium text-gray-800 text-sm">{{ $file->name }}</div>
                                    <div class="font-mono text-xs text-gray-400">{{ $file->filename }}</div>
                                </div>
                                @if($file->filename === $mainLanguage)
                                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">source</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <textarea
                                name="languages[{{ $file->filename }}]"
                                data-lang="{{ $file->filename }}"
                                rows="2"
                                dir="{{ $file->rtl ? 'rtl' : 'ltr' }}"
                                @if($file->filename === $mainLanguage)
                                    @input="sourceText = $event.target.value.trim(); dirty = true"
                                    :class="showError('source') ? 'border-red-400 bg-red-50 focus:ring-red-400' : 'border-gray-200 focus:ring-blue-500'"
                                @endif
                                class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm resize-y focus:outline-none focus:ring-1 focus:border-blue-500"
                            >{{ html_entity_decode($translations[$file->filename] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</textarea>
                            @if($file->filename === $mainLanguage)
                            <p x-show="showError('source')" class="mt-1 text-xs text-red-500">Source text is required for translation.</p>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </form>
</div>
@endsection

@section('scripts')
@once
    @include('l18n-translator::partials.deepl')
@endonce
<script>
function editStringForm() {
    return {
        busy: false,
        dirty: false,
        keyValue: {!! json_encode($key ?? '') !!},
        sourceText: '',
        isNew: {{ $isNew ? 'true' : 'false' }},
        mainLang: {!! json_encode($mainLanguage) !!},

        init() {
            const ta = document.querySelector(`textarea[data-lang="${this.mainLang}"]`);
            this.sourceText = ta?.value?.trim() ?? '';
        },

        get keyValid()    { return this.isNew ? this.keyValue !== '' : true; },
        get sourceValid() { return this.sourceText !== ''; },
        get canSave()     { return this.keyValid; },
        get canTranslate(){ return this.keyValid && this.sourceValid; },

        get saveHint() {
            return !this.keyValid ? 'Translation key is required.' : '';
        },

        get translateHint() {
            if (!this.keyValid && !this.sourceValid) return 'Translation key and source text are required.';
            if (!this.keyValid)    return 'Translation key is required.';
            if (!this.sourceValid) return `Source text (${this.mainLang}) is required.`;
            return '';
        },

        showError(field) {
            if (!this.dirty) return false;
            if (field === 'key')    return !this.keyValid;
            if (field === 'source') return !this.sourceValid;
            return false;
        },

        validateAndSave() {
            this.dirty = true;
            if (!this.keyValid) return;
            document.getElementById('editstring-form').submit();
        },

        attemptTranslate() {
            this.dirty = true;
            if (!this.canTranslate) return;
            this.translateAll();
        },

        async translateAll() {
            const sourceLang = this.mainLang;
            const sourceTa  = document.querySelector(`textarea[data-lang="${sourceLang}"]`);
            const sourceText = sourceTa?.value?.trim();
            this.busy = true;
            const tasks = [...document.querySelectorAll('textarea[data-lang]')]
                .filter(ta => ta.dataset.lang !== sourceLang)
                .map(ta => async () => {
                    const targetLang = (TARGET_LANG_MAP[ta.dataset.lang]) ?? ta.dataset.lang.toUpperCase();
                    try {
                        ta.disabled = true;
                        ta.value = await deeplTranslate(sourceText, targetLang);
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
