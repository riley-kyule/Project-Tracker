<?php

namespace App\Jobs;

use App\Models\Payslip;
use App\Notifications\Hr\PayslipReady;
use App\Services\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an employee their payslip is available — email (HR sender identity)
 * plus an EPE push. No-ops for a payslip whose employee has no linked login.
 */
class SendPayslipNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $payslipId) {}

    public function handle(PushNotifier $push): void
    {
        $payslip = Payslip::with('employee.user', 'period')->find($this->payslipId);
        $user = $payslip?->employee?->user;

        if ($user === null) {
            return;
        }

        $user->notify(new PayslipReady($payslip));

        $push->notify($user, 'payslip_ready', [
            'period' => $payslip->period->label,
            'net_pay' => (float) $payslip->net_pay,
        ]);
    }
}
