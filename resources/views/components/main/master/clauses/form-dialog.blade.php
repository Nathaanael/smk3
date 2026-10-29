{{--
    Add / edit criterion dialog, opened with the `clause-form` window event ({ row } or { row: null }).
    Reopens with the old input when validation fails.
--}}
@use('App\Models\Smk3Criterion')
@php
    $bag = $errors->clause;
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $old = $bag->any() ? [
        'id' => old('_id') ? (int) old('_id') : null,
        'no' => old('no_kriteria', ''),
        'text' => old('kriteria', ''),
        'keywords' => old('kata_kunci', ''),
        'minor' => (bool) old('minor'),
        'major' => (bool) old('major'),
        'kritis' => (bool) old('kritis'),
    ] : null;
@endphp
<div
    x-data="{
        storeUrl: @js(route('master.clauses.store')),
        updateUrl: @js(route('master.clauses.update', ':id')),
        form: { id: null, no: '', text: '', keywords: '', minor: true, major: false, kritis: false },
        submitting: false,
        fill(row) {
            this.form = row
                ? { id: row.id, no: row.no, text: row.text, keywords: row.keywords ?? '', minor: row.minor, major: row.major, kritis: row.kritis }
                : { id: null, no: '', text: '', keywords: '', minor: true, major: false, kritis: false };
            this.submitting = false;
        },
        get action() {
            return this.form.id ? this.updateUrl.replace(':id', this.form.id) : this.storeUrl;
        },
    }"
    class="contents"
>
    <x-ui.dialog
        x-on:clause-form.window="fill($event.detail.row); show()"
        x-init="{{ $old ? 'fill('.\Illuminate\Support\Js::from($old).'); $nextTick(() => show())' : '' }}">
        <x-ui.dialog.content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <x-ui.dialog.header>
                <x-ui.dialog.title x-text="form.id ? @js(__('clause.form.editTitle')) : @js(__('clause.form.createTitle'))">{{ __('clause.form.createTitle') }}</x-ui.dialog.title>
                <x-ui.dialog.description>{{ __('clause.form.description') }}</x-ui.dialog.description>
            </x-ui.dialog.header>

            <form method="POST" x-bind:action="action" x-on:submit="submitting = true" class="flex flex-col gap-4">
                @csrf
                <template x-if="form.id">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="_id" x-bind:value="form.id ?? ''">

                <div>
                    <label for="clause-no" class="{{ $labelClass }}">{{ __('clause.columns.number') }}</label>
                    <x-ui.input id="clause-no" name="no_kriteria" x-model="form.no" required maxlength="20" placeholder="{{ __('clause.form.numberPlaceholder') }}"
                        :aria-invalid="$bag->has('no_kriteria') ? 'true' : null" />
                    @error('no_kriteria', 'clause') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                    <template x-if="form.id">
                        <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('clause.form.renumberHint') }}</p>
                    </template>
                </div>

                <div>
                    <label for="clause-text" class="{{ $labelClass }}">{{ __('clause.columns.criterion') }}</label>
                    <x-ui.textarea id="clause-text" name="kriteria" x-model="form.text" rows="4" required maxlength="2000"
                        :aria-invalid="$bag->has('kriteria') ? 'true' : null" />
                    @error('kriteria', 'clause') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div>
                    <label for="clause-keywords" class="{{ $labelClass }}">
                        {{ __('clause.form.keywords') }}
                        <span class="font-normal text-gray-400 dark:text-gray-500">({{ __('nc.create.optional') }})</span>
                    </label>
                    <x-ui.input id="clause-keywords" name="kata_kunci" x-model="form.keywords" maxlength="1000"
                        placeholder="{{ __('clause.form.keywordsPlaceholder') }}" :aria-invalid="$bag->has('kata_kunci') ? 'true' : null" />
                    <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('clause.form.keywordsHint') }}</p>
                    @error('kata_kunci', 'clause') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <fieldset>
                    <legend class="{{ $labelClass }}">{{ __('clause.form.categories') }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach (Smk3Criterion::CATEGORIES as $column => $label)
                            <input type="hidden" name="{{ $column }}" x-bind:value="form.{{ $column }} ? 1 : 0">
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 has-data-checked:border-brand-300 has-data-checked:bg-brand-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/3 dark:has-data-checked:border-brand-500/40 dark:has-data-checked:bg-brand-500/10">
                                <x-ui.checkbox model="form.{{ $column }}" x-on:change="form.{{ $column }} = $event.detail.checked" />
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('clause.form.categoriesHint') }}</p>
                </fieldset>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <x-ui.dialog.close as-child>
                        <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                    </x-ui.dialog.close>
                    <x-ui.button type="submit" x-bind:disabled="submitting">{{ __('nc.create.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
