# OpenEMR Task Manager

Staff-wide **Task Manager** for OpenEMR (providers, front desk, billing, supervisors): open work queues for chart completeness (insurance plan type, dates, referral/prior-auth visits), missing documentation/coding, review status, and bulk eSign.

**Module Manager name:** JSE Task Manager  
**Package version:** 1.2.0 (`version.php`)  
**Install directory:** `oe-provider-dashboard` (unchanged for upgrade compatibility)  
**Namespace:** `Juggernaut\ProviderDashboard\Module`  
**Author:** Sherwin Gaddis  
**Repository:** https://github.com/juggernautsei/oe-provider-dashboard

---

## Compatibility

| Target | Status |
|--------|--------|
| **OpenEMR 8.x** (tested on 8.5-dev) | **Primary** |
| OpenEMR 7.0.x | May work via session/`$GLOBALS` fallbacks; not the primary target |

Requirements:

- PHP **8.1+**
- OpenEMR custom modules enabled
- No extra Composer packages required at runtime (autoload is PSR-4 only)

This module uses OpenEMR 8.x-friendly patterns where available:

- `OEGlobalsBag` for webroot / srcdir / fileroot
- `SessionWrapperFactory` for user session values
- Session-aware `CsrfUtils::collectCsrfToken($session)` / `verifyCsrfToken($token, $session)`

---

## Documentation

| Doc | Description |
|-----|-------------|
| [docs/CHART_COMPLETENESS.md](docs/CHART_COMPLETENESS.md) | Chart completeness tasks: rules, lifecycle, schema, team board, ops guide |

---

## Features

- **Task Manager** — permanent optional top tab (`default_open_tabs` id `pdb`) and Misc menu item for all staff
- **My Tasks (default)** — chart completeness work queue (insurance plan + referral/prior-auth visits)
- **Supervisor view** — if the user is a supervisor (`users.supervisor_id`), shows their staff plus self
- **Status** — Not Ready / Ready / In Progress (stored on `forms.review_status`)
- **Coding column** — CPT/HCPCS (and related) codes on the encounter
- **Signed column** — eSign signature summary
- **Click status** — toggles review status via AJAX
- **eSign All** — password-as-signature bulk sign for unsigned encounter forms
- **Jump to encounter** — opens patient demographics with encounter context
- **Sidebar** — My Tasks, Team board, Missing Documentation, Cosign Notes, Portal Messaging
- **Chart completeness tasks** — missing primary insurance, plan name, plan type (HMO/PPO), insurance dates, prior-auth/referral visits and units; auto-assigned to the user who creates/updates the patient; auto-closes when data is fixed
- **Team board** — manager view of open/overdue tasks by assignee
- **Nightly scan** — `TaskManagerCompleteness` background service

---

## Installation

### 1. Install files

```bash
cd /path/to/openemr/interface/modules/custom_modules
git clone https://github.com/juggernautsei/oe-provider-dashboard.git
```

Or copy the module directory into:

```text
<openemr>/interface/modules/custom_modules/oe-provider-dashboard
```

### 2. Register and enable

1. Log in as an administrator.
2. Go to **Modules → Manage Modules** (Module Manager).
3. Find **JSE Task Manager**.
4. **Register**, then **Install** (runs `table.sql`), then **Enable**.

### 3. What `table.sql` does

| Change | Purpose |
|--------|---------|
| `forms.review_status` column (if missing) | Stores dashboard review state (Ace702-compatible; not in core OpenEMR by default) |
| `default_open_tabs` row (`pdb`) titled **Task Manager** | Permanent top tab on login when activity=1 |
| `module_task_manager_tasks` table | Chart completeness work queue |
| `background_services` row `TaskManagerCompleteness` | Nightly completeness rescan |

If the module was copied in before install SQL ran, either reinstall/upgrade from Module Manager or apply `table.sql` / `sql/*_upgrade.sql` manually.

Full completeness reference: [docs/CHART_COMPLETENESS.md](docs/CHART_COMPLETENESS.md).

### 4. Open the module

After enable:

- Use the **Task Manager** menu item (Miscellaneous area, preferred), or  
- Open directly:

```text
/interface/modules/custom_modules/oe-provider-dashboard/public/index.php
```

---

## Access control (ACL)

Task Manager is **staff-wide**. Users need **one** of:

| ACL | Typical role |
|-----|----------------|
| `patients` / `demo` | Front desk / registration |
| `patients` / `appt` | Scheduling |
| `patients` / `med` | Clinical |
| `encounters` / `notes` | Clinical notes |
| `encounters` / `coding_a` | Coding |
| `encounters` / `auth_a` | Encounter auth |
| `acct` / `bill` | Billing |
| `admin` / `super` | Administrator |

Menu item uses `patients/demo` so front desk sees it; page gate is the full set above (`public/_init.php`).

---

## Usage

### My Tasks (main screen — chart completeness)

