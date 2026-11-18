# tool_moodiymobile architecture

Moved from `CLAUDE.md` and `AGENTS.md` (18 Nov 2026). `AGENTS.md` keeps the rules and commands and routes here.

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
## GitHub Actions CI

Uses the standalone official `moodle-plugin-ci` recipe in `.github/workflows/ci.yml`.
Every matrix case installs Moodle and the plugin from source, then runs PHP
lint, PHPCS/PHPDoc with zero allowed warnings, validation, savepoints, Mustache,
Grunt, PHPUnit and Behat. PHP Mess Detector is informational. Stable 5.3 has
explicit PHP 8.3/8.4 cases on both PostgreSQL 17 and MariaDB 11.4; moving upstream
main is separate development coverage. The declared registration dependency is
installed from its source main branch; CI records its commit/installed version
and Moodle's commit/version before running checks. Publishing happens only through the
separate tag-driven `.github/workflows/moodle-release.yml`.

## Project Structure & Module Organization
This repository is a Moodle admin/tool plugin (`tool_moodiymobile`). Keep changes scoped to plugin conventions:
- `index.php`: main admin UI flow (registration checks, form handling, config sync).
- `updatesettings.php`: API endpoint for remote airnotifier config updates.
- `settings.php` and `version.php`: admin registration and plugin metadata.
- `classes/`: plugin classes (`utility.php`, `setting_form.php`, `observers.php`).
- `db/events.php`: event observer wiring.
- `lang/en/tool_moodiymobile.php`: all user-facing strings.
