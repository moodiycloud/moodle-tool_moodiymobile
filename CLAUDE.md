# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

`tool_moodiymobile` is a Moodle admin tool plugin (`admin/tool/moodiymobile`) that configures mobile app integration between Moodle sites and the Moodiy Cloud platform. It manages airnotifier push notification settings and mobile app enablement.

- **Component:** `tool_moodiymobile`
- **Moodle version:** Requires 4.0+ (`2022112800`)
- **Dependency:** `tool_moodiyregistration` (for site UUID and registration status)
- **Maturity:** Alpha (v0.1.0)

## Development Setup

This is a pure Moodle plugin — no Composer, npm, or build tools. It must run inside a Moodle installation:

```bash
# Install into Moodle
cp -r . {moodle-root}/admin/tool/moodiymobile

# Run Moodle upgrade
php {moodle-root}/admin/cli/upgrade.php

# Run Moodle's code checker (if available)
php {moodle-root}/local/codechecker/run.php admin/tool/moodiymobile
```

PHPUnit tests live under `tests/` and should be run from a Moodle root:

```bash
# Run all plugin tests (from Moodle root)
php vendor/bin/phpunit --testsuite tool_moodiymobile_testsuite

# Run a specific test file
php vendor/bin/phpunit admin/tool/moodiymobile/tests/utility_test.php
php vendor/bin/phpunit admin/tool/moodiymobile/tests/observers_test.php
```

Always tee test output to temp files: `php vendor/bin/phpunit ... 2>&1 | tee /tmp/test_output.txt`

## Local CI

Run the full CI equivalent locally before any push or PR:

```bash
cd ../..
make -C moodle_plugins pre-pr PLUGIN=moodle-tool_moodiymobile
make -C moodle_plugins lint-only PLUGIN=moodle-tool_moodiymobile
moodle_plugins/scripts/summarize.sh moodle-tool_moodiymobile
```

`make pre-pr` mirrors this plugin's current GitHub workflow. `make lint-only`
is the faster repo-level PHPCS sweep when you only need coding-style feedback.

Plan, prerequisites, and per-step explanation: [`moodle-plugin-quality-toolkit.md`](../moodle-plugin-quality-toolkit.md).

## GitHub Actions CI

Uses Catalyst's reusable Moodle workflow in `.github/workflows/ci.yml`.
`phplint`, `phpcs`, `phpdoc`, `validate`, `savepoints`, `mustache`, and
`phpunit` run with `codechecker_max_warnings: 0`. `behat`, `grunt`, and the
reusable workflow's `release` job remain disabled. Publishing happens through
the separate tag-driven `.github/workflows/moodle-release.yml`.

## Architecture

### Two Operating Modes

The plugin detects whether the Moodle site is an **internal client** (managed by MoodiyCloud, detected via `auth_maintenance` in `$CFG->forced_plugin_settings`) or an **external client** (self-hosted, registered with MoodiyCloud). This distinction drives the entire UI flow in `index.php`.

### Key Files

| File | Purpose |
|------|---------|
| `index.php` | Main admin page — handles mode detection, airnotifier data ingestion (via `?data=` base64 GET param), form display, and config syncing |
| `updatesettings.php` | HMAC-SHA256-secured API endpoint for MoodiyCloud to push airnotifier config updates remotely |
| `settings.php` | Registers admin navigation: external settings page + configurable `mobileappurl` setting |
| `classes/utility.php` | Constants (`MOODIY_PORTAL_URL`, `DEFAULT_PURCHASE_URL`, `DEFAULTSETTING_AIRNOTIFIER`), signup URL generation, base64 encoding helpers |
| `classes/setting_form.php` | Moodleform with single `enabled` checkbox; conditionally disabled based on registration/airnotifier state |
| `classes/observers.php` | Event observer — resets all settings to defaults when site unregisters from MoodiyCloud |
| `db/events.php` | Registers observer for `\tool_moodiyregistration\event\moodiy_unregistration` |

### Config Storage

No custom DB tables. All data stored in Moodle's `config` and `config_plugins` tables:

- `tool_moodiymobile/enabled` — plugin on/off
- `tool_moodiymobile/mobileappurl` — app purchase URL
- `tool_moodiymobile/airnotifiersetting` — full airnotifier config as JSON string
- Individual airnotifier keys synced to global config (`airnotifieraccesskey`, `airnotifierurl`, etc.) and `tool_mobile` config (`androidappid`, `iosappid`, `setuplink`)

### Security

- `updatesettings.php` uses HMAC-SHA256 verification with the site UUID as the key
- CORS restricted to the MoodiyCloud API URL
- All admin pages require `moodle/site:config` capability

## Airnotifier Settings Keys

These are the keys managed by the plugin (defined in `utility::DEFAULTSETTING_AIRNOTIFIER`):

`airnotifieraccesskey`, `airnotifierurl`, `airnotifierport`, `airnotifiermobileappname`, `airnotifierappname`, `androidappid`, `iosappid`, `setuplink`, `forcedurlscheme`

## Moodle Plugin Conventions

- Follow [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle) (PHP, 4-space indentation)
- All files must start with the GPL license header
- Use `get_string('key', 'tool_moodiymobile')` for user-facing text; add strings to `lang/en/tool_moodiymobile.php`
- Use `set_config()` / `get_config()` for persistent storage
- Admin pages must call `require_login()` and check `require_capability('moodle/site:config', ...)`
