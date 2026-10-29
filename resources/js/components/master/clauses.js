/**
 * SMK3 clause master page: one tab per SMK3 element. Switching tabs and searching
 * reload only the results (a Blade fragment) over AJAX, so just one element's
 * criteria are rendered at a time.
 *
 * Category checkboxes are not saved on click: changes are collected (also across
 * tabs) and saved together with the "Save changes" button, or discarded.
 *
 * Usage: x-data="clauseMaster({ url, element, q, summary, saveUrl })"
 *   url     – list url (fragment requests use the X-Partial header)
 *   saveUrl – PATCH url taking { changes: [{ id, minor, major, kritis }] }
 * The fragment calls setRows([{ id, no, text, minor, major, kritis }]) for the criteria it shows.
 */
const CATEGORIES = ['minor', 'major', 'kritis'];

export default ({ url, element, q = '', summary = {}, saveUrl }) => ({
    rows: {},
    original: {},
    element,
    q: q ?? '',
    summary,
    saving: false,
    error: '',
    notice: '',
    loading: false,
    controller: null,

    init() {
        this.$watch('q', () => this.load());

        // unsaved checkbox changes would be lost when leaving the page
        window.addEventListener('beforeunload', (event) => {
            if (this.changeCount) event.preventDefault();
        });
    },

    setRows(rows) {
        rows.forEach((row) => {
            // keep pending changes when coming back to a tab
            if (this.isDirty(row.id)) return;
            this.rows[row.id] = row;
            this.original[row.id] = Object.fromEntries(CATEGORIES.map((category) => [category, row[category]]));
        });
    },

    isDirty(id) {
        const row = this.rows[id];
        const original = this.original[id];
        return Boolean(row && original) && CATEGORIES.some((category) => row[category] !== original[category]);
    },

    get changes() {
        return Object.keys(this.original)
            .filter((id) => this.isDirty(id))
            .map((id) => ({ id: Number(id), ...Object.fromEntries(CATEGORIES.map((category) => [category, this.rows[id][category]])) }));
    },

    get changeCount() {
        return this.changes.length;
    },

    toggle(id, category, checked) {
        if (this.saving) return;
        this.rows[id][category] = checked;
        this.notice = '';
    },

    discard() {
        Object.keys(this.original).forEach((id) => Object.assign(this.rows[id], this.original[id]));
        this.error = '';
    },

    async save() {
        const changes = this.changes;
        if (!changes.length || this.saving) return;

        this.saving = true;
        this.error = '';
        this.notice = '';

        try {
            const { data } = await window.axios.patch(saveUrl, { changes });
            changes.forEach(({ id, ...values }) => Object.assign(this.original[id], values));
            this.summary = data.summary;
            this.notice = data.message;
        } catch (error) {
            this.error = error.response?.data?.message ?? this.$root.dataset.saveError;
        } finally {
            this.saving = false;
        }
    },

    selectElement(element) {
        if (element === this.element || this.loading) return;
        this.element = element;
        this.load();
    },

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        const params = new URLSearchParams({ element: this.element });
        if (this.q.trim()) params.set('q', this.q.trim());
        const target = `${url}?${params}`;

        try {
            const response = await fetch(target, {
                headers: { 'X-Partial': 'results', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            this.$refs.results.innerHTML = await response.text();
            window.history.replaceState(null, '', target);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Failed to load clauses', error);
                this.error = this.$root.dataset.loadError;
            }
        } finally {
            this.loading = false;
        }
    },

    openCreate() {
        this.$dispatch('clause-form', { row: null });
    },

    openEdit(id) {
        this.$dispatch('clause-form', { row: { ...this.rows[id] } });
    },

    openDelete(id) {
        this.$dispatch('clause-delete', { row: { ...this.rows[id] } });
    },
});
