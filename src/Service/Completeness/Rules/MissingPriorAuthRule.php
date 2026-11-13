<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PatientInsuranceReader;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PriorAuthReader;

class MissingPriorAuthRule implements CompletenessRuleInterface
{
    public function __construct(
        private readonly PatientInsuranceReader $insuranceReader = new PatientInsuranceReader(),
        private readonly PriorAuthReader $priorAuthReader = new PriorAuthReader()
    ) {
    }

    public function getCode(): string
    {
        return 'missing_prior_auth';
    }

    public function getTitle(): string
    {
        return 'Add referral / prior auth visits';
    }

    public function getDescription(): string
    {
        return 'Patient has primary insurance but no prior authorization with approved visits and dates.';
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

        if (!$this->insuranceReader->hasPrimaryInsurance($pid)) {
            return false;
        }

        return $this->priorAuthReader->listActiveAuthorizations($pid) === [];
    }
}
