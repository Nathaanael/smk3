/**
 * "Add finding" page: plant → department → PIC options, clause suggestions while the
 * description is typed, categories limited to those ticked for the chosen clause,
 * previews of the chosen photos, and a warning before leaving with unsaved input.
 *
 * Usage: x-data="findingForm({ departments, auditees, clauses, categories, suggestUrl, values, viewer, maxPhotos })"
 *   departments – { plant: { code: name } }
 *   auditees    – [{ name, email, plants: [], departments: [] }]
 *   clauses     – { "6.7.7": { text, categories: ['Minor', ...] } }
 *   values      – initial values (old input after a failed save, or the kept date/plant/department)
 *   viewer      – NonConformity::photoViewerData() of the finding being edited, else null
 */
let photoSlotId = 0;

export default ({ departments = {}, auditees = [], clauses = {}, categories = [], suggestUrl, values = {}, viewer = null, maxPhotos = 5 }) => ({
    departments,
    auditees,
    clauses,
    categories,
    plant: values.pabrik ?? Object.keys(departments)[0] ?? '',
    department: values.departemen ?? '',
    pic: values.pic ?? '',
    description: values.deskripsi_temuan ?? '',
    clause: values.no_klausul ?? '',
    category: values.kategori ?? '',
    suggestions: [],
    suggesting: false,
    suggestController: null,
    // photos already stored on the finding being edited; the viewer modal removes from this object
    viewer,
    maxPhotos,
    // one file input per photo: { id, url } with url set once a file is chosen
    photoSlots: { foto_temuan: [], foto_perbaikan: [] },
    submitting: false,
    initial: '',

    init() {
        // what the form started with (empty, kept values, or the finding being edited)
        this.initial = this.snapshot();
        if (this.description) this.loadSuggestions();
        this.ensurePhotoSlots();

        // a category that does not apply to the newly chosen clause is cleared
        this.$watch('clause', () => {
            if (this.category && !this.allowedCategories.includes(this.category)) this.category = '';
        });

        window.addEventListener('beforeunload', (event) => {
            if (this.dirty && !this.submitting) event.preventDefault();
        });
    },

    /** Something was changed since the page was opened. */
    get dirty() {
        return this.snapshot() !== this.initial || this.hasChosenPhotos;
    },

    get hasChosenPhotos() {
        return Object.values(this.photoSlots).some((slots) => slots.some((slot) => slot.url));
    },

    snapshot() {
        return JSON.stringify([this.plant, this.department, this.pic, this.description.trim(), this.clause.trim(), this.category]);
    },

    get departmentOptions() {
        return Object.entries(this.departments[this.plant] ?? {});
    },

    /** Auditees of the chosen plant and department (an auditee only sees findings of their departments). */
    get picOptions() {
        return this.auditees.filter((auditee) => auditee.plants.includes(this.plant)
            && (!this.department || auditee.departments.includes(this.department)));
    },

    get selectedClause() {
        return this.clauses[this.clause.trim()] ?? null;
    },

    /** Categories ticked for the chosen clause in the clause master; all of them until a clause is chosen. */
    get allowedCategories() {
        return this.selectedClause ? this.selectedClause.categories : this.categories;
    },

    changePlant() {
        this.department = '';
        this.pic = '';
    },

    changeDepartment() {
        if (!this.picOptions.some((auditee) => auditee.email === this.pic)) this.pic = '';
    },

    pickClause(number) {
        this.clause = number;
    },

    /** Related clauses for the description typed so far (ClauseSuggester). */
    async loadSuggestions() {
        this.suggestController?.abort();
        const q = this.description.trim();
        if (q.length < 3) {
            this.suggestions = [];
            return;
        }

        this.suggestController = new AbortController();
        this.suggesting = true;
        try {
            const { data } = await window.axios.get(suggestUrl, { params: { q }, signal: this.suggestController.signal });
            this.suggestions = data.suggestions;
        } catch (error) {
            if (!window.axios.isCancel(error)) this.suggestions = [];
        } finally {
            this.suggesting = false;
        }
    },

    /** Keep one empty input below the chosen photos, as long as the field has room for another. */
    ensurePhotoSlots() {
        Object.entries(this.photoSlots).forEach(([field, slots]) => {
            const stored = this.viewer?.photos[field].length ?? 0;
            const hasEmpty = slots.some((slot) => !slot.url);
            if (!hasEmpty && stored + slots.length < this.maxPhotos) slots.push({ id: ++photoSlotId, url: null });
        });
    },

    /** A file was picked in one input (or the picker was cancelled, which empties that input in some browsers). */
    choosePhoto(field, slot, event) {
        const file = event.target.files[0];
        if (!file) {
            this.removePhoto(field, slot);
            return;
        }
        if (slot.url) URL.revokeObjectURL(slot.url);
        slot.url = URL.createObjectURL(file);
        this.ensurePhotoSlots();
    },

    removePhoto(field, slot) {
        if (slot.url) URL.revokeObjectURL(slot.url);
        this.photoSlots[field] = this.photoSlots[field].filter((other) => other.id !== slot.id);
        this.ensurePhotoSlots();
    },
});
