<?php

namespace App\Services\WordPress;

use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use Illuminate\Support\Facades\DB;

class WordPressUserSync
{
    /** @return array{status: 'ok'|'error', count?: int, error?: string} */
    public function syncWebsite(WebsiteWordPressCredential $credential): array
    {
        $client = new WordPressUserClient($credential);
        $result = $client->fetchAllUsers();

        if ($result['status'] === 'error') {
            $credential->update([
                'status' => WebsiteWordPressCredential::STATUS_ERROR,
                'last_error' => $result['error'],
                'last_synced_at' => now(),
            ]);

            return $result;
        }

        DB::transaction(function () use ($credential, $result) {
            $seenWpUserIds = collect($result['users'])->pluck('id');

            WordPressUser::query()
                ->where('website_id', $credential->website_id)
                ->whereNotIn('wp_user_id', $seenWpUserIds)
                ->delete();

            foreach ($result['users'] as $user) {
                WordPressUser::query()->updateOrCreate(
                    ['website_id' => $credential->website_id, 'wp_user_id' => $user['id']],
                    [
                        'username' => $user['username'] ?? $user['slug'],
                        'email' => $user['email'] ?? null,
                        'display_name' => $user['name'] ?? null,
                        'roles' => $user['roles'] ?? [],
                        'wp_registered_at' => $user['registered_date'] ?? null,
                        'synced_at' => now(),
                    ],
                );
            }
        });

        $credential->update([
            'status' => WebsiteWordPressCredential::STATUS_OK,
            'last_error' => null,
            'last_verified_at' => now(),
            'last_synced_at' => now(),
        ]);

        return ['status' => 'ok', 'count' => count($result['users'])];
    }
}
