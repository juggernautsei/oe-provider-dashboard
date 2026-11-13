<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PatientInsuranceReader;

class MissingPlanTypeRule implements CompletenessRuleInterface
{
    public function __construct(private readonly PatientInsuranceReader $reader = new PatientInsuranceReader())
    {
    }

    public function getCode(): string
    {
        return 'missing_plan_type';
    }

    public function getTitle(): string
    {
        return 'Set insurance plan type (HMO/PPO/etc.)';
    }

    public function getDescription(): string
    {
        return 'Insurance company has no plan type code (HMO, PPO, POS, etc.). Update the payer record.';
    }

    public function getFixPath(): string
    {
        return 'interface/practice/ins_search.php';
    }

    public function fails(int $pid): bool
    {
        $ins = $this->reader->getPrimaryInsurance($pid);
        if ($ins === null) {
            return false;
        }

        $code = $ins['ins_type_code'] ?? null;
        return $code === null || $code === '' || (int) $code <= 0;
    }
}
