{{--
    Results of a "Sent" or "Failed" tab of the email reminder page: the attempts to send a reminder,
    newest first. The failed tab also shows the reason of the failure.
--}}
@use('App\Http\Requests\FindingFilterRequest')
@use('App\Models\ReminderEmail')
@props(['emails', 'status', 'filtered' => false])
@php
    $showError = $status === ReminderEmail::FAILED;
    $empty = match (true) {
        $filtered => __('reminder.history.noMatch'),
        $showError => __('reminder.history.emptyFailed'),
        default => __('reminder.history.emptySent'),
    };
    $headClass = 'px-4 py-3 text-start text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes }}>
    @if ($emails->total() === 0)
        <div class="flex flex-col items-center gap-3 px-5 py-16 text-center sm:px-6">
            <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <x-svg.envelope class="size-6" />
            </span>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
        </div>
    @else
        <div class="table-scrollbar mt-5 max-h-[70vh] max-w-full overflow-auto">
            <table class="min-w-full">
                <thead class="sticky top-0 z-1 border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $headClass }}">{{ __('reminder.history.time') }}</th>
                        <th class="{{ $headClass }}">{{ __('reminder.history.recipient') }}</th>
                        <th class="{{ $headClass }} min-w-56">{{ __('reminder.compose.subject') }}</th>
                        <th class="{{ $headClass }}">{{ __('reminder.history.findings') }}</th>
                        <th class="{{ $headClass }}">{{ __('reminder.history.sentBy') }}</th>
                        @if ($showError)
                            <th class="{{ $headClass }} min-w-72">{{ __('reminder.history.error') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($emails as $email)
                        <tr>
                            <td class="{{ $cellClass }} whitespace-nowrap">{{ $email->created_at->timezone(ReminderEmail::TIMEZONE)->translatedFormat('d M Y H:i') }} WIB</td>
                            <td class="{{ $cellClass }} whitespace-nowrap">
                                <p class="text-gray-800 dark:text-white/90">{{ $email->recipient_name }}</p>
                                <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $email->recipient_email }}</p>
                                @if ($email->redirected_to)
                                    <p class="mt-1 text-theme-xs text-warning-600 dark:text-warning-500">{{ __('reminder.history.redirected', ['address' => $email->redirected_to]) }}</p>
                                @endif
                            </td>
                            <td class="{{ $cellClass }}">{{ $email->subject }}</td>
                            <td class="{{ $cellClass }} whitespace-nowrap">{{ $email->findings_count }}</td>
                            <td class="{{ $cellClass }} whitespace-nowrap">{{ $email->sender?->name ?? '—' }}</td>
                            @if ($showError)
                                <td class="{{ $cellClass }}">
                                    <p class="line-clamp-3 text-theme-xs break-words text-error-600 dark:text-error-500" title="{{ $email->error }}">{{ $email->error }}</p>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800">
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <span>
                    {{ __('reminder.history.showing', ['from' => $emails->firstItem() ?? 0, 'to' => $emails->lastItem() ?? 0, 'total' => $emails->total()]) }}
                </span>

                <div class="flex items-center gap-2">
                    <label for="history-{{ $status }}-per-page">{{ __('nc.perPage') }}</label>
                    <x-ui.native-select id="history-{{ $status }}-per-page" name="per_page" class="h-9 w-20 ps-3 pe-8">
                        @foreach (FindingFilterRequest::PER_PAGE_OPTIONS as $option)
                            <option value="{{ $option }}" @selected($emails->perPage() === $option)>{{ $option }}</option>
                        @endforeach
                    </x-ui.native-select>
                </div>
            </div>
            <x-common.pagination :paginator="$emails" />
        </div>
    @endif
</div>
