<?php

namespace App\View\Components\header;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserDropdown extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $languages = config('app.available_locales');
        $currentLocale = app()->getLocale();

        return view('components.header.user-dropdown', [
            'languages' => $languages,
            'currentLocale' => $currentLocale,
            'currentLanguage' => $languages[$currentLocale] ?? reset($languages),
        ]);
    }
}
