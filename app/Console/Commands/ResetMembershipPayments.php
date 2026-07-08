<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MembershipPayment;

class ResetMembershipPayments extends Command
{
    protected $signature = 'membership:reset-payments';
    protected $description = 'Reset all membership payment data';

    public function handle(): void
    {
        $count = MembershipPayment::count();
        MembershipPayment::truncate();
        $this->info("Cleared {$count} membership payment records.");
    }
}
