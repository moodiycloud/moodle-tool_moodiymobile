# Repository Guidelines

Canonical agent instructions for `tool_moodiymobile`. `CLAUDE.md` imports this file; task detail lives in the docs routed at the end. This repository is public: keep internal hosts, credentials and internal decisions out of it.

## What This Is

`tool_moodiymobile` is a Moodle admin tool plugin (`admin/tool/moodiymobile`) that configures mobile app integration between Moodle sites and the Moodiy Cloud platform. It manages airnotifier push notification settings and mobile app enablement.

- **Component:** `tool_moodiymobile`
- **Moodle version:** Requires 4.5+ (`2024100700`); supports Moodle 4.5–5.3 (`supported = [405, 503]`)
- **Dependency:** `tool_moodiyregistration` (for site UUID and registration status)
- **Maturity:** Stable; `version.php` holds the current release.

Layout: `index.php` (admin UI flow), `updatesettings.php` (remote config endpoint), `settings.php`, `version.php`, `classes/`, `db/events.php`, `lang/en/tool_moodiymobile.php`, `tests/`. File-by-file map: `docs/architecture.md`.

## Security rules

- `updatesettings.php` uses HMAC-SHA256 verification with the site UUID as the key
- CORS restricted to the MoodiyCloud API URL
- All admin pages require `moodle/site:config` capability
- Admin pages must call `require_login()` and check `require_capability('moodle/site:config', ...)`

## Build, Test, and Development Commands
There is no npm/composer build pipeline in this plugin. Work from a Moodle installation root:
```bash
cp -r . {moodle-root}/admin/tool/moodiymobile
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
php local/codechecker/run.php admin/tool/moodiymobile
```
Useful syntax check while iterating:
```bash
find admin/tool/moodiymobile -name '*.php' -print0 | xargs -0 -n1 php -l
```

Local CI, from the `moodiy_meta` workspace root, before any push or PR (plan and prerequisites: `../moodle-plugin-quality-toolkit.md`):

```bash
make -C moodle_plugins pre-pr PLUGIN=moodle-tool_moodiymobile
make -C moodle_plugins lint-only PLUGIN=moodle-tool_moodiymobile
moodle_plugins/scripts/summarize.sh moodle-tool_moodiymobile
```

`make pre-pr` mirrors this plugin's current GitHub workflow. `make lint-only`
is the faster repo-level PHPCS sweep when you only need coding-style feedback.

GitHub Actions (`.github/workflows/ci.yml`) runs the standalone `moodle-plugin-ci` recipe with zero allowed PHPCS/PHPDoc warnings; publishing happens only through the tag-driven `.github/workflows/moodle-release.yml`. Matrix detail: `docs/architecture.md` → GitHub Actions CI.

## Coding Style & Naming Conventions
Follow Moodle PHP coding style:
- 4-space indentation, no tabs.
- Keep Moodle GPL header block in every PHP file.
- Use `defined('MOODLE_INTERNAL') || die();` for internal files.
- Keep plugin strings in `lang/en/tool_moodiymobile.php`; reference via `get_string()`.
- Persist settings via `get_config()` / `set_config()`.
- Match existing class/file naming patterns in `classes/` to avoid autoload issues.

## Testing Guidelines
PHPUnit tests live under `tests/` and should be run from a Moodle root.
- `php vendor/bin/phpunit --testsuite tool_moodiymobile_testsuite`
- `php vendor/bin/phpunit admin/tool/moodiymobile/tests/utility_test.php`
- `php vendor/bin/phpunit admin/tool/moodiymobile/tests/observers_test.php`
- Always tee output to temp files during debugging: `php vendor/bin/phpunit ... 2>&1 | tee /tmp/test_output.txt`
- Still manually verify `/admin/tool/moodiymobile/index.php` behavior for registered and unregistered sites.
- Confirm enabling/disabling updates expected `tool_moodiymobile` and `tool_mobile` configs.
- Exercise `updatesettings.php` with valid and invalid HMAC headers.

## Commit & Pull Request Guidelines
Recent history favors short imperative commit subjects, often with issue/PR refs (example: `add setting for mobile app purchase page (#9)`).
- Keep commits focused to one logical change.
- Use clear subject lines in imperative mood.
- In PRs, include: problem summary, linked issue, verification steps, and screenshots for UI/admin-page changes.
- Call out config/security-impacting changes explicitly (especially around `updatesettings.php`).

## When you… | Read first

| When you… | Read first |
| --- | --- |
| Change the operating modes, config storage, airnotifier keys or a key file | `docs/architecture.md` |
| Change mobile-app branding or the AirNotifier push-identity pair | `docs/codex-deployment-branding.md`; `PLANS.md` |
| Prepare a release or the Moodle plugins directory submission | `CHANGES.md`; `MOODLE_ORG_SUBMISSION.md`; `README.md` |
| Run or debug the local CI runner | `../AGENTS.md` (meta `moodle_plugins/`) → Local CI Runner; `../moodle-plugin-quality-toolkit.md` |
