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
 * Tests for tool_moodiymobile utility helpers.
 *
 * @package    tool_moodiymobile
 * @category   test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_moodiymobile\utility
 */
final class utility_test extends \advanced_testcase {
    /**
     * Allowed keys should match the configured component map.
     */
    public function test_get_allowed_airnotifier_keys_returns_setting_component_keys(): void {
        $this->assertSame(array_keys(utility::SETTING_COMPONENTS), utility::get_allowed_airnotifier_keys());
    }

    /**
     * Unknown payload keys should be dropped before storage.
     */
    public function test_normalize_airnotifier_settings_filters_unknown_keys(): void {
        $settings = [
            'airnotifieraccesskey' => 'secret',
            'androidappid' => 'com.example.app',
            'unexpected' => 'ignore-me',
        ];

        $normalized = utility::normalize_airnotifier_settings($settings);

        $this->assertSame([
            'airnotifieraccesskey' => 'secret',
            'androidappid' => 'com.example.app',
        ], $normalized);
    }

    /**
     * Provider covering the supported decode input types.
     *
     * @return array<string, array{0:mixed, 1:array}>
     */
    public static function decode_airnotifier_settings_provider(): array {
        return [
            'array input' => [
                [
                    'airnotifieraccesskey' => 'abc',
                    'androidappid' => 'com.example.app',
                    'skipme' => 'ignored',
                ],
                [
                    'airnotifieraccesskey' => 'abc',
                    'androidappid' => 'com.example.app',
                ],
            ],
            'object input' => [
                (object)[
                    'airnotifieraccesskey' => 'abc',
                    'androidappid' => 'com.example.app',
                    'skipme' => 'ignored',
                ],
                [
                    'airnotifieraccesskey' => 'abc',
                    'androidappid' => 'com.example.app',
                ],
            ],
            'json input' => [
                '{"airnotifieraccesskey":"abc","androidappid":"com.example.app","skipme":"ignored"}',
                [
                    'airnotifieraccesskey' => 'abc',
                    'androidappid' => 'com.example.app',
                ],
            ],
            'invalid json' => [
                '{invalid-json}',
                [],
            ],
            'unsupported type' => [
                42,
                [],
            ],
        ];
    }

    /**
     * Decoding should normalise all supported payload shapes.
     *
     * @dataProvider decode_airnotifier_settings_provider
     * @param mixed $input
     * @param array $expected
     */
    public function test_decode_airnotifier_settings_handles_supported_input_types($input, array $expected): void {
        $this->assertSame($expected, utility::decode_airnotifier_settings($input));
    }

    /**
     * Stored payloads should be normalised and sorted before persistence.
     */
    public function test_store_airnotifier_settings_persists_sorted_normalized_json(): void {
        $this->resetAfterTest(true);

        utility::store_airnotifier_settings([
            'androidappid' => 'com.example.android',
            'unexpected' => 'ignored',
            'airnotifieraccesskey' => 'secret',
        ]);

        $stored = get_config('tool_moodiymobile', 'airnotifiersetting');

        $this->assertSame(
            '{"airnotifieraccesskey":"secret","androidappid":"com.example.android"}',
            $stored
        );
    }

    /**
     * Timestamp metadata used for signed callbacks must not be stored as plugin settings.
     */
    public function test_store_airnotifier_settings_ignores_timestamp_metadata(): void {
        $this->resetAfterTest(true);

        utility::store_airnotifier_settings([
            'airnotifieraccesskey' => 'secret',
            'timestamp' => time(),
        ]);

        $stored = get_config('tool_moodiymobile', 'airnotifiersetting');

        $this->assertSame('{"airnotifieraccesskey":"secret"}', $stored);
    }

    /**
     * Internal-site detection should follow the shared registration helper contract with fallback.
     */
    public function test_is_internal_site_handles_missing_and_present_forced_plugin_settings(): void {
        global $CFG;

        $this->resetAfterTest(true);

        unset($CFG->forced_plugin_settings);
        $this->assertFalse(utility::is_internal_site());

        $CFG->forced_plugin_settings = [];
        $this->assertFalse(utility::is_internal_site());

        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
        $this->assertTrue(utility::is_internal_site());
    }

    /**
     * Timestamp validation should stay available even during mixed-version plugin rollouts.
     */
    public function test_is_fresh_callback_timestamp_enforces_window(): void {
        $this->assertTrue(utility::is_fresh_callback_timestamp(time()));
        $this->assertTrue(utility::is_fresh_callback_timestamp((string) time()));
        $this->assertFalse(utility::is_fresh_callback_timestamp(time() - 901));
        $this->assertFalse(utility::is_fresh_callback_timestamp(time() + 1));
        $this->assertFalse(utility::is_fresh_callback_timestamp('-1'));
        $this->assertFalse(utility::is_fresh_callback_timestamp('not-a-timestamp'));
        $this->assertFalse(utility::is_fresh_callback_timestamp(null));
    }

