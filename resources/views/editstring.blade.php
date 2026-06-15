@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@if($isNew)
    @section('title', 'Add New Translation String')
@else
    @section('title', 'Edit: ' . ($key ?? ''))
@endif

@section('content')
<div class="max-w-2xl" x-data="editStringForm()">

    <form method="POST"
          action="{{ $isNew ? route('l18n.appendtotranslation') : route('l18n.updatealltranslations') }}">
        @csrf

        {{-- Key --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
            <label for="key" class="block text-sm font-medium text-gray-700 mb-1">
                Translation key <span class="text-gray-400 font-normal">(used in Blade templates as <code class="font-mono">__('key')</code>)</span>
            </label>
            <input
                type="text"
                id="key"
                name="key"
                value="{{ $key ?? '' }}"
                {{ !$isNew ? 'readonly' : '' }}
                required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       {{ !$isNew ? 'bg-gray-50 text-gray-600' : '' }}"
            >
        </div>

        {{-- Per-language textareas --}}
        <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100 mb-4">
            @foreach($availableLanguages as $iso => $name)
            <div class="px-4 py-3">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                    {{ $name }}
                    <span class="font-mono font-normal normal-case text-gray-400 ml-1">{{ $iso }}</span>
                    @if($iso === $mainLanguage)
                        <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">source</span>
                    @endif
                </label>
                <textarea
                    name="languages[{{ $iso }}]"
                    data-lang="{{ $iso }}"
                    rows="2"
                    class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm resize-y
                           focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                >{{ $translations[$iso] ?? '' }}</textarea>
            </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 items-center">
            <button type="submit"
                class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700">
                {{ $isNew ? 'Add to all languages' : 'Save all languages' }}
            </button>
            @if(config('l18n-translator.deepl.enabled'))
            <button type="button" @click="translateAll()" :disabled="busy"
                class="px-4 py-2 bg-sky-600 text-white text-sm rounded hover:bg-sky-700
                       disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="busy ? 'Translating…' : 'DeepL: translate all from {{ $mainLanguage }}'"></span>
            </button>
            @endif
            <a href="{{ route('l18n.index') }}" class="text-sm text-gray-500 hover:text-gray-700 ml-auto">
                ← Back
            </a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
function editStringForm() {
    return {
        busy: false,

        async translateAll() {
            const sourceLang = '{{ $mainLanguage }}';
            const sourceTa = document.querySelector(`textarea[data-lang="${sourceLang}"]`);
            const sourceText = sourceTa?.value?.trim();
            if (!sourceText) {
                alert('The source language ({{ $mainLanguage }}) textarea is empty.');
                return;
            }
            this.busy = true;
            const textareas = document.querySelectorAll('textarea[data-lang]');
            for (const ta of textareas) {
                if (ta.dataset.lang === sourceLang) continue;
                const targetLang = (TARGET_LANG_MAP || {})[ta.dataset.lang];
                if (!targetLang) continue;
                try {
                    ta.disabled = true;
                    ta.value = await deeplTranslate(sourceText, targetLang);
                } catch (err) {
                    ta.style.outline = '2px solid #ef4444';
                    ta.title = err.message;
                    console.error('DeepL failed for', ta.dataset.lang, err);
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
