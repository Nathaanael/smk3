/**
 * "Add finding" page: plant → department → PIC options, clause suggestions while the
 * description is typed, categories limited to those ticked for the chosen clause,
 * photo preview, and a warning before leaving with unsaved input.
 *
 * Usage: x-data="findingForm({ departments, auditees, clauses, categories, suggestUrl, values })"
 *   departments – { plant: { code: name } }
 *   auditees    – [{ name, email, plants: [], departments: [] }]
 *   clauses     – { "6.7.7": { text, categories: ['Minor', ...] } }
 *   values      – initial values (old input after a failed save, or the kept date/plant/department)
 */
export default ({ departments = {}, auditees = [], clauses = {}, categories = [], suggestUrl, values = {} }) => ({
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
    photoUrl: null,
    photoName: '',
    submitting: false,
    initial: '',

    init() {
        // what the form started with (empty, kept values, or the finding being edited)
        this.initial = this.snapshot();
        if (this.description) this.loadSuggestions();

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
        return this.snapshot() !== this.initial || Boolean(this.photoName);
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

    choosePhoto(event) {
        const file = event.target.files?.[0];
        if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
        this.photoUrl = file ? URL.createObjectURL(file) : null;
        this.photoName = file?.name ?? '';
    },

    clearPhoto() {
        if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
        this.photoUrl = null;
        this.photoName = '';
        this.$refs.photo.value = '';
    },
});
