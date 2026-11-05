/**
 * User account master page (auditors, auditees): client-side search, plant filter and
 * pagination, opening the add / edit / delete dialogs, and fetching the template in the
 * background behind a loading toast.
 *
 * Usage: x-data="accountMaster({ rows, perPage, templateUrl, templateError })"
 *   rows          – [{ id, name, email, departemen, departmentName, pabrik, plants, isSelf }], in display order
 *   perPage       – initial rows per page
 *   templateUrl   – template download url
 *   templateError – message shown when the download fails without a message of its own
 */
import { downloadFile } from '../../lib/download';

export default ({ rows = [], perPage = 5, templateUrl, templateError }) => ({
    downloadingTemplate: false,
    downloadController: null,
    error: '',
    rows: Object.fromEntries(rows.map((row) => [row.id, row])),
    order: rows.map((row) => row.id),
    q: '',
    plant: '',
    page: 1,
    perPage,

    init() {
        // any change to what is listed starts again from page 1
        this.$watch('q', () => (this.page = 1));
        this.$watch('plant', () => (this.page = 1));
        this.$watch('perPage', () => (this.page = 1));
    },

    /** Ids matching the search and plant filter, in display order. */
    get filteredIds() {
        const term = this.q.trim().toLowerCase();
        return this.order.filter((id) => {
            const row = this.rows[id];
            return (!this.plant || row.plants.includes(this.plant))
                && (!term || `${row.name} ${row.email} ${row.departemen} ${row.departmentName}`.toLowerCase().includes(term));
        });
    },

    get visibleCount() {
        return this.filteredIds.length;
    },

    get pageCount() {
        return Math.max(1, Math.ceil(this.visibleCount / this.perPage));
    },

    get offset() {
        return (Math.min(this.page, this.pageCount) - 1) * this.perPage;
    },

    get pageIds() {
        return new Set(this.filteredIds.slice(this.offset, this.offset + Number(this.perPage)));
    },

    get from() {
        return this.visibleCount ? this.offset + 1 : 0;
    },

    get to() {
        return Math.min(this.offset + Number(this.perPage), this.visibleCount);
    },

    /** Page numbers around the current page, with '…' gaps: [1, '…', 4, 5, 6, '…', 10]. */
    get pages() {
        const last = this.pageCount;
        const pages = [];
        for (let page = 1; page <= last; page++) {
            if (page === 1 || page === last || Math.abs(page - this.page) <= 1) pages.push(page);
            else if (pages.at(-1) !== '…') pages.push('…');
        }
        return pages;
    },

    goTo(page) {
        this.page = Math.min(Math.max(1, page), this.pageCount);
    },

    isVisible(id) {
        return this.pageIds.has(id);
    },

    /** Row number within the filtered list. */
    position(id) {
        return this.filteredIds.indexOf(id) + 1;
    },

    async downloadTemplate() {
        if (this.downloadingTemplate) return;

        this.downloadController = new AbortController();
        this.downloadingTemplate = true;
        this.error = '';
        try {
            await downloadFile(templateUrl, {
                signal: this.downloadController.signal,
                fallbackName: 'template-akun.xlsx',
                errorMessage: templateError,
            });
        } catch (error) {
            if (error.name !== 'AbortError') this.error = error.message;
        } finally {
            this.downloadingTemplate = false;
            this.downloadController = null;
        }
    },

    cancelTemplateDownload() {
        this.downloadController?.abort();
    },

    openCreate() {
        this.$dispatch('account-form', { row: null });
    },

    openEdit(id) {
        this.$dispatch('account-form', { row: { ...this.rows[id] } });
    },

    openDelete(id) {
        this.$dispatch('account-delete', { row: { ...this.rows[id] } });
    },
});
