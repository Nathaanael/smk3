{{--
    Loading toast for a file prepared in the background (resources/js/lib/download.js).
    show / title / cancel are Alpine expressions of the surrounding component.
--}}
@props(['show', 'title', 'cancel'])
<div x-show="{{ $show }}" x-cloak x-transition.opacity role="status" aria-live="polite"
    {{ $attributes->merge(['class' => 'fixed end-4 bottom-4 z-99999 w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg sm:end-6 sm:bottom-6 dark:border-gray-800 dark:bg-gray-900']) }}>
    <div class="flex items-start gap-3">
        <span class="mt-0.5 size-5 shrink-0 animate-spin rounded-full border-2 border-gray-200 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="{{ $title }}"></p>
            <p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('nc.export.preparingHint') }}</p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full w-1/3 animate-pulse rounded-full bg-brand-500 dark:bg-brand-400"></div>
            </div>
        </div>
        <x-ui.button variant="ghost" size="icon-xs" x-on:click="{{ $cancel }}" aria-label="{{ __('nc.export.cancel') }}" title="{{ __('nc.export.cancel') }}">
            <x-svg.x class="size-4" />
        </x-ui.button>
    </div>
</div>
