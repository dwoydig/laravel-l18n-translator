<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Translations') - L18n Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full text-gray-900">

    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 flex items-center gap-4 h-14">
            <a href="{{ route('l18n.index') }}" class="font-semibold text-gray-800 hover:text-gray-600 tracking-tight shrink-0">
                🌍 L18n Manager
            </a>

            {{-- Key search --}}
            <div class="flex-1 max-w-sm relative"
                 x-data="{
                    query: '',
                    keys: [],
                    open: false,
                    loading: false,
                    highlighted: -1,
                    get filtered() {
                        if (this.query.trim() === '') return [];
                        const q = this.query.toLowerCase();
                        return this.keys.filter(k => k.toLowerCase().includes(q)).slice(0, 20);
                    },
                    async loadKeys() {
                        if (this.keys.length > 0) return;
                        this.loading = true;
                        try {
                            const r = await fetch('{{ route('l18n.keys') }}');
                            this.keys = await r.json();
                        } finally {
                            this.loading = false;
                        }
                    },
                    select(key) {
                        window.location.href = '{{ route('l18n.editstrings') }}?key=' + encodeURIComponent(key);
                    },
                    onKeydown(e) {
                        if (!this.open) return;
                        if (e.key === 'ArrowDown')  { e.preventDefault(); this.highlighted = Math.min(this.highlighted + 1, this.filtered.length - 1); }
                        if (e.key === 'ArrowUp')    { e.preventDefault(); this.highlighted = Math.max(this.highlighted - 1, 0); }
                        if (e.key === 'Enter' && this.highlighted >= 0) { e.preventDefault(); this.select(this.filtered[this.highlighted]); }
                        if (e.key === 'Escape')     { this.open = false; this.highlighted = -1; }
                    },
                 }"
                 @click.outside="open = false; highlighted = -1"
            >
                <div class="relative">
                    <input
                        type="text"
                        placeholder="Search key…"
                        autocomplete="off"
                        x-model="query"
                        @focus="loadKeys(); open = query.trim() !== ''"
                        @input="open = query.trim() !== ''; highlighted = -1"
                        @keydown="onKeydown($event)"
                        class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-1.5 text-sm font-mono
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                    <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                    <svg x-show="loading" class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </div>

                <ul x-show="open && filtered.length > 0"
                    x-transition
                    class="absolute z-50 mt-1 w-full max-h-72 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg text-sm font-mono">
                    <template x-for="(key, i) in filtered" :key="key">
                        <li @click="select(key)"
                            @mouseenter="highlighted = i"
                            :class="highlighted === i ? 'bg-blue-50 text-blue-900' : 'text-gray-800 hover:bg-gray-50'"
                            class="px-3 py-1.5 cursor-pointer truncate"
                            x-text="key">
                        </li>
                    </template>
                </ul>
            </div>

            <nav class="flex items-center gap-2 text-sm shrink-0 ml-auto">
                <a href="{{ route('l18n.index') }}"     class="px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium transition-colors">Languages</a>
                <a href="{{ route('l18n.create') }}"    class="px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">+ Language</a>
                <a href="{{ route('l18n.addstring') }}" class="px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">+ String</a>
                <a href="{{ route('l18n.coverage') }}"  class="px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">Coverage</a>
                <a href="{{ route('l18n.missing') }}"  class="px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">Missing</a>
            </nav>
        </div>
    </header>

    @foreach(['success', 'error', 'warning'] as $_type)
        @if(session($_type))
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 pt-4">
            <div class="rounded-md px-4 py-3 text-sm font-medium
                {{ $_type === 'success' ? 'bg-green-50  border border-green-200  text-green-800'  : '' }}
                {{ $_type === 'error'   ? 'bg-red-50    border border-red-200    text-red-800'    : '' }}
                {{ $_type === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' : '' }}
            ">{{ is_array(session($_type)) ? implode(' ', session($_type)) : session($_type) }}</div>
        </div>
        @endif
    @endforeach

    <main class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6">
        <h1 class="text-xl font-bold text-gray-900 mb-5">@yield('title')</h1>
        @yield('content')
    </main>

    @yield('scripts')
</body>
</html>
