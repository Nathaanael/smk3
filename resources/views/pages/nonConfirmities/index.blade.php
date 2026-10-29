@extends('layouts.app')

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" class="mb-5 px-4 py-3">
            <x-svg.check-circle />
            <x-ui.alert.title class="text-sm">{{ session('success') }}</x-ui.alert.title>
        </x-ui.alert>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3"
        x-data="nonConformityList({
            url: @js(route('non-conformities')),
            filters: @js($filters),
            departments: @js($allDepartments),
        })">
        <div class="flex flex-col gap-4 px-5 pt-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:pt-6">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('smk3.nonConformities') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('smk3.nonConformitiesDesc') }}</p>
            </div>

            @if ($canCreate)
                <x-main.non-conformities.create-dialog
                    :plants="$plants"
                    :departments="$allDepartments"
                    :categories="$categories"
                    :auditees="$auditees" />
            @endif
        </div>

        <x-main.non-conformities.filters class="px-5 py-5 sm:px-6" :filters="$filters" :plants="$plants" :categories="$categories" />

        <div x-ref="results" x-on:click="onResultsClick($event)" x-on:change="onResultsChange($event)"
            x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
            @fragment('results')
                <x-main.non-conformities.table :findings="$findings" :show-plant="count($plants) > 1" />

                <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>
                            {{ __('nc.showing', ['from' => $findings->firstItem() ?? 0, 'to' => $findings->lastItem() ?? 0, 'total' => $findings->total()]) }}
                        </span>

                        <div class="flex items-center gap-2">
                            <label for="per-page">{{ __('nc.perPage') }}</label>
                            <x-ui.native-select id="per-page" name="per_page" class="h-9 w-20 ps-3 pe-8">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($findings->perPage() === $option)>{{ $option }}</option>
                                @endforeach
                            </x-ui.native-select>
                        </div>
                    </div>

                    <x-common.pagination :paginator="$findings" />
                </div>
            @endfragment
        </div>
    </div>
@endsection
