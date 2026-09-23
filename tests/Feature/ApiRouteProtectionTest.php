<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiRouteProtectionTest extends TestCase
{
    public function test_unauthenticated_api_status_update_returns_json_instead_of_a_login_redirect(): void
    {
        $this->patch('/api/v1/user/1/status', ['status' => 'activated'], ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertJsonPath('message', __('notifications.401_description'));
    }

    public function test_removed_bank_card_resource_is_not_registered(): void
    {
        $bankCardRoutes = collect(app('router')->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), 'api/v1/bank-card'));

        $this->assertCount(0, $bankCardRoutes);
    }

    public function test_only_explicitly_allowed_api_routes_are_public(): void
    {
        $publicRoutes = [
            'POST api/v1/user/login',
            'POST api/v1/user/entrepreneurs',
            'GET api/v1/user/username/{username}',
            'GET api/v1/user/search/by-word',
            'POST api/v1/password-reset/find-user',
            'GET api/v1/media/popular/list',
            'GET api/v1/product/popular/list',
            'GET api/v1/comment/news-feed',
            'POST api/payment/store',
        ];

        $apiRoutes = collect(app('router')->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), 'api/v1/') || $route->uri() === 'api/payment/store');

        foreach ($apiRoutes as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routeKey = "{$method} {$route->uri()}";

                if (in_array($routeKey, $publicRoutes, true)) {
                    $this->assertNotContains('auth:sanctum', $route->middleware(), $routeKey);

                    continue;
                }

                $this->assertContains('auth:sanctum', $route->middleware(), $routeKey);
            }
        }
    }
}
