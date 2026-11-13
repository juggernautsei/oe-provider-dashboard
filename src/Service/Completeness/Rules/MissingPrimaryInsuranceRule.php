<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PatientInsuranceReader;

class MissingPrimaryInsuranceRule implements CompletenessRuleInterface
{
    public function __construct(private readonly PatientInsuranceReader $reader = new PatientInsuranceReader())
    {
    }

    public function getCode(): string
    {
        return 'missing_primary_insurance';
    }

    public function getTitle(): string
    {
        return 'Add primary insurance';
    }

    public function getDescription(): string
    {
        return 'Patient has no primary insurance payer on file.';
    }

    public function getFixPath(): string
    {
        return 'interface/patient_file/summary/demographics.php?set_pid={pid}';
    }

    public function fails(int $pid): bool
    {
        return !$this->reader->hasPrimaryInsurance($pid);
    }
}
