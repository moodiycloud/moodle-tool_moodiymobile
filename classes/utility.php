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

        if (array_key_exists(deployment_branding::FIELD, $settings)) {
            // Never strip a stored preservation guard and reinterpret it as a
            // legacy write on a later admin visit or branding helper call.
            return deployment_branding::valid_payload($settings) ? $settings : [];
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
        if (array_key_exists(deployment_branding::FIELD, $settings)) {
            if (!deployment_branding::valid_payload($settings)) {
                throw new \invalid_parameter_exception('Invalid deployment branding.');
            }
            $normalized = $settings;
        } else {
            $normalized = self::normalize_airnotifier_settings($settings);
        }
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
        global $DB, $CFG;
        if (array_key_exists(deployment_branding::FIELD, $settings)) {
            deployment_branding::apply($settings);
            return;
        }
        $normalized = self::normalize_airnotifier_settings($settings);
        $pair = array_key_exists('airnotifieraccesskey', $normalized)
            && array_key_exists('airnotifierappname', $normalized);
        $transaction = null;
        $previous = [];
        if ($pair) {
            // These real endpoint/admin callers own their commit. A nested
            // allow_commit cannot invalidate caches after the physical commit.
            $DB->transactions_forbidden();
            $normalized = ['airnotifieraccesskey' => $normalized['airnotifieraccesskey'],
                'airnotifierappname' => $normalized['airnotifierappname']] + $normalized;
            foreach (['airnotifieraccesskey', 'airnotifierappname'] as $name) {
                $previous[$name] = [property_exists($CFG, $name), $CFG->$name ?? null];
            }
            $transaction = $DB->start_delegated_transaction();
        }
        try {
            if ($pair) {
                // An unchanged value still needs the pair lock before the other field changes.
                $DB->get_records_sql(
                    'SELECT id FROM {config} WHERE name IN (?, ?) ORDER BY name FOR UPDATE',
                    ['airnotifieraccesskey', 'airnotifierappname']
                );
            }
            foreach ($normalized as $key => $value) {
                $component = self::SETTING_COMPONENTS[$key] ?? null;
                set_config($key, $value, $component);
            }
            if ($transaction !== null) {
                $transaction->allow_commit();
                self::invalidate_airnotifier_caches();
            }
        } catch (\Throwable $exception) {
            if ($transaction !== null) {
                try {
                    $transaction->rollback($exception);
                } finally {
                    foreach ($previous as $name => [$existed, $value]) {
                        if ($existed) {
                            $CFG->$name = $value;
                        } else {
                            unset($CFG->$name);
                        }
                    }
                    self::invalidate_airnotifier_caches();
                }
            }
            throw $exception;
        }
    }

    /**
     * Invalidate only the config scopes written by mobile settings, after commit/rollback.
     */
    public static function invalidate_airnotifier_caches(): void {
        foreach (['core', 'tool_mobile', 'tool_moodiymobile'] as $component) {
            \cache_helper::invalidate_by_definition('core', 'config', [], $component);
        }
    }

    /**
     * Applies the mobile-app branding for the currently configured Airnotifier site.
     *
     * On agentless Premium boxes the deployment writes the global identity pair atomically. Branding may be
     * staged by the deployment or by the signed pair-preserving updatesettings.php mode.
     * This step makes the live mobile-app branding keys (app names, androidappid,
     * iosappid, setuplink, forcedurlscheme) consistent with that configured key so the
     * provisioned site is correctly branded without an admin page visit.
     *
     * The effective branding is the stored Airnotifier payload merged over the plugin
     * defaults, excluding both global identity fields so the pair the box
     * already received is never overwritten. It is a no-op (returning
     * false) when no Airnotifier access key is configured, or when no branding payload has
     * been staged on the plugin config, so callers can rely on the return value to report
     * whether branding was applied.
     *
     * Skipping the write when nothing is staged is important: merging an empty stored
     * payload over self::DEFAULTSETTING_AIRNOTIFIER would push the Moodle defaults
     * (e.g. com.moodle.moodlemobile) into the live `tool_mobile` config and clobber any
     * branding an admin already configured. We only apply branding when real branding was
     * actually staged.
     *
     * @return bool True when branding was applied, false when no access key or staged
     *              branding payload is available.
     */
    public static function apply_airnotifier_branding(): bool {
        $stored = self::decode_airnotifier_settings(get_config('tool_moodiymobile', 'airnotifiersetting'));
        if (array_key_exists(deployment_branding::FIELD, $stored)) {
            return deployment_branding::apply($stored) === 'applied';
        }
        if (!self::has_configured_airnotifier_key()) {
            return false;
        }

        // No branding staged: do not overwrite admin-configured tool_mobile branding with
        // the plugin/Moodle defaults. Apply only when a real payload was staged.
        if ($stored === []) {
            return false;
        }

        // Branding never needs to rewrite a key. A bootstrap-cached value may have
        // been replaced or removed by its owner since this process started.
        $effective = array_merge(self::DEFAULTSETTING_AIRNOTIFIER, $stored);
        unset($effective['airnotifieraccesskey'], $effective['airnotifierappname']);

        self::apply_airnotifier_settings($effective);

        return true;
    }

    /**
     * Check persisted key presence without relying on a process's bootstrap cache.
     *
     * @return bool
     */
    public static function has_configured_airnotifier_key(): bool {
        global $DB;
        $key = $DB->get_field('config', 'value', ['name' => 'airnotifieraccesskey']);
        return is_string($key) && trim($key) !== '';
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

        $stored = self::decode_airnotifier_settings(get_config('tool_moodiymobile', 'airnotifiersetting'));
        if (array_key_exists(deployment_branding::FIELD, $stored)) {
            deployment_branding::apply($stored);
            return;
        }

        // Ensure the live access key is set before branding so apply_airnotifier_branding()
        // sees a configured key. In the signed-callback flow apply_airnotifier_settings() has
        // already run, but this keeps the enable path correct when called on its own.
        if (!self::has_configured_airnotifier_key()) {
            $identity = ['airnotifieraccesskey' => $accesskey];
            if (array_key_exists('airnotifierappname', $normalized)) {
                $identity['airnotifierappname'] = $normalized['airnotifierappname'];
            } else if ($stored !== []) {
                // Preserve the existing legacy bootstrap choice, but install
                // its staged/default app name in the same transaction as the key.
                $effective = array_merge(self::DEFAULTSETTING_AIRNOTIFIER, $stored);
                $identity['airnotifierappname'] = $effective['airnotifierappname'];
            }
            self::apply_airnotifier_settings($identity);
        }

        // Make the live mobile-app branding consistent with the configured key. On agentless
        // Premium boxes the key may have been written via cfg.php with the branding staged on
        // tool_moodiymobile config, so re-apply it here to keep the app correctly branded.
        self::apply_airnotifier_branding();

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
