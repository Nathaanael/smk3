{{--
    Photo cell as small square icon buttons: view (opens the photo in a new browser tab) and, when
    allowed, upload / replace (nonConformityList.uploadPhoto). Paths are relative to public/ (uploads/...).
--}}
@use('App\Support\Ui\ButtonVariants')
@props(['path' => null, 'findingId', 'field', 'canUpload' => false])
@php
    $key = $findingId.'.'.$field;
    $square = ButtonVariants::classes('outline', 'icon-xs');
    $uploadLabel = $path ? __('nc.replacePhoto') : __('nc.uploadPhoto');
@endphp
<div class="flex items-center gap-1.5">
    @if ($path)
        <a href="{{ asset($path) }}" target="_blank" rel="noopener" class="{{ $square }} text-brand-500 dark:text-brand-400"
            title="{{ __('nc.viewPhoto') }}" aria-label="{{ __('nc.viewPhoto') }} #{{ $findingId }}">
            <x-svg.eye class="size-4" />
        </a>
    @elseif (! $canUpload)
        <span class="text-gray-400 dark:text-gray-500" title="{{ __('nc.noPhoto') }}">—</span>
    @endif

    @if ($canUpload)
        <label class="{{ $square }} relative cursor-pointer has-focus-visible:ring-3 has-focus-visible:ring-brand-500/20" title="{{ $uploadLabel }}"
            x-bind:class="uploading['{{ $key }}'] && 'pointer-events-none opacity-60'">
            <input type="file" accept="image/*" class="sr-only" aria-label="{{ $uploadLabel }} #{{ $findingId }}"
                x-on:change="uploadPhoto({{ $findingId }}, '{{ $field }}', $event)">
            <x-svg.upload class="size-4" x-show="!uploading['{{ $key }}']" />
            <span x-show="uploading['{{ $key }}']" x-cloak class="size-3.5 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
        </label>
    @endif
</div>
