<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipPayment;
use App\Models\SystemSetting;
use Inertia\Inertia;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    private function activeVenueId(): ?int
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin()) {
            return null;
        }

        return $user->currentVenue()?->id;
    }

    private function scopeVenue($query)
    {
        $user = auth()->user();
        if ($user && !$user->isAdmin()) {
            $venueId = $this->activeVenueId();
            if ($venueId !== null) {
                $query->where('venue_id', $venueId);
            }
        }

        return $query;
    }

    public function index(Request $request)
    {
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');
        $granularityParam = $request->input('granularity', 'auto');

        if (!$startDateParam || !$endDateParam) {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth()->endOfDay();
        } else {
            $start = Carbon::parse($startDateParam)->startOfDay();
            $end = Carbon::parse($endDateParam)->endOfDay();
        }

        // Auto-select granularity based on date range
        $days = $start->diffInDays($end);
        if ($granularityParam === 'auto') {
            $granularity = $days <= 31 ? 'daily' : ($days <= 90 ? 'weekly' : 'monthly');
        } else {
            $granularity = in_array($granularityParam, ['daily', 'weekly', 'monthly']) ? $granularityParam : 'daily';
        }

        // 1. Bookings Revenue (regular bookings only, excludes reclub)
        $bookings = $this->scopeVenue(Booking::with('players'))
            ->where('type', 'booking')
            ->where('payment_status', 'paid')
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('booking_date', 'desc')
            ->get();
        $bookingTotal = $bookings->sum('total_cost');

        // 1b. Cancelled bookings (shown as records with ₱0)
        $cancelledBookings = $this->scopeVenue(Booking::with('players'))
            ->where('status', 'cancelled')
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('booking_date', 'desc')
            ->get();

        // 1c. Reclub Revenue
        $reclubBookings = $this->scopeVenue(Booking::with('players'))
            ->where('type', 'reclub')
            ->where('payment_status', 'paid')
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('booking_date', 'desc')
            ->get();
        $reclubTotal = $reclubBookings->sum('total_cost');

        // 2. Walk-in Revenue
        $walkinMatches = $this->scopeVenue(GameMatch::with(['player1', 'player2', 'player3', 'player4']))
            ->where('is_walkin', true)
            ->whereBetween('match_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('match_date', 'desc')
            ->get();
        $walkinTotal = $walkinMatches->sum('fee_amount');

        // Group walk-ins by date for the summary/table
        $walkinByDate = $walkinMatches->groupBy(fn($m) => Carbon::parse($m->match_date)->toDateString())
            ->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'games_count' => $group->count(),
                    'revenue' => (float) $group->sum('fee_amount'),
                ];
            })->values()->sortByDesc('date')->values();

        // 3. Membership Revenue
        $memberships = $this->scopeVenue(MembershipPayment::with('player'))
            ->whereNull('revoked_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at', 'desc')
            ->get();
        $membershipTotal = $memberships->sum('amount');

        $totalRevenue = $bookingTotal + $reclubTotal + $walkinTotal + $membershipTotal;

        // Chart Data based on granularity
        $chartData = $this->buildChartData($start, $end, $granularity);

        return Inertia::render('SalesReport', [
            'dateRange' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'total_revenue' => (float) $totalRevenue,
                'booking_revenue' => (float) $bookingTotal,
                'reclub_revenue' => (float) $reclubTotal,
                'walkin_revenue' => (float) $walkinTotal,
                'membership_revenue' => (float) $membershipTotal,
                'chart_data' => $chartData,
                'granularity' => $granularity,
            ],
            'bookings' => $bookings,
            'cancelled_bookings' => $cancelledBookings,
            'reclub_bookings' => $reclubBookings,
            'walkin_by_date' => $walkinByDate,
            'walkin_matches' => $walkinMatches,
            'memberships' => $memberships,
        ]);
    }

    private function buildChartData(Carbon $start, Carbon $end, string $granularity)
    {
        $chartData = collect();

        if ($granularity === 'daily') {
            $currentDay = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();
            $dayCount = 0;
            $maxDays = 31;

            while ($currentDay <= $endDay && $dayCount < $maxDays) {
                $dayStr = $currentDay->toDateString();

                $bRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'booking')
                    ->where('payment_status', 'paid')
                    ->whereDate('booking_date', $dayStr)
                    ->sum('total_cost');

                $rRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'reclub')
                    ->where('payment_status', 'paid')
                    ->whereDate('booking_date', $dayStr)
                    ->sum('total_cost');

                $wRev = $this->scopeVenue(GameMatch::query())
                    ->where('is_walkin', true)
                    ->whereDate('match_date', $dayStr)
                    ->sum('fee_amount');

                $mRev = $this->scopeVenue(MembershipPayment::query())
                    ->whereNull('revoked_at')
                    ->whereDate('paid_at', $dayStr)
                    ->sum('amount');

                $chartData->push([
                    'label' => $currentDay->format('M d'),
                    'bookings' => (float) $bRev,
                    'reclub' => (float) $rRev,
                    'walkin' => (float) $wRev,
                    'membership' => (float) $mRev,
                    'total' => (float) ($bRev + $rRev + $wRev + $mRev),
                ]);

                $currentDay->addDay();
                $dayCount++;
            }
        } elseif ($granularity === 'weekly') {
            $currentWeek = $start->copy()->startOfWeek(Carbon::MONDAY);
            $endWeek = $end->copy()->endOfWeek(Carbon::SUNDAY);
            $weekCount = 0;
            $maxWeeks = 52;

            while ($currentWeek <= $endWeek && $weekCount < $maxWeeks) {
                $weekStart = $currentWeek->copy()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $currentWeek->copy()->endOfWeek(Carbon::SUNDAY);

                $bRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'booking')
                    ->where('payment_status', 'paid')
                    ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->sum('total_cost');

                $rRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'reclub')
                    ->where('payment_status', 'paid')
                    ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->sum('total_cost');

                $wRev = $this->scopeVenue(GameMatch::query())
                    ->where('is_walkin', true)
                    ->whereBetween('match_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->sum('fee_amount');

                $mRev = $this->scopeVenue(MembershipPayment::query())
                    ->whereNull('revoked_at')
                    ->whereBetween('paid_at', [$weekStart, $weekEnd])
                    ->sum('amount');

                $chartData->push([
                    'label' => 'Week ' . $currentWeek->weekOfYear,
                    'bookings' => (float) $bRev,
                    'reclub' => (float) $rRev,
                    'walkin' => (float) $wRev,
                    'membership' => (float) $mRev,
                    'total' => (float) ($bRev + $rRev + $wRev + $mRev),
                ]);

                $currentWeek->addWeek();
                $weekCount++;
            }
        } elseif ($granularity === 'monthly') {
            $currentMonth = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->endOfMonth();
            $monthCount = 0;
            $maxMonths = 24;

            while ($currentMonth <= $endMonth && $monthCount < $maxMonths) {
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();

                $bRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'booking')
                    ->where('payment_status', 'paid')
                    ->whereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('total_cost');

                $rRev = $this->scopeVenue(Booking::query())
                    ->where('type', 'reclub')
                    ->where('payment_status', 'paid')
                    ->whereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('total_cost');

                $wRev = $this->scopeVenue(GameMatch::query())
                    ->where('is_walkin', true)
                    ->whereBetween('match_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('fee_amount');

                $mRev = $this->scopeVenue(MembershipPayment::query())
                    ->whereNull('revoked_at')
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $chartData->push([
                    'label' => $currentMonth->format('M Y'),
                    'bookings' => (float) $bRev,
                    'reclub' => (float) $rRev,
                    'walkin' => (float) $wRev,
                    'membership' => (float) $mRev,
                    'total' => (float) ($bRev + $rRev + $wRev + $mRev),
                ]);

                $currentMonth->addMonth();
                $monthCount++;
            }
        }

        return $chartData;
    }

    public function downloadPdf(Request $request)
    {
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');

        if (!$startDateParam || !$endDateParam) {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth()->endOfDay();
        } else {
            $start = Carbon::parse($startDateParam)->startOfDay();
            $end = Carbon::parse($endDateParam)->endOfDay();
        }

        // 1. Bookings Revenue (regular bookings only, excludes reclub)
        $bookings = $this->scopeVenue(Booking::with('players'))
            ->where('type', 'booking')
            ->where('payment_status', 'paid')
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('booking_date', 'desc')
            ->get();
        $bookingTotal = $bookings->sum('total_cost');

        // 1c. Reclub Revenue
        $reclubBookings = $this->scopeVenue(Booking::with('players'))
            ->where('type', 'reclub')
            ->where('payment_status', 'paid')
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('booking_date', 'desc')
            ->get();
        $reclubTotal = $reclubBookings->sum('total_cost');

        // 2. Walk-in Revenue
        $walkinMatches = $this->scopeVenue(GameMatch::with(['player1', 'player2', 'player3', 'player4']))
            ->where('is_walkin', true)
            ->whereBetween('match_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('match_date', 'desc')
            ->get();
        $walkinTotal = $walkinMatches->sum('fee_amount');

        // 3. Membership Revenue
        $memberships = $this->scopeVenue(MembershipPayment::with('player'))
            ->whereNull('revoked_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at', 'desc')
            ->get();
        $membershipTotal = $memberships->sum('amount');

        $totalRevenue = $bookingTotal + $reclubTotal + $walkinTotal + $membershipTotal;

        $summary = [
            'total_revenue' => (float) $totalRevenue,
            'booking_revenue' => (float) $bookingTotal,
            'reclub_revenue' => (float) $reclubTotal,
            'walkin_revenue' => (float) $walkinTotal,
            'membership_revenue' => (float) $membershipTotal,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.sales-report', [
            'start_date' => $start->format('F d, Y'),
            'end_date' => $end->format('F d, Y'),
            'summary' => $summary,
            'bookings' => $bookings,
            'reclub_bookings' => $reclubBookings,
            'walkin_matches' => $walkinMatches,
            'memberships' => $memberships,
        ]);

        $filename = 'sales_report_' . $start->format('Ymd') . '_to_' . $end->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }
}
