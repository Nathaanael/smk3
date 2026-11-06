@extends('layouts.app')

@php
    // tabs of the email sidebar
    $folders = [
        'compose' => ['icon' => 'send', 'label' => __('reminder.compose.title'), 'description' => __('reminder.compose.description')],
        'pending' => ['icon' => 'envelope', 'label' => __('reminder.folders.pending'), 'description' => __('reminder.folders.pendingDesc')],
        'sent' => ['icon' => 'check-circle', 'label' => __('reminder.folders.sent'), 'description' => __('reminder.folders.sentDesc')],
        'failed' => ['icon' => 'alert', 'label' => __('reminder.folders.failed'), 'description' => __('reminder.folders.failedDesc')],
    ];
@endphp

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" class="mb-6 px-4 py-3">
            <x-svg.check-circle />
            <x-ui.alert.title class="text-sm">{{ session('success') }}</x-ui.alert.title>
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="destructive" class="mb-6 px-4 py-3">
            <x-svg.alert />
            <x-ui.alert.title class="text-sm">{{ session('error') }}</x-ui.alert.title>
        </x-ui.alert>
    @endif

    <x-ui.alert variant="warning" class="mb-6 px-4 py-3">
        <x-svg.alert />
        <x-ui.alert.title class="text-sm">{{ __('smk3.comingSoon') }}</x-ui.alert.title>
    </x-ui.alert>

    {{-- a rejected message goes back to the tab where it was written --}}
    <div x-data="emailReminders({
            folder: @js($errors->any() ? 'compose' : 'pending'),
            people: @js($recipients),
            picked: @js(old('recipients')),
            subject: @js(old('subject', __('reminder.compose.defaultSubject'))),
            message: @js(old('message', __('reminder.compose.defaultMessage'))),
            sendUrl: @js(route('email-reminders.send')),
            previewUrl: @js(route('email-reminders.preview')),
            labels: @js([
                'sent' => __('reminder.send.sent'),
                'partial' => __('reminder.send.partial'),
                'stopped' => __('reminder.send.stopped'),
                'interrupted' => __('reminder.send.interrupted'),
                'none' => __('reminder.send.none'),
                'connection' => __('reminder.send.connection'),
            ]),
        })"
        x-on:reminder-recipients="people = $event.detail.list"
        class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <x-main.email-reminders.sidebar :folders="$folders" class="xl:w-72 xl:shrink-0" />

        <section class="min-w-0 flex-1 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            {{-- how the last send ended --}}
            <div x-show="result" x-cloak class="px-5 pt-5 sm:px-6">
                <x-ui.alert variant="success" class="px-4 py-3" x-show="result?.type === 'success'">
                    <x-svg.check-circle />
                    <x-ui.alert.title class="text-sm" x-text="result?.message"></x-ui.alert.title>
                </x-ui.alert>
                <x-ui.alert variant="destructive" class="px-4 py-3" x-show="result?.type === 'error'">
                    <x-svg.alert />
                    <x-ui.alert.title class="text-sm" x-text="result?.message"></x-ui.alert.title>
                </x-ui.alert>
            </div>

            @foreach ($folders as $key => $tab)
                <div x-show="folder === @js($key)" x-cloak class="border-b border-gray-100 px-5 py-5 sm:px-6 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $tab['label'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $tab['description'] }}</p>
                </div>
            @endforeach

            <x-main.email-reminders.compose-form x-show="folder === 'compose'" x-cloak />

            {{-- same search and filters as the findings list, reloading only the results --}}
            <div x-show="folder === 'pending'" x-cloak
                x-data="nonConformityList({
                    url: @js(route('email-reminders')),
                    filters: @js($filters),
                    departments: @js($departments),
                })">
                <div class="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-center sm:px-6">
                    <x-main.non-conformities.filters class="flex-1" :filters="$filters" :plants="$plants" :categories="$categories" :show-status="false" />
                    <x-ui.button class="shrink-0" x-bind:disabled="!people.length" x-on:click="$dispatch('reminder-pick')">
                        <x-svg.send class="size-5" />
                        {{ __('reminder.pick.button') }}
                    </x-ui.button>
                </div>

                <div x-ref="results" x-on:click="onResultsClick($event)"
                    x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
                    @fragment('results')
                        {{-- the people to choose from follow the filters; runs again after every AJAX load --}}
                        <div hidden x-init="$dispatch('reminder-recipients', { list: @js($recipients) })"></div>

                        <x-main.email-reminders.pending-list :findings="$findings" :recipients="count($recipients)" :filtered="$filtered" :show-plant="count($plants) > 1" />
                    @endfragment
                </div>
            </div>

            <x-main.email-reminders.history-panel x-show="folder === 'sent'" x-cloak status="sent" :emails="$sentEmails" :senders="$senders" />
            <x-main.email-reminders.history-panel x-show="folder === 'failed'" x-cloak status="failed" :emails="$failedEmails" :senders="$senders" />
        </section>

        <x-main.email-reminders.send-progress />

        {{-- "send email" of the needs reminder tab: choose the people, using the written message --}}
        <x-ui.dialog x-on:reminder-pick.window="show()">
            {{-- grid-cols-1: without it the column grows with the longest email in the list instead of truncating it --}}
            <x-ui.dialog.content class="max-h-[90vh] grid-cols-1 overflow-y-auto sm:max-w-2xl">
                <x-ui.dialog.header>
                    <x-ui.dialog.title>{{ __('reminder.pick.title') }}</x-ui.dialog.title>
                    <x-ui.dialog.description>{{ __('reminder.pick.description') }}</x-ui.dialog.description>
                </x-ui.dialog.header>

                <x-main.email-reminders.recipient-picker />

                <p class="rounded-lg bg-gray-50 px-3 py-2 text-theme-xs text-gray-700 dark:bg-white/5 dark:text-gray-300">
                    <span class="font-medium">{{ __('reminder.compose.subject') }}:</span>
                    <span x-text="subject"></span>
                </p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-ui.dialog.close as-child>
                        <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                    </x-ui.dialog.close>
                    <x-ui.button variant="outline" x-on:click="close(); folder = 'compose'">{{ __('reminder.pick.editMessage') }}</x-ui.button>
                    <x-ui.button x-bind:disabled="!pickedIds.length || sending" x-on:click="close(); sendEmails()">
                        <span x-text="@js(__('reminder.pick.send')).replace(':count', pickedIds.length)"></span>
                    </x-ui.button>
                </div>
            </x-ui.dialog.content>
        </x-ui.dialog>
    </div>
@endsection
