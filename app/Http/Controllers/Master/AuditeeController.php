<?php

namespace App\Http\Controllers\Master;

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;

/**
 * Auditees are the PIC of findings (non_conformities.pic holds their email), so a
 * changed email is carried over to their findings and an auditee with findings
 * cannot be deleted.
 */
class AuditeeController extends AccountController
{
    protected function role(): Role
    {
        return Role::Auditee;
    }

    protected function routeName(): string
    {
        return 'master.auditees';
    }

    protected function view(): string
    {
        return 'pages.master.auditee';
    }

    protected function emailChanged(string $from, string $to): void
    {
        NonConformity::where('pic', $from)->update(['pic' => $to]);
    }

    protected function deleteBlocker(User $account): ?string
    {
        $findings = NonConformity::where('pic', $account->email)->count();

        return $findings ? __('account.inUse', ['name' => $account->name, 'count' => $findings]) : null;
    }
}
