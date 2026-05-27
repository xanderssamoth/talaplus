<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

/**
 * @author Xanders
 *
 * @see https://team.xsamtech.com/xanderssamoth
 */
class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Session::has('locale')) {
            App::setLocale(Session::get('locale'));

        } else {
            $local = $request->input('lang')
                ?? $request->input('locale')
                ?? $request->input('language')
                ?? (($request->hasHeader('X-localization')) ? $request->header('X-localization') : 'fr');

            App::setLocale($local);
        }

        return $next($request);
    }
}
