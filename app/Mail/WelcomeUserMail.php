<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class WelcomeUserMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Welcome to '.config('app.name'))
            ->markdown('mail.welcome-user', [
                'user' => $this->user,
                'roleName' => $this->user->roles->first()?->name,
                'departmentName' => $this->user->department?->name,
                'url' => route('login'),
            ]);
    }
}
