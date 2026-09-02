<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'user_id' => null,
            'staff_number' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'first_name' => $first,
            'last_name' => $last,
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'national_id_number' => (string) fake()->numberBetween(10000000, 39999999),
            'kra_pin' => 'A'.fake()->numerify('#########').'Z',
            'personal_email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('07########'),
            'job_title' => fake()->jobTitle(),
            'employment_type' => 'permanent',
            'date_hired' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'contract_start_date' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'payment_method' => 'bank',
            'bank_name' => fake()->randomElement(['Equity', 'KCB', 'Co-op', 'NCBA']),
            'bank_account_number' => (string) fake()->numberBetween(1000000000, 9999999999),
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'employment_status' => Employee::STATUS_TERMINATED,
            'termination_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'termination_reason' => 'Contract ended',
        ]);
    }
}
