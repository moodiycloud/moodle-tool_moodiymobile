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
 * Signed deployment branding without credential writes.
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiymobile;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Keep the repository internal-file guard convention.
defined('MOODLE_INTERNAL') || die();

/**
 * Signed branding updates that never write the deployment-owned global identity pair.
 *
 * @package tool_moodiymobile
 * @copyright 2026 MoodiyCloud <support@moodiycloud.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deployment_branding {
    /** @var string Explicit alternate request mode. */
    public const MODE = 'preserve_deployment_pair_v1';

    /** @var string Signed request discriminator. */
    public const FIELD = 'airnotifier_update_mode';

    /** @var string Private signed precondition from current pair-verified delivery. */
    public const PRECONDITION = 'airnotifier_expected_key_sha256';

    /**
     * Require the complete existing branding map, without any access key.
     *
     * @param array $settings
     * @return bool
     */
    public static function valid_payload(array $settings): bool {
        if (
            ($settings[self::FIELD] ?? null) !== self::MODE || !is_int($settings['timestamp'] ?? null)
                || array_key_exists('airnotifieraccesskey', $settings)
        ) {
            return false;
        }
        $fields = utility::SETTING_COMPONENTS;
        unset($fields['airnotifieraccesskey']);
        if (count($settings) !== count($fields) + 3 || !array_key_exists(self::PRECONDITION, $settings)) {
            return false;
        }
        $digest = $settings[self::PRECONDITION];
        if ($digest !== null && (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1)) {
            return false;
        }
        foreach ($fields as $field => $component) {
            if (
                !array_key_exists($field, $settings)
                    || (!is_string($settings[$field]) && !is_int($settings[$field]) && $settings[$field] !== null)
            ) {
                return false;
            }
        }
        return is_string($settings['airnotifierappname']) && trim($settings['airnotifierappname']) !== '';
    }

    /**
     * Stage all fields; apply branding and enable only against the exact current global pair.
     *
     * The ordinary branding/enablement helpers can write a cached key back. This path
     * deliberately never passes a key to either helper, including during bootstrap.
     *
     * @param array $settings Validated authenticated request.
     * @return string State observed after applying the non-key fields.
     */
    public static function apply(array $settings): string {
        global $DB;
        if (!self::valid_payload($settings) || !utility::is_internal_site()) {
            throw new \invalid_parameter_exception('Invalid deployment branding.');
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            utility::store_airnotifier_settings($settings);
            $matched = self::matches_current_pair($settings);
            if ($matched) {
                $branding = utility::normalize_airnotifier_settings($settings);
                unset($branding['airnotifieraccesskey'], $branding['airnotifierappname']);
                utility::apply_airnotifier_settings($branding);
                set_config('enabled', 1, 'tool_moodiymobile');
            }
            $transaction->allow_commit();
            utility::invalidate_airnotifier_caches();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
            throw $exception;
        }
        return $matched ? 'applied' : 'staged_waiting_for_pair';
    }

    /**
     * Read and lock the two persisted global fields inside the owning apply transaction.
     *
     * @param array $settings Authenticated complete settings.
     * @return bool
     */
    private static function matches_current_pair(array $settings): bool {
        $digest = $settings[self::PRECONDITION];
        if ($digest === null) {
            return false;
        }
        $rows = utility::lock_airnotifier_pair();
        $key = $rows['airnotifieraccesskey']->value ?? null;
        $name = $rows['airnotifierappname']->value ?? null;
        return count($rows) === 2 && is_string($key) && is_string($name)
            && hash_equals($digest, hash('sha256', $key)) && $name === $settings['airnotifierappname'];
    }

    /**
     * Bind the success acknowledgement to this exact authenticated payload.
     *
     * @param array $settings
     * @param string $siteuuid
     * @param string $state
     * @return array
     */
    public static function acknowledgement(array $settings, string $siteuuid, string $state): array {
        if (!in_array($state, ['applied', 'staged_waiting_for_pair'], true)) {
            throw new \invalid_parameter_exception('Invalid deployment branding state.');
        }
        ksort($settings);
        $ack = ['mode' => self::MODE, 'schema_version' => 1,
            'request_sha256' => hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR)),
            'state' => $state, 'pair_write_performed' => false];
        ksort($ack);
        return ['status' => 'success', 'deployment_branding' => $ack,
            'deployment_branding_signature' => hash_hmac('sha256', json_encode($ack, JSON_THROW_ON_ERROR), $siteuuid)];
    }
}
