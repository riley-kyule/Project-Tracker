<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ewms-import');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_imports_users_with_role_and_department()
    {
        $path = $this->csv(<<<'CSV'
name,email,department,job_title,role
Jane Doe,jane@example.com,IT,Systems Admin,IT Technician
John Smith,john@example.com,SEO,,
CSV);

        $this->artisan('users:import', ['path' => $path])->assertSuccessful();

        $jane = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($jane->hasRole('IT Technician'));
        $this->assertSame('it', $jane->department->slug);
        $this->assertSame('Systems Admin', $jane->job_title);

        $john = User::query()->where('email', 'john@example.com')->firstOrFail();
        $this->assertTrue($john->hasRole('Employee'));

        unlink($path);
    }

    public function test_dry_run_creates_no_users()
    {
        $path = $this->csv("name,email\nJane Doe,jane@example.com\n");

        $this->artisan('users:import', ['path' => $path, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        unlink($path);
    }

    public function test_skips_duplicates_and_unknown_departments()
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $path = $this->csv(<<<'CSV'
name,email,department,job_title,role
Jane Doe,jane@example.com,,,
New Person,new@example.com,Nonexistent,,
CSV);

        $this->artisan('users:import', ['path' => $path])->assertFailed();

        $this->assertSame(1, User::query()->count());
        unlink($path);
    }
}
