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
 * Native coverage of the key-free deployment branding contract.
 *
 * @package tool_moodiymobile
 * @category test
 * @copyright 2026 MoodiyCloud <support@moodiycloud.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_moodiymobile\deployment_branding
 */
final class deployment_branding_test extends \advanced_testcase {
    /**
     * Complete synthetic non-key request.
     *
     * @return array
     */
    private function payload(): array {
        return [deployment_branding::FIELD => deployment_branding::MODE, 'timestamp' => time(),
            'airnotifierurl' => 'https://push.example.test', 'airnotifierport' => 443,
            'airnotifiermobileappname' => 'com.fixture.mobile', 'airnotifierappname' => 'fixturemobile',
            'androidappid' => 'com.fixture.android', 'iosappid' => '123456789',
            'setuplink' => 'https://example.test/install', 'forcedurlscheme' => 'fixturemobile'];
    }

    /**
     * Complete branding applies without changing the physical key.
     */
    public function test_applies_all_eight_fields_without_storing_or_changing_key(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
        set_config('airnotifieraccesskey', 'provider-owned');
        set_config('enabled', 0, 'tool_moodiymobile');
        $payload = $this->payload();
        $this->assertSame('applied', deployment_branding::apply($payload));
        $this->assertSame('provider-owned', get_config('moodle', 'airnotifieraccesskey'));
        $this->assertSame('1', get_config('tool_moodiymobile', 'enabled'));
        $stored = utility::decode_airnotifier_settings(get_config('tool_moodiymobile', 'airnotifiersetting'));
        $this->assertArrayNotHasKey('airnotifieraccesskey', $stored);
        foreach (utility::SETTING_COMPONENTS as $field => $component) {
            if ($field === 'airnotifieraccesskey') {
                continue;
            }
            $this->assertSame((string)$payload[$field], get_config($component ?? 'moodle', $field));
            $this->assertSame($payload[$field], $stored[$field]);
        }
    }

    /**
     * Missing physical credentials must not be filled from Core or enable a new site.
     */
    public function test_stages_missing_key_without_enabling_or_creating_one(): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
        unset_config('airnotifieraccesskey');
        set_config('enabled', 0, 'tool_moodiymobile');
        $this->assertSame('staged_waiting_for_key', deployment_branding::apply($this->payload()));
        $this->assertFalse($DB->record_exists('config', ['name' => 'airnotifieraccesskey']));
        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
        $this->assertSame('com.fixture.android', get_config('tool_mobile', 'androidappid'));
    }

    /**
     * External mode requests are refused before config effects.
     */
    public function test_refuses_external_site_before_any_write(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->forced_plugin_settings = [];
        set_config('airnotifieraccesskey', 'external-original');
        set_config('enabled', 0, 'tool_moodiymobile');
        try {
            deployment_branding::apply($this->payload());
            $this->fail('External deployment mode must refuse.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertStringContainsString('Invalid deployment branding', $exception->debuginfo);
        }
        $this->assertSame('external-original', get_config('moodle', 'airnotifieraccesskey'));
        $this->assertSame('0', get_config('tool_moodiymobile', 'enabled'));
        $this->assertFalse(get_config('tool_moodiymobile', 'airnotifiersetting'));
    }

    /**
     * Unsupported, partial or mixed key-writing requests cannot enter this path.
     */
    public function test_rejects_ambiguous_or_partial_payloads(): void {
        $this->assertTrue(deployment_branding::valid_payload($this->payload()));
        foreach (array_keys($this->payload()) as $field) {
            $payload = $this->payload();
            unset($payload[$field]);
            $this->assertFalse(deployment_branding::valid_payload($payload));
        }
        foreach (['airnotifieraccesskey' => null, 'unexpected' => true] as $field => $value) {
            $this->assertFalse(deployment_branding::valid_payload($this->payload() + [$field => $value]));
        }
        $payload = $this->payload();
        $payload['androidappid'] = ['not-scalar'];
        $this->assertFalse(deployment_branding::valid_payload($payload));
    }

    /**
     * The signed reply binds the exact payload and observed staging state.
     */
    public function test_acknowledgement_is_bound_to_request_and_state(): void {
        $payload = $this->payload();
        $response = deployment_branding::acknowledgement($payload, 'fixture-registration', 'staged_waiting_for_key');
        ksort($payload);
        $ack = $response['deployment_branding'];
        $this->assertSame(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), $ack['request_sha256']);
        $this->assertFalse($ack['key_write_performed']);
        $this->assertSame('staged_waiting_for_key', $ack['state']);
        $this->assertSame(
            hash_hmac('sha256', json_encode($ack, JSON_THROW_ON_ERROR), 'fixture-registration'),
            $response['deployment_branding_signature']
        );
        $changed = $payload;
        $changed['androidappid'] = 'com.changed';
        $this->assertNotSame(
            $response['deployment_branding_signature'],
            deployment_branding::acknowledgement($changed, 'fixture-registration', 'applied')['deployment_branding_signature']
        );
    }

    /**
     * Concurrent provider replacement and removal cases.
     *
     * @return array<string,array{0:?string,1:bool}>
     */
    public static function concurrent_key_provider(): array {
        return ['newer key' => ['provider-newer', false], 'removed key' => [null, false],
            'helper newer key' => ['provider-newer', true], 'helper removed key' => [null, true]];
    }

    /**
     * A real second process cannot write its bootstrap-cached key over a later provider decision.
     *
     * @dataProvider concurrent_key_provider
     * @param string|null $newkey New provider state after the actor has bootstrapped.
     * @param bool $helper Exercise the legacy branding-only helper.
     */
    public function test_independent_stale_branding_actor_preserves_newer_key(?string $newkey, bool $helper): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        $this->preventResetByRollback();
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
        set_config('airnotifieraccesskey', 'provider-before');
        set_config('enabled', 0, 'tool_moodiymobile');
        $payload = $this->payload();
        if ($helper) {
            utility::store_airnotifier_settings($payload);
            $payload['fixture_legacy_helper'] = true;
        }
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/deployment_branding_actor.php', $CFG->dirroot,
            base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 10);
            $ready = fgets($pipes[1]);
            $this->assertNotFalse($ready, $ready === false ? stream_get_contents($pipes[2]) : '');
            $ready = json_decode($ready, true, 512, JSON_THROW_ON_ERROR);
            $this->assertNotSame(getmypid(), $ready['pid']);
            $this->assertSame(hash('sha256', 'provider-before'), $ready['loaded_key_sha256']);
            if ($newkey === null) {
                unset_config('airnotifieraccesskey');
            } else {
                set_config('airnotifieraccesskey', $newkey);
            }
            fwrite($pipes[0], "apply\n");
            fflush($pipes[0]);
            $result = fgets($pipes[1]);
            $this->assertNotFalse($result);
            $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
            $expectedstate = $helper ? $newkey !== null : ($newkey === null ? 'staged_waiting_for_key' : 'applied');
            $this->assertSame($expectedstate, $result['state']);
            $this->assertSame($newkey ?? false, $DB->get_field('config', 'value', ['name' => 'airnotifieraccesskey']));
            $this->assertSame(
                $helper || $newkey === null ? '0' : '1',
                $DB->get_field('config_plugins', 'value', ['plugin' => 'tool_moodiymobile', 'name' => 'enabled'])
            );
            fclose($pipes[0]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
            $process = null;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
        }
    }
}
