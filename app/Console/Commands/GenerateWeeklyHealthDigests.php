<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Ai\HealthDigestService;
use App\Services\BusinessResolver;
use Illuminate\Console\Command;
use Throwable;

/**
 * Capability #5 - generates the weekly business health digest for every
 * business, once per reporting period.
 *
 * Idempotent: re-running for the same week skips businesses that already have
 * a stored digest (so an operator can safely retry a failed night). Delivery
 * is configurable via config('ai.health.delivery').
 */
class GenerateWeeklyHealthDigests extends Command
{
    protected $signature = 'ai:weekly-digests
        {--business= : Only generate for this business id}
        {--week= : Reference date inside the week to report on (defaults to today)}
        {--force : Regenerate even when a digest already exists for the period}
        {--dry-run : Compute without storing}';

    protected $description = 'Generate the weekly business health digest for all businesses';

    public function handle(): int
    {
        $delivery = (array) config('ai.health.delivery', []);
        if (! ($delivery['enabled'] ?? true)) {
            $this->warn('Health digest delivery is disabled (ai.health.delivery.enabled).');

            return self::SUCCESS;
        }

        if (! config('ai.features.health', true)) {
            $this->warn('The health capability is disabled (ai.features.health).');

            return self::SUCCESS;
        }

        $period = HealthDigestService::periodForWeek($this->option('week'));
        $this->info("Weekly health digest for {$period['start']} → {$period['end']}");

        $businessIds = $this->businessIds();
        if ($businessIds === []) {
            $this->warn('No businesses found.');

            return self::SUCCESS;
        }

        $generated = 0;
        $reused = 0;
        $failed = 0;

        foreach ($businessIds as $businessId) {
            try {
                $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
                if ($memberIds === []) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  [dry-run] {$businessId}");
                    $generated++;

                    continue;
                }

                $locale = HealthDigestService::defaultLocaleForBusiness($businessId);

                $digest = HealthDigestService::generateForBusiness(
                    $memberIds,
                    $businessId,
                    $period['start'],
                    $period['end'],
                    $locale,
                    [
                        'refresh' => (bool) $this->option('force'),
                        'generated_by' => HealthDigestService::GENERATED_SCHEDULED,
                    ],
                );

                if ($digest['reused'] ?? false) {
                    $reused++;
                } else {
                    $generated++;
                }

                if (! ($digest['stored'] ?? false)) {
                    $failed++;
                    $this->warn("  {$businessId}: not stored ({$digest['storage_error']})");
                }
            } catch (Throwable $error) {
                $failed++;
                $this->error("  {$businessId}: {$error->getMessage()}");
            }
        }

        $this->info("Generated {$generated}, reused {$reused}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function businessIds(): array
    {
        $single = trim((string) $this->option('business'));
        if ($single !== '') {
            return [$single];
        }

        $max = max(1, (int) (config('ai.health.delivery.max_businesses') ?? 500));

        try {
            return Business::select('id')->limit($max)->get()
                ->map(fn ($row) => (string) $row->id)
                ->filter()
                ->values()
                ->toArray();
        } catch (Throwable $error) {
            $this->error('Could not load businesses: '.$error->getMessage());

            return [];
        }
    }
}
