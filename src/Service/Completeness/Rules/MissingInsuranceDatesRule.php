<?php

/**
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\PatientInsuranceReader;

class MissingInsuranceDatesRule implements CompletenessRuleInterface
{
    public function __construct(private readonly PatientInsuranceReader $reader = new PatientInsuranceReader())
    {
    }

    public function getCode(): string
    {
        return 'missing_insurance_dates';
    }

    public function getTitle(): string
    {
        return 'Enter insurance effective dates';
    }

    public function getDescription(): string
    {
        return 'Primary insurance is missing a valid effective start date and/or end date.';
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

        $start = trim((string) ($ins['date'] ?? ''));
        $end = trim((string) ($ins['date_end'] ?? ''));

        $startMissing = $start === '' || $start === '0000-00-00';
        $endMissing = $end === '' || $end === '0000-00-00';

        // Require start always; require end so staff capture validity window Natisha asked for.
        return $startMissing || $endMissing;
    }
}
