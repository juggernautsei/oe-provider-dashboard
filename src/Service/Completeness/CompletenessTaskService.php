<?php

/**
 * Open/close chart-completeness tasks from rule results.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness;

use Juggernaut\ProviderDashboard\Module\Exception\TaskManagerException;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\IncompletePriorAuthRule;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\MissingInsuranceDatesRule;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\MissingPlanNameRule;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\MissingPlanTypeRule;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\MissingPrimaryInsuranceRule;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\Rules\MissingPriorAuthRule;

class CompletenessTaskService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';
    public const TABLE = 'module_task_manager_tasks';

    /** @var list<CompletenessRuleInterface> */
    private array $rules;

    /**
     * @param list<CompletenessRuleInterface>|null $rules
     */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /**
     * @return list<CompletenessRuleInterface>
     */
    public static function defaultRules(): array
    {
        $insurance = new PatientInsuranceReader();
        $priorAuth = new PriorAuthReader();

        return [
            new MissingPrimaryInsuranceRule($insurance),
            new MissingPlanNameRule($insurance),
            new MissingInsuranceDatesRule($insurance),
            new MissingPlanTypeRule($insurance),
            new MissingPriorAuthRule($insurance, $priorAuth),
            new IncompletePriorAuthRule($priorAuth),
        ];
    }

    public function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $row = sqlQuery(
            "SELECT 1 AS ok
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?
             LIMIT 1",
            [self::TABLE]
        );
        $exists = is_array($row) && !empty($row['ok']);

        return $exists;
    }

    /**
     * Evaluate all rules for a patient and open/close tasks.
     *
     * @return array{opened:int,closed:int}
     */
    public function syncPatient(int $pid, ?int $assigneeUserId = null, ?int $createdByUserId = null): array
    {
        if ($pid <= 0) {
            throw new TaskManagerException('Invalid patient id for completeness sync');
        }

        if (!$this->tableExists()) {
            return ['opened' => 0, 'closed' => 0];
        }

        $assignee = $assigneeUserId && $assigneeUserId > 0 ? $assigneeUserId : $this->currentUserId();
        $createdBy = $createdByUserId && $createdByUserId > 0 ? $createdByUserId : $assignee;
        $opened = 0;
        $closed = 0;
        $dueAt = date('Y-m-d H:i:s', strtotime('+1 day'));

        foreach ($this->rules as $rule) {
            $fails = $rule->fails($pid);
            $existing = $this->findOpenTask($pid, $rule->getCode());

            if ($fails) {
                if ($existing === null) {
                    $this->insertOpenTask($pid, $rule, $assignee, $createdBy, $dueAt);
                    ++$opened;
                }
                continue;
            }

            if ($existing !== null) {
                $this->closeTask((int) $existing['id'], 'auto_data');
                ++$closed;
            }
        }

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * Rescan recently touched patients (and optional backlog of open-task patients).
     *
     * @return array{patients:int,opened:int,closed:int}
     */
    public function syncRecentPatients(int $days = 30, int $limit = 500): array
    {
        if (!$this->tableExists()) {
            return ['patients' => 0, 'opened' => 0, 'closed' => 0];
        }

        $since = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
        $limit = max(1, min(5000, $limit));

        // Patients with open tasks + recent insurance / registration activity
        $pids = [];
        $openRes = sqlStatement(
            'SELECT DISTINCT pid FROM ' . self::TABLE . " WHERE status = ? LIMIT {$limit}",
            [self::STATUS_OPEN]
        );
        while ($row = sqlFetchArray($openRes)) {
            $pids[(int) $row['pid']] = true;
        }

        $insRes = sqlStatement(
            "SELECT DISTINCT pid FROM insurance_data
             WHERE (date IS NOT NULL AND date != '0000-00-00' AND date >= ?)
                OR (date_end IS NOT NULL AND date_end != '0000-00-00' AND date_end >= ?)
             LIMIT {$limit}",
            [$since, $since]
        );
        while ($row = sqlFetchArray($insRes)) {
            $pids[(int) $row['pid']] = true;
        }

        $pdRes = sqlStatement(
            "SELECT pid FROM patient_data
             WHERE regdate IS NOT NULL AND DATE(regdate) >= ?
             ORDER BY pid DESC
             LIMIT {$limit}",
            [$since]
        );
        while ($row = sqlFetchArray($pdRes)) {
            $pids[(int) $row['pid']] = true;
        }

        $opened = 0;
        $closed = 0;
        $count = 0;
        foreach (array_keys($pids) as $pid) {
            if ($pid <= 0) {
                continue;
            }
            $result = $this->syncPatient($pid, null, null);
            $opened += $result['opened'];
            $closed += $result['closed'];
            ++$count;
            if ($count >= $limit) {
                break;
            }
        }

        return ['patients' => $count, 'opened' => $opened, 'closed' => $closed];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOpenTasksForUser(int $userId, bool $includeSupervised = true): array
    {
        if (!$this->tableExists() || $userId <= 0) {
            return [];
        }

        $userIds = [$userId];
        if ($includeSupervised) {
            $staff = sqlStatement('SELECT id FROM users WHERE supervisor_id = ?', [$userId]);
            while ($row = sqlFetchArray($staff)) {
                $userIds[] = (int) $row['id'];
            }
        }
        $userIds = array_values(array_unique(array_filter($userIds)));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $sql = 'SELECT t.*,
                       pd.fname, pd.lname, pd.mname, pd.pubpid,
                       ua.fname AS assignee_fname, ua.lname AS assignee_lname
                FROM ' . self::TABLE . ' t
                LEFT JOIN patient_data pd ON pd.pid = t.pid
                LEFT JOIN users ua ON ua.id = t.assignee_user_id
                WHERE t.status = ?
                  AND t.assignee_user_id IN (' . $placeholders . ')
                ORDER BY t.due_at IS NULL, t.due_at ASC, t.created_at ASC';

        $binds = array_merge([self::STATUS_OPEN], $userIds);
        $res = sqlStatement($sql, $binds);

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $this->hydrateTaskRow($row);
        }

        return $rows;
    }

    /**
     * Manager rollup: all open tasks.
     *
     * @return list<array<string, mixed>>
     */
    public function listAllOpenTasks(int $limit = 500): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(2000, $limit));
        $res = sqlStatement(
            'SELECT t.*,
                    pd.fname, pd.lname, pd.mname, pd.pubpid,
                    ua.fname AS assignee_fname, ua.lname AS assignee_lname
             FROM ' . self::TABLE . ' t
             LEFT JOIN patient_data pd ON pd.pid = t.pid
             LEFT JOIN users ua ON ua.id = t.assignee_user_id
             WHERE t.status = ?
             ORDER BY t.due_at IS NULL, t.due_at ASC, ua.lname, ua.fname, t.created_at ASC
             LIMIT ' . $limit,
            [self::STATUS_OPEN]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $this->hydrateTaskRow($row);
        }

        return $rows;
    }

    /**
     * @return array<int, array{user_id:int,name:string,open_count:int,overdue_count:int}>
     */
    public function summarizeOpenByAssignee(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $today = date('Y-m-d H:i:s');
        $res = sqlStatement(
            'SELECT t.assignee_user_id AS user_id,
                    ua.fname, ua.lname,
                    COUNT(*) AS open_count,
                    SUM(CASE WHEN t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) AS overdue_count
             FROM ' . self::TABLE . ' t
             LEFT JOIN users ua ON ua.id = t.assignee_user_id
             WHERE t.status = ?
             GROUP BY t.assignee_user_id, ua.fname, ua.lname
             ORDER BY overdue_count DESC, open_count DESC, ua.lname, ua.fname',
            [$today, self::STATUS_OPEN]
        );

        $out = [];
        while ($row = sqlFetchArray($res)) {
            $uid = (int) ($row['user_id'] ?? 0);
            $name = trim(($row['lname'] ?? '') . ', ' . ($row['fname'] ?? ''), ' ,');
            if ($name === '') {
                $name = xl('Unassigned');
            }
            $out[] = [
                'user_id' => $uid,
                'name' => $name,
                'open_count' => (int) ($row['open_count'] ?? 0),
                'overdue_count' => (int) ($row['overdue_count'] ?? 0),
            ];
        }

        return $out;
    }

    public function countOpenForUser(int $userId): int
    {
        if (!$this->tableExists() || $userId <= 0) {
            return 0;
        }

        $row = sqlQuery(
            'SELECT COUNT(*) AS c FROM ' . self::TABLE . ' WHERE status = ? AND assignee_user_id = ?',
            [self::STATUS_OPEN, $userId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateTaskRow(array $row): array
    {
        $due = $row['due_at'] ?? null;
        $overdue = false;
        if (!empty($due) && $due !== '0000-00-00 00:00:00') {
            $overdue = strtotime((string) $due) < time();
        }

        $patientName = trim(
            ($row['lname'] ?? '') . ', ' . ($row['fname'] ?? '') . ' ' . ($row['mname'] ?? '')
        );
        $assigneeName = trim(($row['assignee_lname'] ?? '') . ', ' . ($row['assignee_fname'] ?? ''), ' ,');

        $fixPath = str_replace('{pid}', (string) ($row['pid'] ?? ''), (string) ($row['fix_path'] ?? ''));

        $row['patient_name'] = $patientName;
        $row['assignee_name'] = $assigneeName !== '' ? $assigneeName : xl('Unassigned');
        $row['is_overdue'] = $overdue;
        $row['fix_path_resolved'] = $fixPath;

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOpenTask(int $pid, string $ruleCode): ?array
    {
        $row = sqlQuery(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND rule_code = ? AND status = ? LIMIT 1',
            [$pid, $ruleCode, self::STATUS_OPEN]
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    private function insertOpenTask(
        int $pid,
        CompletenessRuleInterface $rule,
        int $assignee,
        int $createdBy,
        string $dueAt
    ): void {
        sqlInsert(
            'INSERT INTO ' . self::TABLE . ' (
                pid, rule_code, title, description, status, assignee_user_id, created_by_user_id,
                due_at, fix_path, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $pid,
                $rule->getCode(),
                $rule->getTitle(),
                $rule->getDescription(),
                self::STATUS_OPEN,
                $assignee > 0 ? $assignee : null,
                $createdBy > 0 ? $createdBy : null,
                $dueAt,
                $rule->getFixPath(),
            ]
        );
    }

    private function closeTask(int $taskId, string $completionSource): void
    {
        sqlStatement(
            'UPDATE ' . self::TABLE . '
             SET status = ?, completed_at = NOW(), completion_source = ?, updated_at = NOW()
             WHERE id = ? AND status = ?',
            [self::STATUS_DONE, $completionSource, $taskId, self::STATUS_OPEN]
        );
    }

    private function currentUserId(): int
    {
        if (function_exists('provider_dashboard_context')) {
            $ctx = provider_dashboard_context();
            return (int) ($ctx['authUserID'] ?? 0);
        }

        return (int) ($_SESSION['authUserID'] ?? 0);
    }
}
