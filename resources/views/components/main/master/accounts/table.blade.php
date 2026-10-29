{{-- Account list; rows are filtered client-side by accountMaster (search + plant). --}}
@props(['users'])
@php
    $headClass = 'px-4 py-3 text-start text-xs font-medium tracking-wide whitespace-nowrap text-gray-500 uppercase dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes->merge(['class' => 'custom-scrollbar max-w-full overflow-x-auto']) }}>
    <table class="min-w-full">
        <thead class="border-y border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-white/2">
            <tr>
                <th class="{{ $headClass }} w-14 text-center">{{ __('clause.columns.no') }}</th>
                <th class="{{ $headClass }} min-w-64">{{ __('account.columns.name') }}</th>
                <th class="{{ $headClass }}">{{ __('account.columns.department') }}</th>
                <th class="{{ $headClass }}">{{ __('account.columns.plant') }}</th>
                <th class="{{ $headClass }} w-24 text-center">{{ __('clause.columns.actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($users as $user)
                <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/2" x-show="isVisible({{ $user->id }})" @if ($loop->iteration > 5) x-cloak @endif>
                    <td class="{{ $cellClass }} text-center text-gray-500 dark:text-gray-500" x-text="position({{ $user->id }})">{{ $loop->iteration }}</td>
                    <td class="{{ $cellClass }}">
                        <div class="flex items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-white/90">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <x-ui.badge color="gray" size="sm" class="ms-1">{{ __('account.you') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="{{ $cellClass }}">
                        @if (count($user->departmentCodes()) > 1)
                            <div class="flex max-w-72 flex-wrap gap-1">
                                @foreach ($user->departmentCodes() as $code)
                                    <x-ui.badge color="gray" size="sm" title="{{ \App\Models\User::departmentsForPlant($user->pabrik)[$code] ?? $code }}">{{ $code }}</x-ui.badge>
                                @endforeach
                            </div>
                        @else
                            <span class="whitespace-nowrap">{{ $user->departmentName() ?? '-' }}</span>
                        @endif
                    </td>
                    <td class="{{ $cellClass }} whitespace-nowrap">
                        <x-ui.badge color="gray" size="sm">{{ $user->plantLabel() }}</x-ui.badge>
                    </td>
                    <td class="{{ $cellClass }}">
                        <div class="flex items-center justify-center gap-1">
                            <x-ui.button variant="ghost" size="icon-sm" x-on:click="openEdit({{ $user->id }})" aria-label="{{ __('clause.edit') }} {{ $user->name }}" title="{{ __('clause.edit') }}">
                                <x-svg.pencil class="size-4" />
                            </x-ui.button>
                            @unless ($user->is(auth()->user()))
                                <x-ui.button variant="ghost" size="icon-sm" class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10"
                                    x-on:click="openDelete({{ $user->id }})" aria-label="{{ __('clause.delete') }} {{ $user->name }}" title="{{ __('clause.delete') }}">
                                    <x-svg.trash-bin class="size-4" />
                                </x-ui.button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>

        <tbody x-show="visibleCount === 0" x-cloak>
            <tr>
                <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('account.empty') }}</td>
            </tr>
        </tbody>
    </table>
</div>
