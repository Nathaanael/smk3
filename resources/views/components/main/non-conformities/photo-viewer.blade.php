{{--
    Photo viewer modal: gallery of a finding's photos with a table of its key facts on the right
    (below the gallery on small screens). Behaviour in resources/js/components/non-conformities/photo-viewer.js;
    opened with the window event finding-photos-open.
--}}
@php
    $tabs = ['foto_temuan' => __('nc.columns.findingPhoto'), 'foto_perbaikan' => __('nc.columns.repairPhoto')];
@endphp
<div class="contents" x-data="findingPhotoViewer({
    destroyUrl: @js(route('non-conformities.photo.destroy', [':id', ':photo'])),
    errorMessage: @js(__('nc.actionError')),
})">
    <x-ui.dialog x-on:finding-photos-open.window="view($event.detail); show()">
        <x-ui.dialog.content class="custom-scrollbar max-h-[90dvh] gap-0 overflow-y-auto p-0 sm:max-w-3xl lg:max-w-5xl"
            x-on:keydown.arrow-left="step(-1)" x-on:keydown.arrow-right="step(1)">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem]">
                {{-- gallery --}}
                <div class="flex min-w-0 flex-col gap-4 p-5 sm:p-6">
                    <x-ui.dialog.header class="pe-8 lg:pe-0">
                        <x-ui.dialog.title><span x-text="@js(__('nc.photoViewer.title')).replace(':id', finding?.id ?? '')"></span></x-ui.dialog.title>
                        <x-ui.dialog.description class="sr-only">{{ __('nc.photoViewer.description') }}</x-ui.dialog.description>
                    </x-ui.dialog.header>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($tabs as $tabField => $tabLabel)
                            <button type="button" class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                                x-bind:class="field === '{{ $tabField }}'
                                    ? 'border-brand-300 bg-brand-50 text-brand-600 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-400'
                                    : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/3'"
                                x-bind:aria-pressed="field === '{{ $tabField }}'" x-on:click="selectField('{{ $tabField }}')">
                                {{ $tabLabel }}
                                (<span x-text="finding?.photos.{{ $tabField }}.length ?? 0"></span>)
                            </button>
                        @endforeach
                    </div>

                    <div class="relative flex h-[45dvh] items-center justify-center overflow-hidden rounded-xl bg-gray-100 lg:h-[60dvh] dark:bg-gray-900">
                        <template x-if="photo">
                            <img x-bind:src="photo.url" x-bind:alt="@js(__('nc.photoViewer.photoNumber')).replace(':number', index + 1)" class="size-full object-contain">
                        </template>
                        <p x-show="!photo" class="text-sm text-gray-500 dark:text-gray-400">{{ __('nc.noPhoto') }}</p>

                        <template x-if="photos.length > 1">
                            <div>
                                <x-ui.button variant="outline" size="icon-sm" class="absolute start-3 top-1/2 -translate-y-1/2 bg-white/90 dark:bg-gray-900/90"
                                    x-on:click="step(-1)" aria-label="{{ __('nc.photoViewer.previous') }}" title="{{ __('nc.photoViewer.previous') }}">
                                    <x-svg.chevron-left class="size-4 rtl:rotate-180" />
                                </x-ui.button>
                                <x-ui.button variant="outline" size="icon-sm" class="absolute end-3 top-1/2 -translate-y-1/2 bg-white/90 dark:bg-gray-900/90"
                                    x-on:click="step(1)" aria-label="{{ __('nc.photoViewer.next') }}" title="{{ __('nc.photoViewer.next') }}">
                                    <x-svg.chevron-right class="size-4 rtl:rotate-180" />
                                </x-ui.button>
                            </div>
                        </template>
                    </div>

                    <div class="flex min-h-9 flex-wrap items-center justify-between gap-3">
                        <span class="text-theme-sm text-gray-500 dark:text-gray-400" x-show="photo" x-text="`${index + 1} / ${photos.length}`"></span>

                        <div class="ms-auto flex items-center gap-2" x-show="canRemove">
                            <x-ui.button variant="outline" size="sm" x-show="!confirmingRemove" x-on:click="confirmingRemove = true"
                                class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10">
                                <x-svg.trash-bin class="size-4" />
                                {{ __('nc.create.removePhoto') }}
                            </x-ui.button>
                            <template x-if="confirmingRemove">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('nc.photoViewer.confirmRemove') }}</span>
                                    <x-ui.button variant="outline" size="sm" x-on:click="confirmingRemove = false">{{ __('nc.create.cancel') }}</x-ui.button>
                                    <x-ui.button variant="destructive" size="sm" x-bind:disabled="removing" x-on:click="removePhoto()">{{ __('clause.delete') }}</x-ui.button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <p x-show="viewerError" x-text="viewerError" class="text-sm text-error-600 dark:text-error-400"></p>

                    <div class="custom-scrollbar flex gap-2 overflow-x-auto pb-1" x-show="photos.length > 1">
                        <template x-for="(item, position) in photos" x-bind:key="item.id">
                            <button type="button" class="size-16 shrink-0 overflow-hidden rounded-lg border-2 transition-colors"
                                x-bind:class="position === index ? 'border-brand-500' : 'border-transparent opacity-70 hover:opacity-100'"
                                x-bind:aria-label="@js(__('nc.photoViewer.photoNumber')).replace(':number', position + 1)"
                                x-bind:aria-current="position === index" x-on:click="pick(position)">
                                <img x-bind:src="item.url" alt="" loading="lazy" class="size-full object-cover">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- key facts --}}
                <div class="min-w-0 border-t border-gray-200 p-5 sm:p-6 lg:border-s lg:border-t-0 dark:border-gray-800">
                    <h3 class="mb-3 pe-8 text-base font-semibold text-gray-800 dark:text-white/90">{{ __('nc.photoViewer.details') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="row in finding?.details ?? []" x-bind:key="row.label">
                                <tr>
                                    <th scope="row" class="w-28 py-2.5 pe-3 text-start align-top font-medium text-gray-500 dark:text-gray-400" x-text="row.label"></th>
                                    <td class="py-2.5 align-top wrap-anywhere text-gray-800 dark:text-white/90">
                                        <span x-show="!row.badge" class="whitespace-pre-line" x-text="row.value"></span>
                                        @foreach (['success', 'warning', 'error', 'info', 'gray'] as $color)
                                            <template x-if="row.badge === '{{ $color }}'">
                                                <x-ui.badge size="sm" :color="$color"><span x-text="row.value"></span></x-ui.badge>
                                            </template>
                                        @endforeach
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
