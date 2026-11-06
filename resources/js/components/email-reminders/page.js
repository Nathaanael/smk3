/**
 * Email reminder page: the open tab, the written message and who it goes to, shared by the
 * "needs reminder" and "write message" tabs, and the sending itself.
 *
 * The list of people follows the filters of the "needs reminder" tab. Everyone is picked until
 * they are unticked, and someone unticked stays that way while the filters change.
 *
 * Emails go out one request per person: the page stays usable, the loading bar shows real
 * progress, and a long list cannot run into the time limit of a single request. Leaving the page
 * stops the rest, so it asks first.
 *
 * Usage: x-data="emailReminders({ folder, people, picked, subject, message, sendUrl, previewUrl, labels })"
 *   people – [{ id, name, email, departments, findings }]
 *   picked – ids sent with a rejected message, to restore that choice; null when there is none
 *   labels – { sent, partial, stopped, interrupted, none, connection } messages about a finished send
 */
const warnBeforeLeaving = (event) => event.preventDefault();

export default ({ folder = 'pending', people = [], picked = null, subject = '', message = '', sendUrl, previewUrl, labels = {} }) => ({
    folder,
    people,
    excluded: picked ? people.filter((person) => !picked.map(Number).includes(person.id)).map((person) => person.id) : [],
    personSearch: '',
    subject,
    message,
    attachPdf: false,
    attachExcel: false,
    sending: false,
    stopRequested: false,
    progress: { total: 0, done: 0, sent: 0, failed: 0 },
    // { type: 'success' | 'error', message } of the last finished send
    result: null,

    get pickedIds() {
        return this.people.filter((person) => !this.excluded.includes(person.id)).map((person) => person.id);
    },

    get visiblePeople() {
        const search = this.personSearch.trim().toLowerCase();
        return this.people.filter((person) => !search || `${person.name} ${person.email}`.toLowerCase().includes(search));
    },

    get progressPercent() {
        return this.progress.total ? Math.round((this.progress.done / this.progress.total) * 100) : 0;
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

    /** A blank message opens its tab to show why it stopped. */
    messageIsValid() {
        const form = document.getElementById('reminder-form');
        if (form.checkValidity()) return true;

        this.folder = 'compose';
        this.$nextTick(() => form.reportValidity());
        return false;
    },

    /** The example of the first recipient, in a new tab. */
    openPreview() {
        if (!this.messageIsValid()) return;

        const form = document.getElementById('reminder-form');
        form.action = previewUrl + window.location.search;
        form.requestSubmit();
    },

    async sendEmails() {
        if (this.sending || !this.pickedIds.length || !this.messageIsValid()) return;

        // what was confirmed, even if the filters or the text change while this runs
        const ids = [...this.pickedIds];
        const url = sendUrl + window.location.search;
        const payload = { subject: this.subject, message: this.message, attachments: [this.attachPdf && 'pdf', this.attachExcel && 'xlsx'].filter(Boolean) };

        this.progress = { total: ids.length, done: 0, sent: 0, failed: 0 };
        this.result = null;
        this.stopRequested = false;
        this.sending = true;
        window.addEventListener('beforeunload', warnBeforeLeaving);

        let interruption = null;
        try {
            for (const id of ids) {
                if (this.stopRequested) break;

                const { data } = await window.axios.post(url, { ...payload, recipients: [id] });
                this.progress.sent += data.sent;
                this.progress.failed += data.failed;
                this.progress.done += 1;
            }
        } catch (error) {
            const errors = error.response?.data?.errors;
            interruption = (errors && Object.values(errors)[0]?.[0]) ?? error.response?.data?.message ?? labels.connection;
        } finally {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
            this.sending = false;
        }

        this.finishSending(interruption);
    },

    /** Report the outcome, open the tab that shows it and bring the history up to date. */
    finishSending(interruption) {
        const { sent, failed } = this.progress;
        const fill = (text) => text.replace(':sent', sent).replace(':failed', failed).replace(':error', interruption);

        if (interruption) {
            this.result = { type: 'error', message: fill(labels.interrupted) };
        } else if (this.stopRequested) {
            this.result = { type: 'error', message: fill(labels.stopped) };
        } else if (failed) {
            this.result = { type: 'error', message: fill(labels.partial) };
        } else if (sent) {
            this.result = { type: 'success', message: fill(labels.sent) };
        } else {
            this.result = { type: 'error', message: labels.none };
        }

        if (failed) {
            this.folder = 'failed';
        } else if (sent) {
            this.folder = 'sent';
        }

        window.dispatchEvent(new CustomEvent('reminder-history-refresh'));
    },
});
