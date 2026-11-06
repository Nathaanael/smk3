<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\User;
use App\Services\FindingExporter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reminder to one PIC: the written subject and message, with its {variables} filled in for this
 * PIC, and the list of their findings still waiting for a repair photo.
 */
class FindingReminderMail extends Mailable
{
    public const ATTACHMENTS = ['pdf', 'xlsx'];

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @param  list<string>  $attachmentFormats  reports of these findings to attach; see ATTACHMENTS
     */
    public function __construct(
        public User $recipient,
        public Collection $findings,
        private string $subjectTemplate,
        private string $messageTemplate,
        private string $link,
        private array $attachmentFormats = [],
    ) {}

    /**
     * The reports only hold this recipient's findings. They are built when the email is sent, so
     * a preview stays quick; a report that cannot be built makes the email fail, like a mail error.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $format) => Attachment::fromData(fn () => $this->report($format), $this->attachmentName($format))
                ->withMime($format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            $this->attachmentFormats,
        );
    }

    /**
     * @return list<string>
     */
    public function attachmentNames(): array
    {
        return array_map($this->attachmentName(...), $this->attachmentFormats);
    }

    private function attachmentName(string $format): string
    {
        return 'laporan-smk3-'.now(config('smk3.report.timezone'))->format('Ymd').".{$format}";
    }

    private function report(string $format): string
    {
        $exporter = app(FindingExporter::class);
        $findings = EloquentCollection::make($this->findings)->loadMissing('photos');
        $columns = collect(FindingExporter::columns())->where('default', true)->pluck('key')->all();

        if ($format === 'pdf') {
            return $exporter->pdf($findings, $columns, __('nc.report.total'))->output();
        }

        $workbook = $exporter->excel($findings, $columns, __('nc.report.total'));
        ob_start();
        IOFactory::createWriter($workbook, 'Xlsx')->save('php://output');
        $workbook->disconnectWorksheets();

        return (string) ob_get_clean();
    }

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
