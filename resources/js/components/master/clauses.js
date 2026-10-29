/**
 * SMK3 clause master page: client-side search, inline category checkboxes saved
 * over AJAX, and opening the add / edit / delete dialogs.
 *
 * Usage: x-data="clauseMaster({ rows, summary, categoryUrl })"
 *   rows        – [{ id, no, text, element, minor, major, kritis }]
 *   categoryUrl – PATCH url with ":id" placeholder
 */
export default ({ rows = [], summary = {}, categoryUrl }) => ({
    rows: Object.fromEntries(rows.map((row) => [row.id, row])),
    summary,
    q: '',
    saving: {},
    error: '',

    get visibleIds() {
        const term = this.q.trim().toLowerCase();
        const all = Object.values(this.rows);
        const matches = term ? all.filter((row) => `${row.no} ${row.text}`.toLowerCase().includes(term)) : all;
        return new Set(matches.map((row) => row.id));
    },

    get visibleCount() {
        return this.visibleIds.size;
    },

    isVisible(id) {
        return this.visibleIds.has(id);
    },

    isElementVisible(element) {
        return Object.values(this.rows).some((row) => row.element === element && this.visibleIds.has(row.id));
    },

    /** Optimistic update; reverted when the server refuses. */
    async toggle(id, category, checked) {
        const key = `${id}.${category}`;
        if (this.saving[key]) return;

        const row = this.rows[id];
        row[category] = checked;
        this.saving[key] = true;
        this.error = '';

        try {
            const { data } = await window.axios.patch(categoryUrl.replace(':id', id), { category, checked });
            this.summary = data.summary;
        } catch (error) {
            row[category] = !checked;
            this.error = error.response?.data?.message ?? this.$root.dataset.saveError;
        } finally {
            delete this.saving[key];
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
