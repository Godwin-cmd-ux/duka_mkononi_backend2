<?php

namespace Tests\Unit;

use App\Services\Ai\Embeddings;
use App\Services\Ai\ProductSearch;
use Tests\TestCase;

/**
 * Capability #24 - ranked, typo-tolerant search over real catalogue row shapes.
 *
 * What these tests pin:
 *  - the catalogue's own example ("brake pads for premio") finds a product whose
 *    name puts the words in a different order, and a misspelling still finds it;
 *  - synonyms cross languages in both directions (mchele <-> rice);
 *  - results are RANKED and the ranking is explainable (`relevance`,
 *    `matched_on`, `search_signals`);
 *  - the existing filters still apply (business scope, availability);
 *  - retrieval is bounded and reports that it was (max_candidates/max_results,
 *    `truncated`);
 *  - a query with no plausible match returns nothing rather than noise;
 *  - THE CRITICAL ONE: with embeddings off - or on but unusable - the search
 *    still returns useful lexical results (it must never degrade to empty).
 */
class ProductSearchTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1',
            'name' => '',
            'description' => null,
            'category' => null,
            'image_url' => null,
            'unit' => null,
            'selling_price' => 1000.0,
            'currency' => 'TZS',
            'stock' => 5,
            'available' => true,
            'business_id' => 'b1',
            'business_name' => 'Shop One',
            'business_location' => null,
            'business_logo_url' => null,
            'business_is_certified' => false,
        ], $overrides);
    }

    public function test_the_catalogue_example_finds_the_product_despite_word_order(): void
    {
        $rows = [
            $this->row(['id' => 'brakes', 'name' => 'Brake Pads Toyota Premio']),
            $this->row(['id' => 'rice', 'name' => 'Rice 5kg']),
        ];

        $result = ProductSearch::rank($rows, 'brake pads for premio');

        $this->assertSame('brakes', $result['results'][0]['id']);
        $this->assertGreaterThan(0.0, $result['results'][0]['relevance']);
        $this->assertContains('name', $result['results'][0]['matched_on']);
    }

    public function test_a_misspelling_still_finds_the_product(): void
    {
        $rows = [$this->row(['id' => 'brakes', 'name' => 'Brake Pads Toyota Premio'])];

        // "braka" is not a prefix of "brake" and does not fold onto it, so the
        // only thing that can find this row is the fuzzy pass.
        $result = ProductSearch::rank($rows, 'braka');

        $this->assertCount(1, $result['results']);
        $this->assertSame('brakes', $result['results'][0]['id']);
        $this->assertContains('name', $result['results'][0]['matched_on']);
        $this->assertLessThan(1.0, $result['results'][0]['relevance'], 'A typo must score below an exact hit.');
    }

    public function test_switching_fuzzy_off_makes_a_misspelling_stop_matching(): void
    {
        $rows = [$this->row(['id' => 'brakes', 'name' => 'Brake Pads Toyota Premio'])];

        config(['ai.search.fuzzy_enabled' => false]);
        $result = ProductSearch::rank($rows, 'braka');

        $this->assertSame([], $result['results']);
        $this->assertFalse($result['meta']['fuzzy_enabled']);

        // ...but an exact token still matches with fuzzy off.
        $this->assertCount(1, ProductSearch::rank($rows, 'premio')['results']);
    }

    public function test_synonyms_cross_languages_in_both_directions(): void
    {
        $rows = [
            $this->row(['id' => 'rice', 'name' => 'Rice 5kg']),
            $this->row(['id' => 'mchele', 'name' => 'Mchele 5kg']),
        ];

        $english = ProductSearch::rank($rows, 'mchele');
        $this->assertContains('rice', array_column($english['results'], 'id'), 'A Swahili query must find the English name.');

        $swahili = ProductSearch::rank($rows, 'rice');
        $this->assertContains('mchele', array_column($swahili['results'], 'id'), 'An English query must find the Swahili name.');

        // The variant list is reported, so the expansion is never a secret.
        $this->assertContains('rice', $english['meta']['variants']);
    }

    public function test_results_are_ranked_and_the_ranking_is_explainable(): void
    {
        $rows = [
            $this->row(['id' => 'desc', 'name' => 'Wholesale bundle', 'description' => 'Sukari nzuri kwa bei nafuu']),
            $this->row(['id' => 'category', 'name' => 'Bundle', 'category' => 'Sukari']),
            $this->row(['id' => 'name', 'name' => 'Sukari 1kg']),
            $this->row(['id' => 'other', 'name' => 'Cement bag']),
        ];

        $result = ProductSearch::rank($rows, 'sukari');

        $ids = array_column($result['results'], 'id');
        // Name match first, then category, then the weakest (description) hit;
        // the row that matches nothing is absent entirely.
        $this->assertSame(['name', 'category', 'desc'], $ids);
        $this->assertGreaterThan($result['results'][1]['relevance'], $result['results'][0]['relevance']);
        $this->assertGreaterThan($result['results'][2]['relevance'], $result['results'][1]['relevance']);
        $this->assertNotContains('other', $ids);

        foreach ($result['results'] as $row) {
            $this->assertArrayHasKey('relevance', $row);
            $this->assertArrayHasKey('matched_on', $row);
            $this->assertArrayHasKey('search_signals', $row);
            $this->assertGreaterThan(0.0, $row['relevance']);
            $this->assertLessThanOrEqual(1.0, $row['relevance']);
            $this->assertNotEmpty($row['matched_on']);
        }
    }

    public function test_business_scope_and_availability_filters_still_apply(): void
    {
        $rows = [
            $this->row(['id' => 'a', 'name' => 'Sukari', 'business_id' => 'b1']),
            $this->row(['id' => 'b', 'name' => 'Sukari', 'business_id' => 'b2']),
            $this->row(['id' => 'c', 'name' => 'Sukari', 'business_id' => 'b1', 'available' => false, 'stock' => 0]),
        ];

        $scoped = ProductSearch::rank($rows, 'sukari', ['business_id' => 'b1']);
        $this->assertSame(['a', 'c'], array_column($scoped['results'], 'id'));

        $inStock = ProductSearch::rank($rows, 'sukari', ['business_id' => 'b1', 'available_only' => true]);
        $this->assertSame(['a'], array_column($inStock['results'], 'id'));

        // The filters are reported, not hidden.
        $this->assertTrue($inStock['meta']['matched'] === 1);
    }

    public function test_retrieval_and_results_are_bounded_and_that_is_reported(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $rows[] = $this->row(['id' => 'p'.$i, 'name' => 'Sukari '.$i]);
        }

        config(['ai.search.max_candidates' => 2]);
        $result = ProductSearch::rank($rows, 'sukari');

        $this->assertTrue($result['meta']['truncated'], 'Scanning more rows than allowed must be reported.');
        $this->assertSame(5, $result['meta']['candidates']);
        $this->assertSame(2, $result['meta']['scored']);

        config(['ai.search.max_candidates' => 2000]);
        $limited = ProductSearch::rank($rows, 'sukari', ['limit' => 2]);
        $this->assertCount(2, $limited['results']);
        $this->assertSame(5, $limited['meta']['matched']);
        $this->assertSame(2, $limited['meta']['returned']);
    }

    public function test_no_plausible_match_returns_nothing_rather_than_noise(): void
    {
        $rows = [$this->row(['id' => 'a', 'name' => 'Sukari 1kg'])];

        $result = ProductSearch::rank($rows, 'zzzzzz');

        $this->assertSame([], $result['results']);
        $this->assertSame(0, $result['meta']['matched']);

        // An empty query never matches everything.
        $this->assertSame([], ProductSearch::rank($rows, '   ')['results']);
        $this->assertSame([], ProductSearch::rank($rows, '')['results']);
    }

    public function test_the_meta_block_reports_how_the_search_ran(): void
    {
        $meta = ProductSearch::rank([$this->row(['id' => 'a', 'name' => 'Sukari'])], 'sukari')['meta'];

        foreach (['query', 'variants', 'mode', 'candidates', 'scored', 'matched', 'returned', 'truncated', 'min_score', 'fuzzy_enabled', 'max_candidates', 'max_results', 'took_ms', 'embeddings'] as $key) {
            $this->assertArrayHasKey($key, $meta, "meta.{$key} must be reported.");
        }
        $this->assertSame('lexical', $meta['mode']);
        $this->assertIsFloat($meta['took_ms']);
        $this->assertFalse($meta['embeddings']['used']);
    }

    /**
     * THE DEGRADATION GUARD. Embeddings are optional; the lexical path is not.
     */
    public function test_search_stays_useful_when_embeddings_are_off_or_unusable(): void
    {
        $rows = [$this->row(['id' => 'a', 'name' => 'Sukari 1kg'])];

        // 1. Default: disabled.
        $off = ProductSearch::rank($rows, 'sukari');
        $this->assertSame('lexical', $off['meta']['mode']);
        $this->assertFalse($off['meta']['embeddings']['enabled']);
        $this->assertCount(1, $off['results']);

        // 2. Enabled but with no provider key: still lexical, still useful,
        //    and it must NOT attempt a provider call.
        config(['ai.search.embeddings.enabled' => true, 'ai.api_key' => '']);
        $this->assertFalse(Embeddings::isConfigured());

        $unusable = ProductSearch::rank($rows, 'sukari');
        $this->assertTrue($unusable['meta']['embeddings']['enabled']);
        $this->assertFalse($unusable['meta']['embeddings']['used']);
        $this->assertSame('lexical', $unusable['meta']['mode']);
        $this->assertCount(1, $unusable['results'], 'An unusable embedding provider must not empty the results.');
        $this->assertSame('v1', $unusable['meta']['embeddings']['version']);
    }

    public function test_embedding_helpers_are_bounded_and_safe(): void
    {
        // An operator-supplied table name can never reach the data layer raw.
        config(['ai.search.embeddings.table' => 'evil; drop table users']);
        $this->assertSame('ai_product_embeddings', Embeddings::table());

        $this->assertSame(1.0, round(Embeddings::cosine([1, 0], [1, 0]), 6));
        $this->assertSame(0.0, round(Embeddings::cosine([1, 0], [0, 1]), 6));
        $this->assertSame(0.0, Embeddings::cosine([], []));
        $this->assertSame(0.0, Embeddings::cosine([0, 0], [1, 1]), 'A zero vector must not divide by zero.');

        // Control characters are stripped and the input is length-bounded, so a
        // paste cannot smuggle a large blob (or a newline log injection) out.
        $this->assertSame('hello world', Embeddings::clean("hello\n\tworld\x07"));
        $this->assertSame(2000, mb_strlen(Embeddings::clean(str_repeat('a', 5000))));
    }
}
