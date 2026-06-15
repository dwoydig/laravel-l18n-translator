@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Create New Language')

@section('content')
<div class="max-w-md bg-white border border-gray-200 rounded-lg p-6"
     x-data="localeSearch()">

    <form method="POST" action="{{ route('l18n.store') }}">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Target Language
            </label>

            <div class="relative">
                <input
                    type="text"
                    x-model="query"
                    @input="open = true; selected = ''"
                    @focus="open = true"
                    @keydown.escape="open = false"
                    @keydown.enter.prevent="pickFirst()"
                    placeholder="Type to search languages…"
                    autocomplete="off"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="selected ? 'border-green-400' : ''"
                >
                <input type="hidden" name="targetLanguage" :value="selected">

                <div x-show="open && filtered.length > 0"
                     @click.outside="open = false"
                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-60 overflow-y-auto">
                    <template x-for="locale in filtered" :key="locale.iso">
                        <button type="button"
                            @click="choose(locale)"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 hover:text-blue-700">
                            <span x-text="locale.name + ' (' + locale.iso + ')'"></span>
                        </button>
                    </template>
                </div>

                <div x-show="open && query.trim() && filtered.length === 0"
                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg px-3 py-2 text-sm text-gray-400">
                    No matching languages.
                </div>
            </div>

            <p class="mt-1.5 text-xs text-gray-500">
                An empty <code class="font-mono">{lang}.json</code> will be created with all keys from
                <code class="font-mono">{{ config('l18n-translator.main_language', 'en') }}.json</code>.
                Use the editor to fill in translations manually or via DeepL.
            </p>
        </div>

        <button type="submit"
            :disabled="!selected"
            class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700
                   disabled:opacity-40 disabled:cursor-not-allowed">
            Create language file
        </button>
    </form>
</div>
@endsection

@section('scripts')
<script>
function localeSearch() {
    const all = Object.entries({!! json_encode($availableLanguages) !!})
        .map(([iso, name]) => ({ iso, name }));

    return {
        query: '',
        selected: '',
        open: false,

        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return [];
            return all.filter(l =>
                l.name.toLowerCase().includes(q) || l.iso.toLowerCase().includes(q)
            ).slice(0, 80);
        },

        choose(locale) {
            this.query    = locale.name + ' (' + locale.iso + ')';
            this.selected = locale.iso;
            this.open     = false;
        },

        pickFirst() {
            if (this.filtered.length) this.choose(this.filtered[0]);
        },
    };
}
</script>
@endsection
