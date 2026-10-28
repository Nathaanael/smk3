@props(['width' => 150])

{{-- The logo's blue wordmark has low contrast on dark backgrounds, so it sits on a white chip in dark mode. --}}
<span {{ $attributes->merge(['class' => 'inline-flex dark:rounded-md dark:bg-white dark:px-2 dark:py-1']) }}>
    <img src="/assets/images/logo/indofood-cbp.webp" alt="Indofood CBP" width="{{ $width }}" height="{{ round($width * 108 / 640) }}" />
</span>
