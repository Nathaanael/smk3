{{--
    Several photos for one field of the add / edit finding form, with a preview of each chosen one
    (findingForm.choosePhotos). When editing, a button opens the photos already stored in the viewer modal.
--}}
@use('App\Services\FindingPhotos')
@props(['field', 'id', 'label', 'editing' => false])
@php
    $error = $errors->first($field.'.*') ?: $errors->first($field);
@endphp
<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        {{ $label }}
        <span class="font-normal text-gray-400 dark:text-gray-500">({{ __('nc.create.optional') }})</span>
    </label>
    <x-ui.file-input :id="$id" name="{{ $field }}[]" accept="image/*" multiple x-ref="{{ $field }}" x-on:change="choosePhotos('{{ $field }}', $event)"
        :aria-invalid="$error ? 'true' : null" />
    <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
        {{ __('nc.create.photoHint') }}
        {{ __('nc.create.photoManyHint', ['max' => FindingPhotos::MAX_PER_FIELD]) }}
        @if ($editing)
            {{ __('nc.edit.photosAdded') }}
        @endif
    </p>

    @if ($editing)
        <button type="button" class="mt-1.5 text-theme-xs font-medium text-brand-500 hover:underline dark:text-brand-400"
            x-show="viewer.photos.{{ $field }}.length" x-cloak
            x-on:click="$dispatch('finding-photos-open', { finding: viewer, field: '{{ $field }}' })"
            x-text="@js(__('nc.edit.viewStored')).replace(':count', viewer.photos.{{ $field }}.length)"></button>
    @endif

    @if ($error)
        <x-ui.field.error class="mt-1.5">{{ $error }}</x-ui.field.error>
    @endif
    <p x-show="photoNotice.{{ $field }}" x-cloak x-text="photoNotice.{{ $field }}" class="mt-1.5 text-theme-xs text-warning-600 dark:text-warning-400"></p>

    <div x-show="chosenPhotos.{{ $field }}.length" x-cloak class="mt-3 flex flex-wrap gap-3">
        <template x-for="(photo, position) in chosenPhotos.{{ $field }}" x-bind:key="photo.url">
            <div class="relative">
                <img x-bind:src="photo.url" x-bind:alt="photo.file.name" class="size-28 rounded-xl border border-gray-200 object-cover dark:border-gray-800">
                <x-ui.button type="button" variant="outline" size="icon-xs" class="absolute end-1.5 top-1.5 bg-white/90 dark:bg-gray-900/90"
                    x-on:click="removeChosenPhoto('{{ $field }}', position)" aria-label="{{ __('nc.create.removePhoto') }}" title="{{ __('nc.create.removePhoto') }}">
                    <x-svg.x class="size-4" />
                </x-ui.button>
            </div>
        </template>
    </div>
</div>
