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
 * Plugin administration pages are defined here.
 *
 * @package     tool_moodiymobile
 * @category    admin
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'tools',
        new admin_category('moodiymobile', get_string('moodiymobile', 'tool_moodiymobile'))
    );

    $managepage = new admin_externalpage(
        'tool_moodiymobile_settings',
        get_string('pluginname', 'tool_moodiymobile'),
        new moodle_url('/admin/tool/moodiymobile/index.php')
    );

    $ADMIN->add('moodiymobile', $managepage);
    $settings = new admin_settingpage('tool_moodiymobile', get_string('moodiymobilesettings', 'tool_moodiymobile'));
    $settings->add(new admin_setting_configtext(
        'tool_moodiymobile/mobileappurl',
        get_string('mobileappurl', 'tool_moodiymobile'),
        get_string('mobileappurl_desc', 'tool_moodiymobile'),
        \tool_moodiymobile\utility::DEFAULT_PURCHASE_URL,
        PARAM_URL
    ));
    $ADMIN->add('moodiymobile', $settings);
}
