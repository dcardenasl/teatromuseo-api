<?php

declare(strict_types=1);

namespace App\Filters;

use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Filters\FeatureToggleFilter as BaseFeatureToggleFilter;
use Throwable;

/**
 * App-side FeatureToggleFilter — overrides the core filter with metrics
 * recording. The hub starter ships a `MetricsService` that observes
 * feature evaluations; consumer projects without metrics use the core
 * filter directly (no override).
 */
class FeatureToggleFilter extends BaseFeatureToggleFilter
{
    protected function recordToggle(string $flag, bool $enabled): void
    {
        try {
            Services::metricsService()->recordFeatureToggle($flag, $enabled);
        } catch (Throwable $e) {
            // Feature evaluation must not fail because observability is unavailable.
            log_message('warning', '[FeatureToggleFilter] Failed to record feature metric: ' . $e->getMessage());
        }
    }

    protected function disabledMessage(string $flag): string
    {
        return $flag === 'metrics'
            ? lang('Metrics.disabled')
            : parent::disabledMessage($flag);
    }
}
