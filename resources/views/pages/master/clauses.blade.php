@extends('layouts.app')

@section('content')
    @php
        $rows = $groups->flatten()->map(fn ($criterion) => [
            'id' => $criterion->id,
            'no' => $criterion->no_kriteria,
            'text' => $criterion->kriteria,
            'element' => $criterion->element(),
            'minor' => $criterion->minor,
            'major' => $criterion->major,
            'kritis' => $criterion->kritis,
        ])->values();
    @endphp

    <div class="space-y-5"
        x-data="clauseMaster({
            rows: @js($rows),
            summary: @js($summary),
            categoryUrl: @js(route('master.clauses.category', ':id')),
        })"
        data-save-error="{{ __('clause.saveError') }}">

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
                    <x-ui.button variant="outline" as="a" href="{{ route('master.clauses.template') }}">
                        <x-svg.arrow-down class="size-5" />
                        {{ __('clause.download') }}
                    </x-ui.button>
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
                    <x-ui.input type="search" x-model.debounce.200ms="q" placeholder="{{ __('clause.searchPlaceholder') }}" aria-label="{{ __('clause.searchPlaceholder') }}" class="ps-12" />
                </div>
            </div>

            <x-main.master.clauses.table :groups="$groups" :elements="$elements" />
        </div>

        <x-main.master.clauses.form-dialog />
        <x-main.master.clauses.delete-dialog />
    </div>
@endsection
