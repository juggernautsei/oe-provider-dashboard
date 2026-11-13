# Chart Completeness Tasks

Staff work-queue feature in **JSE Task Manager** (`oe-provider-dashboard`) that nags incomplete **insurance plan** and **referral / prior-authorization visit** data until it is fixed.

Version introduced: **1.2.0** (`v_database` 3).

---

## Purpose

Front desk and billing often skip:

- Plan name (product label)
- Plan type (HMO, PPO, POS, etc. on the payer)
- Insurance effective / end dates
- Prior auth / referral visit limits (units) and validity dates

Instead of relying on an SOP, Task Manager opens **assigned tasks** when data is missing and **auto-closes** them when the chart is completed. Supervisors and managers can see who is behind on the **Team board**.

---

## User-facing screens

| Screen | Path | Who |
|--------|------|-----|
| **My Tasks** (default) | `public/index.php` | Assignee + their supervised staff |
| **Team board** | `public/team.php` | Managers (demo / acct rep / admin) |
| **Missing Documentation** | `public/missing_documentation.php` | Providers/coders (separate queue) |
| Manual patient resync API | `public/sync_patient.php` | Authenticated staff (CSRF) |

Sidebar badge on **My Tasks** shows the open count for the logged-in user.

### My Tasks workflow

1. Open **Task Manager** (menu item or permanent top tab `pdb`).
2. Review open rows: due date, patient, task title, assignee.
3. Click **Open chart** → patient demographics (then edit insurance or prior auth).
4. Save missing data → on next patient update event or **Rescan**, the task closes automatically.
5. Overdue rows (past `due_at`) are highlighted.

### Team board workflow (Natisha / management)

1. Open **Team board**.
2. Review per-assignee cards: open count and overdue count.
3. Drill the full open-task list and open any patient chart.
4. Follow up with staff who have overdue insurance/auth tasks.

---

## Task rules

Rules live under `src/Service/Completeness/Rules/`.  
Each open task is unique per **patient + `rule_code`** while `status = open`.

| `rule_code` | Title (EN) | Opens when | Fix location |
|-------------|------------|------------|--------------|
| `missing_primary_insurance` | Add primary insurance | No primary `insurance_data` with a payer | Demographics / insurance |
| `missing_plan_name` | Enter insurance plan name | Primary exists, `plan_name` empty | Demographics / insurance |
| `missing_insurance_dates` | Enter insurance effective dates | Primary missing start and/or end date (`date` / `date_end`) | Demographics / insurance |
| `missing_plan_type` | Set insurance plan type (HMO/PPO/etc.) | Linked `insurance_companies.ins_type_code` empty | Insurance company admin |
| `missing_prior_auth` | Add referral / prior auth visits | Primary insurance exists and no **active** row in `module_prior_authorizations` | Prior Auth module (if installed) |
| `incomplete_prior_auth` | Complete prior auth visit limits or dates | Any PA row missing `init_units`, start, or end date | Prior Auth module |

### Rule notes

- **Prior auth rules are no-ops** if `module_prior_authorizations` does not exist (Advanced Prior Auth module not installed).
- **Active prior auth** = `end_date` empty/`0000-00-00` **or** `end_date >= today`.
- Dependent rules short-circuit: e.g. plan name is not flagged if there is no primary insurance.
- Requiring **end dates** will create many tasks on first rescan of legacy charts. That is intentional for accountability; relax `MissingInsuranceDatesRule` if the backlog is too large.

---

## Lifecycle

```text
Patient created / updated  ──►  CompletenessTaskService::syncPatient(pid, assignee)
        │
        ├─ rule fails and no open task  → INSERT open task (due = +1 day)
        ├─ rule fails and open task     → leave assignee/due unchanged
        └─ rule passes and open task    → status=done, completion_source=auto_data

Nightly background / Rescan button  ──►  syncRecentPatients()
        │
        └─ patients with open tasks + recent insurance/registration activity
```

### Assignment

- **Default assignee** = current session user (`authUserID`) when the patient is created or updated.
- New tasks only; existing open tasks are **not** reassigned on later edits.
- Supervisors (`users.supervisor_id`) see their own tasks **plus** staff tasks on My Tasks.

### Completion

| Source | Meaning |
|--------|---------|
| `auto_data` | Rule passed after chart data changed (preferred) |
| (future) | Manual complete is not required for MVP |

There is no “mark done without fixing data” button on purpose—closing requires the rule to pass.

---

## Triggers

| Trigger | Implementation |
|---------|----------------|
| Patient created | `Bootstrap::onPatientCreated` → `PatientCreatedEvent` |
| Patient updated | `Bootstrap::onPatientUpdated` → `PatientUpdatedEvent` |
| UI Rescan | POST on `public/index.php` |
| Single patient AJAX | `public/sync_patient.php` (`pid` + CSRF) |
| Nightly job | `background_services.name = TaskManagerCompleteness` → `scripts/completeness_background.php` → `task_manager_completeness_scan()` |

**Insurance-only saves** that do not fire patient events still close/open on Rescan, nightly job, or `sync_patient.php`. Call `sync_patient.php` from a custom insurance-save hook if you need immediate updates without waiting for the job.

---

## Data model

### Table `module_task_manager_tasks`

