<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseAdminGuard
{
    /**
     * Select the guard before sessions, shared props, or controllers resolve it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $previousGuard = Auth::getDefaultDriver();
        app()->forgetInstance('auth.driver');
        Auth::shouldUse('admin');

        try {
            // StartSession writes database metadata before this call returns.
            return $next($request);
        } finally {
            Auth::shouldUse($previousGuard);
            app()->forgetInstance('auth.driver');
        }
    }
}
