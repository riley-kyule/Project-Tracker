<?php

namespace App\Services\WordPress;

use App\Models\WebsiteWordPressCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to one WordPress site's REST API via Basic Auth over an Application
 * Password. Every method catches its own failures and returns a structured
 * ['status' => 'error', 'error' => ...] instead of throwing — this is the
 * per-site isolation primitive the rest of the WordPress integration builds
 * on, since one bad site (wrong credentials, blocked REST API, a timeout)
 * must never abort a batch spanning 100+ other sites.
 */
class WordPressUserClient
{
    private const PER_PAGE = 100;

    // A hard ceiling on pagination, independent of the per-request HTTP
    // timeout: a site returning a full page indefinitely (an unexpectedly
    // huge user table, or a REST API misbehaving under Basic Auth) would
    // otherwise loop until memory is exhausted rather than the job's own
    // timeout catching it. 200 pages * 100/page is far beyond any real
    // staff roster this feature targets.
    private const MAX_PAGES = 200;

    public function __construct(private readonly WebsiteWordPressCredential $credential) {}

    /** @return array{status: 'ok'|'error', users: array, error?: string} */
    public function fetchAllUsers(): array
    {
        $users = [];
        $page = 1;

        try {
            do {
                $response = $this->http()->get('/users', [
                    'context' => 'edit',
                    'per_page' => self::PER_PAGE,
                    'page' => $page,
                ]);

                if ($response->status() === 400 && $page > 1) {
                    // WordPress returns 400 rest_post_invalid_page_number past the last page.
                    break;
                }

                if ($response->failed()) {
                    return $this->error('fetchAllUsers', $response);
                }

                $batch = $response->json();

                if (! is_array($batch)) {
                    // A 200 with an unparseable/non-array body (a WAF interstitial,
                    // a misconfigured proxy) is not "zero users" — treating it as
                    // an empty page would let WordPressUserSync wipe out this
                    // site's entire cached roster on the next sync.
                    return $this->error('fetchAllUsers', $response);
                }

                $users = [...$users, ...$batch];
                $page++;
            } while (count($batch) === self::PER_PAGE && $page <= self::MAX_PAGES);

            return ['status' => 'ok', 'users' => $users];
        } catch (Throwable $e) {
            return $this->exceptionError('fetchAllUsers', $e);
        }
    }

    /** @return array{status: 'ok'|'error', wp_user_id?: int, error?: string} */
    public function createUser(array $attrs): array
    {
        try {
            $response = $this->http()->post('/users', $attrs);

            if ($response->failed()) {
                return $this->error('createUser', $response);
            }

            return ['status' => 'ok', 'wp_user_id' => $response->json('id')];
        } catch (Throwable $e) {
            return $this->exceptionError('createUser', $e);
        }
    }

    /** @return array{status: 'ok'|'error', error?: string} */
    public function updateUser(int $wpUserId, array $attrs): array
    {
        try {
            // WordPress REST uses POST, not PUT, for partial updates to a user.
            $response = $this->http()->post("/users/{$wpUserId}", $attrs);

            if ($response->failed()) {
                return $this->error('updateUser', $response);
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return $this->exceptionError('updateUser', $e);
        }
    }

    /** @return array{status: 'ok'|'error', error?: string} */
    public function deleteUser(int $wpUserId, ?int $reassignTo = null): array
    {
        try {
            $response = $this->http()->delete("/users/{$wpUserId}", array_filter([
                'force' => true,
                'reassign' => $reassignTo,
            ], fn ($value) => $value !== null));

            if ($response->failed()) {
                return $this->error('deleteUser', $response);
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return $this->exceptionError('deleteUser', $e);
        }
    }

    /** Lightweight ping used by the "Test connection" action. */
    public function verifyCredentials(): array
    {
        try {
            $response = $this->http()->get('/users', ['context' => 'edit', 'per_page' => 1]);

            if ($response->failed()) {
                return $this->error('verifyCredentials', $response);
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return $this->exceptionError('verifyCredentials', $e);
        }
    }

    private function http(): PendingRequest
    {
        $domain = rtrim($this->credential->website->domain ?? '', '/');

        return Http::withBasicAuth($this->credential->wp_username, $this->credential->wp_app_password)
            ->baseUrl("{$domain}/wp-json/wp/v2")
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function error(string $method, Response $response): array
    {
        $message = $response->json('message') ?? "HTTP {$response->status()}";

        Log::warning('WordPress API call failed', [
            'website_id' => $this->credential->website_id,
            'method' => $method,
            'status' => $response->status(),
            'error' => $message,
        ]);

        return ['status' => 'error', 'error' => $message];
    }

    private function exceptionError(string $method, Throwable $e): array
    {
        Log::warning('WordPress API call failed', [
            'website_id' => $this->credential->website_id,
            'method' => $method,
            'error' => $e->getMessage(),
        ]);

        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}
