<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Every FormRequest in this app defaults to authorize() => true (see
 * app/Http/Requests/*), so authorization for a mutating route depends
 * entirely on its controller action remembering to call an authorization
 * check. This is a static backstop for that single point of failure: it
 * doesn't verify the check is *correct*, only that a future action can't
 * silently ship with no authorization call at all.
 */
class ControllerAuthorizationCoverageTest extends TestCase
{
    /**
     * Routes that mutate state but are deliberately not policy-gated, with
     * the reason each is safe to skip.
     */
    private const EXEMPT = [
        'logout' => 'destroys the caller\'s own session only',
        'auth.google.callback' => 'OAuth callback, identity established by Google, not by policy',
        'verification.send' => 'resends a verification email to the caller\'s own address',
        'verification.verify' => 'signed URL middleware is the authorization',
        'profile.update' => 'mutates only $request->user() itself',
        'notifications.update' => 'mutates only $request->user()->notification_preferences',
        'notifications.read' => 'scoped via $request->user()->notifications()->findOrFail() — cannot target another user\'s row',
        'notifications.read-all' => 'scoped via $request->user()->unreadNotifications',
        'saved-filters.store' => 'always written with user_id = $request->user()->id, never client-supplied',
        'push.subscribe' => 'mutates only $request->user() itself',
    ];

    public function test_every_mutating_web_route_calls_an_authorization_check()
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array($route->methods()[0], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                continue;
            }

            $name = $route->getName();
            if ($name !== null && array_key_exists($name, self::EXEMPT)) {
                continue;
            }

            $action = $route->getActionName();
            if (! str_contains($action, '@') || ! str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue; // closures, or framework-internal routes (e.g. Fortify)
            }

            [$class, $method] = explode('@', $action);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            $source = $this->methodSource($class, $method);

            $hasAuthCall = preg_match(
                '/Gate::authorize\(|Gate::forUser\(|\$this->authorize\(|abort_unless\(|abort_if\(/',
                $source
            ) === 1;

            if (! $hasAuthCall) {
                $offenders[] = "{$name} ({$action})";
            }
        }

        $this->assertEmpty(
            $offenders,
            "Mutating routes with no visible authorization check:\n".implode("\n", $offenders)
            ."\n\nIf a route is genuinely safe without one, add it to ControllerAuthorizationCoverageTest::EXEMPT with a reason."
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }
}
