<?php

namespace App\Services\WordPress;

use App\Models\WordPressCredential;
use App\Models\WordPressUser;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Mutation-side counterpart to WordPressUserSync: every method groups its
 * targets by site (one WordPressUserClient built per site, reused across
 * that site's rows) and returns a per-row result array so the controller can
 * report partial failure across many sites rather than a single pass/fail
 * flag — that's the expected common case here, not the exception.
 */
class WordPressUserBulkAction
{
    /** @return array<int, array{site_id: int, site: string, status: string, error?: string}> */
    public function add(array $siteIds, string $username, string $email, string $password, array $roles): array
    {
        $results = [];

        $credentials = WordPressCredential::query()->with('site')->whereIn('wordpress_site_id', $siteIds)->get();

        foreach ($credentials as $credential) {
            $client = new WordPressUserClient($credential);
            $result = $client->createUser([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'roles' => $roles,
            ]);

            if ($result['status'] === 'ok') {
                $wpUser = WordPressUser::query()->create([
                    'wordpress_site_id' => $credential->wordpress_site_id,
                    'wp_user_id' => $result['wp_user_id'],
                    'username' => $username,
                    'email' => $email,
                    'display_name' => $username,
                    'roles' => $roles,
                    'synced_at' => now(),
                ]);

                AuditLogger::log($wpUser, 'wp_user_created', [], ['username' => $username, 'email' => $email, 'roles' => $roles]);
            }

            $results[] = [
                'site_id' => $credential->wordpress_site_id,
                'site' => $credential->site->name,
                'status' => $result['status'],
                'error' => $result['error'] ?? null,
            ];
        }

        return $results;
    }

    /** @return array<int, array{id: int, status: string, error?: string}> */
    public function changeRole(Collection $wordpressUsers, array $roles): array
    {
        return $this->perUser($wordpressUsers, function (WordPressUser $wpUser, WordPressUserClient $client) use ($roles) {
            $result = $client->updateUser($wpUser->wp_user_id, ['roles' => $roles]);

            if ($result['status'] === 'ok') {
                $oldRoles = $wpUser->roles;
                $wpUser->update(['roles' => $roles]);
                AuditLogger::log($wpUser, 'wp_user_role_changed', ['roles' => $oldRoles], ['roles' => $roles]);
            }

            return $result;
        });
    }

    /** @param array<int, array{id: int, email: string}> $updates @return array<int, array{id: int, status: string, error?: string}> */
    public function updateEmail(Collection $wordpressUsers, array $updates): array
    {
        $emailById = collect($updates)->pluck('email', 'id');

        return $this->perUser($wordpressUsers, function (WordPressUser $wpUser, WordPressUserClient $client) use ($emailById) {
            $newEmail = $emailById->get($wpUser->id);
            $result = $client->updateUser($wpUser->wp_user_id, ['email' => $newEmail]);

            if ($result['status'] === 'ok') {
                $oldEmail = $wpUser->email;
                $wpUser->update(['email' => $newEmail]);
                AuditLogger::log($wpUser, 'wp_user_email_changed', ['email' => $oldEmail], ['email' => $newEmail]);
            }

            return $result;
        });
    }

    /** @return array<int, array{id: int, status: string, error?: string}> */
    public function delete(Collection $wordpressUsers): array
    {
        return $this->perUser($wordpressUsers, function (WordPressUser $wpUser, WordPressUserClient $client) {
            $result = $client->deleteUser($wpUser->wp_user_id);

            if ($result['status'] === 'ok') {
                AuditLogger::log($wpUser, 'wp_user_deleted', ['username' => $wpUser->username, 'email' => $wpUser->email], []);
                $wpUser->delete();
            }

            return $result;
        });
    }

    private function perUser(Collection $wordpressUsers, callable $action): array
    {
        $results = [];
        $clientsBySite = [];

        foreach ($wordpressUsers->groupBy('wordpress_site_id') as $siteId => $group) {
            $credential = WordPressCredential::query()->with('site')->where('wordpress_site_id', $siteId)->first();

            if (! $credential) {
                foreach ($group as $wpUser) {
                    $results[] = ['id' => $wpUser->id, 'status' => 'error', 'error' => 'No WordPress credentials configured for this site.'];
                }

                continue;
            }

            $clientsBySite[$siteId] ??= new WordPressUserClient($credential);
            $client = $clientsBySite[$siteId];

            foreach ($group as $wpUser) {
                $result = $action($wpUser, $client);

                $results[] = [
                    'id' => $wpUser->id,
                    'status' => $result['status'],
                    'error' => $result['error'] ?? null,
                ];
            }
        }

        return $results;
    }
}
