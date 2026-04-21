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
 * Observer class containing methods monitoring various events.
 *
 * @package     tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiymobile;

/**
 * Class event observers
 *
 * This class contains methods that respond to various events in Moodle.
 *
 * @package tool_moodiymobile
 */
class observers {
    /**
     * Observer method to reset app on site unregister from Moodiycloud
     *
     * This method is triggered when site unregister from MoodiyCloud.
     *
     * @param object $event The event object.
     */
    public static function reset_app(object $event): void {

        // Reset to default.
        set_config('enabled', 0, 'tool_moodiymobile');
        \tool_moodiymobile\utility::reset_airnotifier_settings(true);
        unset_config('airnotifiersetting', 'tool_moodiymobile');
    }
}
