/**
 * Non-Conformities list: search + filter panel that reload only the results
 * (a Blade fragment) over AJAX and keep the URL in sync.
 *
 * Usage: x-data="nonConformityList({ url, filters, departments })"
 *   filters     – current query values (q, pabrik, departemen, kategori, status, dari, sampai, per_page)
 *   departments – { plantCode: { departmentCode: name } } for the plants the user can see
 */
const FILTER_KEYS = ['pabrik', 'departemen', 'kategori', 'status', 'dari', 'sampai'];

export default ({ url, filters = {}, departments = {} }) => ({
    url,
    departments,
    q: filters.q ?? '',
    pabrik: filters.pabrik ?? '',
    departemen: filters.departemen ?? '',
    kategori: filters.kategori ?? '',
    status: filters.status ?? '',
    dari: filters.dari ?? '',
    sampai: filters.sampai ?? '',
    perPage: filters.per_page ?? '',
    page: Number(new URL(window.location.href).searchParams.get('page')) || 1,
    filterOpen: false,
    loading: false,
    controller: null,

    /** Departments of the chosen plant, or of every visible plant. */
    get departmentOptions() {
        const lists = this.pabrik ? [this.departments[this.pabrik] ?? {}] : Object.values(this.departments);
        return Object.entries(Object.assign({}, ...lists));
    },

    get activeFilterCount() {
        // a date range counts once
        return FILTER_KEYS.filter((key) => key !== 'sampai' && this[key]).length;
    },

    query() {
        const params = new URLSearchParams();
        const values = { q: this.q.trim(), ...Object.fromEntries(FILTER_KEYS.map((key) => [key, this[key]])), per_page: this.perPage };
        Object.entries(values).forEach(([key, value]) => value && params.set(key, value));
        if (this.page > 1) params.set('page', this.page);
        return params.toString();
    },

    /** Any filter change starts again from page 1. */
    apply() {
        this.page = 1;
        this.load();
    },

    changePlant() {
        // department codes differ per plant
        if (!this.departmentOptions.some(([code]) => code === this.departemen)) this.departemen = '';
        this.apply();
    },

    setDates({ from, to }) {
        this.dari = from;
        this.sampai = to;
        this.apply();
    },

    reset() {
        FILTER_KEYS.forEach((key) => (this[key] = ''));
        const picker = this.$refs.dateRange?.querySelector('[x-data]');
        if (picker) window.Alpine.$data(picker).clear(true);
        this.apply();
    },

    goTo(href) {
        this.page = Number(new URL(href, window.location.origin).searchParams.get('page')) || 1;
        this.load();
    },

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        const query = this.query();
        const target = query ? `${this.url}?${query}` : this.url;

        try {
            const response = await fetch(target, {
                headers: { 'X-Partial': 'results', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            this.$refs.results.innerHTML = await response.text();
            window.history.replaceState(null, '', target);
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Failed to load findings', error);
        } finally {
            this.loading = false;
        }
    },

    /** Delegated from the results container, which is replaced on every load. */
    onResultsClick(event) {
        const link = event.target.closest('a[data-slot="pagination-link"]');
        if (!link) return;
        event.preventDefault();
        if (link.getAttribute('aria-disabled') !== 'true') this.goTo(link.href);
    },

    onResultsChange(event) {
        if (event.target.name !== 'per_page') return;
        this.perPage = event.target.value;
        this.apply();
    },
});
