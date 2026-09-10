<?php

/**
 * Supervisor feedback lookup for LBF forms.
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

namespace Juggernaut\ProviderDashboard\Module\Controllers;

class SupervisorFeedback
{
    public function getSupervisorFeedback($formname, $pid, $encounter)
    {
        $sql = "SELECT l.field_value
            FROM forms AS f
            JOIN lbf_data AS l ON f.form_id = l.form_id
            WHERE f.encounter = ?
              AND f.form_name = ?
              AND f.pid = ?
              AND l.field_id = 'supervisor_feedback'
              AND f.deleted = 0";

        $params = [$encounter, $formname, $pid];
        $row = sqlQuery($sql, $params);
        if (empty($row) || !isset($row['field_value'])) {
            return null;
        }
        $value = trim((string) $row['field_value']);
        return $value !== '' ? $value : null;
    }
}
