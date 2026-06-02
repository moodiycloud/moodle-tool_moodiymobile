# Moodle.org submission notes

Use this file as a copy source when creating the first Moodle plugins directory
record for `tool_moodiymobile`.

## Plugin metadata

- Name: Moodiy mobile app
- Component: `tool_moodiymobile`
- Plugin type: Administration tool (`tool`)
- Release name: `0.1.2`
- Version: `2026042500`
- Maturity: Alpha
- Supported Moodle versions: 4.5, 5.0, 5.1
- License: GNU GPL v3 or later
- Required dependency: `tool_moodiyregistration` version `2025062300` or later

## Short description

Provision MoodiyCloud mobile app and AirNotifier settings for registered sites.

## Full description

Moodiy mobile is a Moodle administration tool that connects a registered Moodle
site to the MoodiyCloud mobile-app provisioning flow. It stores and applies the
AirNotifier configuration delivered for the site, syncs relevant Moodle mobile
settings, and lets administrators enable or disable the MoodiyCloud mobile
integration.

This is a MoodiyCloud integration and is separate from Moodle's official site
registration service.

The plugin depends on `tool_moodiyregistration` because it uses the registered
site UUID when provisioning mobile settings with MoodiyCloud.

## Links

- Website: `https://moodiycloud.com/free-mobile-app-for-moodle`
- Source control: `https://github.com/moodiycloud/moodle-tool_moodiymobile`
- Bug tracker: `https://github.com/moodiycloud/moodle-tool_moodiymobile/issues`
- Documentation: `https://github.com/moodiycloud/moodle-tool_moodiymobile#readme`
- Support: `support@moodiycloud.com`

## Reviewer notes

Install and configure `tool_moodiyregistration` first. Then install this plugin
at `admin/tool/moodiymobile`, run the Moodle upgrade, and open Site
administration > Moodiy mobile app.

The mobile provisioning flow requires MoodiyCloud reviewer access or service
entitlement. Temporary reviewer access or provisioning support can be provided
through `support@moodiycloud.com`; private credentials should be shared through
the Moodle.org approval issue, not in the public repository.

## Release notes

- Auto-enable the mobile plugin for internal hosted sites after a signed AirNotifier sync with a non-empty key.
- Added privacy metadata for MoodiyCloud mobile provisioning flows.
- Moodle.org release workflow scaffolding.
- Hardened external endpoint origin/header handling for MoodiyCloud mobile updates.
