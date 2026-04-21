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

namespace tool_moodiymobile;

/**
 * Tests for tool_moodiymobile observers.
 *
 * @package    tool_moodiymobile
 * @category   test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_moodiymobile\observers
 */
final class observers_test extends \advanced_testcase {
    /**
     * Unregistering a site should reset the mobile-app configuration.
     */
    public function test_reset_app_clears_plugin_state_and_restores_defaults(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'tool_moodiymobile');
        set_config('airnotifiersetting', '{"airnotifieraccesskey":"secret"}', 'tool_moodiymobile');
        utility::apply_airnotifier_settings([
            'airnotifieraccesskey' => 'secret',
            'androidappid' => 'com.example.android',
            'forcedurlscheme' => 'customscheme',
        ]);

        observers::reset_app((object) ['eventname' => 'tool_moodiyregistration\\event\\moodiy_unregistration']);

        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
        $this->assertFalse(get_config('tool_moodiymobile', 'airnotifiersetting'));
        $this->assertSame('', get_config('moodle', 'airnotifieraccesskey'));
        $this->assertSame(
            utility::DEFAULTSETTING_AIRNOTIFIER['androidappid'],
            get_config('tool_mobile', 'androidappid')
        );
        $this->assertFalse(get_config('tool_mobile', 'forcedurlscheme'));
    }
}
