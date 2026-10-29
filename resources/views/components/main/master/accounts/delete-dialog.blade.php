{{-- Delete confirmation, opened with the `account-delete` window event ({ row }). --}}
@props(['role', 'destroyUrl'])
<div
    x-data="{
        url: @js($destroyUrl),
        row: { id: null, name: '', email: '' },
        submitting: false,
    }"
    class="contents"
>
    <x-ui.dialog x-on:account-delete.window="row = $event.detail.row; submitting = false; show()">
        <x-ui.dialog.content class="sm:max-w-md">
            <x-ui.dialog.header>
                <x-ui.dialog.title>{{ __('account.deleteDialog.title', ['role' => $role]) }}</x-ui.dialog.title>
                <x-ui.dialog.description>{{ __('account.deleteDialog.description') }}</x-ui.dialog.description>
            </x-ui.dialog.header>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-800 dark:bg-white/3">
                <p class="font-semibold text-gray-800 dark:text-white/90" x-text="row.name"></p>
                <p class="mt-1 text-gray-600 dark:text-gray-400" x-text="row.email"></p>
            </div>

            <form method="POST" x-bind:action="url.replace(':id', row.id)" x-on:submit="submitting = true"
                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                @csrf
                @method('DELETE')
                <x-ui.dialog.close as-child>
                    <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                </x-ui.dialog.close>
                <x-ui.button type="submit" variant="destructive" x-bind:disabled="submitting">{{ __('clause.delete') }}</x-ui.button>
            </form>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
