<?php

namespace App\Http\Controllers\Master;

use App\Enums\Role;

class AuditorController extends AccountController
{
    protected function role(): Role
    {
        return Role::Auditor;
    }

    protected function routeName(): string
    {
        return 'master.auditors';
    }

    protected function view(): string
    {
        return 'pages.master.auditor';
    }
}
