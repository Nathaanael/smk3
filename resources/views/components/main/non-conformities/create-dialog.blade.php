{{-- "Add finding" button + dialog (legacy "Form Input Temuan"). Reopens with the old input when validation fails. --}}
@props(['plants' => [], 'departments' => [], 'categories' => [], 'auditees' => []])
@php
    $bag = $errors->createFinding;
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
@endphp
<div
    x-data="{
        departments: @js($departments),
        auditees: @js($auditees),
        plant: @js(old('pabrik', array_key_first($plants))),
        department: @js(old('departemen', '')),
        pic: @js(old('pic', '')),
        submitting: false,
        get departmentOptions() {
            return Object.entries(this.departments[this.plant] ?? {});
        },
        /** Auditees of the chosen plant and department (an auditee only sees findings of their departments). */
        get picOptions() {
            return this.auditees.filter((auditee) => auditee.plants.includes(this.plant)
                && (!this.department || auditee.departments.includes(this.department)));
        },
        changePlant() {
            this.department = '';
            this.pic = '';
        },
        changeDepartment() {
            if (!this.picOptions.some((auditee) => auditee.email === this.pic)) this.pic = '';
        },
    }"
    class="contents"
>
    <x-ui.dialog x-init="{{ $bag->any() ? '$nextTick(() => show())' : '' }}">
        <x-ui.button x-ref="trigger" x-on:click="show()">
            <x-svg.plus class="size-5" />
            {{ __('nc.create.button') }}
        </x-ui.button>

        <x-ui.dialog.content class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
            <x-ui.dialog.header>
                <x-ui.dialog.title>{{ __('nc.create.title') }}</x-ui.dialog.title>
                <x-ui.dialog.description>{{ __('nc.create.description') }}</x-ui.dialog.description>
            </x-ui.dialog.header>

            <form method="POST" action="{{ route('non-conformities.store') }}" enctype="multipart/form-data" x-on:submit="submitting = true"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf

                <div>
                    <label for="create-tanggal" class="{{ $labelClass }}">{{ __('nc.columns.date') }}</label>
                    <div class="relative">
                        <x-ui.input type="text" id="create-tanggal" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" class="cursor-pointer pe-10"
                            x-init="window.flatpickr($el, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y' })" :aria-invalid="$bag->has('tanggal') ? 'true' : null" />
                        <span class="pointer-events-none absolute end-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <x-svg.calendar class="size-5" />
                        </span>
                    </div>
                    @error('tanggal', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                @if (count($plants) > 1)
                    <div>
                        <label for="create-pabrik" class="{{ $labelClass }}">{{ __('nc.plant') }}</label>
                        <x-ui.native-select id="create-pabrik" name="pabrik" x-model="plant" x-on:change="changePlant()">
                            @foreach ($plants as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </x-ui.native-select>
                        @error('pabrik', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                    </div>
                @else
                    <input type="hidden" name="pabrik" x-bind:value="plant">
                @endif

                <div>
                    <label for="create-departemen" class="{{ $labelClass }}">{{ __('nc.department') }}</label>
                    {{-- autofocus here: focusing the date field first would pop its calendar open --}}
                    <x-ui.native-select id="create-departemen" name="departemen" x-model="department" x-on:change="changeDepartment()" required data-autofocus>
                        <option value="">{{ __('nc.create.chooseDepartment') }}</option>
                        <template x-for="[code, name] in departmentOptions" :key="code">
                            <option :value="code" x-text="name" :selected="code === department"></option>
                        </template>
                    </x-ui.native-select>
                    @error('departemen', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div>
                    <label for="create-kategori" class="{{ $labelClass }}">{{ __('nc.category') }}</label>
                    <x-ui.native-select id="create-kategori" name="kategori" required>
                        <option value="">{{ __('nc.create.chooseCategory') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('kategori') === $category)>{{ $category }}</option>
                        @endforeach
                    </x-ui.native-select>
                    @error('kategori', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                {{-- fills the row alone when the plant field takes a cell (keeps the grid without gaps) --}}
                <div @class(['sm:col-span-2' => count($plants) > 1])>
                    <label for="create-pic" class="{{ $labelClass }}">{{ __('nc.columns.pic') }}</label>
                    <x-ui.native-select id="create-pic" name="pic" x-model="pic" required>
                        <option value="" x-text="picOptions.length ? @js(__('nc.create.choosePic')) : @js(__('nc.create.noAuditee'))"></option>
                        <template x-for="auditee in picOptions" :key="auditee.email">
                            <option :value="auditee.email" x-text="auditee.name + ' — ' + auditee.email" :selected="auditee.email === pic"></option>
                        </template>
                    </x-ui.native-select>
                    @error('pic', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="create-deskripsi" class="{{ $labelClass }}">{{ __('nc.columns.finding') }}</label>
                    <x-ui.textarea id="create-deskripsi" name="deskripsi_temuan" rows="4" maxlength="2000" required
                        placeholder="{{ __('nc.create.findingPlaceholder') }}">{{ old('deskripsi_temuan') }}</x-ui.textarea>
                    @error('deskripsi_temuan', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="create-no-klausul" class="{{ $labelClass }}">{{ __('nc.columns.clause') }}</label>
                    <x-ui.input type="text" id="create-no-klausul" name="no_klausul" value="{{ old('no_klausul') }}" maxlength="50" required
                        placeholder="{{ __('nc.create.clausePlaceholder') }}" />
                    @error('no_klausul', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="create-foto" class="{{ $labelClass }}">
                        {{ __('nc.columns.findingPhoto') }}
                        <span class="font-normal text-gray-400 dark:text-gray-500">({{ __('nc.create.optional') }})</span>
                    </label>
                    <x-ui.file-input id="create-foto" name="foto_temuan" accept="image/*" />
                    <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('nc.create.photoHint') }}</p>
                    @error('foto_temuan', 'createFinding') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:col-span-2 sm:flex-row sm:justify-end">
                    <x-ui.dialog.close as-child>
                        <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                    </x-ui.dialog.close>
                    <x-ui.button type="submit" x-bind:disabled="submitting">{{ __('nc.create.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
