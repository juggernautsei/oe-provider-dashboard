# OpenEMR Provider Dashboard

Provider-facing **Tasks** dashboard for OpenEMR: find encounters that still need documentation or coding, track review status, and bulk eSign forms.

**Module Manager name:** JSE Provider Dashboard  
**Package version:** 1.0.0 (`version.php`)  
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

## Features

- **Tasks list** — encounters in the last 6 months for the logged-in provider
- **Supervisor view** — if the user is a supervisor (`users.supervisor_id`), shows their staff plus self
- **Status** — Not Ready / Ready / In Progress (stored on `forms.review_status`)
- **Coding column** — CPT/HCPCS (and related) codes on the encounter
- **Signed column** — eSign signature summary
- **Click status** — toggles review status via AJAX
- **eSign All** — password-as-signature bulk sign for unsigned encounter forms
- **Jump to encounter** — opens patient demographics with encounter context
- **Sidebar** — Missing Documentation, Cosign Notes (placeholder), Portal Messaging shortcut

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
3. Find **JSE Provider Dashboard**.
4. **Register**, then **Install** (runs `table.sql`), then **Enable**.

### 3. What `table.sql` does

| Change | Purpose |
|--------|---------|
| `forms.review_status` column (if missing) | Stores dashboard review state (Ace702-compatible; not in core OpenEMR by default) |
| Optional `default_open_tabs` row (`pdb`) | Can open the dashboard as a default tab |

If the module was copied in before install SQL ran, either reinstall from Module Manager or apply the `forms.review_status` alter manually once.

### 4. Open the module

After enable:

- Use the **Provider Dashboard** menu item (Encounters or Miscellaneous area), or  
- Open directly:

```text
/interface/modules/custom_modules/oe-provider-dashboard/public/index.php
```

---

## Access control (ACL)

Users need **one** of:

| ACL | Typical role |
|-----|----------------|
| `encounters` / `coding_a` | Coding |
| `encounters` / `auth_a` | Encounter auth |
| `encounters` / `notes` | Clinical notes |
| `patients` / `med` | Clinical |
| `admin` / `super` | Administrator |

Adjust ACL in `public/_init.php` and `src/Bootstrap.php` if your site uses different roles.

---

## Usage

### Tasks (main screen)

1. Open **Provider Dashboard**.
2. Review the table of recent encounters (provider, date, patient, status, forms, coding, signed).
3. Click **Status** to toggle Ready / Not Ready (AJAX).
4. Click the **Encounter Number** button to open that patient/encounter.
5. Use **eSign All** to sign unsigned forms:
   - Enter your OpenEMR password (used as signature)
   - Optional amendment text
   - Submit

Supervisors automatically see encounters for assigned providers (`users.supervisor_id`).

### Cosign Notes

Sidebar placeholder for future cosign workflow.

### Portal Messaging

Sidebar action loads the portal messaging frame in the main UI (same idea as the classic dashboard).

---

## Configuration notes

- Date window is fixed at **6 months** in `DashboardData` (single- and multi-provider queries). Change there if you need a different range.
- Status UI values map from `forms.review_status` (`NULL`/`0` = Not Ready, `1` = Ready / In Progress when feedback exists).
- eSign uses core `ESign\Form_Factory` and locks signatures like the native eSign flow.

---

## Directory layout

```text
oe-provider-dashboard/
├── LICENSE
├── README.md
├── composer.json
├── info.txt
├── version.php
├── table.sql
├── openemr.bootstrap.php          # registers namespace + menu
├── ModuleManagerListener.php      # install/unregister hooks
├── moduleConfig.php
├── public/
│   ├── _init.php                  # ACL, globals bag, session, autoload
│   ├── index.php                  # Tasks dashboard
│   ├── statuschange.php           # AJAX review status
│   ├── signAll.php                # AJAX bulk eSign
│   ├── cosign.php
│   └── portal_messages.php
├── src/
│   ├── Bootstrap.php              # menu event
│   └── Controllers/
│       ├── DashboardData.php
│       ├── DocumentStatus.php
│       └── SupervisorFeedback.php
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
| Not Authorized | ACL | Grant coding/notes/med or admin |
| Empty table | No encounters in last 6 months for this provider | Create encounters or widen date range in `DashboardData` |
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
