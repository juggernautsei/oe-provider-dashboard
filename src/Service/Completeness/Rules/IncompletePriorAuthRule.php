<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PriorAuthReader;

class IncompletePriorAuthRule implements CompletenessRuleInterface
{
    public function __construct(private readonly PriorAuthReader $priorAuthReader = new PriorAuthReader())
    {
    }

    public function getCode(): string
    {
        return 'incomplete_prior_auth';
    }

    public function getTitle(): string
    {
        return 'Complete prior auth visit limits or dates';
    }

    public function getDescription(): string
    {
        return 'A prior authorization exists but is missing approved units and/or valid start/end dates.';
    }

    public function getFixPath(): string
    {
        return 'interface/modules/custom_modules/oe-module-prior-authorizations/public/patient_auth_manager.php';
    }

    public function fails(int $pid): bool
    {
        if (!$this->priorAuthReader->tableExists()) {
            return false;
        }

        foreach ($this->priorAuthReader->listAuthorizations($pid) as $auth) {
            $start = trim((string) ($auth['start_date'] ?? ''));
            $end = trim((string) ($auth['end_date'] ?? ''));
            $units = $auth['init_units'] ?? null;

            $startMissing = $start === '' || $start === '0000-00-00';
            $endMissing = $end === '' || $end === '0000-00-00';
            $unitsMissing = $units === null || $units === '' || (int) $units <= 0;

            if ($startMissing || $endMissing || $unitsMissing) {
                return true;
            }
        }

        return false;
    }
}
