<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Standalone child for native PHPUnit concurrency tests; never a web endpoint.
 *
 * @package tool_moodiymobile
 * @copyright 2026 MoodiyCloud <support@moodiycloud.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This CLI-only actor uses Moodle's PHPUnit utility bootstrap below, not the runtime config.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    exit(1);
}
// Bootstrap the PHPUnit database as a utility actor without resetting the parent's fixture.
define('PHPUNIT_UTIL', true);
// Moodle 5.1+ keeps Composer above public/, while dirroot and PHPUnit remain inside it.
$composerroot = is_file($argv[1] . '/vendor/autoload.php') ? $argv[1] : dirname($argv[1]);
require($composerroot . '/vendor/autoload.php');
require($argv[1] . '/lib/phpunit/bootstrap.php');
$CFG->forced_plugin_settings = ['auth_maintenance' => []];
$payload = json_decode(base64_decode($argv[2], true), true, 512, JSON_THROW_ON_ERROR);
echo json_encode(['pid' => getmypid(),
    'loaded_key_sha256' => hash('sha256', (string)get_config('moodle', 'airnotifieraccesskey'))], JSON_THROW_ON_ERROR) . "\n";
flush();
if (trim((string)fgets(STDIN)) !== 'apply') {
    exit(2);
}
$state = ($payload['fixture_legacy_helper'] ?? false)
    ? \tool_moodiymobile\utility::apply_airnotifier_branding()
    : \tool_moodiymobile\deployment_branding::apply($payload);
echo json_encode(['state' => $state], JSON_THROW_ON_ERROR) . "\n";
