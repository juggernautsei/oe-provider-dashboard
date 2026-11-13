<?php

/**
 * Read primary insurance + payer type for completeness rules.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness;

class PatientInsuranceReader
{
    /**
     * Latest primary insurance row for the patient (by date desc, then id desc).
     *
     * @return array<string, mixed>|null
     */
    public function getPrimaryInsurance(int $pid): ?array
    {
        if ($pid <= 0) {
            return null;
        }

        $row = sqlQuery(
            "SELECT id.provider, id.plan_name, id.policy_number, id.date, id.date_end, id.type,
                    ic.name AS company_name, ic.ins_type_code, itc.type AS ins_type_label
             FROM insurance_data id
             LEFT JOIN insurance_companies ic ON ic.id = id.provider
             LEFT JOIN insurance_type_codes itc ON itc.id = ic.ins_type_code
             WHERE id.pid = ?
               AND id.type = 'primary'
               AND (id.provider IS NOT NULL AND id.provider != '' AND id.provider != '0')
             ORDER BY id.date DESC, id.id DESC
             LIMIT 1",
            [$pid]
        );

        if (!is_array($row) || empty($row)) {
            return null;
        }

        return $row;
    }

    public function hasPrimaryInsurance(int $pid): bool
    {
        return $this->getPrimaryInsurance($pid) !== null;
    }
}
