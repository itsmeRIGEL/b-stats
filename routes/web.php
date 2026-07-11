<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\PickleballController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentDayController;
use App\Http\Controllers\TournamentRequestController;
use App\Http\Controllers\TournamentSubFolderController;
use App\Http\Controllers\SalesReportController;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

Route::get('/mail-preview', function () {
    $user = User::whereNotNull('email_verified_at')->first() ?? User::factory()->make();
    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
    );
    $notification = new VerifyEmail;
    $mail = $notification->toMail($user);
    return $mail->render();
});

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/book', [PickleballController::class, 'clientBooking'])->name('book');
Route::post('/book', [PickleballController::class, 'storeClientBooking'])->name('book.store');
Route::get('/book/{venue:name}', [PickleballController::class, 'clientBooking'])->name('book.venue');
Route::post('/book/{venue:name}', [PickleballController::class, 'storeClientBooking'])->name('book.venue.store');

// Public spectator routes for tournament brackets
Route::get('tournaments/live', [TournamentController::class, 'publicIndex'])->name('tournaments.live.index');
Route::get('tournaments/live/{tournament}', [TournamentController::class, 'publicShow'])->name('tournaments.live.show');

Route::middleware(['auth', 'verified', 'venue'])->group(function () {

    Route::middleware(['role:admin,scheduler,scheduler_scorer'])->group(function () {
        Route::get('venue-setup', [PickleballController::class, 'venueSetup'])->name('venue-setup');
        Route::post('venue-setup', [PickleballController::class, 'storeVenue'])->name('venue-setup.store');
    });

    Route::middleware('role:admin,scheduler')->group(function () {
        Route::get('dashboard', [PickleballController::class, 'dashboard'])->name('dashboard');
    });

    Route::middleware('role:player')->group(function () {
        Route::get('venues', [TournamentRequestController::class, 'playerVenues'])->name('venues.index');
        Route::post('tournament-requests', [TournamentRequestController::class, 'store'])->name('tournament-requests.store');
    });

    // ── Admin + Scheduler shared routes (Users Management) ───────────────────
    Route::middleware('role:admin,scheduler')->group(function () {
        Route::get('admin-users', [PickleballController::class, 'adminUsers'])->name('admin-users');
        Route::post('admin-users', [PickleballController::class, 'adminStoreUser'])->name('admin-users.store');
        Route::put('admin-users/{user}', [PickleballController::class, 'adminUpdateUser'])->name('admin-users.update');
        Route::delete('admin-users/{user}', [PickleballController::class, 'adminDestroyUser'])->name('admin-users.destroy');
    });

    // ── Admin-only routes ──────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::delete('bookings/{booking}', [PickleballController::class, 'destroyBooking'])->name('bookings.destroy');
    });

    // ── Admin + Scheduler settings routes ──────────────────────────────────────
    Route::middleware('role:admin,scheduler')->group(function () {
        Route::get('pickleball-settings', [PickleballController::class, 'settings'])->name('pickleball-settings');
        Route::post('pickleball-settings', [PickleballController::class, 'updateSettings'])->name('pickleball-settings.update');
        Route::post('pickleball-settings/weekly', [PickleballController::class, 'updateWeeklyAvailability'])->name('pickleball-settings.update-weekly');
        Route::post('pickleball-settings/override', [PickleballController::class, 'updateDateOverride'])->name('pickleball-settings.update-override');
        Route::delete('pickleball-settings/override/{override}', [PickleballController::class, 'deleteDateOverride'])->name('pickleball-settings.delete-override');
    });

    // ── Admin + Scheduler routes ─────────────────────────────────────────────
    Route::middleware(['role:admin,scheduler'])->group(function () {
        Route::get('memberships', [PickleballController::class, 'memberships'])->name('memberships');
        Route::post('memberships/{player}/toggle', [PickleballController::class, 'toggleMembership'])->name('memberships.toggle');
        Route::post('memberships/{player}/pay-due', [PickleballController::class, 'payMonthlyDue'])->name('memberships.pay-due');
        Route::post('memberships/{player}/revoke-due', [PickleballController::class, 'revokeMonthlyDue'])->name('memberships.revoke-due');
    });

    // ── Admin + Scorer shared routes ─────────────────────────────────────────
    Route::middleware(['auth'])->group(function () {
        Route::get('scoring', [PickleballController::class, 'scoring'])->name('scoring');
        Route::post('scoring/reset', [PickleballController::class, 'resetSession'])->name('scoring.reset');
        Route::post('scoring/save', [PickleballController::class, 'saveSession'])->name('scoring.save');
        Route::post('scoring/state', [PickleballController::class, 'storeScoringState'])->name('scoring.state.store');
        Route::post('scoring/invitations', [PickleballController::class, 'inviteBookingPlayers'])->name('scoring.invitations.store');
        Route::post('scoring/invitations/{booking}/respond', [PickleballController::class, 'respondToBookingInvitation'])->name('scoring.invitations.respond');
        Route::post('matches', [PickleballController::class, 'storeMatch'])->name('matches.store');
        Route::put('matches/{match}', [PickleballController::class, 'updateMatch'])->name('matches.update');

        Route::get('all-time-stats', [PickleballController::class, 'allTimeStats'])->name('all-time-stats');
    });

    // ── Shared routes (admin + scheduler) ─────────────────────────────────────
    Route::middleware('role:admin,scheduler,player')->group(function () {
        Route::get('tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
        Route::post('tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
        Route::put('tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
        Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
        Route::delete('tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');
        Route::post('tournaments/{tournament}/archive', [TournamentController::class, 'archive'])->name('tournaments.archive');
        Route::post('tournaments/{tournament}/unarchive', [TournamentController::class, 'unarchive'])->name('tournaments.unarchive');
        Route::post('tournaments/{tournament}/back-to-setup', [TournamentController::class, 'backToSetup'])->name('tournaments.back-to-setup');
        Route::post('tournaments/{tournament}/teams', [TournamentController::class, 'addTeam'])->name('tournaments.add-team');
        Route::put('tournaments/{tournament}/teams/{team}', [TournamentController::class, 'updateTeam'])->name('tournaments.update-team');
        Route::delete('tournaments/{tournament}/teams/{team}', [TournamentController::class, 'removeTeam'])->name('tournaments.remove-team');
        Route::post('tournaments/{tournament}/generate', [TournamentController::class, 'generateBracket'])->name('tournaments.generate');
        Route::post('tournaments/bulk-destroy', [TournamentController::class, 'bulkDestroy'])->name('tournaments.bulk-destroy');
        Route::put('tournament-matches/{match}/teams', [TournamentController::class, 'updateMatchTeams'])->name('tournaments.update-match-teams');
        Route::post('tournament-matches/{match}/swap-opponents', [TournamentController::class, 'swapOpponents'])->name('tournaments.swap-opponents');
        Route::post('tournament-matches/{match}/score', [TournamentController::class, 'recordScore'])->name('tournaments.record-score');
        Route::post('tournament-matches/{match}/reset', [TournamentController::class, 'resetMatch'])->name('tournaments.reset-match');
        Route::post('tournament-matches/{match}/bypass', [TournamentController::class, 'bypassMatch'])->name('tournaments.bypass-match');
        Route::post('tournament-matches/{match}/forfeit', [TournamentController::class, 'forfeitMatch'])->name('tournaments.forfeit-match');
        Route::put('tournaments/{tournament}/schedule-settings', [TournamentController::class, 'updateScheduleSettings'])->name('tournaments.update-schedule-settings');
        Route::post('tournament-days/{day}/finish-player-access', [TournamentDayController::class, 'finishForPlayer'])->name('tournament-days.finish-player-access');
        Route::put('tournament-days/{day}', [TournamentDayController::class, 'update'])->name('tournament-days.update');
        Route::delete('tournament-days/{day}', [TournamentDayController::class, 'destroy'])->name('tournament-days.destroy');
        Route::post('tournament-sub-folders', [TournamentSubFolderController::class, 'store'])->name('tournament-sub-folders.store');
        Route::put('tournament-sub-folders/{subFolder}', [TournamentSubFolderController::class, 'update'])->name('tournament-sub-folders.update');
        Route::delete('tournament-sub-folders/{subFolder}', [TournamentSubFolderController::class, 'destroy'])->name('tournament-sub-folders.destroy');
        Route::post('tournaments/bulk-assign-sub-folder', [TournamentSubFolderController::class, 'bulkAssign'])->name('tournaments.bulk-assign-sub-folder');
    });

    Route::middleware('role:admin,scheduler')->group(function () {
        Route::post('bookings/{booking}/approve', [PickleballController::class, 'approveBooking'])->name('bookings.approve');
        Route::post('bookings/{booking}/reject', [PickleballController::class, 'rejectBooking'])->name('bookings.reject');
        Route::post('bookings/{booking}/toggle-payment', [PickleballController::class, 'togglePaymentStatus'])->name('bookings.toggle-payment');
        Route::post('bookings/{booking}/cancel', [PickleballController::class, 'cancelBooking'])->name('bookings.cancel');
        Route::get('bookings', [PickleballController::class, 'bookings'])->name('bookings');
        Route::post('bookings', [PickleballController::class, 'storeBooking'])->name('bookings.store');
        Route::put('bookings/{booking}', [PickleballController::class, 'updateBooking'])->name('bookings.update');
        Route::post('court-assignments', [PickleballController::class, 'saveCourtAssignment'])->name('court-assignments.save');

        Route::post('players', [PickleballController::class, 'storePlayer'])->name('players.store');
        Route::post('players/bulk-session', [PickleballController::class, 'bulkAddToSession'])->name('players.bulk-session');
        Route::put('players/{player}', [PickleballController::class, 'updatePlayer'])->name('players.update');
        Route::delete('players/{player}', [PickleballController::class, 'destroyPlayer'])->name('players.destroy');
        Route::post('players/{player}/remove-from-session', [PickleballController::class, 'removeFromSession'])->name('players.remove-from-session');

        Route::get('sales-report', [SalesReportController::class, 'index'])->name('sales-report.index');
        Route::get('sales-report/download', [SalesReportController::class, 'downloadPdf'])->name('sales-report.download');

        Route::post('tournaments/archive-completed', [TournamentController::class, 'archiveCompleted'])->name('tournaments.archive-completed');

        Route::post('tournaments/bulk-assign-day', [TournamentDayController::class, 'bulkAssign'])->name('tournaments.bulk-assign-day');
        Route::get('tournament-days', [TournamentDayController::class, 'index'])->name('tournament-days.index');
        Route::post('tournament-days', [TournamentDayController::class, 'store'])->name('tournament-days.store');
        Route::get('tournament-sub-folders', [TournamentSubFolderController::class, 'index'])->name('tournament-sub-folders.index');

        Route::get('tournament-requests', [TournamentRequestController::class, 'index'])->name('tournament-requests.index');
        Route::post('tournament-requests/{requestModel}/approve', [TournamentRequestController::class, 'approve'])->name('tournament-requests.approve');
        Route::post('tournament-requests/{requestModel}/reject', [TournamentRequestController::class, 'reject'])->name('tournament-requests.reject');
    });

    Route::post('switch-role', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user->role !== 'scheduler_scorer') {
            return back()->with('error', 'You do not have multiple roles.');
        }
        $newRole = $request->input('role');
        if (!in_array($newRole, ['scheduler', 'scorer'], true)) {
            return back()->with('error', 'Invalid role selection.');
        }
        $request->session()->put('active_role', $newRole);
        
        $route = $newRole === 'scorer' ? 'scoring' : 'bookings';
        return redirect()->route($route)->with('success', 'Switched to ' . ucfirst($newRole) . ' view.');
    })->name('switch-role');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::get('/clear-all-caches-temp', function() {
    \Artisan::call('route:clear');
    \Artisan::call('config:clear');
    \Artisan::call('view:clear');
    \Artisan::call('cache:clear');
    
    $files = [
        base_path('bootstrap/cache/config.php'),
        base_path('bootstrap/cache/routes-v7.php'),
        base_path('bootstrap/cache/services.php'),
        base_path('bootstrap/cache/packages.php'),
    ];
    
    $deleted = [];
    foreach ($files as $file) {
        if (file_exists($file)) {
            @unlink($file);
            $deleted[] = basename($file);
        }
    }
    
    return "Caches cleared successfully! Deleted cache files: " . (empty($deleted) ? 'None' : implode(', ', $deleted));
});




