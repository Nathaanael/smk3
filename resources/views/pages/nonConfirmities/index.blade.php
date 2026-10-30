@extends('layouts.app')

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" class="mb-5 px-4 py-3">
            <x-svg.check-circle />
            <x-ui.alert.title class="text-sm">{{ session('success') }}</x-ui.alert.title>
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="destructive" class="mb-5 px-4 py-3">
            <x-svg.alert />
            <x-ui.alert.title class="text-sm">{{ session('error') }}</x-ui.alert.title>
        </x-ui.alert>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3"
        x-data="nonConformityList({
            url: @js(route('non-conformities')),
            filters: @js($filters),
            departments: @js($allDepartments),
            bulkUrl: @js(route('non-conformities.bulk')),
            destroyUrl: @js(route('non-conformities.destroy', ':id')),
            photoUrl: @js(route('non-conformities.photo', [':id', 'foto_temuan'])).replace('foto_temuan', ':field'),
            exportUrl: @js(route('non-conformities.export')),
            exportColumns: @js($exportColumns),
        })"
        data-action-error="{{ __('nc.actionError') }}">
        <div class="flex flex-col gap-4 px-5 pt-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:pt-6">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('smk3.nonConformities') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('smk3.nonConformitiesDesc') }}</p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-3">
                @if ($canManage)
                    <x-ui.button as="a" href="{{ route('non-conformities.create') }}">
                        <x-svg.plus class="size-5" />
                        {{ __('nc.create.button') }}
                    </x-ui.button>

                    {{-- bulk actions on the ticked rows of this page --}}
                    <x-ui.dropdown-menu>
                        <x-ui.dropdown-menu.trigger as-child>
                            <x-ui.button variant="outline" x-bind:disabled="!selected.length || busy">
                                {{ __('nc.bulk.button') }}
                                <span x-show="selected.length" x-cloak class="rounded-full bg-brand-50 px-2 py-0.5 text-theme-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400" x-text="selected.length"></span>
                                <x-svg.chevron-down class="size-4" />
                            </x-ui.button>
                        </x-ui.dropdown-menu.trigger>
                        <x-ui.dropdown-menu.content align="end" :side-offset="8" class="w-56 p-2">
                            <x-ui.dropdown-menu.item x-on:click.capture="bulk('closed')">
                                <span class="size-2 rounded-full bg-success-500"></span>
                                {{ __('nc.bulk.markClosed') }}
                            </x-ui.dropdown-menu.item>
                            <x-ui.dropdown-menu.item x-on:click.capture="bulk('open')">
                                <span class="size-2 rounded-full bg-warning-500"></span>
                                {{ __('nc.bulk.markOpen') }}
                            </x-ui.dropdown-menu.item>
                            <x-ui.dropdown-menu.separator />
                            <x-ui.dropdown-menu.item variant="destructive" x-on:click.capture="confirmDelete()">
                                <x-svg.trash-bin class="size-4" />
                                {{ __('nc.bulk.delete') }}
                            </x-ui.dropdown-menu.item>
                        </x-ui.dropdown-menu.content>
                    </x-ui.dropdown-menu>
                @endif

                <x-main.non-conformities.export-menu format="xlsx" :label="__('nc.export.excel')" />
                <x-main.non-conformities.export-menu format="pdf" :label="__('nc.export.pdf')" />
            </div>
        </div>

        <div class="flex flex-col gap-3 px-5 pt-5 sm:px-6" x-show="notice || error" x-cloak>
            <x-ui.alert variant="success" class="px-4 py-3" x-show="notice">
                <x-svg.check-circle />
                <x-ui.alert.title class="text-sm" x-text="notice"></x-ui.alert.title>
            </x-ui.alert>
            <x-ui.alert variant="destructive" class="px-4 py-3" x-show="error">
                <x-svg.alert />
                <x-ui.alert.title class="text-sm" x-text="error"></x-ui.alert.title>
            </x-ui.alert>
        </div>

        <x-main.non-conformities.filters class="px-5 py-5 sm:px-6" :filters="$filters" :plants="$plants" :categories="$categories" />

        <div x-ref="results" x-on:click="onResultsClick($event)" x-on:change="onResultsChange($event)"
            x-bind:aria-busy="loading || busy" x-bind:class="(loading || busy) && 'pointer-events-none opacity-60'" class="transition-opacity">
            @fragment('results')
                {{-- ids of this page, for "tick all"; runs again after every AJAX load --}}
                <div hidden x-init="setPageIds(@js($findings->pluck('id')))"></div>

                <x-main.non-conformities.table :findings="$findings" :show-plant="count($plants) > 1" :can-manage="$canManage"
                    :sort="$filters['sort'] ?? null" :dir="$filters['dir'] ?? 'asc'" />

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

        {{-- export in progress (download() fetches the file, so the browser shows no loading of its own) --}}
        <x-common.download-toast show="exporting" cancel="cancelDownload()"
            :title="'exporting === \'pdf\' ? '.Js::from(__('nc.export.preparing', ['format' => __('nc.export.pdf')])).' : '.Js::from(__('nc.export.preparing', ['format' => __('nc.export.excel')]))" />

        @if ($canManage)
            {{-- delete confirmation for one row or the ticked rows (confirmDelete → finding-delete) --}}
            <x-ui.dialog x-on:finding-delete.window="show()">
                <x-ui.dialog.content class="sm:max-w-md">
                    <x-ui.dialog.header>
                        <x-ui.dialog.title x-text="@js(__('nc.deleteDialog.title')).replace(':count', pendingDelete.length)"></x-ui.dialog.title>
                        <x-ui.dialog.description>{{ __('nc.deleteDialog.description') }}</x-ui.dialog.description>
                    </x-ui.dialog.header>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-ui.dialog.close as-child>
                            <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                        </x-ui.dialog.close>
                        <x-ui.button variant="destructive" x-bind:disabled="busy" x-on:click="close(); deleteConfirmed()">{{ __('clause.delete') }}</x-ui.button>
                    </div>
                </x-ui.dialog.content>
            </x-ui.dialog>
        @endif
    </div>
@endsection
