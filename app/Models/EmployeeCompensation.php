<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    protected $table = 'employee_compensation';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'basic_salary' => 'decimal:2',
            'allowances' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return list<array{name: string, amount: float, taxable: bool, pensionable: bool}> */
    public function allowanceLines(): array
    {
        return array_map(fn (array $a) => [
            'name' => $a['name'] ?? 'Allowance',
            'amount' => (float) ($a['amount'] ?? 0),
            'taxable' => (bool) ($a['taxable'] ?? true),
            'pensionable' => (bool) ($a['pensionable'] ?? false),
        ], $this->allowances ?? []);
    }
}
