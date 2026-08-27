<?php

namespace Tests\Unit\Services\WordPress;

use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use App\Services\WordPress\WordPressUserClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressUserClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(): WordPressUserClient
    {
        return $this->clientForDomain('https://example-site.test');
    }

    private function clientForDomain(string $domain): WordPressUserClient
    {
        $site = WordPressSite::factory()->create(['domain' => $domain]);
        $credential = WordPressCredential::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);

        return new WordPressUserClient($credential);
    }

    /**
     * Regression test: wordpress_sites.domain is stored bare (e.g. "example.com")
     * rather than as a full URL. Without a scheme, Http::baseUrl() throws Guzzle's
     * "URI must include a scheme and host" on every call — this is exactly the
     * error reported when adding an Application Password for a normally-entered site.
     */
    public function test_a_bare_domain_without_a_scheme_still_works()
    {
        Http::fake(['*/wp-json/wp/v2/users*' => Http::response([['id' => 1, 'username' => 'jdoe', 'roles' => ['administrator']]], 200)]);

        $result = $this->clientForDomain('example.com')->fetchAllUsers();

        $this->assertSame('ok', $result['status']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://example.com/wp-json/wp/v2/users'));
    }

    /**
     * Regression test: fetchAllUsers() must filter server-side rather than
     * paginating through every account a site has — some connected sites are
     * membership/customer platforms with thousands of non-staff users, and
     * pulling all of them (confirmed in production: one site's sync reached
     * page 10, ~900 users, before timing out) is both wasteful and slow
     * enough to look like scraping to a site's WAF.
     */
    public function test_the_search_param_scopes_the_request_to_the_staff_domain()
    {
        Http::fake(['*/wp-json/wp/v2/users*' => Http::response([], 200)]);

        $this->client()->fetchAllUsers();

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['search'] ?? null) === '@exotic-online.com';
        });
    }

    public function test_a_full_page_triggers_fetching_the_next_page()
    {
        $page1 = array_map(fn ($i) => ['id' => $i, 'username' => "user{$i}", 'roles' => ['subscriber']], range(1, 100));
        $page2 = [['id' => 101, 'username' => 'user101', 'roles' => ['subscriber']]];

        Http::fake(function ($request) use ($page1, $page2) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return match ($query['page'] ?? null) {
                '1' => Http::response($page1, 200),
                '2' => Http::response($page2, 200),
                default => Http::response([], 200),
            };
        });

        $result = $this->client()->fetchAllUsers();

        $this->assertSame('ok', $result['status']);
        $this->assertCount(101, $result['users']);
        Http::assertSentCount(2);
    }

    public function test_a_short_page_does_not_trigger_another_request()
    {
        $page = [['id' => 1, 'username' => 'solo', 'roles' => ['subscriber']]];

        Http::fake(['*/wp-json/wp/v2/users*' => Http::response($page, 200)]);

        $result = $this->client()->fetchAllUsers();

        $this->assertSame('ok', $result['status']);
        $this->assertCount(1, $result['users']);
        Http::assertSentCount(1);
    }

    public function test_a_malformed_200_response_is_treated_as_an_error_not_zero_users()
    {
        // A non-JSON 200 body (a WAF interstitial, a misconfigured proxy) must
        // never be read as "this site has zero users" — that would let
        // WordPressUserSync wipe out the site's entire cached roster.
        Http::fake(['*/wp-json/wp/v2/users*' => Http::response('not json', 200, ['Content-Type' => 'text/plain'])]);

        $result = $this->client()->fetchAllUsers();

        $this->assertSame('error', $result['status']);
    }

    public function test_pagination_stops_at_the_hard_page_ceiling()
    {
        // A site that keeps returning full pages forever (misbehaving REST API,
        // an unexpectedly huge table) must not loop until memory is exhausted.
        Http::fake(['*/wp-json/wp/v2/users*' => Http::response(
            array_map(fn ($i) => ['id' => $i, 'username' => "user{$i}", 'roles' => ['subscriber']], range(1, 100)),
            200,
        )]);

        $result = $this->client()->fetchAllUsers();

        $this->assertSame('ok', $result['status']);
        $this->assertCount(200 * 100, $result['users']);
        Http::assertSentCount(200);
    }

    public function test_a_connection_failure_returns_a_structured_error_instead_of_throwing()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $result = $this->client()->fetchAllUsers();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Connection refused', $result['error']);
    }
}
