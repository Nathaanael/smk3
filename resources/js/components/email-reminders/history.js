/**
 * One history tab of the email reminder page ("sent" or "failed"): search, filters, page size and
 * page links reload only the results over AJAX. A finished send reloads them too.
 *
 * Usage: x-data="reminderHistory({ url })"
 */
export default ({ url }) => ({
    q: '',
    sender: '',
    dari: '',
    sampai: '',
    perPage: '',
    page: 1,
    filterOpen: false,
    loading: false,
    controller: null,

    get activeFilterCount() {
        return (this.sender ? 1 : 0) + (this.dari || this.sampai ? 1 : 0);
    },

    query() {
        const values = { q: this.q.trim(), sender: this.sender, dari: this.dari, sampai: this.sampai, per_page: this.perPage, page: this.page > 1 ? this.page : '' };
        const params = new URLSearchParams();
        Object.entries(values).forEach(([key, value]) => value && params.set(key, value));
        return params.toString();
    },

    /** Any filter change starts again from page 1. */
    apply() {
        this.page = 1;
        this.load();
    },

    reset() {
        this.sender = '';
        this.dari = '';
        this.sampai = '';
        this.$refs.dateRange.querySelectorAll('[data-date-input]').forEach((input) => window.Alpine.$data(input).clear(true));
        this.apply();
    },

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        const query = this.query();

        try {
            const response = await fetch(query ? `${url}?${query}` : url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            this.$refs.results.innerHTML = await response.text();
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Failed to load the send history', error);
        } finally {
            this.loading = false;
        }
    },

    /** Delegated from the results container, which is replaced on every load. */
    onResultsClick(event) {
        const link = event.target.closest('a[data-slot="pagination-link"]');
        if (!link) return;

        event.preventDefault();
        if (link.getAttribute('aria-disabled') === 'true') return;

        this.page = Number(new URL(link.href).searchParams.get('page')) || 1;
        this.load();
    },

    onResultsChange(event) {
        if (event.target.name !== 'per_page') return;
        this.perPage = event.target.value;
        this.apply();
    },
});
