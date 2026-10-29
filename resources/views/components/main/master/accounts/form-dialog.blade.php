{{--
    Add / edit account dialog, opened with the `account-form` window event ({ row } or { row: null }).
    Reopens with the old input when validation fails.
--}}
@use('App\Services\UserAccountSpreadsheet')
@props(['role', 'storeUrl', 'updateUrl', 'plantOptions' => [], 'departments' => []])
@php
    $bag = $errors->account;
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $old = $bag->any() ? [
        'id' => old('_id') ? (int) old('_id') : null,
        'name' => old('name', ''),
        'email' => old('email', ''),
        'pabrik' => old('pabrik', ''),
        'departemen' => old('departemen', ''),
    ] : null;
@endphp
<div
    x-data="{
        storeUrl: @js($storeUrl),
        updateUrl: @js($updateUrl),
        departments: @js($departments),
        form: { id: null, name: '', email: '', pabrik: '', departemen: '' },
        submitting: false,
        fill(row) {
            this.form = row
                ? { id: row.id, name: row.name, email: row.email, pabrik: row.pabrik ?? '', departemen: row.departemen ?? '' }
                : { id: null, name: '', email: '', pabrik: '', departemen: '' };
            this.submitting = false;
        },
        get departmentOptions() {
            return Object.entries(this.departments[this.form.pabrik] ?? {});
        },
        changePlant() {
            // department codes differ per plant
            if (!this.departmentOptions.some(([code]) => code === this.form.departemen)) this.form.departemen = '';
        },
        get action() {
            return this.form.id ? this.updateUrl.replace(':id', this.form.id) : this.storeUrl;
        },
    }"
    class="contents"
>
    <x-ui.dialog
        x-on:account-form.window="fill($event.detail.row); show()"
        x-init="{{ $old ? 'fill('.\Illuminate\Support\Js::from($old).'); $nextTick(() => show())' : '' }}">
        <x-ui.dialog.content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <x-ui.dialog.header>
                <x-ui.dialog.title x-text="form.id ? @js(__('account.form.editTitle', ['role' => $role])) : @js(__('account.form.createTitle', ['role' => $role]))">{{ __('account.form.createTitle', ['role' => $role]) }}</x-ui.dialog.title>
                <x-ui.dialog.description>{{ __('account.form.description') }}</x-ui.dialog.description>
            </x-ui.dialog.header>

            <form method="POST" x-bind:action="action" x-on:submit="submitting = true" class="flex flex-col gap-4">
                @csrf
                <template x-if="form.id">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="_id" x-bind:value="form.id ?? ''">

                <div>
                    <label for="account-name" class="{{ $labelClass }}">{{ __('account.form.name') }}</label>
                    <x-ui.input id="account-name" name="name" x-model="form.name" required maxlength="255" autocomplete="off"
                        :aria-invalid="$bag->has('name') ? 'true' : null" />
                    @error('name', 'account') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div>
                    <label for="account-email" class="{{ $labelClass }}">{{ __('account.form.email') }}</label>
                    <x-ui.input id="account-email" type="email" name="email" x-model="form.email" required maxlength="255" autocomplete="off"
                        :aria-invalid="$bag->has('email') ? 'true' : null" />
                    @error('email', 'account') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="account-plant" class="{{ $labelClass }}">{{ __('account.form.plant') }}</label>
                        <x-ui.native-select id="account-plant" name="pabrik" x-model="form.pabrik" x-on:change="changePlant()" required
                            :aria-invalid="$bag->has('pabrik') ? 'true' : null">
                            <option value="">{{ __('account.form.selectPlant') }}</option>
                            @foreach ($plantOptions as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </x-ui.native-select>
                        @error('pabrik', 'account') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                    </div>

                    <div>
                        <label for="account-department" class="{{ $labelClass }}">{{ __('account.form.department') }}</label>
                        <x-ui.native-select id="account-department" name="departemen" x-model="form.departemen" x-bind:disabled="!form.pabrik" required
                            :aria-invalid="$bag->has('departemen') ? 'true' : null">
                            <option value="" x-text="form.pabrik ? @js(__('account.form.selectDepartment')) : @js(__('account.form.selectPlantFirst'))">{{ __('account.form.selectDepartment') }}</option>
                            <template x-for="[code, name] in departmentOptions" :key="code">
                                <option x-bind:value="code" x-text="name" x-bind:selected="code === form.departemen"></option>
                            </template>
                        </x-ui.native-select>
                        @error('departemen', 'account') <x-ui.field.error class="mt-1.5">{{ $message }}</x-ui.field.error> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="account-password" class="{{ $labelClass }}">{{ __('account.form.password') }}</label>
                        <x-ui.input id="account-password" type="password" name="password" autocomplete="new-password"
                            minlength="{{ UserAccountSpreadsheet::MIN_PASSWORD }}" x-bind:required="!form.id"
                            :aria-invalid="$bag->has('password') ? 'true' : null" />
                    </div>
                    <div>
                        <label for="account-password-confirmation" class="{{ $labelClass }}">{{ __('account.form.passwordConfirmation') }}</label>
                        <x-ui.input id="account-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" x-bind:required="!form.id" />
                    </div>
                    <div class="sm:col-span-2">
                        @error('password', 'account')
                            <x-ui.field.error>{{ $message }}</x-ui.field.error>
                        @else
                            <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                                {{ __('account.form.passwordHint', ['min' => UserAccountSpreadsheet::MIN_PASSWORD]) }}
                                <span x-show="form.id">{{ __('account.form.passwordKeep') }}</span>
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <x-ui.dialog.close as-child>
                        <x-ui.button variant="outline">{{ __('nc.create.cancel') }}</x-ui.button>
                    </x-ui.dialog.close>
                    <x-ui.button type="submit" x-bind:disabled="submitting">{{ __('nc.create.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
