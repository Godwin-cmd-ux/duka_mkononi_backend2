<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Smart Expense Categorisation capability is only reachable through
 * POST /api/ai/expenses/parse, and always behind JWT auth. Saving stays with
 * the existing POST /api/office-expenses workflow, so a parse can never
 * write a record on its own. Fails loudly if the route is dropped or loses
 * its authentication middleware.
 */
class AiExpenseParseRouteTest extends TestCase
{
    public function test_expense_parse_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/expenses/parse'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/expenses/parse must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }

    public function test_expense_saving_stays_on_the_existing_workflow_route(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/office-expenses'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/office-expenses must remain the only write path.');
    }
}
