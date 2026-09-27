<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Editing a sale goes through PUT /api/sales/{id}, from the web page and from
 * the mobile app. That route only lives in routes/api.php, so any deployment
 * that has not picked the file up answers
 * "The route api/sales/<id> could not be found." while every other sales route
 * keeps working. This guard fails loudly if the route is dropped again.
 */
class SaleUpdateRouteTest extends TestCase
{
    public function test_sales_update_route_is_registered_for_put(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/sales/{id}'
                && in_array('PUT', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'PUT api/sales/{id} must stay registered for sale edits.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
