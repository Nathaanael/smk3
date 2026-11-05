@extends('layouts.app')

@php
    // tabs of the email sidebar; the content of the mail folders comes with the sending feature
    $folders = [
        'compose' => ['icon' => 'send', 'label' => __('reminder.compose.title'), 'description' => __('reminder.compose.description')],
        'pending' => ['icon' => 'envelope', 'label' => __('reminder.folders.pending'), 'description' => __('reminder.folders.pendingDesc')],
        'sent' => ['icon' => 'check-circle', 'label' => __('reminder.folders.sent'), 'description' => __('reminder.folders.sentDesc')],
        'failed' => ['icon' => 'alert', 'label' => __('reminder.folders.failed'), 'description' => __('reminder.folders.failedDesc')],
    ];
@endphp

@section('content')
    <x-ui.alert variant="warning" class="mb-6 px-4 py-3">
        <x-svg.alert />
        <x-ui.alert.title class="text-sm">{{ __('smk3.comingSoon') }}</x-ui.alert.title>
    </x-ui.alert>

    <div x-data="{ folder: 'pending' }" class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <x-main.email-reminders.sidebar :folders="$folders" class="xl:w-72 xl:shrink-0" />

        <section class="min-w-0 flex-1 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            @foreach ($folders as $key => $folder)
                <div x-show="folder === @js($key)" @if ($key !== 'pending') x-cloak @endif class="border-b border-gray-100 px-5 py-5 sm:px-6 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $folder['label'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $folder['description'] }}</p>
                </div>
            @endforeach

            <x-main.email-reminders.compose-form x-show="folder === 'compose'" x-cloak />

            <div x-show="folder !== 'compose'" class="flex flex-col items-center gap-3 px-5 py-16 text-center sm:px-6">
                <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <x-svg.envelope class="size-6" />
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('smk3.comingSoon') }}</p>
            </div>
        </section>
    </div>
@endsection
