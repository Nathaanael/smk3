{{--
    Native <select> styled like <x-ui.input>, for plain form submits (GET filters etc.).
    Pass <option> elements in the slot; extra attributes go to the <select>.
--}}
@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        'h-11 w-full min-w-0 cursor-pointer appearance-none truncate rounded-[10px] border border-gray-300 bg-white py-2.5 ps-4 pe-10 text-sm font-normal text-gray-800 shadow-theme-xs outline-hidden transition-colors focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100/50 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-500/40 [&>option]:text-gray-700 dark:[&>option]:bg-gray-900 dark:[&>option]:text-gray-300',
        $attributes->get('class')
    );
@endphp
<div data-slot="native-select" class="relative">
    <select {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</select>
    <x-svg.chevron-down class="pointer-events-none absolute end-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
</div>
