<?php

namespace App\Notifications\Hr;

use App\Models\CompanySetting;
use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayslipReady extends Notification
{
    use Queueable;

    public function __construct(public Payslip $payslip) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$address, $name] = CompanySetting::hrMailFrom();
        $p = $this->payslip->loadMissing('period');

        return (new MailMessage)
            ->from($address, $name)
            ->subject("Your payslip for {$p->period->label} is ready")
            ->line("Your {$p->period->label} payslip is available.")
            ->line("Net pay: {$p->currency} ".number_format((float) $p->net_pay, 2))
            ->action('View my payslips', route('hr.me.payslips'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hr_payslip_ready',
            'payslip_id' => $this->payslip->id,
            'message' => "Your payslip for {$this->payslip->period->label} is ready",
        ];
    }
}
