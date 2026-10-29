{{-- "Upload Template" button + dialog for the Excel import. --}}
@php
    $bag = $errors->import;
@endphp
<div x-data="{ submitting: false, fileName: '' }" class="contents">
    <x-ui.dialog x-init="{{ $bag->any() ? '$nextTick(() => show())' : '' }}">
        <x-ui.button variant="outline" x-ref="trigger" x-on:click="submitting = false; show()">
            <x-svg.upload class="size-5" />
            {{ __('clause.upload') }}
        </x-ui.button>

        <x-ui.dialog.content class="sm:max-w-lg">
            <x-ui.dialog.header>
                <x-ui.dialog.title>{{ __('clause.uploadDialog.title') }}</x-ui.dialog.title>
                <x-ui.dialog.description>{{ __('clause.uploadDialog.description') }}</x-ui.dialog.description>
            </x-ui.dialog.header>

            <ol class="list-decimal space-y-1.5 ps-5 text-sm text-gray-600 dark:text-gray-400">
                <li>
                    {{ __('clause.uploadDialog.step1') }}
                    <a href="{{ route('master.clauses.template') }}" class="font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400">{{ __('clause.download') }}</a>
                </li>
                <li>{{ __('clause.uploadDialog.step2') }}</li>
                <li>{{ __('clause.uploadDialog.step3') }}</li>
            </ol>

            <form method="POST" action="{{ route('master.clauses.import') }}" enctype="multipart/form-data" x-on:submit="submitting = true" class="flex flex-col gap-4">
                @csrf
                <div>
                    <x-ui.file-input name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                        x-on:change="fileName = $event.target.files[0]?.name ?? ''" :aria-invalid="$bag->has('file') ? 'true' : null" />
                    <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('clause.uploadDialog.hint') }}</p>
                    @error('file', 'import') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-ui.dialog.close as-child>
                        <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                    </x-ui.dialog.close>
                    <x-ui.button type="submit" x-bind:disabled="submitting || !fileName">
                        <span x-show="!submitting">{{ __('clause.uploadDialog.submit') }}</span>
                        <span x-show="submitting" x-cloak>{{ __('clause.uploadDialog.processing') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
