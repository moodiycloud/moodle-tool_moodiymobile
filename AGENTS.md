# Repository Guidelines

## Project Structure & Module Organization
This repository is a Moodle admin/tool plugin (`tool_moodiymobile`). Keep changes scoped to plugin conventions:
- `index.php`: main admin UI flow (registration checks, form handling, config sync).
- `updatesettings.php`: API endpoint for remote airnotifier config updates.
- `settings.php` and `version.php`: admin registration and plugin metadata.
- `classes/`: plugin classes (`utility.php`, `setting_form.php`, `observers.php`).
- `db/events.php`: event observer wiring.
- `lang/en/tool_moodiymobile.php`: all user-facing strings.

## Build, Test, and Development Commands
There is no npm/composer build pipeline in this plugin. Work from a Moodle installation root:
```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
php local/codechecker/run.php admin/tool/moodiymobile
```
Useful syntax check while iterating:
```bash
find admin/tool/moodiymobile -name '*.php' -print0 | xargs -0 -n1 php -l
```

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
