# OpenEMR Provider Dashboard

Provider-facing dashboard for **missing documentation and coding**, review status, and bulk eSign — extracted from the Ace702 `interface/provider_dashboard` application into a custom module.

**Module Manager name:** JSE Provider Dashboard  
**Package version:** 1.0.0 (`version.php`)  
**Namespace:** `Juggernaut\ProviderDashboard\Module`  
**Author:** Sherwin Gaddis

## Compatibility

| Target | Status |
|--------|--------|
| **OpenEMR 8.x** (developed against 8.5-dev Docker) | **Primary** — uses `OEGlobalsBag` + `SessionWrapperFactory` with `$_SESSION` fallback |
| OpenEMR 7.0.x | Likely works via fallbacks; not the primary target |

## Features (from Ace702)

- Encounter list (last 6 months) for the logged-in provider
- Supervisor multi-provider view when `users.supervisor_id` is set
- Documentation / coding / eSign status columns
- Toggle review status (AJAX)
- eSign all unsigned encounter forms (password = signature)
- Sidebar links: missing docs, cosign placeholder, portal messaging

## Installation

```bash
# Copy or clone into custom modules
cp -a oe-provider-dashboard /path/to/openemr/interface/modules/custom_modules/
# or bind-mount into Docker development-easy
```

1. Module Manager → register / install / enable  
2. Open **Provider Dashboard** from the menu (Encounters/Misc) or  
   `/interface/modules/custom_modules/oe-provider-dashboard/public/index.php`

### ACL

Requires `encounters` / `coding_a` **or** `encounters` / `auth_a` **or** admin super.

## Layout

```text
oe-provider-dashboard/
├── openemr.bootstrap.php      # menu registration
├── public/
│   ├── _init.php              # 8.x globals bag + session + ACL
│   ├── index.php              # main dashboard
│   ├── signAll.php / statuschange.php
│   ├── cosign.php / portal_messages.php
├── src/Controllers/           # DashboardData, SupervisorFeedback, DocumentStatus
├── css/ js/ resources/
└── table.sql                  # optional default open tab
```

## License

GPL-3.0-only (OpenEMR-compatible). See [LICENSE](LICENSE).

## Source

Logic ported from Ace702 `interface/provider_dashboard` (© 2023 Sherwin Gaddis).  
Module shell patterns aligned with ACEHR `oe-provider-dashboard`.

## Support

Sherwin Gaddis / Juggernaut Systems Enterprise  
https://github.com/juggernautsei/oe-provider-dashboard
