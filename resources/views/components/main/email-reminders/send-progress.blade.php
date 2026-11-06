{{--
    Loading bar while reminder emails go out. State lives in the surrounding emailReminders Alpine
    component (resources/js/components/email-reminders/page.js).
--}}
<div x-show="sending" x-cloak x-transition.opacity role="status" aria-live="polite"
    class="fixed end-4 bottom-4 z-99999 w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg sm:end-6 sm:bottom-6 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-gray-800 dark:text-white/90"
            x-text="@js(__('reminder.progress.title')).replace(':done', progress.done).replace(':total', progress.total)"></p>
        <span class="text-sm font-medium text-brand-600 dark:text-brand-400" x-text="`${progressPercent}%`"></span>
    </div>

    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuemin="0" aria-valuemax="100"
        aria-label="{{ __('reminder.progress.label') }}" x-bind:aria-valuenow="progressPercent">
        {{-- the width follows the progress, which no utility class can express --}}
        <div class="h-full rounded-full bg-brand-500 transition-all duration-300 dark:bg-brand-400" x-bind:style="`width: ${progressPercent}%`"></div>
    </div>

    <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400"
        x-text="@js(__('reminder.progress.counts')).replace(':sent', progress.sent).replace(':failed', progress.failed)"></p>

    <div class="mt-3 flex items-center justify-between gap-3">
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('reminder.progress.hint') }}</p>
        <button type="button" x-on:click="stopRequested = true" x-bind:disabled="stopRequested"
            class="shrink-0 text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-error-500 dark:hover:text-error-400">
            <span x-show="!stopRequested">{{ __('reminder.progress.stop') }}</span>
            <span x-show="stopRequested" x-cloak>{{ __('reminder.progress.stopping') }}</span>
        </button>
    </div>
</div>
