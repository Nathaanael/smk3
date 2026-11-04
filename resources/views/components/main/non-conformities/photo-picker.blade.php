{{--
    Photos for one field of the add / edit finding form: one file input per photo, so every file name
    stays readable (findingForm.photoSlots). An input only gets its name once it holds a file, because
    an empty one would be submitted too and fail the image rule.
    When editing, a button opens the photos already stored in the viewer modal.
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

    <div class="flex flex-col gap-2">
        <template x-for="(slot, position) in photoSlots.{{ $field }}" x-bind:key="slot.id">
            <div class="flex items-center gap-2">
                <img x-show="slot.url" x-bind:src="slot.url" alt="" class="size-11 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-gray-800">
                <x-ui.file-input accept="image/*" class="flex-1"
                    x-bind:id="position === 0 ? '{{ $id }}' : null"
                    x-bind:name="slot.url ? '{{ $field }}[]' : null"
                    x-bind:aria-label="`{{ $label }} ${position + 1}`"
                    x-on:change="choosePhoto('{{ $field }}', slot, $event)"
                    :aria-invalid="$error ? 'true' : null" />
                <x-ui.button type="button" variant="outline" size="icon-xs" class="shrink-0" x-show="slot.url"
                    x-on:click="removePhoto('{{ $field }}', slot)" aria-label="{{ __('nc.create.removePhoto') }}" title="{{ __('nc.create.removePhoto') }}">
                    <x-svg.x class="size-4" />
                </x-ui.button>
            </div>
        </template>
    </div>

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
</div>
