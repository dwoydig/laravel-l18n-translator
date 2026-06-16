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