<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AiNlEntryController;
use App\Services\Ai\NlEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #25 - Schema-Free Natural-Language Data Entry, at the HTTP seam.
 *
 * What these tests pin:
 *  - the three routes stay registered exactly once and behind auth.jwt: a
 *    sentence that can write to a business must never be reachable anonymously;
 *  - a preview is READ-ONLY and always explains itself: a sentence that is not
 *    one of the four record types comes back as `unsupported` with a localized
 *    explanation instead of a guess;
 *  - the response contract is stable (type, preview, flags, missing_required,
 *    resolution, the existing workflow the commit would use);
 *  - a commit needs an explicit confirmation, an allow-listed type, a staff
 *    role and a well-formed idempotency key - and it refuses to forward an id
 *    that does not belong to the caller's business;
 *  - the catalogue being unreadable is REPORTED (`resolution.catalogue.degraded`)
 *    so an outage can never look like "you have no such product".
 *
 * NOTE: the suite is hermetic (phpunit.xml blanks SUPABASE_*), so anything that
 * reads real rows cannot run here. That is deliberate - these tests pin the
 * CONTRACT, the translation layer covers the extraction rules, and the live
 * probe against the real catalogue was run by hand. Because of that, the
 * controller is called directly with the JWT attributes the middleware would set
 * (the middleware itself needs a live user row).
 */
class AiNlEntryRouteTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** A request shaped exactly like one the auth.jwt middleware hands over. */
    private function authedRequest(array $payload = [], string $role = 'seller'): Request
    {
        $request = Request::create('/api/ai/entry/preview', 'POST', $payload);
        $request->attributes->set('jwt_user_id', 'user-1');
        $request->attributes->set('jwt_role', $role);
        $request->attributes->set('jwt_business_id', 'biz-1');
        $request->attributes->set('jwt_business_name', 'Duka la Amina');
        $request->attributes->set('jwt_language', 'sw');
        $request->attributes->set('jwt_ip', '10.0.0.1');

        return $request;
    }

    private function controller(): AiNlEntryController
    {
        return new AiNlEntryController;
    }

    /** The offline path must never depend on the provider being reachable. */
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => false]);
    }

    /* ------------------------------------------------------------------ */
    /* Routes                                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_entry_routes_are_registered_once_and_behind_auth(): void
    {
        $expected = [
            'api/ai/entry/examples' => 'GET',
            'api/ai/entry/preview' => 'POST',
            'api/ai/entry/commit' => 'POST',
        ];

        foreach ($expected as $uri => $method) {
            $matches = collect(Route::getRoutes()->getRoutes())->filter(
                fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
            );

            $this->assertCount(1, $matches, "{$method} {$uri} must stay registered exactly once.");
            $this->assertStringContainsString(
                'auth.jwt',
                implode(',', $matches->first()->gatherMiddleware()),
                "{$uri} writes business data and must never be public."
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* GET examples                                                       */
    /* ------------------------------------------------------------------ */

    public function test_the_examples_endpoint_lists_the_allow_listed_types_and_localizes(): void
    {
        $english = $this->controller()->examples($this->authedRequest(['locale' => 'en']))->getData(true);

        $this->assertTrue($english['success']);
        $this->assertSame('entry', $english['capability']);
        $this->assertSame(NlEntry::TYPES, array_column($english['types'], 'type'));
        $this->assertNotEmpty($english['examples']['product']);
        $this->assertNotEmpty($english['categories']);
        $this->assertNotEmpty($english['limitations']);
        $this->assertArrayHasKey('confirm', $english['messages']);

        $chinese = $this->controller()->examples($this->authedRequest(['locale' => 'zh']))->getData(true);
        $this->assertSame('zh', $chinese['locale']);
        $this->assertNotSame($english['messages']['confirm'], $chinese['messages']['confirm']);
    }

    /* ------------------------------------------------------------------ */
    /* POST preview                                                       */
    /* ------------------------------------------------------------------ */

    public function test_preview_requires_a_sentence(): void
    {
        $response = $this->controller()->preview($this->authedRequest(['text' => '   ']));

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('TEXT_REQUIRED', $data['code']);
        $this->assertNotEmpty($data['examples'], 'A refusal must show what a good sentence looks like.');
    }

    public function test_a_sentence_that_is_not_one_of_the_four_types_is_explained_not_guessed(): void
    {
        $response = $this->controller()->preview($this->authedRequest(['text' => 'The weather is nice today', 'locale' => 'en']));
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('unsupported', $data['status']);
        $this->assertNull($data['type']);
        $this->assertNull($data['preview'], 'Nothing may be produced when the type is unknown.');
        $this->assertNotSame('', trim((string) $data['explanation']));
        $this->assertSame(NlEntry::TYPES, array_column($data['types'], 'type'));
    }

    public function test_a_product_sentence_returns_the_documented_preview_contract(): void
    {
        $response = $this->controller()->preview($this->authedRequest([
            'text' => 'Add 20 bottles of blue perfume to stock',
            'locale' => 'en',
        ]));
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('product', $data['type']);
        $this->assertSame('Product', $data['type_label']);

        foreach (['status', 'type', 'type_label', 'text', 'preview', 'resolution', 'flags', 'missing_required', 'classification', 'messages', 'examples', 'limitations', 'commit'] as $key) {
            $this->assertArrayHasKey($key, $data, "{$key} must be present.");
        }

        $this->assertSame('blue perfume', $data['preview']['name']);
        $this->assertSame(20, $data['preview']['stock']);
        $this->assertSame('bottle', $data['preview']['unit']);
        $this->assertTrue($data['preview_editable']);
        $this->assertContains('price', $data['missing_required']);
        $this->assertNotEmpty($data['flags'], 'Every missing field must carry a localized message.');

        foreach ($data['flags'] as $flag) {
            $this->assertNotSame('', trim($flag['message']));
            $this->assertNotSame($flag['code'], $flag['message']);
        }

        $this->assertSame('/api/ai/entry/commit', $data['commit']['endpoint']);
        $this->assertSame('/api/products', $data['commit']['workflow'], 'The preview must say which existing workflow would write it.');
    }

    /**
     * The hermetic suite cannot read the catalogue, so it must say so rather
     * than let an outage look like "your product does not exist".
     */
    public function test_an_unreadable_catalogue_is_reported_not_hidden(): void
    {
        $data = $this->controller()->preview($this->authedRequest([
            'text' => 'I sold three units of soap for 2,000 each',
            'locale' => 'en',
        ]))->getData(true);

        $this->assertSame('sale', $data['type']);
        $this->assertTrue($data['resolution']['catalogue']['degraded']);
        $this->assertNotSame('', trim((string) $data['resolution']['catalogue']['degraded_reason']));
        $this->assertSame('not_found', $data['resolution']['items'][0]['match']['status']);
        $this->assertNull($data['resolution']['items'][0]['product_id'], 'A reference that could not be checked must never gain an id.');
    }

    public function test_every_locale_gets_its_own_messages_from_the_preview(): void
    {
        $messages = [];

        foreach (self::LOCALES as $locale) {
            $data = $this->controller()->preview($this->authedRequest([
                'text' => 'Add 20 bottles of blue perfume to stock',
                'locale' => $locale,
            ]))->getData(true);

            $this->assertSame($locale, $data['locale']);
            $messages[$locale] = $data['messages']['preview_title'];
            $this->assertNotSame('', trim((string) $messages[$locale]), "{$locale} needs a preview title.");
        }

        $this->assertGreaterThan(4, count(array_unique($messages)), 'The chrome must really be translated.');
    }

    public function test_the_capability_can_be_switched_off_explicitly(): void
    {
        config(['ai.features.nl_entry' => false]);

        $this->assertSame(403, $this->controller()->preview($this->authedRequest(['text' => 'Add 20 bottles to stock']))->getStatusCode());
        $this->assertSame(403, $this->controller()->commit($this->authedRequest(['confirm' => true, 'type' => 'product']))->getStatusCode());
        $this->assertSame(403, $this->controller()->examples($this->authedRequest())->getStatusCode());
    }

    /* ------------------------------------------------------------------ */
    /* POST commit - every gate before anything is written                */
    /* ------------------------------------------------------------------ */

    public function test_commit_requires_an_explicit_confirmation(): void
    {
        foreach ([null, false, 'yes', 0, ''] as $confirm) {
            $response = $this->controller()->commit($this->authedRequest([
                'confirm' => $confirm,
                'type' => 'expense',
                'idempotency_key' => 'key-123456',
                'preview' => ['amount' => 5000, 'description' => 'transport', 'category' => 'Usafiri'],
            ]));

            $this->assertSame(422, $response->getStatusCode(), 'Confirm='.var_export($confirm, true).' must not commit.');
            $this->assertSame('CONFIRMATION_REQUIRED', $response->getData(true)['code']);
        }
    }

    public function test_commit_only_accepts_the_four_record_types(): void
    {
        foreach (['invoice', 'DROP TABLE users', ''] as $type) {
            $response = $this->controller()->commit($this->authedRequest([
                'confirm' => true,
                'type' => $type,
                'idempotency_key' => 'key-123456',
            ]));

            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame('TYPE_REQUIRED', $response->getData(true)['code']);
        }
    }

    public function test_commit_requires_a_role_that_may_record_data(): void
    {
        $response = $this->controller()->commit($this->authedRequest([
            'confirm' => true,
            'type' => 'product',
            'idempotency_key' => 'key-123456',
        ], 'customer'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN_ROLE', $response->getData(true)['code']);
    }

    public function test_commit_requires_a_well_formed_idempotency_key(): void
    {
        foreach (['', 'short', 'has spaces', str_repeat('x', 200)] as $key) {
            $response = $this->controller()->commit($this->authedRequest([
                'confirm' => true,
                'type' => 'expense',
                'idempotency_key' => $key,
                'preview' => ['amount' => 5000, 'description' => 'transport', 'category' => 'Usafiri'],
            ]));

            $this->assertSame(422, $response->getStatusCode(), "Key '" . substr($key, 0, 12) . "' must be refused.");
            $this->assertSame('IDEMPOTENCY_KEY_REQUIRED', $response->getData(true)['code']);
        }
    }

    public function test_commit_reports_the_missing_required_fields_instead_of_writing_half_a_record(): void
    {
        $response = $this->controller()->commit($this->authedRequest([
            'confirm' => true,
            'type' => 'expense',
            'idempotency_key' => 'key-123456',
            'preview' => ['amount' => null, 'description' => '', 'category' => ''],
            'locale' => 'en',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['committed']);
        $this->assertSame('MISSING_FIELDS', $data['code']);
        $this->assertSame(['amount', 'description', 'category'], $data['missing_required']);
    }

    /**
     * The security rule that matters most here: an id the client sends back is
     * re-verified against the caller's own business. A product from another
     * shop is refused, never relayed to the sale endpoint.
     */
    public function test_a_sale_item_must_belong_to_the_callers_own_catalogue(): void
    {
        $response = $this->controller()->commit($this->authedRequest([
            'confirm' => true,
            'type' => 'sale',
            'idempotency_key' => 'key-123456',
            'preview' => [
                'items' => [['product_id' => 'someone-elses-product', 'quantity' => 2, 'unit_price' => 1000]],
                'sale_date' => gmdate('Y-m-d'),
                'payment_method' => 'cash',
            ],
            'locale' => 'en',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['committed']);
        $this->assertSame('PRODUCT_NOT_RESOLVED', $data['code']);
        $this->assertContains('items[0].product_id', $data['missing_required']);
    }

    /**
     * A confirmed expense with valid fields must reach the EXISTING workflow -
     * and with Supabase blanked here that workflow itself fails, which must be
     * reported as the workflow's own rejection, not as a silent success.
     */
    public function test_a_confirmed_expense_is_forwarded_to_the_existing_workflow(): void
    {
        $response = $this->controller()->commit($this->authedRequest([
            'confirm' => true,
            'type' => 'expense',
            'idempotency_key' => 'key-654321',
            'preview' => ['amount' => 5000, 'description' => 'transport to town', 'category' => 'Usafiri'],
            'locale' => 'en',
        ]));

        $this->assertContains($response->getStatusCode(), [200, 201, 500, 503]);
        $data = $response->getData(true);

        $this->assertSame('expense', $data['type']);

        if ($data['success'] === true) {
            $this->assertTrue($data['committed']);
            $this->assertSame('/api/office-expenses', $data['workflow']['endpoint']);
            $this->assertSame('key-654321', $data['idempotency_key']);
        } else {
            // The hermetic suite cannot write: the response must SAY that the
            // existing workflow rejected it rather than pretending otherwise.
            $this->assertFalse($data['committed']);
            $this->assertSame('WORKFLOW_REJECTED', $data['code']);
            $this->assertSame('/api/office-expenses', $data['workflow']['endpoint']);
        }
    }

    /* ------------------------------------------------------------------ */
    /* The write itself: once, after confirmation, and never twice        */
    /* ------------------------------------------------------------------ */

    /**
     * PostgREST faked in memory, but through the REAL controller code path: the
     * fakes count every INSERT the capability causes. phpunit.xml blanks
     * SUPABASE_*, so this is the only way the suite can observe a write here.
     *
     * The sinks are ArrayObjects because the arrays themselves would be copied
     * by value at the moment of return - the fakes fill them in later.
     *
     * @return array{0: \ArrayObject<int, array<string, mixed>>, 1: \ArrayObject<int, array<string, mixed>>}
     */
    private function fakePostgrest(): array
    {
        $ledger = new \ArrayObject;
        $writes = new \ArrayObject;

        Http::fake(function ($request) use ($ledger, $writes) {
            $url = $request->url();
            $method = $request->method();

            // The idempotency ledger: user_logs IS the durable store. (Match on
            // the table alone: with SUPABASE_URL blank in tests the client
            // prefixes the path twice, which production never does.)
            if (str_contains($url, 'user_logs')) {
                if ($method === 'POST') {
                    $row = (array) $request->data();
                    $ledger[] = $row;

                    return Http::response([$row], 201);
                }

                // Emulate the one filter that matters: PostgREST only returns
                // rows whose action matches the query.
                $rows = $ledger->getArrayCopy();
                if (preg_match('/action=eq\.([^&]+)/', $url, $m) === 1) {
                    $wanted = urldecode($m[1]);
                    $rows = array_values(array_filter(
                        $rows,
                        static fn (array $row): bool => ($row['action'] ?? null) === $wanted
                    ));
                }

                return Http::response($rows, 200);
            }

            if (str_contains($url, 'users')) {
                return Http::response([['business_name' => 'Duka la Amina']], 200);
            }

            // The EXISTING expense workflow the commit forwards to.
            if (str_contains($url, 'office_expenses')) {
                $body = (array) $request->data();
                $writes[] = $body;

                return Http::response([array_merge($body, ['id' => 'expense-1'])], 201);
            }

            return Http::response([], 200);
        });

        return [$writes, $ledger];
    }

    /**
     * `forward()` reads the CURRENT request (as production's JWT middleware
     * leaves it), so the synthesized request has to be the container's request
     * for the forwarded workflow to see the caller's identity.
     */
    private function commitThroughTheRealSeam(array $payload): \Illuminate\Http\JsonResponse
    {
        $request = $this->authedRequest($payload);
        $this->app->instance('request', $request);

        return $this->controller()->commit($request);
    }

    public function test_an_unconfirmed_commit_never_reaches_a_write_endpoint(): void
    {
        [$writes] = $this->fakePostgrest();

        $response = $this->commitThroughTheRealSeam([
            'confirm' => false,
            'type' => 'expense',
            'idempotency_key' => 'attempt-0001',
            'preview' => ['amount' => 5000, 'description' => 'transport to town', 'category' => 'Usafiri'],
            'locale' => 'en',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('CONFIRMATION_REQUIRED', $response->getData(true)['code']);
        $this->assertCount(0, $writes, 'Nothing may be written before an explicit confirmation.');
    }

    /**
     * The guarantee behind "confirm, then we record it": one confirmed commit
     * creates exactly ONE record, and replaying the same idempotency key (the
     * double-tap, the retry after a slow response) returns the ORIGINAL result
     * instead of writing a second one.
     */
    public function test_a_confirmed_commit_writes_once_and_a_replayed_key_does_not_write_again(): void
    {
        [$writes, $ledger] = $this->fakePostgrest();

        $payload = [
            'confirm' => true,
            'type' => 'expense',
            'idempotency_key' => 'attempt-0001',
            'preview' => ['amount' => 5000, 'description' => 'transport to town', 'category' => 'Usafiri'],
            'locale' => 'en',
        ];

        $first = $this->commitThroughTheRealSeam($payload);
        $firstData = $first->getData(true);

        $this->assertSame(201, $first->getStatusCode());
        $this->assertTrue($firstData['committed']);
        $this->assertFalse($firstData['duplicate']);
        $this->assertSame('expense', $firstData['type']);
        $this->assertSame('/api/office-expenses', $firstData['workflow']['endpoint']);
        $this->assertSame('attempt-0001', $firstData['idempotency_key']);
        $this->assertSame('expense-1', $firstData['record']['id']);
        $this->assertCount(1, $writes, 'A confirmed commit must create exactly one record.');

        // The ledger row is what makes the replay safe. (The forwarded workflow
        // writes its own audit row too, so filter for the ledger entry.)
        $ledgerRows = fn (): array => array_values(array_filter(
            $ledger->getArrayCopy(),
            static fn (array $row): bool => ($row['action'] ?? '') === 'AI_ENTRY_COMMIT'
        ));

        $this->assertCount(1, $ledgerRows(), 'The committed attempt must be recorded in the idempotency ledger.');
        $this->assertStringContainsString('"attempt-0001"', (string) ($ledgerRows()[0]['details'] ?? ''));

        // The replay: same key, same user. No second write, the original result.
        $second = $this->commitThroughTheRealSeam($payload);
        $secondData = $second->getData(true);

        $this->assertSame(200, $second->getStatusCode());
        $this->assertTrue($secondData['committed']);
        $this->assertTrue($secondData['duplicate'], 'A replayed key must say it is a duplicate.');
        $this->assertSame($firstData['record'], $secondData['record'], 'The replay must return the original record.');
        $this->assertCount(1, $writes, 'A replayed key must not write a second record.');
        $this->assertCount(1, $ledgerRows(), 'A replayed key must not add a second ledger entry.');
    }
}
