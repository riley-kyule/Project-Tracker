<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PublicHoliday extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Y-m-d strings of every holiday falling within [$from, $to], expanding
     * recurring (fixed-date) holidays across each year in the range.
     *
     * @return list<string>
     */
    public static function datesBetween(Carbon $from, Carbon $to): array
    {
        $dates = [];

        foreach (static::all() as $holiday) {
            if ($holiday->is_recurring) {
                for ($year = $from->year; $year <= $to->year; $year++) {
                    $d = $holiday->date->copy()->year($year);
                    if ($d->betweenIncluded($from, $to)) {
                        $dates[] = $d->toDateString();
                    }
                }
            } elseif ($holiday->date->betweenIncluded($from, $to)) {
                $dates[] = $holiday->date->toDateString();
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Named occurrences within [$from, $to], recurring holidays expanded,
     * sorted by date.
     *
     * @return list<array{name: string, date: string}>
     */
    public static function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        $out = [];

        foreach (static::all() as $holiday) {
            $years = $holiday->is_recurring ? range($from->year, $to->year) : [$holiday->date->year];

            foreach ($years as $year) {
                $d = $holiday->date->copy()->year($year);
                if ($d->betweenIncluded($from, $to)) {
                    $out[] = ['name' => $holiday->name, 'date' => $d->toDateString()];
                }
            }
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }
}
