@extends('layouts.app')

@section('content')
    @php
        // add and edit share this page; $finding is set when editing
        // values: old input after a failed save, else the finding being edited, else what "Save & add another" kept
        $values = match (true) {
            session()->hasOldInput() => session()->getOldInput(),
            $finding !== null => ['tanggal' => $finding->tanggal->toDateString(), 'status' => (int) $finding->status]
                + $finding->only(['pabrik', 'departemen', 'deskripsi_temuan', 'no_klausul', 'kategori', 'pic']),
            default => $keep,
        };
        $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3';
        $stepClass = 'flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300';
        $headingClass = 'text-base font-semibold text-gray-800 dark:text-white/90';
    @endphp

    <form method="POST" action="{{ $finding ? route('non-conformities.update', $finding) : route('non-conformities.store') }}" enctype="multipart/form-data"
        x-data="findingForm({
            departments: @js($departments),
            auditees: @js($auditees),
            clauses: @js($clauses),
            categories: @js($categories),
            suggestUrl: @js(route('non-conformities.clause-suggestions')),
            values: @js((object) $values),
        })"
        x-on:submit="submitting ? $event.preventDefault() : (submitting = true)">
        @csrf
        @if ($finding)
            @method('PUT')
        @endif

        <a href="{{ route('non-conformities') }}" class="mb-4 inline-flex w-fit items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
            <x-svg.chevron-left class="size-4 rtl:rotate-180" />
            {{ __('smk3.nonConformities') }}
        </a>

        @if (session('success'))
            <x-ui.alert variant="success" class="mb-5 px-4 py-3">
                <x-svg.check-circle />
                <x-ui.alert.title class="text-sm">{{ session('success') }} {{ __('nc.create.nextOne') }}</x-ui.alert.title>
            </x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="destructive" class="mb-5 px-4 py-3">
                <x-svg.alert />
                <x-ui.alert.title class="text-sm">{{ __('nc.create.fixErrors', ['count' => $errors->count()]) }}</x-ui.alert.title>
                @if (old('_had_photo'))
                    <x-ui.alert.description>{{ __('nc.create.photoAgain') }}</x-ui.alert.description>
                @endif
            </x-ui.alert>
        @endif

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="flex flex-col gap-5 lg:col-span-2">
                {{-- 1. what was found --}}
                <section class="{{ $cardClass }}" aria-labelledby="step-finding">
                    {{-- page title sits in the first card, split from the section by a divider --}}
                    <div class="mb-5 border-b border-gray-200 pb-5 dark:border-gray-800">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $finding ? __('nc.edit.description') : __('nc.create.description') }}</p>
                    </div>

                    <div class="mb-5 flex items-center gap-3">
                        <span class="{{ $stepClass }}">1</span>
                        <h3 id="step-finding" class="{{ $headingClass }}">{{ __('nc.create.stepFinding') }}</h3>
                    </div>

                    <div class="flex flex-col gap-5">
                        <div>
                            <label for="create-deskripsi" class="{{ $labelClass }}">{{ __('nc.columns.finding') }}</label>
                            <x-ui.textarea id="create-deskripsi" name="deskripsi_temuan" rows="4" maxlength="2000" required autofocus
                                x-model="description" x-on:input.debounce.400ms="loadSuggestions()"
                                :aria-invalid="$errors->has('deskripsi_temuan') ? 'true' : null"
                                placeholder="{{ __('nc.create.findingPlaceholder') }}">{{ $values['deskripsi_temuan'] ?? '' }}</x-ui.textarea>
                            @error('deskripsi_temuan') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                        </div>

                        <div>
                            <label for="create-foto" class="{{ $labelClass }}">
                                {{ __('nc.columns.findingPhoto') }}
                                <span class="font-normal text-gray-400 dark:text-gray-500">({{ __('nc.create.optional') }})</span>
                            </label>
                            <input type="hidden" name="_had_photo" x-bind:value="photoName ? 1 : ''">
                            <x-ui.file-input id="create-foto" name="foto_temuan" accept="image/*" x-ref="photo" x-on:change="choosePhoto($event)"
                                :aria-invalid="$errors->has('foto_temuan') ? 'true' : null" />
                            <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                {{ __('nc.create.photoHint') }}
                                @if ($finding?->foto_temuan)
                                    {{ __('nc.edit.photoReplaces') }}
                                    <a href="{{ asset($finding->foto_temuan) }}" target="_blank" rel="noopener" class="font-medium text-brand-500 hover:underline dark:text-brand-400">{{ __('nc.edit.currentPhoto') }}</a>
                                @endif
                            </p>
                            @error('foto_temuan') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror

                            <div x-show="photoUrl" x-cloak class="relative mt-3 w-fit">
                                <img x-bind:src="photoUrl" x-bind:alt="photoName" class="max-h-64 rounded-xl border border-gray-200 object-contain dark:border-gray-800">
                                <x-ui.button type="button" variant="outline" size="icon-sm" class="absolute end-2 top-2 bg-white/90 dark:bg-gray-900/90"
                                    x-on:click="clearPhoto()" aria-label="{{ __('nc.create.removePhoto') }}" title="{{ __('nc.create.removePhoto') }}">
                                    <x-svg.x class="size-4" />
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 2. clause and category --}}
                <section class="{{ $cardClass }}" aria-labelledby="step-classification">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="{{ $stepClass }}">2</span>
                        <h3 id="step-classification" class="{{ $headingClass }}">{{ __('nc.create.stepClassification') }}</h3>
                    </div>

                    <div class="flex flex-col gap-5">
                        <div>
                            <label for="create-no-klausul" class="{{ $labelClass }}">{{ __('nc.columns.clause') }}</label>
                            <x-ui.input type="text" id="create-no-klausul" name="no_klausul" maxlength="50" required autocomplete="off"
                                list="clause-numbers" x-model="clause" placeholder="{{ __('nc.create.clausePlaceholder') }}"
                                :aria-invalid="$errors->has('no_klausul') ? 'true' : null" />
                            <datalist id="clause-numbers">
                                @foreach ($clauses as $number => $clause)
                                    <option value="{{ $number }}">{{ \Illuminate\Support\Str::limit($clause['text'], 80) }}</option>
                                @endforeach
                            </datalist>
                            @error('no_klausul') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                            <p x-show="clause.trim() && !selectedClause" x-cloak class="mt-1.5 text-theme-xs text-warning-600 dark:text-warning-400">{{ __('nc.create.unknownClause') }}</p>

                            {{-- phones: chosen clause and suggestions right below the field (the side panel holds them on large screens) --}}
                            <x-main.non-conformities.chosen-clause class="mt-4 lg:hidden" />
                            <x-main.non-conformities.clause-suggestions class="mt-4 lg:hidden" />
                        </div>

                        <fieldset>
                            <legend class="{{ $labelClass }}">{{ __('nc.category') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($categories as $category)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 has-checked:border-brand-300 has-checked:bg-brand-50 has-checked:text-brand-600 has-disabled:cursor-not-allowed has-disabled:opacity-40 has-disabled:hover:bg-transparent dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/3 dark:has-checked:border-brand-500/40 dark:has-checked:bg-brand-500/10 dark:has-checked:text-brand-400">
                                        <input type="radio" name="kategori" value="{{ $category }}" x-model="category" required
                                            x-bind:disabled="!allowedCategories.includes(@js($category))" class="size-4 accent-brand-500">
                                        {{ $category }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                                x-text="selectedClause ? @js(__('nc.create.categoriesForClause')).replace(':clause', clause.trim()) : @js(__('nc.create.categoriesHint'))">
                                {{ __('nc.create.categoriesHint') }}
                            </p>
                            @error('kategori') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                        </fieldset>
                    </div>
                </section>

                {{-- 3. where, when and who follows up --}}
                <section class="{{ $cardClass }}" aria-labelledby="step-location">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="{{ $stepClass }}">3</span>
                        <h3 id="step-location" class="{{ $headingClass }}">{{ __('nc.create.stepLocation') }}</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="create-tanggal" class="{{ $labelClass }}">{{ __('nc.columns.date') }}</label>
                            <div class="relative">
                                <x-ui.input type="text" id="create-tanggal" name="tanggal" value="{{ $values['tanggal'] ?? now()->toDateString() }}" class="cursor-pointer pe-10"
                                    x-init="window.flatpickr($el, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y', maxDate: 'today' })"
                                    :aria-invalid="$errors->has('tanggal') ? 'true' : null" />
                                <span class="pointer-events-none absolute end-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <x-svg.calendar class="size-5" />
                                </span>
                            </div>
                            @error('tanggal') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                        </div>

                        @if (count($plants) > 1)
                            <div>
                                <label for="create-pabrik" class="{{ $labelClass }}">{{ __('nc.plant') }}</label>
                                <x-ui.native-select id="create-pabrik" name="pabrik" x-model="plant" x-on:change="changePlant()">
                                    @foreach ($plants as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </x-ui.native-select>
                                @error('pabrik') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                            </div>
                        @else
                            <input type="hidden" name="pabrik" x-bind:value="plant">
                        @endif

                        <div>
                            <label for="create-departemen" class="{{ $labelClass }}">{{ __('nc.department') }}</label>
                            <x-ui.native-select id="create-departemen" name="departemen" x-model="department" x-on:change="changeDepartment()" required
                                :aria-invalid="$errors->has('departemen') ? 'true' : null">
                                <option value="">{{ __('nc.create.chooseDepartment') }}</option>
                                <template x-for="[code, name] in departmentOptions" :key="code">
                                    <option :value="code" x-text="name" :selected="code === department"></option>
                                </template>
                            </x-ui.native-select>
                            @error('departemen') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                        </div>

                        <div>
                            <label for="create-pic" class="{{ $labelClass }}">{{ __('nc.columns.pic') }}</label>
                            <x-ui.native-select id="create-pic" name="pic" x-model="pic" required
                                :aria-invalid="$errors->has('pic') ? 'true' : null">
                                <option value="" x-text="picOptions.length ? @js(__('nc.create.choosePic')) : @js(__('nc.create.noAuditee'))"></option>
                                <template x-for="auditee in picOptions" :key="auditee.email">
                                    <option :value="auditee.email" x-text="auditee.name" :selected="auditee.email === pic"></option>
                                </template>
                            </x-ui.native-select>
                            @error('pic') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                        </div>
                    </div>
                </section>

                @if ($finding)
                    {{-- 4. follow-up (edit only) --}}
                    <section class="{{ $cardClass }}" aria-labelledby="step-follow-up">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="{{ $stepClass }}">4</span>
                            <h3 id="step-follow-up" class="{{ $headingClass }}">{{ __('nc.edit.stepFollowUp') }}</h3>
                        </div>

                        <div class="flex flex-col gap-5">
                            <fieldset>
                                <legend class="{{ $labelClass }}">{{ __('nc.columns.status') }}</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['0' => __('nc.open'), '1' => __('nc.closed')] as $value => $label)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 has-checked:border-brand-300 has-checked:bg-brand-50 has-checked:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/3 dark:has-checked:border-brand-500/40 dark:has-checked:bg-brand-500/10 dark:has-checked:text-brand-400">
                                            <input type="radio" name="status" value="{{ $value }}" class="size-4 accent-brand-500" @checked((string) ($values['status'] ?? '0') === (string) $value)>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('status') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                            </fieldset>

                            <div>
                                <label for="edit-foto-perbaikan" class="{{ $labelClass }}">
                                    {{ __('nc.columns.repairPhoto') }}
                                    <span class="font-normal text-gray-400 dark:text-gray-500">({{ __('nc.create.optional') }})</span>
                                </label>
                                <x-ui.file-input id="edit-foto-perbaikan" name="foto_perbaikan" accept="image/*"
                                    :aria-invalid="$errors->has('foto_perbaikan') ? 'true' : null" />
                                <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
                                    {{ __('nc.create.photoHint') }}
                                    @if ($finding->foto_perbaikan)
                                        {{ __('nc.edit.photoReplaces') }}
                                        <a href="{{ asset($finding->foto_perbaikan) }}" target="_blank" rel="noopener" class="font-medium text-brand-500 hover:underline dark:text-brand-400">{{ __('nc.edit.currentPhoto') }}</a>
                                    @endif
                                </p>
                                @error('foto_perbaikan') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            {{-- side panel (large screens): suggestions and the full text of the chosen clause --}}
            <aside class="hidden lg:block">
                <div class="{{ $cardClass }} sticky top-24 flex flex-col gap-5">
                    <x-main.non-conformities.clause-suggestions />

                    <x-main.non-conformities.chosen-clause class="border-t border-gray-100 pt-5 dark:border-gray-800" />
                </div>
            </aside>
        </div>

        {{-- actions stay in view while scrolling; buttons are not disabled on submit, or the
             browser would drop the clicked button's "another" value (double submits are blocked above) --}}
        <div class="sticky bottom-0 z-9 -mx-4 mt-5 border-t border-gray-200 bg-white/95 px-4 py-4 backdrop-blur md:-mx-6 md:px-6 dark:border-gray-800 dark:bg-gray-900/95">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" x-bind:class="submitting && 'pointer-events-none opacity-60'" x-bind:aria-busy="submitting">
                <x-ui.button variant="outline" as="a" href="{{ route('non-conformities') }}">{{ __('nc.create.cancel') }}</x-ui.button>
                @unless ($finding)
                    <x-ui.button type="submit" variant="outline" name="another" value="1">{{ __('nc.create.saveAndAnother') }}</x-ui.button>
                @endunless
                <x-ui.button type="submit">{{ $finding ? __('nc.edit.save') : __('nc.create.save') }}</x-ui.button>
            </div>
        </div>
    </form>
@endsection
