<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckExpiredMemberships
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Cache::has('membership_revoke_last_run')) {
            Player::where('is_member', true)
                ->whereNotNull('membership_expires_at')
                ->where('membership_expires_at', '<', now())
                ->update([
                    'is_member' => false,
                    'membership_expires_at' => null,
                ]);

            Cache::put('membership_revoke_last_run', true, now()->addHours(24));
        }

        return $next($request);
    }
}
