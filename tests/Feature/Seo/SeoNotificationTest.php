<?php

namespace Tests\Feature\Seo;

use App\Jobs\GenerateSeoDailyCardReport;
use App\Mail\SeoDailyCardReportMail;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\Employee;
use App\Models\ReportDelivery;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** SEO Board Requirements Specification v1.1 §4.2.1 — the midnight report and its configurable recipients. */
class SeoNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    private function cardFor(Employee $employee, Department $department): SeoDailyCard
    {
        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today(), 'status' => SeoDailyCard::STATUS_CLOSED, 'closed_at' => now(), 'planned_points' => 100,
        ]);
        $card->items()->create(['section' => 'monitoring', 'name' => 'A', 'weight' => 100, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);

        return $card;
    }

    public function test_the_report_reaches_the_ceo_and_the_departments_hod(): void
    {
        Mail::fake();

        $department = $this->seoDepartment();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create()->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);
        $ceo = User::factory()->create()->assignRole('CEO');

        $employeeUser = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $department->id]);
        $card = $this->cardFor($employee, $department);

        GenerateSeoDailyCardReport::dispatchSync($card->id);

        Mail::assertSent(SeoDailyCardReportMail::class, 2);
        Mail::assertSent(SeoDailyCardReportMail::class, fn ($mail) => $mail->hasTo($hod->email));
        Mail::assertSent(SeoDailyCardReportMail::class, fn ($mail) => $mail->hasTo($ceo->email));
    }

    public function test_an_additional_recipient_configured_without_a_code_change_also_receives_the_report(): void
    {
        Mail::fake();

        $department = $this->seoDepartment();
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post("/seo-board/settings/notifications/{$department->id}", [
            'email' => 'extra-recipient@exotic-online.com',
            'label' => 'Regional director',
        ])->assertRedirect();

        $this->assertDatabaseHas('department_notification_recipients', ['department_id' => $department->id, 'email' => 'extra-recipient@exotic-online.com']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'department_notification_recipient.added']);

        $employeeUser = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $department->id]);
        $card = $this->cardFor($employee, $department);

        GenerateSeoDailyCardReport::dispatchSync($card->id);

        Mail::assertSent(SeoDailyCardReportMail::class, fn ($mail) => $mail->hasTo('extra-recipient@exotic-online.com'));
        $this->assertDatabaseHas('report_deliveries', ['recipient_email' => 'extra-recipient@exotic-online.com']);
    }

    public function test_removing_a_recipient_is_audited_and_stops_future_reports_without_a_code_change(): void
    {
        Mail::fake();

        $department = $this->seoDepartment();
        $admin = User::factory()->create()->assignRole('Administrator');

        $recipient = DepartmentNotificationRecipient::query()->create([
            'department_id' => $department->id, 'email' => 'leaving@exotic-online.com', 'is_active' => true, 'effective_from' => now(),
        ]);

        $this->actingAs($admin)->delete("/seo-board/settings/notifications-recipients/{$recipient->id}")->assertRedirect();

        $recipient->refresh();
        $this->assertFalse($recipient->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'department_notification_recipient.deactivated']);

        $employeeUser = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $department->id]);
        $card = $this->cardFor($employee, $department);

        GenerateSeoDailyCardReport::dispatchSync($card->id);

        Mail::assertNotSent(SeoDailyCardReportMail::class, fn ($mail) => $mail->hasTo('leaving@exotic-online.com'));
    }
}
