# Changelog

## Unreleased

- No unreleased changes.

## 0.2.0 - 2026-06-07

- declare support for Moodle 5.2 and 5.3 (supported range Moodle 4.5–5.3)
- raise the minimum required Moodle to 4.5 to match the supported range
- replace the deprecated `$OUTPUT->notification()` call with a `\core\output\notification` render

## 0.1.3 - 2026-06-02

- clarify that the mobile provisioning flow is a MoodiyCloud integration

## 0.1.2 - 2026-04-25

- auto-enable the mobile plugin for internal hosted sites after a signed AirNotifier sync with a non-empty key, without requiring a Moodle admin page visit
- add the missing privacy provider for MoodiyCloud mobile provisioning flows
- normalize public licensing and ownership metadata for MoodiyCloud
- expand the README for public distribution and future Moodle plugins-directory submission
- add Moodle.org release workflow scaffolding
- harden external endpoint origin/header handling for MoodiyCloud mobile updates

## 0.1.1 - 2026-04-02

- current alpha release of the MoodiyCloud mobile integration plugin
