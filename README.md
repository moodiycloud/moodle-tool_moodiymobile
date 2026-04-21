# Moodiy mobile

`tool_moodiymobile` is a Moodle admin tool plugin that helps a registered Moodle
site connect to MoodiyCloud's mobile-app provisioning flow and related
Airnotifier configuration.

## What the plugin does

- checks whether the site is already registered with MoodiyCloud
- guides administrators through the MoodiyCloud mobile-app signup flow
- stores and applies the Airnotifier settings delivered for the site
- lets administrators enable or disable the MoodiyCloud mobile integration

## Supported Moodle versions

Current plugin metadata declares support for:

- Moodle `4.5`
- Moodle `5.0`
- Moodle `5.1`

## Dependency

This plugin depends on `tool_moodiyregistration`.

Install and configure `tool_moodiyregistration` first so the site has a
registered site UUID before attempting to provision the mobile app.

## Installation

### Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file containing the plugin code.
3. Check the validation report and finish the installation.

### Installing manually

Copy this repository into:

```text
{your/moodle/dirroot}/admin/tool/moodiymobile
```

Then complete the installation from _Site administration > Notifications_ or with:

```bash
php admin/cli/upgrade.php
```

## Configuration and usage

- Ensure the site is already registered with MoodiyCloud via `tool_moodiyregistration`.
- Open _Site administration > Moodiy mobile app_.
- If the site does not yet have mobile settings, follow the MoodiyCloud signup link.
- Once mobile settings are provisioned, enable the integration from the admin form.

## External service and privacy

This plugin integrates with MoodiyCloud services, including the MoodiyCloud
portal used for mobile-app provisioning.

The plugin can share the registered site UUID with MoodiyCloud during the mobile
signup flow so MoodiyCloud can provision the correct mobile-app settings for the
site.

Some MoodiyCloud mobile offerings may require a separate MoodiyCloud account or
service entitlement.

## Issue tracker and support

- Source code: `https://github.com/moodiycloud/moodle-tool_moodiymobile`
- Issue tracker: `https://github.com/moodiycloud/moodle-tool_moodiymobile/issues`
- Support: `support@moodiycloud.com`

## Release notes

Release notes for future tagged versions are tracked in `CHANGES.md`.

## License

2025-2026 MoodiyCloud <support@moodiycloud.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
