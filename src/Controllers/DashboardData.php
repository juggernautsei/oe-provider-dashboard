<?php

/**
 * Provider dashboard encounter/documentation queries.
 *
 * Extracted from Ace702 interface/provider_dashboard.
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

namespace Juggernaut\ProviderDashboard\Module\Controllers;

class DashboardData
{
    private int $authUserID;

    public function __construct(?int $authUserID = null)
    {
        if ($authUserID !== null) {
            $this->authUserID = $authUserID;
            return;
        }
        $ctx = function_exists('provider_dashboard_context') ? provider_dashboard_context() : [];
        $this->authUserID = (int) ($ctx['authUserID'] ?? ($_SESSION['authUserID'] ?? 0));
    }

    public function getDocumentSigner($enc): array
    {
        $sql = sqlStatement(
            "
        SELECT CONCAT(u.lname, ' ', u.fname) AS name, COUNT(*) AS signature_count
        FROM `users` u
        JOIN `esign_signatures` es ON `es`.`uid` = `u`.`id`
        JOIN forms f ON `f`.`id` = `es`.`tid`
        WHERE `es`.`tid` = `f`.`id` AND `f`.`encounter` = ?
        GROUP BY CONCAT(u.lname, ', ', u.fname)
    ",
            [$enc]
        );

        $signers = '';
        $signersCount = 0;
        $c = 0;
        while ($row = sqlFetchArray($sql)) {
            $signersCount = $row['signature_count'];
            if ($c > 0) {
                $signers .= "/\r\n";
            }
            $signers .= $row['name'];
            ++$c;
        }
        if (empty($signers)) {
            return ['count' => $signersCount, 'signers' => 'No Signers'];
        }

        return ['count' => $signersCount, 'signers' => $signers];
    }

    public function encounterSingleProviderData()
    {
        $currentDate = date('Y-m-d');
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months', strtotime($currentDate)));
        $sql = "SELECT fe.encounter, fe.date, f.id, fe.reason, f.formdir, f.form_name, f.review_status, p.fname, p.mname, p.lname, p.pid,
            p.pubpid, p.dob, u.lname AS ulname, u.fname AS ufname, u.mname AS umname
            FROM ( form_encounter AS fe, forms AS f )
            LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid
            LEFT JOIN users AS u ON u.id = fe.provider_id
            WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient'
            AND fe.date >= ? AND fe.date <= ? AND fe.provider_id = ? ORDER BY lower(u.lname), lower(u.fname), fe.date";

        return sqlStatement($sql, [$sixMonthsAgo . ' 00:00:00', $currentDate . ' 23:59:59', $this->authUserID]);
    }

    public function checkIfSupervisor(): array
    {
        $sql = "SELECT id FROM users WHERE supervisor_id = ?";
        $staff = [];
        $result = sqlStatement($sql, [$this->authUserID]);
        while ($row = sqlFetchArray($result)) {
            $staff[] = (int) $row['id'];
        }
        $staff[] = $this->authUserID;
        return $staff;
    }

    public function encounterMultiProviderData(array $staff)
    {
        $staff = array_values(array_filter(array_map('intval', $staff), static fn ($id) => $id > 0));
        if ($staff === []) {
            return sqlStatement('SELECT 1 WHERE 0');
        }

        $currentDate = date('Y-m-d');
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months', strtotime($currentDate)));
        $placeholders = implode(',', array_fill(0, count($staff), '?'));
        $sql = "SELECT fe.encounter, fe.date,f.id, fe.reason, f.formdir, f.form_name, f.review_status, p.fname, p.mname, p.lname, p.pid,
            p.pubpid, p.dob, u.lname AS ulname, u.fname AS ufname, u.mname AS umname
            FROM ( form_encounter AS fe, forms AS f )
            LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid
            LEFT JOIN users AS u ON u.id = fe.provider_id
            WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient'
            AND fe.date >= ? AND fe.date <= ? AND fe.provider_id IN ($placeholders) ORDER BY lower(u.lname), lower(u.fname), fe.date";

        $binds = array_merge([$sixMonthsAgo . ' 00:00:00', $currentDate . ' 23:59:59'], $staff);
        return sqlStatement($sql, $binds);
    }

    public function updateReviewStatus($enc, $status, $id): void
    {
        $sql = "UPDATE `forms` SET `review_status` = ? WHERE `forms`.`encounter` = ? AND `forms`.`id` = ?  AND `forms`.`formdir` = 'newpatient'";
        sqlStatement($sql, [$status, $enc, $id]);
    }

    public function encounterMultiProviderForSignData(array $staff)
    {
        $staff = array_values(array_filter(array_map('intval', $staff), static fn ($id) => $id > 0));
        if ($staff === []) {
            return sqlStatement('SELECT 1 WHERE 0');
        }

        $currentDate = date('Y-m-d');
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months', strtotime($currentDate)));
        $placeholders = implode(',', array_fill(0, count($staff), '?'));
        $sql = "SELECT fe.encounter,f.id, f.formdir
            FROM ( form_encounter AS fe, forms AS f )
            LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid
            LEFT JOIN users AS u ON u.id = fe.provider_id
            WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient'
            AND fe.date >= ? AND fe.date <= ? AND fe.provider_id IN ($placeholders) ORDER BY lower(u.lname), lower(u.fname), fe.date";

        $binds = array_merge([$sixMonthsAgo . ' 00:00:00', $currentDate . ' 23:59:59'], $staff);
        return sqlStatement($sql, $binds);
    }

    public function encounterSingleProviderForSignData()
    {
        $currentDate = date('Y-m-d');
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months', strtotime($currentDate)));
        $sql = "SELECT fe.encounter, f.id, f.formdir
            FROM ( form_encounter AS fe, forms AS f )
            LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid
            LEFT JOIN users AS u ON u.id = fe.provider_id
            WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient'
            AND fe.date >= ? AND fe.date <= ? AND fe.provider_id = ? ORDER BY lower(u.lname), lower(u.fname), fe.date";

        return sqlStatement($sql, [$sixMonthsAgo . ' 00:00:00', $currentDate . ' 23:59:59', $this->authUserID]);
    }

    public function fetchEsignRecord($id)
    {
        $sql = "SELECT *
            FROM esign_signatures
            WHERE tid = ? AND is_lock = ?";

        $result = sqlStatement($sql, [$id, 1]);

        return sqlFetchArray($result);
    }
}
