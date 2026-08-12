<script>
// Merge mixins into a base Alpine component object, preserving getters/setters
// (a plain {...spread} would evaluate getters once and copy the resulting value instead).
function withMixins(base, ...mixins) {
    for (const mixin of mixins) {
        Object.defineProperties(base, Object.getOwnPropertyDescriptors(mixin));
    }
    return base;
}

// Shared by any component that lets the user check/uncheck rows by a string key.
function keySelectionMixin() {
    return {
        selected: new Set(),

        get selectedCount() {
            return this.selected.size;
        },

        toggleRow(key) {
            const next = new Set(this.selected);
            next.has(key) ? next.delete(key) : next.add(key);
            this.selected = next;
        },

        selectNone() {
            this.selected = new Set();
        },
    };
}

// Shared by table-based editors (translationEditor, missingEditor): a searchable,
// filterable list of <tr> rows with per-row checkboxes and a "translatable" subset.
function filterableRowsMixin(extraSearchFields = []) {
    return {
        ...keySelectionMixin(),
        search: '',
        showOnlySelected: false,

        visibleRows() {
            return [...(this.$refs.tbody?.querySelectorAll('tr') ?? [])]
                .filter(r => r.style.display !== 'none');
        },

        translatableRows() {
            return [...(this.$refs.tbody?.querySelectorAll('tr') ?? [])]
                .filter(r => this.selected.has(r.dataset.key) && r.dataset.original?.trim());
        },

        selectAll() {
            this.selected = new Set(this.visibleRows().map(r => r.dataset.key));
        },

        get selectedCount() {
            return this.selected.size;
        },

        get translatableCount() {
            return this.translatableRows().length;
        },

        get selectedChars() {
            return this.translatableRows().reduce((sum, r) => sum + r.dataset.original.length, 0);
        },

        isVisible(el) {
            if (this.showOnlySelected && !this.selected.has(el.dataset.key)) return false;
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase();
            const fields = ['key', 'original', ...extraSearchFields];
            return fields.some(f => el.dataset[f]?.toLowerCase().includes(q))
                || el.querySelector('textarea')?.value?.toLowerCase().includes(q);
        },
    };
}
</script>
