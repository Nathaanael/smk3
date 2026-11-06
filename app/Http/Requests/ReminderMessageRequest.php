<?php

namespace App\Http\Requests;

use App\Mail\FindingReminderMail;
use Illuminate\Validation\Rule;

/**
 * The written reminder and who it goes to, plus the filters of the "needs reminder" list it goes out for.
 */
class ReminderMessageRequest extends FindingFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'recipients' => ['required', 'array', 'max:1000'],
            'recipients.*' => ['integer'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [Rule::in(FindingReminderMail::ATTACHMENTS)],
        ];
    }
}
