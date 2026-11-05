{{--
    "Write message" tab of the email reminder page: the subject and message, and how it is sent
    (manually or on a schedule). Layout only for now: nothing is saved or sent yet, so the actions
    stay disabled and the values live in this component until the page is reloaded.
--}}
@use('Illuminate\Support\Carbon')
@php
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $checkboxClass = 'mt-0.5 size-4 shrink-0 cursor-pointer rounded border-gray-300 accent-brand-500 dark:border-gray-700';
    $attachments = [
        'attachPdf' => ['label' => __('reminder.compose.attachPdf'), 'description' => __('reminder.compose.attachPdfDesc')],
        'attachExcel' => ['label' => __('reminder.compose.attachExcel'), 'description' => __('reminder.compose.attachExcelDesc')],
    ];
    $modes = [
        'manual' => ['label' => __('reminder.compose.modeManual'), 'description' => __('reminder.compose.modeManualDesc')],
        'schedule' => ['label' => __('reminder.compose.modeSchedule'), 'description' => __('reminder.compose.modeScheduleDesc')],
    ];
    $variables = ['{nama}', '{departemen}', '{jumlah_temuan}', '{tautan}'];
    // Monday first; the day names follow the active language
    $weekdays = collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays($day - 1)->translatedFormat('l')]);
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-5 px-5 py-5 sm:px-6']) }}
    x-data="{
        mode: 'manual',
        frequency: 'weekly',
        attachPdf: false,
        attachExcel: false,
        subject: @js(__('reminder.compose.defaultSubject')),
        message: @js(__('reminder.compose.defaultMessage')),
    }">
    <fieldset>
        <legend class="{{ $labelClass }}">{{ __('reminder.compose.mode') }}</legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($modes as $value => $mode)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition-colors has-checked:border-brand-500 has-checked:bg-brand-50 has-focus-visible:ring-3 has-focus-visible:ring-brand-500/20 dark:border-gray-800 dark:has-checked:border-brand-500/50 dark:has-checked:bg-brand-500/10">
                    <input type="radio" name="reminder-mode" value="{{ $value }}" x-model="mode" class="mt-0.5 size-4 shrink-0 accent-brand-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $mode['label'] }}</span>
                        <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{{ $mode['description'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- only for the scheduler --}}
    <div x-show="mode === 'schedule'" x-cloak class="grid grid-cols-1 gap-4 rounded-xl bg-gray-50 p-4 sm:grid-cols-3 dark:bg-white/3">
        <div>
            <label for="reminder-frequency" class="{{ $labelClass }}">{{ __('reminder.compose.frequency') }}</label>
            <x-ui.native-select id="reminder-frequency" x-model="frequency">
                <option value="daily">{{ __('reminder.compose.daily') }}</option>
                <option value="weekly">{{ __('reminder.compose.weekly') }}</option>
                <option value="monthly">{{ __('reminder.compose.monthly') }}</option>
            </x-ui.native-select>
        </div>
        <div x-show="frequency === 'weekly'">
            <label for="reminder-weekday" class="{{ $labelClass }}">{{ __('reminder.compose.weekday') }}</label>
            <x-ui.native-select id="reminder-weekday">
                @foreach ($weekdays as $value => $name)
                    <option value="{{ $value }}">{{ $name }}</option>
                @endforeach
            </x-ui.native-select>
        </div>
        <div x-show="frequency === 'monthly'" x-cloak>
            <label for="reminder-monthday" class="{{ $labelClass }}">{{ __('reminder.compose.monthday') }}</label>
            {{-- up to 28: that date exists in every month --}}
            <x-ui.native-select id="reminder-monthday">
                @foreach (range(1, 28) as $day)
                    <option value="{{ $day }}">{{ $day }}</option>
                @endforeach
            </x-ui.native-select>
        </div>
        <div>
            <label for="reminder-time" class="{{ $labelClass }}">{{ __('reminder.compose.time') }}</label>
            <x-ui.input id="reminder-time" type="time" value="08:00" />
        </div>
    </div>

    <div>
        <label for="reminder-subject" class="{{ $labelClass }}">{{ __('reminder.compose.subject') }}</label>
        <x-ui.input id="reminder-subject" type="text" x-model="subject" />
    </div>

    <div>
        <label for="reminder-message" class="{{ $labelClass }}">{{ __('reminder.compose.message') }}</label>
        <x-ui.textarea id="reminder-message" rows="9" x-model="message" />
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('reminder.compose.variables') }}</span>
            @foreach ($variables as $variable)
                <button type="button" x-on:click="message += @js($variable); $nextTick(() => document.getElementById('reminder-message').focus())"
                    class="rounded-md bg-gray-100 px-2 py-1 font-mono text-theme-xs text-gray-700 transition-colors hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10">{{ $variable }}</button>
            @endforeach
        </div>
    </div>

    <fieldset>
        <legend class="{{ $labelClass }}">{{ __('reminder.compose.attachments') }}</legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($attachments as $model => $attachment)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition-colors has-checked:border-brand-500 has-checked:bg-brand-50 has-focus-visible:ring-3 has-focus-visible:ring-brand-500/20 dark:border-gray-800 dark:has-checked:border-brand-500/50 dark:has-checked:bg-brand-500/10">
                    <input type="checkbox" x-model="{{ $model }}" class="{{ $checkboxClass }}">
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $attachment['label'] }}</span>
                        <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{{ $attachment['description'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('reminder.compose.attachmentsHint') }}</p>
    </fieldset>

    <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('reminder.compose.notReady') }}</p>
        <x-ui.button class="shrink-0" disabled x-show="mode === 'manual'">
            <x-svg.send class="size-5" />
            {{ __('reminder.compose.sendNow') }}
        </x-ui.button>
        <x-ui.button class="shrink-0" disabled x-show="mode === 'schedule'" x-cloak>
            <x-svg.check-circle class="size-5" />
            {{ __('reminder.compose.saveSchedule') }}
        </x-ui.button>
    </div>
</div>
