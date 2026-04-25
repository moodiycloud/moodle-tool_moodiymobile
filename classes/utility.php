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
 * Class utility for moodiy mobile
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiymobile;

use moodle_url;

/**
 * Provides methods related to moodiy mobile.
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utility {
    /** @var string The Moodiy portal URL */
    const MOODIY_PORTAL_URL = 'https://portal.moodiycloud.com';
    /**
     * Keep this fallback aligned with tool_moodiyregistration\registration during mixed-version rollouts.
     *
     * @var int
     */
    private const CALLBACK_FRESHNESS_WINDOW = 900;

    /**
     * Keep this fallback aligned with tool_moodiyregistration\registration::STALE_TIMESTAMP_MESSAGE.
     *
     * @var string
     */
    private const STALE_TIMESTAMP_MESSAGE = 'Stale timestamp';

    /** @var string Default URL used to purchase the free app product. */
    public const DEFAULT_PURCHASE_URL = self::MOODIY_PORTAL_URL . '/purchase?type=3';

    /** @var array<string, string|null> Map of setting key to owning config component. */
    public const SETTING_COMPONENTS = [
        'airnotifieraccesskey' => null,
        'airnotifierurl' => null,
        'airnotifierport' => null,
        'airnotifiermobileappname' => null,
        'airnotifierappname' => null,
        'androidappid' => 'tool_mobile',
        'iosappid' => 'tool_mobile',
        'setuplink' => 'tool_mobile',
        'forcedurlscheme' => 'tool_mobile',
    ];

    /** @var array<string, mixed> Default Airnotifier settings managed by this plugin. */
    public const DEFAULTSETTING_AIRNOTIFIER = [
        'airnotifieraccesskey' => '',
        'airnotifierurl' => \message_airnotifier_manager::AIRNOTIFIER_PUBLICURL,
        'airnotifierport' => 443,
        'airnotifiermobileappname' => 'com.moodle.moodlemobile',
        'airnotifierappname' => 'commoodlemoodlemobile',
        'androidappid' => 'com.moodle.moodlemobile',
        'iosappid' => 633359593,
        'setuplink' => 'https://download.moodle.org/mobile',
        'forcedurlscheme' => 'moodlemobile',
    ];

    /**
     * Returns the list of supported Airnotifier keys.
     *
     * @return array
     */
    public static function get_allowed_airnotifier_keys() {
        return array_keys(self::SETTING_COMPONENTS);
    }

    /**
     * Filters the incoming payload to supported Airnotifier settings only.
     *
     * @param array $settings
     * @return array
     */
    public static function normalize_airnotifier_settings(array $settings) {
        return array_intersect_key($settings, self::SETTING_COMPONENTS);
    }

    /**
     * Decodes stored plugin settings into a normalized array.
     *
     * @param mixed $settings
     * @return array
     */
    public static function decode_airnotifier_settings($settings) {
        if (is_object($settings)) {
            $settings = (array) $settings;
        } else if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            if (!is_array($decoded)) {
                return [];
            }
            $settings = $decoded;
        } else if (!is_array($settings)) {
            return [];
        }

        return self::normalize_airnotifier_settings($settings);
    }

    /**
     * Persists the normalized Airnotifier payload on the plugin config table.
     *
     * @param array $settings
     * @return void
     */
    public static function store_airnotifier_settings(array $settings) {
        $normalized = self::normalize_airnotifier_settings($settings);
        ksort($normalized);
        $encoded = json_encode($normalized);
        if ($encoded === false) {
            throw new \coding_exception('Failed to encode Airnotifier settings.');
        }

        set_config('airnotifiersetting', $encoded, 'tool_moodiymobile');
    }

    /**
     * Applies the normalized Airnotifier payload into Moodle config storage.
     *
     * @param array $settings
     * @return void
     */
    public static function apply_airnotifier_settings(array $settings) {
        $normalized = self::normalize_airnotifier_settings($settings);

        foreach ($normalized as $key => $value) {
            $component = self::SETTING_COMPONENTS[$key] ?? null;
            set_config($key, $value, $component);
        }
    }

    /**
     * Enable the mobile app plugin once an internal hosted site receives an Airnotifier key.
     *
     * Keep this separate from apply_airnotifier_settings(): applying config values and deciding
     * whether the plugin should be switched on are distinct concerns. External sites continue to
     * opt in through the admin form, while internal hosted sites are enabled by the signed callback
     * after Moodiy provisions their app settings.
     *
     * @param array $settings
     * @return void
     */
    public static function enable_internal_site_with_airnotifier(array $settings) {
        $normalized = self::normalize_airnotifier_settings($settings);
        $accesskey = trim((string) ($normalized['airnotifieraccesskey'] ?? ''));

        if ($accesskey === '' || !self::is_internal_site()) {
            return;
        }

        if (get_config('tool_moodiymobile', 'enabled')) {
            return;
        }

        set_config('enabled', 1, 'tool_moodiymobile');
        debugging(
            'tool_moodiymobile auto-enabled via signed Airnotifier callback for internal hosted site.',
            DEBUG_DEVELOPER
        );
    }

    /**
     * Determine whether the current Moodle instance is an internal hosted site.
     *
     * @return bool
     */
    public static function is_internal_site(): bool {
        global $CFG;

        $forcedpluginsettings = is_array($CFG->forced_plugin_settings ?? null) ? $CFG->forced_plugin_settings : [];

        return class_exists('\tool_moodiyregistration\registration')
            && is_callable(['\tool_moodiyregistration\registration', 'is_internal_site'])
            ? \tool_moodiyregistration\registration::is_internal_site()
            : array_key_exists('auth_maintenance', $forcedpluginsettings);
    }

    /**
     * Determine whether a signed callback timestamp is within the allowed freshness window.
     * Falls back to a local copy of the registration helper contract so mixed-version deployments
     * keep accepting timestamped callbacks before every plugin repo is upgraded together.
     *
     * @param mixed $timestamp
     * @return bool
     */
    public static function is_fresh_callback_timestamp($timestamp): bool {
        if (
            class_exists('\tool_moodiyregistration\registration')
            && is_callable(['\tool_moodiyregistration\registration', 'is_fresh_callback_timestamp'])
        ) {
            return \tool_moodiyregistration\registration::is_fresh_callback_timestamp($timestamp);
        }

        if (!is_scalar($timestamp)) {
            return false;
        }

        $timestampvalue = trim((string) $timestamp);
        if ($timestampvalue === '' || !preg_match('/^\d+$/', $timestampvalue)) {
            return false;
        }

        $epochtime = (int) $timestampvalue;
        if ($epochtime <= 0) {
            return false;
        }

        $timedifference = time() - $epochtime;

        return $timedifference >= 0 && $timedifference <= self::CALLBACK_FRESHNESS_WINDOW;
    }

    /**
     * Build the stale timestamp callback response contract.
     *
     * @return array
     */
    public static function stale_timestamp_error_response(): array {
        if (
            class_exists('\tool_moodiyregistration\registration')
            && is_callable(['\tool_moodiyregistration\registration', 'stale_timestamp_error_response'])
        ) {
            return \tool_moodiyregistration\registration::stale_timestamp_error_response();
        }

        return [
            'status' => 'error',
            'message' => self::STALE_TIMESTAMP_MESSAGE,
        ];
    }

    /**
     * Resets Airnotifier settings to plugin defaults.
     *
     * @param bool $clearforcedurlscheme
     * @return void
     */
    public static function reset_airnotifier_settings(bool $clearforcedurlscheme = false) {
        foreach (self::DEFAULTSETTING_AIRNOTIFIER as $key => $value) {
            if ($clearforcedurlscheme && $key === 'forcedurlscheme') {
                unset_config($key, 'tool_mobile');
                continue;
            }

            $component = self::SETTING_COMPONENTS[$key] ?? null;
            set_config($key, $value, $component);
        }
    }

    /**
     * Renders the widget for signup the free app
     *
     * @return string
     */
    public function index_page_moodiysignup() {
        global $CFG, $OUTPUT;
        $url = $this->get_moodiy_signup_url();

        $out = $OUTPUT->box(
            get_string('moodiyregistration', 'tool_moodiymobile') .
            $OUTPUT->single_button($url, get_string('moodiysignup', 'tool_moodiymobile'), 'get') .
            $OUTPUT->help_icon('moodiysignup', 'tool_moodiymobile'),
            'generalbox alert alert-warning',
            'signupforfreeapp'
        );

        return $out;
    }

    /**
     * Returns the Moodiy signup URL.
     *
     * @return moodle_url
     */
    public function get_moodiy_signup_url() {
        global $CFG;

        $url = get_config('tool_moodiymobile', 'mobileappurl') ?: self::DEFAULT_PURCHASE_URL;

        // Append the basic information about our site.
        $site = [
            'siteuuid' => \tool_moodiyregistration\registration::get_siteuuid(),
            'product' => 'free app',
        ];

        $site = $this->encode_site_information($site);

        return new moodle_url($url, ['site' => $site]);
    }

    /**
     * Encodes the given array in a way that can be safely appended as HTTP GET param
     *
     * Be ware! The recipient may rely on the exact way how the site information is encoded.
     * Do not change anything here unless you know what you are doing and understand all
     * consequences! (Don't you love warnings like that, too? :-p)
     *
     * @param array $info
     * @return string
     */
    protected function encode_site_information(array $info) {
        return base64_encode(json_encode($info));
    }
}
