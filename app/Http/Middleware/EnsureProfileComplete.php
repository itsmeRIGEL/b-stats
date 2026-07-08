<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isPlayer()) {
                $playerProfile = Player::where('user_id', $user->id)->first();
                if (!$playerProfile || empty($playerProfile->phone) || empty($playerProfile->address)) {
                    // Avoid redirect loops on settings page and logout
                    if (!$request->routeIs('profile.edit') &&
                        !$request->routeIs('profile.update') &&
                        !$request->routeIs('logout') &&
                        !$request->is('settings/profile*') &&
                        !$request->is('logout')
                    ) {
                        return redirect()->route('profile.edit')->with('error', 'Please complete your profile details (phone and address) before proceeding.');
                    }
                }
            }
        }

        return $next($request);
    }
}
