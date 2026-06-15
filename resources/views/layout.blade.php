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
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ route('l18n.index') }}" class="font-semibold text-gray-800 hover:text-gray-600 tracking-tight">
                🌍 L18n Manager
            </a>
            <nav class="flex items-center gap-5 text-sm">
                <a href="{{ route('l18n.index') }}"     class="text-gray-600 hover:text-gray-900 font-medium">Languages</a>
                <a href="{{ route('l18n.create') }}"    class="text-gray-600 hover:text-gray-900">+ Language</a>
                <a href="{{ route('l18n.addstring') }}" class="text-gray-600 hover:text-gray-900">+ String</a>
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
            ">{{ session($_type) }}</div>
        </div>
        @endif
    @endforeach

    <main class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6">
        <h1 class="text-xl font-bold text-gray-900 mb-5">@yield('title')</h1>
        @yield('content')
    </main>

    {{-- DeepL helper — always present, no-ops when DEEPL_AUTH_KEY is not set --}}
    <script>
    const TARGET_LANG_MAP    = @json(config('l18n-translator.deepl_lang_map', []));
    const DEEPL_CONCURRENCY  = {{ (int) config('l18n-translator.deepl.concurrency', 5) }};

    async function runConcurrent(tasks, concurrency = DEEPL_CONCURRENCY) {
        for (let i = 0; i < tasks.length; i += concurrency) {
            await Promise.all(tasks.slice(i, i + concurrency).map(fn => fn()));
        }
    }

    async function deeplTranslate(text, targetLang) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content
                   ?? document.querySelector('input[name="_token"]')?.value;
        const res = await fetch('{{ route('l18n.deepl') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ text, target_lang: targetLang }),
        });
        if (!res.ok) {
            const msg = await res.text().catch(() => '');
            throw new Error(`DeepL error ${res.status}: ${msg || res.statusText}`);
        }
        const data = await res.json();
        if (!data?.text) throw new Error('DeepL response missing "text"');
        return data.text;
    }
    </script>

    @yield('scripts')
</body>
</html>
