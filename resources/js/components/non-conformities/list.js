/**
 * Non-Conformities list: search + filter panel that reload only the results
 * (a Blade fragment) over AJAX and keep the URL in sync.
 *
 * Column headers (buttons with data-sort) sort the list: asc → desc → back to the default order.
 *
 * Also handles the row actions: ticking rows for bulk open / closed / delete, deleting
 * one row, and uploading a photo straight into a photo cell.
 *
 * Usage: x-data="nonConformityList({ url, filters, departments, bulkUrl, destroyUrl, photoUrl })"
 *   filters     – current query values (q, pabrik, departemen, kategori, status, dari, sampai, per_page, sort, dir)
 *   departments – { plantCode: { departmentCode: name } } for the plants the user can see
 *   destroyUrl  – DELETE url with ":id"; photoUrl – POST url with ":id" and ":field"
 */
import { downloadFile } from '../../lib/download';

const FILTER_KEYS = ['pabrik', 'departemen', 'kategori', 'status', 'dari', 'sampai'];

const EXPORT_COLUMNS_KEY = 'nc.exportColumns';
const EXPORT_PROGRESS_KEY = 'nc.exportProgress';

export default ({ url, filters = {}, departments = {}, bulkUrl = null, destroyUrl = null, photoUrl = null, exportUrl = null, exportColumns = [] }) => ({
    url,
    departments,
    // export menu: ticked columns (remembered per browser) and "only the ticked rows"
    exportColumnList: exportColumns,
    exportKeys: rememberedColumns(exportColumns),
    exportSelectedOnly: false,
    // also export the Progress Monitoring SMK3 recap (remembered per browser, on by default)
    exportProgress: rememberedFlag(EXPORT_PROGRESS_KEY, true),
    // format being prepared ('xlsx' / 'pdf'), shown in the loading toast
    exporting: null,
    exportController: null,
    // ticked rows of the current page (ids as strings, like checkbox values)
    selected: [],
    pageIds: [],
    pendingDelete: [],
    notice: '',
    error: '',
    busy: false,
    uploading: {},
    q: filters.q ?? '',
    pabrik: filters.pabrik ?? '',
    departemen: filters.departemen ?? '',
    kategori: filters.kategori ?? '',
    status: filters.status ?? '',
    dari: filters.dari ?? '',
    sampai: filters.sampai ?? '',
    perPage: filters.per_page ?? '',
    sort: filters.sort ?? '',
    // a sorted list without a direction is ascending (as on the server)
    dir: filters.sort ? filters.dir || 'asc' : '',
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
        // "from" and "to" together count as one date filter
        const dates = this.dari || this.sampai ? 1 : 0;
        return FILTER_KEYS.filter((key) => key !== 'dari' && key !== 'sampai' && this[key]).length + dates;
    },

    query() {
        const params = new URLSearchParams();
        const values = { q: this.q.trim(), ...Object.fromEntries(FILTER_KEYS.map((key) => [key, this[key]])), per_page: this.perPage, sort: this.sort, dir: this.sort && this.dir };
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

    reset() {
        FILTER_KEYS.forEach((key) => (this[key] = ''));
        this.$refs.dateRange?.querySelectorAll('[data-date-input]').forEach((input) => window.Alpine.$data(input).clear(true));
        this.apply();
    },

    /** Header click: a new column starts ascending, then descending, then the default order again. */
    sortBy(column) {
        if (this.sort !== column) {
            this.sort = column;
            this.dir = 'asc';
        } else if (this.dir === 'asc') {
            this.dir = 'desc';
        } else {
            this.sort = '';
            this.dir = '';
        }
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
        const sortButton = event.target.closest('button[data-sort]');
        if (sortButton) {
            this.sortBy(sortButton.dataset.sort);
            return;
        }

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

    /** Called by the results fragment; a new page starts with nothing ticked. */
    setPageIds(ids) {
        this.pageIds = ids.map(String);
        this.selected = [];
    },

    get allSelected() {
        return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
    },

    get someSelected() {
        return this.selected.length > 0 && !this.allSelected;
    },

    toggleAll(checked) {
        this.selected = checked ? [...this.pageIds] : [];
    },

    /** Bulk "open" / "closed" from the action menu; delete goes through the confirm dialog. */
    async bulk(action, ids = this.selected) {
        if (!ids.length || this.busy) return;
        await this.send(() => window.axios.post(bulkUrl, { action, ids }));
    },

    /** Ask for confirmation before deleting the given ids (one row, or the ticked rows). */
    confirmDelete(ids = this.selected) {
        if (!ids.length) return;
        this.pendingDelete = ids.map(String);
        this.$dispatch('finding-delete');
    },

    async deleteConfirmed() {
        const ids = this.pendingDelete;
        if (ids.length === 1) {
            await this.send(() => window.axios.delete(destroyUrl.replace(':id', ids[0])));
        } else {
            await this.bulk('delete', ids);
        }
        this.pendingDelete = [];
    },

    /** Upload a photo straight from a table cell, then reload the page of results. */
    async uploadPhoto(id, field, event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        const key = `${id}.${field}`;
        const data = new FormData();
        data.append('photo', file);
        this.uploading[key] = true;
        try {
            await this.send(() => window.axios.post(photoUrl.replace(':id', id).replace(':field', field), data));
        } finally {
            delete this.uploading[key];
        }
    },

    get allExportColumns() {
        return this.exportKeys.length === this.exportColumnList.length;
    },

    toggleExportColumns(checked) {
        this.exportKeys = checked ? this.exportColumnList.map((column) => column.key) : [];
        this.rememberExportColumns();
    },

    rememberExportColumns() {
        try {
            window.localStorage.setItem(EXPORT_COLUMNS_KEY, JSON.stringify(this.exportKeys));
        } catch (error) {
            // storage blocked (private window): the choice just is not remembered
        }
    },

    rememberExportProgress() {
        try {
            window.localStorage.setItem(EXPORT_PROGRESS_KEY, JSON.stringify(this.exportProgress));
        } catch (error) {
            // storage blocked (private window): the choice just is not remembered
        }
    },

    /** Download link: current search / filters, the ticked columns, optionally the ticked rows and the recap. */
    exportHref(format) {
        const params = new URLSearchParams(this.query());
        params.delete('page');
        params.delete('per_page');
        params.set('format', format);
        this.exportKeys.forEach((key) => params.append('columns[]', key));
        if (this.exportSelectedOnly) this.selected.forEach((id) => params.append('ids[]', id));
        if (this.exportProgress) params.set('progress', '1');
        return `${exportUrl}?${params}`;
    },

    /**
     * Fetch the export in the background (the page shows its own loading toast instead of the
     * browser's), then save it under the file name the server gave.
     */
    async download(format) {
        if (this.exporting || !this.exportKeys.length) return;

        this.exportController = new AbortController();
        this.exporting = format;
        this.notice = '';
        this.error = '';

        try {
            await downloadFile(this.exportHref(format), {
                signal: this.exportController.signal,
                fallbackName: `export.${format}`,
                errorMessage: this.$root.dataset.actionError,
            });
        } catch (error) {
            if (error.name !== 'AbortError') this.error = error.message || this.$root.dataset.actionError;
        } finally {
            this.exporting = null;
            this.exportController = null;
        }
    },

    cancelDownload() {
        this.exportController?.abort();
    },

    /** Run a request, show its message (or the validation error) and refresh the results. */
    async send(request) {
        this.busy = true;
        this.notice = '';
        this.error = '';
        try {
            const { data } = await request();
            this.notice = data.message ?? '';
            await this.load();
        } catch (error) {
            const errors = error.response?.data?.errors;
            this.error = (errors && Object.values(errors)[0]?.[0]) ?? error.response?.data?.message ?? this.$root.dataset.actionError;
        } finally {
            this.busy = false;
        }
    },
});

/** Export columns picked last time in this browser, else the default ones. */
function rememberedColumns(columns) {
    const keys = columns.map((column) => column.key);
    try {
        const saved = JSON.parse(window.localStorage.getItem(EXPORT_COLUMNS_KEY) ?? 'null');
        if (Array.isArray(saved)) {
            const valid = saved.filter((key) => keys.includes(key));
            if (valid.length) return valid;
        }
    } catch (error) {
        // unreadable or blocked storage: fall back to the defaults
    }
    return columns.filter((column) => column.default).map((column) => column.key);
}

/** A yes / no choice saved in this browser, else the default. */
function rememberedFlag(key, fallback) {
    try {
        const saved = JSON.parse(window.localStorage.getItem(key) ?? 'null');
        if (typeof saved === 'boolean') return saved;
    } catch (error) {
        // unreadable or blocked storage: fall back to the default
    }
    return fallback;
}
