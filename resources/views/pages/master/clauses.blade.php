@extends('layouts.app')

@section('content')
    <div class="space-y-5"
        x-data="clauseMaster({
            url: @js(route('master.clauses')),
            element: @js($active),
            q: @js($search),
            summary: @js($summary),
            saveUrl: @js(route('master.clauses.categories')),
            templateUrl: @js(route('master.clauses.template')),
            templateElements: @js(array_keys($templateElements)),
        })"
        data-save-error="{{ __('clause.saveError') }}"
        data-load-error="{{ __('clause.loadError') }}">

        @if (session('success'))
            <x-ui.alert variant="success" class="px-4 py-3">
                <x-svg.check-circle />
                <x-ui.alert.title class="text-sm">{{ session('success') }}</x-ui.alert.title>
            </x-ui.alert>
        @endif

        @if (session('error'))
            <x-ui.alert variant="destructive" class="px-4 py-3">
                <x-svg.alert />
                <x-ui.alert.title class="text-sm">{{ session('error') }}</x-ui.alert.title>
            </x-ui.alert>
        @endif

        @if (session('importErrors'))
            <x-ui.alert variant="destructive" class="px-4 py-3">
                <x-svg.alert />
                <x-ui.alert.title class="text-sm">{{ __('clause.import.failed', ['count' => count(session('importErrors'))]) }}</x-ui.alert.title>
                <x-ui.alert.description>
                    <ul class="mt-1 list-disc space-y-0.5 ps-4">
                        @foreach (array_slice(session('importErrors'), 0, 10) as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                        @if (count(session('importErrors')) > 10)
                            <li>{{ __('clause.import.more', ['count' => count(session('importErrors')) - 10]) }}</li>
                        @endif
                    </ul>
                </x-ui.alert.description>
            </x-ui.alert>
        @endif

        <x-ui.alert variant="destructive" class="px-4 py-3" x-show="error" x-cloak>
            <x-svg.alert />
            <x-ui.alert.title class="text-sm" x-text="error"></x-ui.alert.title>
        </x-ui.alert>

        <x-ui.alert variant="success" class="px-4 py-3" x-show="notice" x-cloak>
            <x-svg.check-circle />
            <x-ui.alert.title class="text-sm" x-text="notice"></x-ui.alert.title>
        </x-ui.alert>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            <div class="flex flex-col gap-4 px-5 pt-5 sm:px-6 sm:pt-6">
                <div class="flex flex-col gap-1">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('master.clauses') }}</h3>
                    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('clause.description') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button x-on:click="openCreate()">
                        <x-svg.plus class="size-5" />
                        {{ __('clause.add') }}
                    </x-ui.button>
                    <x-main.master.clauses.template-menu :elements="$templateElements" />
                    <x-main.master.clauses.upload-dialog />
                    <span class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('clause.bulkHint') }}</span>
                </div>
            </div>

            <div class="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-semibold text-gray-800 dark:text-white/90" x-text="summary.total">{{ $summary['total'] }}</span> {{ __('clause.summary.total') }}
                    · Minor <span class="font-semibold text-gray-800 dark:text-white/90" x-text="summary.minor">{{ $summary['minor'] }}</span>
                    · Major <span class="font-semibold text-gray-800 dark:text-white/90" x-text="summary.major">{{ $summary['major'] }}</span>
                    · Kritis <span class="font-semibold text-gray-800 dark:text-white/90" x-text="summary.kritis">{{ $summary['kritis'] }}</span>
                </p>

                <div class="relative w-full sm:w-80">
                    <x-svg.search class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
                    <x-ui.input type="search" x-model.debounce.400ms="q" placeholder="{{ __('clause.searchPlaceholder') }}" aria-label="{{ __('clause.searchPlaceholder') }}" class="ps-12" />
                </div>
            </div>

            <div x-ref="results" x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
                @fragment('results')
                    @php
                        $rows = $criteria->map(fn ($criterion) => [
                            'id' => $criterion->id,
                            'no' => $criterion->no_kriteria,
                            'text' => $criterion->kriteria,
                            'keywords' => $criterion->kata_kunci,
                            'minor' => $criterion->minor,
                            'major' => $criterion->major,
                            'kritis' => $criterion->kritis,
                        ])->values();
                    @endphp
                    {{-- rows of this element for the checkboxes and dialogs; runs again after every AJAX load --}}
                    <div hidden x-init="setRows(@js($rows)); element = {{ $active }}"></div>

                    <x-main.master.clauses.tabs :elements="$elements" :counts="$counts" :active="$active" :search="$search" />

                    <div id="clause-panel" role="tabpanel" aria-labelledby="clause-tab-{{ $active }}">
                        <div class="px-5 py-4 sm:px-6">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ __('clause.element', ['number' => $active]) }}
                                @if ($elements[$active] ?? null)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $elements[$active] }}</span>
                                @endif
                            </p>
                        </div>
                        <x-main.master.clauses.table :criteria="$criteria" :search="$search" />
                    </div>
                @endfragment
            </div>

            {{-- checkbox changes are saved together; the bar stays in view while scrolling --}}
            <div class="sticky bottom-0 z-1 flex flex-col gap-3 rounded-b-2xl border-t border-gray-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span x-show="changeCount" x-cloak class="font-medium text-warning-600 dark:text-warning-400"
                        x-text="@js(__('clause.unsaved')).replace(':count', changeCount)"></span>
                    <span x-show="!changeCount">{{ __('clause.noChanges') }}</span>
                </p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <x-ui.button variant="outline" x-on:click="discard()" x-bind:disabled="!changeCount || saving">{{ __('clause.discard') }}</x-ui.button>
                    <x-ui.button x-on:click="save()" x-bind:disabled="!changeCount || saving">
                        <span x-show="!saving">{{ __('clause.saveChanges') }}</span>
                        <span x-show="saving" x-cloak>{{ __('clause.uploadDialog.processing') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </div>

        <x-main.master.clauses.form-dialog />
        <x-main.master.clauses.delete-dialog />
        <x-common.download-toast show="downloadingTemplate" cancel="cancelTemplateDownload()" :title="Js::from(__('clause.template.preparing'))" />
    </div>
@endsection
