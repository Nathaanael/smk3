<?php

namespace App\Http\Controllers\Master;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Master page for the user accounts of one role. Admins manage every account; an
 * auditor only manages accounts whose plants are all among their own.
 */
abstract class AccountController extends Controller
{
    abstract protected function role(): Role;

    /** Route name prefix, e.g. "master.auditors". */
    abstract protected function routeName(): string;

    abstract protected function view(): string;

    /** Called inside the update transaction when an account's email changes. */
    protected function emailChanged(string $from, string $to): void {}

    /** Reason the account cannot be deleted, or null. */
    protected function deleteBlocker(User $account): ?string
    {
        return null;
    }

    public function index(Request $request): View
    {
        $accounts = $this->accounts($request->user());
        $plants = $request->user()->accessiblePlants();
        $plantOptions = $this->plantOptions($request->user());

        return view($this->view(), [
            'title' => __('master.'.strtolower($this->role()->name)),
            'accounts' => $accounts,
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'plantOptions' => $plantOptions,
            'departments' => collect($plantOptions)->keys()->mapWithKeys(fn (string $pabrik) => [$pabrik => User::departmentsForPlant($pabrik)])->all(),
            'summary' => ['total' => $accounts->count()] + collect($plants)->mapWithKeys(fn (string $plant) => [
                $plant => $accounts->filter(fn (User $account) => in_array($plant, $account->plantCodes(), true))->count(),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);

        User::create($validated + ['otorisasi' => $this->role()]);

        return $this->back()->with('success', __('account.created', ['name' => $validated['name']]));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $this->ensureManageable($request->user(), $account);

        $validated = $this->validateAccount($request, $account);
        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($account, $validated) {
            if (strcasecmp($validated['email'], $account->email) !== 0) {
                $this->emailChanged($account->email, $validated['email']);
            }

            $account->update($validated);
        });

        return $this->back()->with('success', __('account.updated', ['name' => $account->name]));
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->ensureManageable($request->user(), $account);

        if ($account->is($request->user())) {
            return $this->back()->with('error', __('account.deleteSelf'));
        }

        if ($reason = $this->deleteBlocker($account)) {
            return $this->back()->with('error', $reason);
        }

        $account->delete();

        return $this->back()->with('success', __('account.deleted', ['name' => $account->name]));
    }

    public function template(Request $request, UserAccountSpreadsheet $spreadsheet): StreamedResponse
    {
        $role = $this->role()->name;
        $workbook = $spreadsheet->template($this->accounts($request->user()), $this->plantOptions($request->user()), $role);

        return response()->streamDownload(function () use ($workbook) {
            IOFactory::createWriter($workbook, 'Xlsx')->save('php://output');
        }, 'template-'.strtolower($role).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request, UserAccountSpreadsheet $spreadsheet): RedirectResponse
    {
        $request->validateWithBag('import', [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ]);

        ['rows' => $rows, 'errors' => $errors] = $spreadsheet->read($request->file('file'), $this->role(), $this->plantOptions($request->user()));

        // nothing is saved unless the whole file is valid
        if ($errors !== []) {
            return $this->back()->with('importErrors', $errors);
        }

        $result = $spreadsheet->import($rows, $this->role());

        return $this->back()->with('success', __('account.import.done', $result));
    }

    private function back(): RedirectResponse
    {
        return redirect()->route($this->routeName());
    }

    /**
     * @return Collection<int, User>
     */
    private function accounts(User $user): Collection
    {
        return User::query()
            ->where('otorisasi', $this->role())
            ->orderBy('name')
            ->get()
            ->filter(fn (User $account) => $this->canManage($user, $account))
            ->values();
    }

    /**
     * users.pabrik values this user may give an account.
     *
     * @return array<string, string>
     */
    private function plantOptions(User $user): array
    {
        $plants = $user->accessiblePlants();

        return array_filter(
            User::plantOptions(),
            fn (string $pabrik) => array_diff(explode(',', $pabrik), $plants) === [],
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function canManage(User $user, User $account): bool
    {
        return $account->otorisasi === $this->role()
            && array_diff($account->plantCodes(), $user->accessiblePlants()) === [];
    }

    private function ensureManageable(User $user, User $account): void
    {
        abort_unless($this->canManage($user, $account), 404);
    }

    /**
     * @return array{name: string, email: string, departemen: string, pabrik: string, password?: ?string}
     */
    private function validateAccount(Request $request, ?User $account = null): array
    {
        $pabrik = $request->input('pabrik');

        return $request->validateWithBag('account', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account)],
            'pabrik' => ['required', Rule::in(array_keys($this->plantOptions($request->user())))],
            'departemen' => ['required', Rule::in(array_keys(User::departmentsForPlant(is_string($pabrik) ? $pabrik : null)))],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:'.UserAccountSpreadsheet::MIN_PASSWORD, 'confirmed'],
        ]);
    }
}
