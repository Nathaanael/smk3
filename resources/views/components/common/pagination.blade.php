{{-- Page links for a Laravel LengthAwarePaginator, styled like the TailAdmin table pagination. --}}
@use('App\Support\Ui\ButtonVariants')
@use('Illuminate\Pagination\UrlWindow')
@props(['paginator'])
@php
    $window = UrlWindow::make($paginator);
    $elements = array_filter([
        $window['first'],
        is_array($window['slider']) ? '...' : null,
        $window['slider'],
        is_array($window['last']) ? '...' : null,
        $window['last'],
    ]);
    $link = ButtonVariants::classes('outline', 'icon');
    $linkActive = ButtonVariants::classes('default', 'icon');
    $navDisabled = ButtonVariants::classes('outline', 'icon', 'pointer-events-none cursor-not-allowed opacity-50');
@endphp
@if ($paginator->hasPages())
    <x-ui.pagination {{ $attributes->merge(['class' => 'mx-0 w-auto']) }}>
        <x-ui.pagination.content class="gap-1.5">
            <x-ui.pagination.item>
                <a href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="{{ __('pagination.previousPage') }}" data-slot="pagination-link"
                    class="{{ $paginator->onFirstPage() ? $navDisabled : $link }}" @if ($paginator->onFirstPage()) aria-disabled="true" @endif>
                    <x-svg.chevron-left class="rtl:rotate-180" />
                </a>
            </x-ui.pagination.item>

            {{-- numbered pages are hidden on phones; the arrows are enough there --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li data-slot="pagination-item" class="max-sm:hidden"><x-ui.pagination.ellipsis /></li>
                @else
                    @foreach ($element as $page => $url)
                        <x-ui.pagination.item>
                            <a href="{{ $url }}" data-slot="pagination-link"
                                @class([$page === $paginator->currentPage() ? $linkActive : $link, 'max-sm:hidden' => $page !== $paginator->currentPage()])
                                @if ($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                        </x-ui.pagination.item>
                    @endforeach
                @endif
            @endforeach

            <x-ui.pagination.item>
                <a href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="{{ __('pagination.nextPage') }}" data-slot="pagination-link"
                    class="{{ $paginator->hasMorePages() ? $link : $navDisabled }}" @unless ($paginator->hasMorePages()) aria-disabled="true" @endunless>
                    <x-svg.chevron-right class="rtl:rotate-180" />
                </a>
            </x-ui.pagination.item>
        </x-ui.pagination.content>
    </x-ui.pagination>
@endif
