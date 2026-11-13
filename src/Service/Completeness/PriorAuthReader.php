<?php

/**
 * Read prior-authorization / referral visit rows when the PA module table exists.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness;

class PriorAuthReader
{
    private static ?bool $tableExists = null;

    public function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        $row = sqlQuery(
            "SELECT 1 AS ok
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'module_prior_authorizations'
             LIMIT 1"
        );
        self::$tableExists = is_array($row) && !empty($row['ok']);

        return self::$tableExists;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAuthorizations(int $pid): array
    {
        if ($pid <= 0 || !$this->tableExists()) {
            return [];
        }

        $res = sqlStatement(
            "SELECT id, auth_num, start_date, end_date, cpt, init_units, remaining_units
             FROM module_prior_authorizations
             WHERE pid = ?
             ORDER BY start_date DESC, id DESC",
            [$pid]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Active = end_date empty/zero OR end_date >= today.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveAuthorizations(int $pid): array
    {
        $today = date('Y-m-d');
        $active = [];
        foreach ($this->listAuthorizations($pid) as $row) {
            $end = trim((string) ($row['end_date'] ?? ''));
            if ($end === '' || $end === '0000-00-00' || $end >= $today) {
                $active[] = $row;
            }
        }

        return $active;
    }
}