| Column | Type | Notes |
|--------|------|--------|
| `id` | BIGINT PK | Auto-increment |
| `pid` | BIGINT | Patient |
| `rule_code` | VARCHAR(64) | Stable machine id |
| `title` | VARCHAR(255) | English source string for `xl()` |
| `description` | TEXT | Human detail |
| `status` | VARCHAR(16) | `open` \| `done` |
| `assignee_user_id` | INT | Owner |
| `created_by_user_id` | INT | Who caused creation |
| `due_at` | DATETIME | Default now + 1 day |
| `completed_at` | DATETIME | When closed |
| `completion_source` | VARCHAR(32) | e.g. `auto_data` |
| `fix_path` | VARCHAR(512) | Relative path; may include `{pid}` |
| `created_at` / `updated_at` | DATETIME | Audit |

Indexes: assignee+status, pid+status, rule+status, due_at.

Created by `table.sql` and `sql/1_1_0-to-1_2_0_upgrade.sql`.  
Dropped on module **unregister** (`ModuleManagerListener`).

### Background service row

| Field | Value |
|-------|--------|
| name | `TaskManagerCompleteness` |
| title | Task Manager Completeness Scan |
| execute_interval | 1440 (minutes) |
| function | `task_manager_completeness_scan` |
| require_once | `/interface/modules/custom_modules/oe-provider-dashboard/scripts/completeness_background.php` |

---

## Code map

```text
src/Exception/TaskManagerException.php
src/Service/Completeness/
  CompletenessRuleInterface.php
  CompletenessTaskService.php      # sync, list, summarize
  PatientInsuranceReader.php
  PriorAuthReader.php
  Rules/
    MissingPrimaryInsuranceRule.php
    MissingPlanNameRule.php
    MissingInsuranceDatesRule.php
    MissingPlanTypeRule.php
    MissingPriorAuthRule.php
    IncompletePriorAuthRule.php
src/Bootstrap.php                  # menu + patient events
public/index.php                   # My Tasks
public/team.php                    # Team board
public/sync_patient.php
scripts/completeness_background.php
tests/Unit/Completeness/RuleCodesTest.php
```

Service API (high level):

- `syncPatient(int $pid, ?int $assignee, ?int $createdBy): array{opened,closed}`
- `syncRecentPatients(int $days = 30, int $limit = 500): array{patients,opened,closed}`
- `listOpenTasksForUser(int $userId, bool $includeSupervised = true): array`
- `listAllOpenTasks(int $limit = 500): array`
- `summarizeOpenByAssignee(): array`
- `countOpenForUser(int $userId): int`

---

## Installation / upgrade

1. Deploy module under `interface/modules/custom_modules/oe-provider-dashboard`.
2. Module Manager → **JSE Task Manager** → Install or **Upgrade** (applies `table.sql` / versioned SQL).
3. Confirm:
   - Table `module_task_manager_tasks` exists
   - `background_services` row `TaskManagerCompleteness` exists (active)
   - Optional permanent tab: Admin → Lists → **Default Open Tabs** → **Task Manager** (`option_id` `pdb`, activity 1)
4. First load of **My Tasks** may be empty until patients are created/updated or **Rescan** / nightly job runs.

### Upgrade path

| From | Action |
|------|--------|
| Fresh install | `table.sql` creates tasks table + background service + tab |
| 1.0.x / 1.1.x | Bump to 1.2.0; run Module Manager upgrade (`sql/1_1_0-to-1_2_0_upgrade.sql`, `v_database` 3) |

---

## Operational guidance

### For registration staff

- When adding a patient, complete primary insurance **plan name**, **dates**, and ensure the payer has a **plan type**.
- Enter referral/prior auth **auth number**, **units**, and **start/end** in the Prior Auth module when visits are limited.
- Clear your My Tasks list daily; overdue items should be zero before end of shift when possible.

### For managers

- Use Team board in huddles: sort by overdue.
- Do not clear tasks manually in SQL—fix the chart so auto-close keeps an audit trail (`completion_source`).
- After go-live, expect a one-time backlog from historical incomplete end dates; either staff down the list or temporarily relax the dates rule.

### For developers

- Add a new rule: implement `CompletenessRuleInterface`, register in `CompletenessTaskService::defaultRules()`, document the `rule_code` here.
- Prefer module-scoped `TaskManagerException` for unexpected failures.
- Keep rules **idempotent** and cheap (single-patient queries).

---

## Troubleshooting

| Symptom | Cause | Fix |
|---------|--------|-----|
| Warning: tables not installed | Upgrade SQL not run | Module Manager Install/Upgrade |
| No tasks after new patient | Events not firing / module disabled | Enable module; confirm `Bootstrap` listeners; check PHP error log |
| Prior auth rules never open | PA table missing | Install `oe-module-prior-authorizations` |
| Tasks stay open after fix | Insurance saved without patient update event | Click **Rescan** or wait for nightly job; optional `sync_patient.php` |
| Huge backlog of date tasks | End date required on legacy policies | Staff catch-up or soften `MissingInsuranceDatesRule` |
| Team board forbidden | ACL | Need `patients/demo`, `acct/rep`, or `admin/super` |
| Badge always 0 | Wrong user / no open rows for assignee | Confirm `assignee_user_id` on rows |

---

## Privacy

Tasks store **patient id**, rule metadata, and staff user ids only—not full demographics dumps or clinical notes. Still treat the queue as PHI-linked operational data under site policy.

---

## Related

- Module README: [../README.md](../README.md)
- Prior authorizations module (visit units): `oe-module-prior-authorizations`
- OpenEMR insurance UI: patient demographics insurance card (`plan_name`, dates)
- Payer type codes: `insurance_type_codes` / `insurance_companies.ins_type_code`
