/**
 * Email reminder page: the open tab, the written message and who it goes to, shared by the
 * "needs reminder" and "write message" tabs.
 *
 * The list of people follows the filters of the "needs reminder" tab. Everyone is picked until
 * they are unticked, and someone unticked stays that way while the filters change.
 *
 * Usage: x-data="emailReminders({ folder, people, picked, subject, message, sendUrl, previewUrl })"
 *   people – [{ id, name, email, departments, findings }]
 *   picked – ids sent with a rejected message, to restore that choice; null when there is none
 */
export default ({ folder = 'pending', people = [], picked = null, subject = '', message = '', sendUrl, previewUrl }) => ({
    folder,
    people,
    excluded: picked ? people.filter((person) => !picked.map(Number).includes(person.id)).map((person) => person.id) : [],
    personSearch: '',
    subject,
    message,
    sendUrl,
    previewUrl,
    sending: false,

    get pickedIds() {
        return this.people.filter((person) => !this.excluded.includes(person.id)).map((person) => person.id);
    },

    get visiblePeople() {
        const search = this.personSearch.trim().toLowerCase();
        return this.people.filter((person) => !search || `${person.name} ${person.email}`.toLowerCase().includes(search));
    },

    isPicked(id) {
        return !this.excluded.includes(id);
    },

    togglePerson(id, checked) {
        this.excluded = checked ? this.excluded.filter((value) => value !== id) : [...this.excluded, id];
    },

    /** Tick or untick everyone the search currently shows. */
    pickVisible(checked) {
        const ids = this.visiblePeople.map((person) => person.id);
        this.excluded = checked ? this.excluded.filter((id) => !ids.includes(id)) : [...new Set([...this.excluded, ...ids])];
    },

    /** Post the form to url, keeping the page's filters. A blank message opens its tab to show why it stopped. */
    submitTo(url, target) {
        const form = document.getElementById('reminder-form');
        if (!form.checkValidity()) {
            this.folder = 'compose';
            this.$nextTick(() => form.reportValidity());
            return;
        }

        form.action = url + window.location.search;
        form.target = target;
        this.sending = target === '_self';
        form.requestSubmit();
    },
});