    /**
     * The stale timestamp callback contract should stay stable even without the shared helper.
     */
    public function test_stale_timestamp_error_response_matches_callback_contract(): void {
        $this->assertSame([
            'status' => 'error',
            'message' => 'Stale timestamp',
        ], utility::stale_timestamp_error_response());
    }

    /**
     * Applying settings should write values to the right config component.
     */
    public function test_apply_airnotifier_settings_writes_to_expected_components(): void {
        $this->resetAfterTest(true);
        set_config('enabled', 0, 'tool_moodiymobile');

        utility::apply_airnotifier_settings([
            'airnotifieraccesskey' => 'secret',
            'airnotifierurl' => 'https://push.example.test',
            'androidappid' => 'com.example.android',
            'iosappid' => '123456',
        ]);

        $this->assertSame('secret', get_config('moodle', 'airnotifieraccesskey'));
        $this->assertSame('https://push.example.test', get_config('moodle', 'airnotifierurl'));
        $this->assertSame('com.example.android', get_config('tool_mobile', 'androidappid'));
        $this->assertSame('123456', get_config('tool_mobile', 'iosappid'));
        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
    }

    /**
     * Internal hosted sites should be enabled after a valid Airnotifier key arrives.
     */
    public function test_enable_internal_site_with_airnotifier_enables_internal_sites_with_key(): void {
        global $CFG;

        $this->resetAfterTest(true);
        set_config('enabled', 0, 'tool_moodiymobile');
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];

        utility::enable_internal_site_with_airnotifier([
            'airnotifieraccesskey' => 'secret',
        ]);

        $this->assertSame('1', get_config('tool_moodiymobile', 'enabled'));
        $this->assertDebuggingCalled(
            'tool_moodiymobile auto-enabled via signed Airnotifier callback for internal hosted site.',
            DEBUG_DEVELOPER
        );
    }

    /**
     * Internal hosted sites should not be enabled without an Airnotifier key.
     */
    public function test_enable_internal_site_with_airnotifier_requires_key(): void {
        global $CFG;

        $this->resetAfterTest(true);
        set_config('enabled', 0, 'tool_moodiymobile');
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];

        utility::enable_internal_site_with_airnotifier([
            'airnotifieraccesskey' => ' ',
        ]);

        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
    }

    /**
     * Internal hosted sites should not be enabled if the callback omits the Airnotifier key.
     */
    public function test_enable_internal_site_with_airnotifier_handles_missing_key(): void {
        global $CFG;

        $this->resetAfterTest(true);
        set_config('enabled', 0, 'tool_moodiymobile');
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];

        utility::enable_internal_site_with_airnotifier([
            'androidappid' => 'com.example.app',
        ]);

        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
    }

    /**
     * External sites should not be auto-enabled by an Airnotifier settings callback.
     */
    public function test_enable_internal_site_with_airnotifier_does_not_enable_external_sites(): void {
        global $CFG;

        $this->resetAfterTest(true);
        set_config('enabled', 0, 'tool_moodiymobile');
        $CFG->forced_plugin_settings = [];

        utility::enable_internal_site_with_airnotifier([
            'airnotifieraccesskey' => 'secret',
        ]);

        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
    }

    /**
     * Resetting settings should restore defaults and optionally clear the URL scheme.
     */
    public function test_reset_airnotifier_settings_restores_defaults_and_can_clear_forcedurlscheme(): void {
        $this->resetAfterTest(true);
        set_config('enabled', 1, 'tool_moodiymobile');

        utility::apply_airnotifier_settings([
            'airnotifieraccesskey' => 'secret',
            'forcedurlscheme' => 'customscheme',
        ]);

        utility::reset_airnotifier_settings(true);

        $this->assertSame('', get_config('moodle', 'airnotifieraccesskey'));
        $this->assertSame(
            utility::DEFAULTSETTING_AIRNOTIFIER['airnotifierurl'],
            get_config('moodle', 'airnotifierurl')
        );
        $this->assertSame(
            utility::DEFAULTSETTING_AIRNOTIFIER['androidappid'],
            get_config('tool_mobile', 'androidappid')
        );
        $this->assertFalse(get_config('tool_mobile', 'forcedurlscheme'));
        $this->assertSame('1', get_config('tool_moodiymobile', 'enabled'));
    }
}
