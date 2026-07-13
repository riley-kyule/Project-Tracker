<?php

namespace App\Services\Registry;

use App\Models\Country;
use App\Models\Website;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Support\Str;

/**
 * Pulls the domain/name/country registry EWMS's own BigQuery views join
 * against (see WebsiteRegistryQuery) into the local `websites` table, so the
 * admin website list and the assignment/reporting features below it don't
 * require re-entering ~70 sites by hand.
 *
 * Matches by `domain` (see the unique index added alongside this class).
 * Country is matched by name against the existing `countries` table only —
 * this never invents an ISO code, so an unmatched country name is recorded
 * on the website's `metadata` instead of fabricating a Country row.
 */
class WebsiteRegistrySync
{
    public function __construct(private WebsiteRegistryQuery $registry) {}

    /** @return array{created: int, updated: int, total: int} */
    public function sync(): array
    {
        $rows = $this->registry->websites();
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $country = $row['country'] ? $this->findCountry($row['country']) : null;
            $unmatchedCountryName = $row['country'] && ! $country ? $row['country'] : null;

            $website = Website::query()->updateOrCreate(
                ['domain' => $row['domain']],
                [
                    'name' => $row['name'],
                    'country_id' => $country?->id,
                    'metadata' => $unmatchedCountryName ? ['registry_country' => $unmatchedCountryName] : null,
                    'synced_from_registry_at' => now(),
                ],
            );

            $website->wasRecentlyCreated ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated, 'total' => count($rows)];
    }

    private function findCountry(string $name): ?Country
    {
        return Country::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
    }
}
