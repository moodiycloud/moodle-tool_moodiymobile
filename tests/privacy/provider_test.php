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

/**
 * Privacy provider tests for tool_moodiymobile.
 *
 * @package    tool_moodiymobile
 * @category   test
 * @copyright  2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_moodiymobile\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Verify that metadata is exposed for the external MoodiyCloud service.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('tool_moodiymobile');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $items[0]);
    }

    /**
     * Verify that the provider advertises the expected privacy interfaces.
     *
     * @return void
     */
    public function test_provider_interfaces(): void {
        $provider = new provider();

        $this->assertInstanceOf(\core_privacy\local\metadata\provider::class, $provider);
        $this->assertInstanceOf(\core_privacy\local\request\plugin\provider::class, $provider);
        $this->assertInstanceOf(\core_privacy\local\request\core_userlist_provider::class, $provider);
    }
}
