{{--
    Photo cell as small square icon buttons: view (opens the photo viewer modal on the row's
    data-photo-viewer) with the number of photos and, when allowed, add photos (nonConformityList.uploadPhotos).
--}}
@use('App\Services\FindingPhotos')
@use('App\Support\Ui\ButtonVariants')
@props(['count' => 0, 'findingId', 'field', 'canUpload' => false])
@php
    $key = $findingId.'.'.$field;
    $square = ButtonVariants::classes('outline', 'icon-xs');
    $canAdd = $canUpload && $count < FindingPhotos::MAX_PER_FIELD;
@endphp
<div class="flex items-center gap-1.5">
    @if ($count)
        <button type="button" class="{{ $square }} relative text-brand-500 dark:text-brand-400"
            title="{{ __('nc.viewPhoto') }}" aria-label="{{ __('nc.viewPhoto') }} #{{ $findingId }} ({{ $count }})"
            x-on:click="$dispatch('finding-photos-open', { finding: JSON.parse($el.closest('[data-photo-viewer]').dataset.photoViewer), field: '{{ $field }}' })">
            <x-svg.eye class="size-4" />
            @if ($count > 1)
                <span class="absolute -end-1.5 -top-1.5 flex size-4 items-center justify-center rounded-full bg-brand-500 text-[10px] leading-none font-semibold text-white">{{ $count }}</span>
            @endif
        </button>
    @elseif (! $canUpload)
        <span class="text-gray-400 dark:text-gray-500" title="{{ __('nc.noPhoto') }}">—</span>
    @endif

    @if ($canAdd)
        <label class="{{ $square }} relative cursor-pointer has-focus-visible:ring-3 has-focus-visible:ring-brand-500/20" title="{{ __('nc.addPhotos') }}"
            x-bind:class="uploading['{{ $key }}'] && 'pointer-events-none opacity-60'">
            <input type="file" accept="image/*" multiple class="sr-only" aria-label="{{ __('nc.addPhotos') }} #{{ $findingId }}"
                x-on:change="uploadPhotos({{ $findingId }}, '{{ $field }}', $event)">
            <x-svg.upload class="size-4" x-show="!uploading['{{ $key }}']" />
            <span x-show="uploading['{{ $key }}']" x-cloak class="size-3.5 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
        </label>
    @endif
</div>
