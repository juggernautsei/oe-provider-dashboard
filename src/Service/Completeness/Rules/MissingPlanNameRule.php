<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PatientInsuranceReader;

class MissingPlanNameRule implements CompletenessRuleInterface
{
    public function __construct(private readonly PatientInsuranceReader $reader = new PatientInsuranceReader())
    {
    }

    public function getCode(): string
    {
        return 'missing_plan_name';
    }

    public function getTitle(): string
    {
        return 'Enter insurance plan name';
    }

    public function getDescription(): string
    {
        return 'Primary insurance is on file but plan name (HMO/PPO product name) is empty.';
    }

    public function getFixPath(): string
    {
        return 'interface/patient_file/summary/demographics.php?set_pid={pid}';
    }

    public function fails(int $pid): bool
    {
        $ins = $this->reader->getPrimaryInsurance($pid);
        if ($ins === null) {
            return false;
        }

        return trim((string) ($ins['plan_name'] ?? '')) === '';
    }
}
