<?php

namespace Tests\Feature\Security;

use App\Mail\TicketCreatedMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XssEscapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_malicious_ticket_title_is_escaped_in_the_notification_email()
    {
        $requester = User::factory()->create()->assignRole('Employee');
        $ticket = Ticket::factory()->create([
            'requester_id' => $requester->id,
            'title' => '<script>alert(1)</script>',
        ]);

        $html = (new TicketCreatedMail($ticket))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
