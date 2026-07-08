<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Start session regeneration early for better performance
        $request->session()->regenerate();

        // Authenticate with optimized checks
        $request->authenticate();

        // Get authenticated user with minimal data needed for redirect
        $user = $request->user();
        $selectedRole = $request->input('role');

        // Store active role in session
        $request->session()->put('active_role', $selectedRole);

        // Pre-determine redirect route based on the role the user selected at login
        $default = match ($selectedRole) {
            'scorer' => route('scoring', absolute: false),
            'scheduler' => route('bookings', absolute: false),
            'player' => route('all-time-stats', absolute: false),
            default => route('dashboard', absolute: false),
        };

        if (in_array($user->role, ['scheduler', 'scheduler_scorer'], true) && !$user->currentVenue()) {
            $default = route('venue-setup', absolute: false);
        }

        // Use fast redirect without additional checks
        return redirect()->intended($default);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
