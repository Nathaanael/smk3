<?php

namespace App\Http\Requests;

use App\Mail\FindingReminderMail;
use App\Models\ReminderSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderScheduleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'frequency' => ['required', Rule::in(ReminderSchedule::FREQUENCIES)],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:1,7'],
            'monthday' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,28'],
            'time' => ['required', 'date_format:H:i'],
            'pabrik' => ['nullable', Rule::in(array_keys(config('smk3.plants')))],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [Rule::in(FindingReminderMail::ATTACHMENTS)],
            'active' => ['required', 'boolean'],
        ];
    }
}
