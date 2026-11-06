@extends('layouts.fullscreen-layout')

@section('content')
    {{-- the email has its own styles, so it stays in an iframe (sandboxed: an email needs no scripts) --}}
    <main class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4 sm:p-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('reminder.preview.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('reminder.preview.note', ['count' => $recipients]) }}</p>
        </div>

        <dl class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-white/3">
            <div class="flex gap-2">
                <dt class="w-20 shrink-0 text-gray-500 dark:text-gray-400">{{ __('reminder.preview.to') }}</dt>
                <dd class="min-w-0 break-words text-gray-800 dark:text-white/90">{{ $recipient->name }} &lt;{{ $recipient->email }}&gt;</dd>
            </div>
            <div class="mt-2 flex gap-2">
                <dt class="w-20 shrink-0 text-gray-500 dark:text-gray-400">{{ __('reminder.compose.subject') }}</dt>
                <dd class="min-w-0 break-words text-gray-800 dark:text-white/90">{{ $subject }}</dd>
            </div>
            @if ($attachments)
                <div class="mt-2 flex gap-2">
                    <dt class="w-20 shrink-0 text-gray-500 dark:text-gray-400">{{ __('reminder.compose.attachments') }}</dt>
                    <dd class="min-w-0 break-words text-gray-800 dark:text-white/90">{{ implode(', ', $attachments) }}</dd>
                </div>
            @endif
        </dl>

        <iframe title="{{ __('reminder.preview.title') }}" sandbox srcdoc="{{ $html }}" class="min-h-[60vh] w-full flex-1 rounded-xl border border-gray-200 bg-white dark:border-gray-800"></iframe>
    </main>
@endsection
