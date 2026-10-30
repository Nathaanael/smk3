/**
 * Progress Monitoring: plant + period filters that reload only the recap (a Blade fragment)
 * over AJAX and keep the URL in sync.
 *
 * Usage: x-data="progressMonitor({ url, filters, currentMonth })"
 *   filters      – current values (pabrik, dari, sampai)
 *   currentMonth – [first day, last day] of this month, for the "This month" button
 *
 * Both dates are always sent, so clearing them means "all dates" (no dates at all is the
 * server's default: the current month).
 */
export default ({ url, filters = {}, currentMonth = ['', ''] }) => ({
    url,
    pabrik: filters.pabrik ?? '',
    dari: filters.dari ?? '',
    sampai: filters.sampai ?? '',
    loading: false,
    controller: null,

    get isThisMonth() {
        return this.dari === currentMonth[0] && this.sampai === currentMonth[1];
    },

    thisMonth() {
        [this.dari, this.sampai] = currentMonth;
        // set the pickers without firing their date-change (that would load twice)
        this.$refs.filters.querySelectorAll('[data-date-input]').forEach((element, index) => {
            const input = window.Alpine.$data(element);
            input.value = currentMonth[index];
            input.picker.setDate(currentMonth[index], false);
        });
        this.load();
    },

    query() {
        const params = new URLSearchParams({ dari: this.dari, sampai: this.sampai });
        if (this.pabrik) params.set('pabrik', this.pabrik);
        return params.toString();
    },

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        const target = `${this.url}?${this.query()}`;

        try {
            const response = await fetch(target, {
                headers: { 'X-Partial': 'results', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            this.$refs.results.innerHTML = await response.text();
            window.history.replaceState(null, '', target);
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Failed to load progress monitoring', error);
        } finally {
            this.loading = false;
        }
    },
});
