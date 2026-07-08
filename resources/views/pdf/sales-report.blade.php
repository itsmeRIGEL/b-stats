<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales & Revenue Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #334155;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 5px 0;
        }
        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 0;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 25px;
        }
        .summary-title {
            font-size: 12px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-grid {
            width: 100%;
        }
        .summary-grid td {
            padding: 4px 8px;
        }
        .summary-label {
            color: #64748b;
            font-weight: 500;
        }
        .summary-value {
            font-weight: bold;
            color: #0f172a;
            text-align: right;
        }
        .total-row td {
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 13px;
        }
        .section-container {
            page-break-inside: avoid;
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .currency {
            font-weight: bold;
        }
        .empty-message {
            padding: 15px;
            text-align: center;
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="title">Financial Ledger / Sales Report</h1>
        <p class="subtitle">Report Period: <strong>{{ $start_date }}</strong> to <strong>{{ $end_date }}</strong></p>
    </div>

    <!-- Revenue Summary Box -->
    <div class="summary-box">
        <div class="summary-title">Revenue Overview</div>
        <table class="summary-grid">
            <tr>
                <td class="summary-label">Bookings Revenue:</td>
                <td class="summary-value">PHP {{ number_format($summary['booking_revenue'], 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Reclub Revenue:</td>
                <td class="summary-value">PHP {{ number_format($summary['reclub_revenue'], 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Walk-in Games Revenue:</td>
                <td class="summary-value">PHP {{ number_format($summary['walkin_revenue'], 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Memberships Revenue:</td>
                <td class="summary-value">PHP {{ number_format($summary['membership_revenue'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="summary-label" style="font-weight: bold; color: #0f172a;">TOTAL REVENUE:</td>
                <td class="summary-value" style="font-size: 14px; color: #1e3a8a;">PHP {{ number_format($summary['total_revenue'], 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- 1. Bookings Income -->
    <div class="section-container">
        <h2 class="section-title">Bookings Income</h2>
        @if(count($bookings) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 40%;">Players / Details</th>
                        <th style="width: 15%; text-align: center;">Status</th>
                        <th style="width: 15%; text-align: center;">Payment</th>
                        <th style="width: 15%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $booking)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</td>
                            <td>
                                @if($booking->lead_name)
                                    {{ $booking->lead_name }}
                                @elseif($booking->players && count($booking->players) > 0)
                                    {{ implode(', ', $booking->players->pluck('name')->toArray()) }}
                                @else
                                    No players assigned
                                @endif
                                <div style="font-size: 9px; color: #64748b;">
                                    Time: {{ $booking->start_time }} - {{ $booking->end_time }}
                                </div>
                            </td>
                            <td class="text-center">{{ ucfirst($booking->status) }}</td>
                            <td class="text-center">{{ ucfirst($booking->payment_status) }}</td>
                            <td class="text-right currency">PHP {{ number_format($booking->total_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message">No regular bookings recorded in this range.</div>
        @endif
    </div>

    <!-- 2. Reclub Income -->
    <div class="section-container">
        <h2 class="section-title">Reclub Income</h2>
        @if(count($reclub_bookings) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 40%;">Players / Details</th>
                        <th style="width: 15%; text-align: center;">Status</th>
                        <th style="width: 15%; text-align: center;">Payment</th>
                        <th style="width: 15%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reclub_bookings as $booking)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</td>
                            <td>
                                @if($booking->lead_name)
                                    {{ $booking->lead_name }}
                                @elseif($booking->players && count($booking->players) > 0)
                                    {{ implode(', ', $booking->players->pluck('name')->toArray()) }}
                                @else
                                    No players assigned
                                @endif
                                <div style="font-size: 9px; color: #64748b;">
                                    Time: {{ $booking->start_time }} - {{ $booking->end_time }}
                                </div>
                            </td>
                            <td class="text-center">{{ ucfirst($booking->status) }}</td>
                            <td class="text-center">{{ ucfirst($booking->payment_status) }}</td>
                            <td class="text-right currency">PHP {{ number_format($booking->total_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message">No Reclub bookings recorded in this range.</div>
        @endif
    </div>

    <!-- 3. Walk-in Games Income -->
    <div class="section-container">
        <h2 class="section-title">Walk-in Games Income</h2>
        @if(count($walkin_matches) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 65%;">Match Players</th>
                        <th style="width: 20%; text-align: right;">Fee Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($walkin_matches as $match)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($match->match_date)->format('M d, Y') }}</td>
                            <td>
                                {{ $match->player1?->name ?? 'Empty' }} vs {{ $match->player2?->name ?? 'Empty' }}
                                @if($match->player3 || $match->player4)
                                    / {{ $match->player3?->name ?? 'Empty' }} vs {{ $match->player4?->name ?? 'Empty' }}
                                @endif
                            </td>
                            <td class="text-right currency">PHP {{ number_format($match->fee_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message">No walk-in games recorded in this range.</div>
        @endif
    </div>

    <!-- 4. Memberships Income -->
    <div class="section-container">
        <h2 class="section-title">Memberships Income</h2>
        @if(count($memberships) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Paid Date</th>
                        <th style="width: 45%;">Player</th>
                        <th style="width: 20%; text-align: center;">Billing Period</th>
                        <th style="width: 15%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($memberships as $payment)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y H:i') }}</td>
                            <td>{{ $payment->player?->name ?? 'Unknown Player' }}</td>
                            <td class="text-center">{{ ucfirst($payment->billing_period) }}</td>
                            <td class="text-right currency">PHP {{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message">No membership payments recorded in this range.</div>
        @endif
    </div>

</body>
</html>
