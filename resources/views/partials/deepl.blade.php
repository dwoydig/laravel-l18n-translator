<script>
const TARGET_LANG_MAP    = @json(config('l18n-translator.deepl_lang_map', []));
const DEEPL_CONCURRENCY  = {{ (int) config('l18n-translator.deepl.concurrency', 5) }};

async function runConcurrent(tasks, concurrency = DEEPL_CONCURRENCY, signal) {
    for (let i = 0; i < tasks.length; i += concurrency) {
        if (signal?.aborted) break;
        await Promise.all(tasks.slice(i, i + concurrency).map(fn => fn()));
    }
}

async function deeplTranslate(text, targetLang, signal) {
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
        signal,
    });
    if (!res.ok) {
        const msg = await res.text().catch(() => '');
        throw new Error(`DeepL error ${res.status}: ${msg || res.statusText}`);
    }
    const data = await res.json();
    if (!data?.text) throw new Error('DeepL response missing "text"');
    Alpine.store('deeplUsage')?.scheduleRefresh();
    return data.text;
}

// Translates a single <textarea> in place, showing an inline error (via a sibling
// .translate-error element) on failure. Shared by every per-field translate button.
async function translateField(ta, sourceText, targetLang, signal) {
    const errEl = ta.parentElement.querySelector('.translate-error');
    try {
        ta.disabled = true;
        ta.style.outline = '';
        errEl?.classList.add('hidden');
        ta.value = await deeplTranslate(sourceText, targetLang, signal);
    } catch (err) {
        if (err.name === 'AbortError') return;
        ta.style.outline = '2px solid #ef4444';
        ta.title = err.message;
        if (errEl) {
            errEl.textContent = err.message;
            errEl.classList.remove('hidden');
        }
    } finally {
        ta.disabled = false;
    }
}

// Runs a batch of translateField() tasks against an Alpine component, managing its
// busy/abortController state. buildTasks(signal) must return an array of () => Promise tasks.
async function runTranslationJob(component, buildTasks) {
    component.busy = true;
    component.abortController = new AbortController();
    const signal = component.abortController.signal;
    await runConcurrent(buildTasks(signal), DEEPL_CONCURRENCY, signal);
    component.busy = false;
    component.abortController = null;
}

document.addEventListener('alpine:init', () => {
    Alpine.store('deeplUsage', {
        count: 0,
        limit: 0,
        loaded: false,
        selectedChars: 0,
        refreshTimer: null,

        get percent() {
            return this.limit ? Math.min(100, Math.round((this.count / this.limit) * 100)) : 0;
        },
        get selectedPercent() {
            if (!this.limit) return 0;
            return Math.min(100 - this.percent, Math.round((this.selectedChars / this.limit) * 100));
        },
        get overBudget() {
            return this.limit > 0 && (this.count + this.selectedChars) > this.limit;
        },
        get barColor() {
            if (this.percent >= 100) return 'bg-red-500';
            if (this.percent >= 90) return 'bg-amber-500';
            return 'bg-sky-600';
        },
        async load() {
            try {
                const res = await fetch('{{ route('l18n.deepl.usage') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                this.count = data.character_count ?? 0;
                this.limit = data.character_limit ?? 0;
                this.loaded = true;
            } catch (err) {
                // usage display is non-critical, fail silently
            }
        },
        scheduleRefresh() {
            clearTimeout(this.refreshTimer);
            this.refreshTimer = setTimeout(() => this.load(), 800);
        },
    });
});
</script>