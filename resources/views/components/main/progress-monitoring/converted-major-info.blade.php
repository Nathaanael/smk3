{{--
    The "i" next to the +n of the Major column: what the number means and which clauses it comes from.
    The dropdown content is teleported to <body>, so the scrolling table does not clip it.
--}}
@use('App\Services\ProgressMonitor')
@props(['clauses' => []])
@php
    $min = ProgressMonitor::MINOR_TO_MAJOR;
@endphp
<x-ui.dropdown-menu>
    <x-ui.dropdown-menu.trigger aria-label="{{ __('pm.convertedMajorHelp') }}" title="{{ __('pm.convertedMajorHelp') }}"
        class="inline-flex rounded-full p-0.5 text-gray-400 outline-none transition-colors hover:text-brand-500 focus-visible:text-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500/30 dark:text-gray-500 dark:hover:text-brand-400 dark:focus-visible:text-brand-400">
        <x-svg.info-circle class="size-4" />
    </x-ui.dropdown-menu.trigger>
    <x-ui.dropdown-menu.content align="center" class="w-80 max-w-[calc(100vw-2rem)] gap-2 p-4 text-start">
        <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('pm.convertedMajor', ['count' => count($clauses), 'min' => $min]) }}</p>
        <p class="text-theme-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ __('pm.convertedMajorInfo', ['min' => $min]) }}</p>
        <p class="mt-1 text-theme-xs font-medium text-gray-700 dark:text-gray-300">{{ __('pm.convertedMajorClauses') }}</p>
        <ul class="custom-scrollbar flex max-h-40 flex-col gap-1 overflow-y-auto pe-1 text-theme-xs text-gray-600 dark:text-gray-400">
            @foreach ($clauses as $clause)
                <li class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/3">
                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $clause['clause'] }}</span>
                    <span>{{ __('pm.convertedMajorTimes', ['count' => $clause['count']]) }}</span>
                </li>
            @endforeach
        </ul>
    </x-ui.dropdown-menu.content>
</x-ui.dropdown-menu>
