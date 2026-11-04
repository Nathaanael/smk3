/**
 * Photo viewer modal of a finding: gallery of its finding / repair photos next to a table with
 * the key facts. Opened from anywhere with the window event
 *   finding-photos-open  { finding: NonConformity::photoViewerData(), field: 'foto_temuan' }
 * Removing a photo changes finding.photos in place and dispatches finding-photos-changed { message }.
 *
 * Usage: x-data="findingPhotoViewer({ destroyUrl, errorMessage })"
 *   destroyUrl – DELETE url with ":id" and ":photo"
 */
export default ({ destroyUrl, errorMessage = '' }) => ({
    finding: null,
    field: 'foto_temuan',
    index: 0,
    confirmingRemove: false,
    removing: false,
    viewerError: '',

    get photos() {
        return this.finding?.photos[this.field] ?? [];
    },

    get photo() {
        return this.photos[this.index] ?? null;
    },

    get canRemove() {
        return Boolean(this.photo && this.finding?.canDelete[this.field]);
    },

    view({ finding, field }) {
        this.finding = finding;
        this.selectField(field);
    },

    selectField(field) {
        this.field = field;
        this.pick(0);
    },

    pick(index) {
        this.index = index;
        this.confirmingRemove = false;
        this.viewerError = '';
    },

    /** Previous / next photo, wrapping around. */
    step(delta) {
        if (this.photos.length > 1) this.pick((this.index + delta + this.photos.length) % this.photos.length);
    },

    async removePhoto() {
        if (!this.photo || this.removing) return;

        this.removing = true;
        this.viewerError = '';
        try {
            const { data } = await window.axios.delete(destroyUrl.replace(':id', this.finding.id).replace(':photo', this.photo.id));
            this.photos.splice(this.index, 1);
            this.pick(Math.max(0, Math.min(this.index, this.photos.length - 1)));
            this.$dispatch('finding-photos-changed', { message: data.message });
        } catch (error) {
            this.viewerError = error.response?.data?.message || errorMessage;
        } finally {
            this.removing = false;
        }
    },
});
