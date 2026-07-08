<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchedulerVenue
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Access denied.');
        }

        if (!Schema::hasTable('venues')) {
            return $next($request);
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), ['venue-setup', 'venue-setup.store'], true)) {
            return $next($request);
        }

        if ($user->role === 'scorer' && !$user->currentVenue()) {
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
