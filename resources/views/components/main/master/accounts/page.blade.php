{{--
    Body of an account master page (auditor, auditee).
    routeName is the route name prefix, e.g. "master.auditors".
--}}
@props(['role', 'description', 'routeName', 'accounts', 'plants', 'plantOptions', 'departments', 'summary'])
@php
    $rows = $accounts->map(fn ($account) => [
        'id' => $account->id,
        'name' => $account->name,
        'email' => $account->email,
        'departemen' => $account->departemen,
        'departmentName' => $account->departmentName(),
        'pabrik' => $account->pabrik,
        'plants' => $account->plantCodes(),
        'isSelf' => $account->is(auth()->user()),
    ])->values();
@endphp

<div class="space-y-5" x-data="accountMaster({ rows: @js($rows), perPage: 5 })">
    <x-main.master.accounts.alerts />

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="flex flex-col gap-4 px-5 pt-5 sm:px-6 sm:pt-6">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $role }}</h3>
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button x-on:click="openCreate()">
                    <x-svg.plus class="size-5" />
                    {{ __('account.add', ['role' => $role]) }}
                </x-ui.button>
                <x-ui.button variant="outline" as="a" href="{{ route($routeName.'.template') }}">
                    <x-svg.arrow-down class="size-5" />
                    {{ __('clause.download') }}
                </x-ui.button>
                <x-main.master.accounts.upload-dialog :template-url="route($routeName.'.template')" :import-url="route($routeName.'.import')" />
                <span class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('account.bulkHint') }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-3 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <span class="font-semibold text-gray-800 dark:text-white/90">{{ $summary['total'] }}</span> {{ __('account.count') }}
                @if (count($plants) > 1)
                    @foreach ($plants as $code => $name)
                        · {{ strtoupper($code) }} <span class="font-semibold text-gray-800 dark:text-white/90">{{ $summary[$code] }}</span>
                    @endforeach
                @endif
            </p>

            <div class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                @if (count($plants) > 1)
                    <x-ui.native-select x-model="plant" aria-label="{{ __('account.columns.plant') }}" class="sm:w-48">
                        <option value="">{{ __('account.allPlants') }}</option>
                        @foreach ($plants as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </x-ui.native-select>
                @endif
                <div class="relative w-full sm:w-80">
                    <x-svg.search class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
                    <x-ui.input type="search" x-model.debounce.200ms="q" placeholder="{{ __('account.searchPlaceholder') }}" aria-label="{{ __('account.searchPlaceholder') }}" class="ps-12" />
                </div>
            </div>
        </div>

        <x-main.master.accounts.table :users="$accounts" />
        <x-main.master.accounts.pagination />
    </div>

    <x-main.master.accounts.form-dialog
        :role="$role"
        :store-url="route($routeName.'.store')"
        :update-url="route($routeName.'.update', ':id')"
        :plant-options="$plantOptions"
        :departments="$departments" />
    <x-main.master.accounts.delete-dialog :role="$role" :destroy-url="route($routeName.'.destroy', ':id')" />
</div>