1. Open **Task Manager** (menu or permanent tab).
2. Review open insurance / prior-auth tasks assigned to you (due date, patient, rule).
3. Click **Open chart** to jump to the patient and fix plan name, dates, plan type, or referral visits.
4. Tasks **auto-close** when the missing data is saved; use **Rescan** to refresh.
5. Supervisors also see their staff’s tasks; managers use **Team board** for overdue rollups.

### Missing Documentation

1. Open **Missing Documentation** from the sidebar.
2. Review encounters needing documentation or coding.
3. Click **Status** to toggle Ready / Not Ready (AJAX).
4. Click the **Encounter Number** button to open that patient/encounter.
5. Use **eSign All** for unsigned forms (password-as-signature).

Supervisors automatically see encounters for assigned providers (`users.supervisor_id`).

### Cosign Notes

Sidebar placeholder for future cosign workflow.

### Portal Messaging

Sidebar action loads the portal messaging frame in the main UI (same idea as the classic dashboard).

---

## Configuration notes

- Completeness due date default: **+1 day** from task create (`CompletenessTaskService`).
- Completeness nightly window: recent open-task patients + insurance/registration activity (`syncRecentPatients`).
- Missing-documentation date window is fixed at **6 months** in `DashboardData`. Change there if you need a different range.
- Status UI values map from `forms.review_status` (`NULL`/`0` = Not Ready, `1` = Ready / In Progress when feedback exists).
- eSign uses core `ESign\Form_Factory` and locks signatures like the native eSign flow.

---

## Directory layout

```text
oe-provider-dashboard/
├── LICENSE
├── README.md
├── docs/
│   └── CHART_COMPLETENESS.md       # completeness tasks guide
├── composer.json
├── info.txt
├── version.php                    # 1.2.0 / v_database 3
├── table.sql
├── sql/
│   ├── 1_0_0-to-1_1_0_upgrade.sql # Task Manager branding tab
│   └── 1_1_0-to-1_2_0_upgrade.sql # completeness table + background job
├── openemr.bootstrap.php          # registers namespace + Bootstrap
├── ModuleManagerListener.php      # install/unregister hooks
├── moduleConfig.php
├── public/
│   ├── _init.php                  # ACL, globals bag, session, autoload
│   ├── index.php                  # My Tasks (completeness default)
│   ├── team.php                   # manager team board
│   ├── missing_documentation.php  # encounter doc/coding queue
│   ├── sync_patient.php           # completeness resync API
│   ├── statuschange.php           # AJAX review status
│   ├── signAll.php                # AJAX bulk eSign
│   ├── cosign.php
│   └── portal_messages.php
├── scripts/
│   └── completeness_background.php
├── src/
│   ├── Bootstrap.php              # menu + patient create/update hooks
│   ├── Exception/
│   ├── Service/Completeness/      # rules + task service
│   └── Controllers/
│       ├── DashboardData.php
│       ├── DocumentStatus.php
│       └── SupervisorFeedback.php
├── tests/Unit/Completeness/
├── resources/                     # header, menu, footer partials
├── css/
└── js/
```

---

## Development / Docker

Example: copy into a running OpenEMR container:

```bash
docker cp oe-provider-dashboard development-easy-openemr-1:/var/www/localhost/htdocs/openemr/interface/modules/custom_modules/
docker exec development-easy-openemr-1 chown -R apache:apache \
  /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-provider-dashboard
```

Then register/install/enable in Module Manager.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| “An error has occurred” / Unknown tab | CSRF API mismatch on OE 8.5 | Ensure build uses `collectCsrfToken($session, …)` |
| `Unknown column 'f.review_status'` | Install SQL not applied | Reinstall module or run `table.sql` alter |
| Completeness tables missing warning | Upgrade not run | Module Manager upgrade; see [docs/CHART_COMPLETENESS.md](docs/CHART_COMPLETENESS.md) |
| Completeness tasks never close | Insurance saved without patient event | Rescan / nightly job / `sync_patient.php` |
| Not Authorized | ACL | Grant demo/appt/med/notes/coding/bill or admin |
| Empty missing-documentation table | No encounters in last 6 months for this provider | Create encounters or widen date range in `DashboardData` |
| eSign fails | Wrong password or missing ESign factory | Confirm password; verify core ESign is available |

---

## License

**GNU General Public License v3.0 only (GPL-3.0-only)**, consistent with OpenEMR.  
See [LICENSE](LICENSE).

---

## Credits / source

Dashboard logic ported from Ace702 `interface/provider_dashboard` (Sherwin Gaddis, 2023).  
Module packaging and OpenEMR 8.x session/CSRF integration for public distribution under [juggernautsei/oe-provider-dashboard](https://github.com/juggernautsei/oe-provider-dashboard).

## Support

Sherwin Gaddis / Juggernaut Systems Enterprise  
Issues: https://github.com/juggernautsei/oe-provider-dashboard/issues
