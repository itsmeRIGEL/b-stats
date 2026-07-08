<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Access denied.');
        }

        $hasRole = false;
        foreach ($roles as $role) {
            if ($role === 'admin' && $user->isAdmin()) {
                $hasRole = true;
                break;
            }
            if ($role === 'scheduler' && $user->isScheduler()) {
                $hasRole = true;
                break;
            }
            if ($role === 'scorer' && $user->isScorer()) {
                $hasRole = true;
                break;
            }
            if ($role === 'player' && $user->isPlayer()) {
                $hasRole = true;
                break;
            }
            if ($user->role === $role) {
                $hasRole = true;
                break;
            }
        }

        if (!$hasRole) {
            if ($request->expectsJson()) {
                abort(403, 'Access denied.');
            }
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
