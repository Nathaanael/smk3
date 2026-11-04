<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the filters of a list page while the user works elsewhere. Opening the page without a
 * query string (menu, back link, redirect after saving) continues with the query it last had
 * in this session. A query in the URL always wins, and clearing the filters clears the memory.
 *
 * The list pages reload their results over AJAX with the X-Partial header. Such a request
 * without a query means the filters were cleared, so it is never sent back to the saved ones.
 */
class RememberListFilters
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()->getName();
        $key = "list_filters.{$routeName}";
        $saved = $request->session()->get($key);

        if ($saved && $request->query() === [] && ! $request->hasHeader('X-Partial')) {
            // this extra redirect must not use up a "saved" message meant for the list page
            $request->session()->reflash();

            return redirect()->route($routeName, $saved);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $request->query() === [] ? $request->session()->forget($key) : $request->session()->put($key, $request->query());
        } elseif ($saved == $request->query()) {
            // no longer valid (e.g. the plant was taken away), or the page would keep failing
            $request->session()->forget($key);
        }

        return $response;
    }
}
