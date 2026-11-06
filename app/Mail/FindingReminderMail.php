<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * Reminder to one PIC: the written subject and message, with its {variables} filled in for this
 * PIC, and the list of their findings still waiting for a repair photo.
 */
class FindingReminderMail extends Mailable
{
    /**
     * @param  Collection<int, NonConformity>  $findings
     */
    public function __construct(
        public User $recipient,
        public Collection $findings,
        private string $subjectTemplate,
        private string $messageTemplate,
        private string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: strtr($this->subjectTemplate, $this->variables()));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finding-reminder',
            text: 'emails.finding-reminder-text',
            with: [
                'htmlBody' => $this->htmlBody(),
                'textBody' => strtr($this->messageTemplate, $this->variables()),
            ],
        );
    }

    /** The message is plain text: everything is escaped, only the line breaks and the link become HTML. */
    private function htmlBody(): string
    {
        $variables = array_map(e(...), $this->variables());
        $variables['{tautan}'] = '<a href="'.e($this->link).'">'.e($this->link).'</a>';

        return nl2br(strtr(e($this->messageTemplate), $variables), false);
    }

    /**
     * @return array<string, string>
     */
    private function variables(): array
    {
        return [
            '{nama}' => $this->recipient->name,
            '{departemen}' => $this->findings->map(fn (NonConformity $finding) => $finding->departmentName())->unique()->implode(', '),
            '{jumlah_temuan}' => (string) $this->findings->count(),
            '{tautan}' => $this->link,
        ];
    }
}
