{{-- Thumbnail linking to the full photo; paths are relative to public/ (legacy uploads/...). --}}
@props(['path' => null, 'alt' => ''])
@if ($path)
    <a href="{{ asset($path) }}" target="_blank" rel="noopener" title="{{ __('nc.viewPhoto') }}"
        class="block size-12 overflow-hidden rounded-lg border border-gray-200 transition-opacity hover:opacity-80 dark:border-gray-800">
        <img src="{{ asset($path) }}" alt="{{ $alt }}" loading="lazy" class="size-full object-cover" />
    </a>
@else
    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('nc.noPhoto') }}</span>
@endif
