<?php

namespace App\Mail\Hr;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;

/**
 * Base for every HR-module notification (leave, contract renewals, payslips).
 * Sends under the HR sender name from Company Settings while keeping the same
 * configured "from" address as the rest of the platform — see
 * {@see CompanySetting::hrMailFrom()}. Subclasses set subject + content.
 */
abstract class HrMail extends Mailable implements ShouldQueue
{
    use Queueable;

    protected function hrFrom(): Address
    {
        [$address, $name] = CompanySetting::hrMailFrom();

        return new Address($address, $name);
    }
}
