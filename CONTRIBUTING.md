# Contributing to oe-provider-dashboard

Thanks for helping improve the OpenEMR Provider Dashboard module.

## Code of conduct
Be respectful and professional. This project is meant for clinical/ops workflows—never post PHI, passwords, API keys, or real patient data in issues, PRs, or logs.

## Development setup

1. Clone OpenEMR 8.x (or use Docker `development-easy`).
2. Place this module under:

   ```text
   openemr/interface/modules/custom_modules/oe-provider-dashboard
   ```

3. In OpenEMR: **Modules → Manage Modules** → Register → Install → Enable.
4. Open **Provider Dashboard** (Tasks).

Example Docker copy:

```bash
docker cp oe-provider-dashboard CONTAINER:/var/www/localhost/htdocs/openemr/interface/modules/custom_modules/
docker exec CONTAINER chown -R apache:apache \
  /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-provider-dashboard
```

## Branching and commits

- Fork the repo and create a feature branch from `main`.
- Prefer focused commits with clear messages.
- Conventional-style prefixes are welcome: `fix:`, `feat:`, `docs:`, `refactor:`.
- If an AI tool helped author the change, disclose it in the PR and optionally use an `Assisted-by:` / co-author trailer.

## Coding guidelines

- Target **OpenEMR 8.x** patterns:
  - Prefer `OEGlobalsBag` over ad-hoc `$GLOBALS` when practical
  - Prefer `SessionWrapperFactory` for session values
  - Use session-aware CSRF: `CsrfUtils::collectCsrfToken($session)` / `verifyCsrfToken($token, $session)`
- Keep ACL checks on every public entry point.
- Use bound SQL parameters; do not concatenate untrusted input into queries.
- New PHP files should include GPL-3.0 license headers (same as OpenEMR).
- Do not commit `vendor/`, secrets, certificates, or environment files (see `.gitignore`).

## SQL / schema

- Put install schema in `table.sql` using OpenEMR module directives (`#IfMissingColumn`, `#IfNotRow2D`, etc.).
- Avoid breaking core tables without clear module documentation.
- `forms.review_status` is a module-managed extension—document any further schema changes in the PR and README.

## Testing checklist (before PR)

- [ ] PHP syntax clean on touched files (`php -l`)
- [ ] Dashboard loads for an authorized user
- [ ] Unauthorized user is blocked
- [ ] Status AJAX still works if you changed status code
- [ ] eSign All still works if you changed signing code
- [ ] No secrets or PHI in the diff

## Pull requests

1. Open a PR against `main`.
2. Fill out the PR template.
3. Link related issues.
4. Keep the diff reviewable; large refactors should be explained.

## Reporting bugs

Use the **Bug report** issue template. Include OpenEMR/PHP versions and redacted logs.

## Security

Do not open public issues for vulnerabilities that could expose patient data or allow privilege escalation. Contact the maintainer privately or use GitHub security advisories when enabled.

## License

By contributing, you agree your contributions are licensed under **GPL-3.0-only**, the same license as this repository and OpenEMR.
