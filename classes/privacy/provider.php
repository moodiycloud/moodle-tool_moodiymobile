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

namespace tool_moodiymobile\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for tool_moodiymobile.
 *
 * The plugin coordinates site-level mobile-app provisioning with MoodiyCloud but
 * does not map that site-level data to Moodle privacy contexts.
 *
 * @package     tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data exported to MoodiyCloud.
     *
     * @param collection $collection The metadata collection to update.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('moodiycloud', [
            'siteuuid' => 'privacy:metadata:moodiycloud:siteuuid',
            'product' => 'privacy:metadata:moodiycloud:product',
        ], 'privacy:metadata:moodiycloud');

        return $collection;
    }

    /**
     * The plugin does not currently map its site-level provisioning data to Moodle contexts.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * The plugin does not currently map its site-level provisioning data to Moodle contexts.
     *
     * @param userlist $userlist The userlist containing the users with data in this context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
    }

    /**
     * The plugin does not currently export context-linked user data.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
    }
}
