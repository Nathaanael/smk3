{{--
    Footer of the account table: "showing x–y of z", rows per page and page links.
    Driven by accountMaster (client-side); styled like <x-common.pagination>.
--}}
@use('App\Support\Ui\ButtonVariants')
@props(['perPageOptions' => [5, 10, 25, 50]])
@php
    $link = ButtonVariants::classes('outline', 'icon');
    $linkActive = ButtonVariants::classes('default', 'icon');
    $navDisabled = 'pointer-events-none cursor-not-allowed opacity-50';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800']) }}>
    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
        <span x-text="@js(__('account.showing')).replace(':from', from).replace(':to', to).replace(':total', visibleCount)"></span>

        <div class="flex items-center gap-2">
            <label for="account-per-page">{{ __('nc.perPage') }}</label>
            <x-ui.native-select id="account-per-page" x-model.number="perPage" class="h-9 w-20 ps-3 pe-8">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </x-ui.native-select>
        </div>
    </div>

    <x-ui.pagination class="mx-0 w-auto">
        <x-ui.pagination.content class="gap-1.5">
            <x-ui.pagination.item>
                <button type="button" class="{{ $link }}" x-bind:class="page <= 1 && @js($navDisabled)" x-bind:disabled="page <= 1"
                    x-on:click="goTo(page - 1)" aria-label="{{ __('pagination.previousPage') }}">
                    <x-svg.chevron-left class="rtl:rotate-180" />
                </button>
            </x-ui.pagination.item>

            {{-- numbered pages are hidden on phones; the arrows are enough there --}}
            <template x-for="(item, index) in pages" :key="index">
                <li data-slot="pagination-item" x-bind:class="item !== page && 'max-sm:hidden'">
                    <template x-if="item === '…'">
                        <x-ui.pagination.ellipsis />
                    </template>
                    <template x-if="item !== '…'">
                        <button type="button" x-on:click="goTo(item)" x-text="item"
                            x-bind:class="item === page ? @js($linkActive) : @js($link)"
                            x-bind:aria-current="item === page ? 'page' : null"></button>
                    </template>
                </li>
            </template>

            <x-ui.pagination.item>
                <button type="button" class="{{ $link }}" x-bind:class="page >= pageCount && @js($navDisabled)" x-bind:disabled="page >= pageCount"
                    x-on:click="goTo(page + 1)" aria-label="{{ __('pagination.nextPage') }}">
                    <x-svg.chevron-right class="rtl:rotate-180" />
                </button>
            </x-ui.pagination.item>
        </x-ui.pagination.content>
    </x-ui.pagination>
</div>
