{{-- Flash messages of the account master pages: success, error and the upload problem list. --}}
@if (session('success'))
    <x-ui.alert variant="success" class="px-4 py-3">
        <x-svg.check-circle />
        <x-ui.alert.title class="text-sm">{{ session('success') }}</x-ui.alert.title>
    </x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert variant="destructive" class="px-4 py-3">
        <x-svg.alert />
        <x-ui.alert.title class="text-sm">{{ session('error') }}</x-ui.alert.title>
    </x-ui.alert>
@endif

@if (session('importErrors'))
    <x-ui.alert variant="destructive" class="px-4 py-3">
        <x-svg.alert />
        <x-ui.alert.title class="text-sm">{{ __('clause.import.failed', ['count' => count(session('importErrors'))]) }}</x-ui.alert.title>
        <x-ui.alert.description>
            <ul class="mt-1 list-disc space-y-0.5 ps-4">
                @foreach (array_slice(session('importErrors'), 0, 10) as $message)
                    <li>{{ $message }}</li>
                @endforeach
                @if (count(session('importErrors')) > 10)
                    <li>{{ __('clause.import.more', ['count' => count(session('importErrors')) - 10]) }}</li>
                @endif
            </ul>
        </x-ui.alert.description>
    </x-ui.alert>
@endif
