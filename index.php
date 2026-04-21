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
 * Version details.
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This page displays the moodiy registration form.
 * It also handles update operation by web service.
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$data = optional_param('data', '', PARAM_TEXT);

admin_externalpage_setup('tool_moodiymobile_settings');

$PAGE->set_title(get_string('pluginname', 'tool_moodiymobile'));
$PAGE->set_heading(get_site()->fullname);

// Process airnotifier settings if provided.
if (!empty($data)) {
    try {
        $data = base64_decode($data, true);
        if ($data === false) {
            throw new coding_exception(get_string('invalidjsondata', 'tool_moodiymobile'));
        }
        $datajson = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new coding_exception(get_string('invalidjsondata', 'tool_moodiymobile'));
        }
        \tool_moodiymobile\utility::store_airnotifier_settings($datajson);
        \tool_moodiymobile\utility::apply_airnotifier_settings($datajson);
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Prepare airnotifier settings.
$airnotifier = get_config('tool_moodiymobile', 'airnotifiersetting') ?? false;
$airnotifiersetting = \tool_moodiymobile\utility::decode_airnotifier_settings($airnotifier);
$hasairnotifier = !empty($airnotifiersetting);

// Check registration status and airnotifier settings.
$isregister = \tool_moodiyregistration\registration::is_registered();
$isinternal = \tool_moodiymobile\utility::is_internal_site();
$out = '';

// Internal client logic.
if ($isinternal) {
    if (!$hasairnotifier) {
        $out = $OUTPUT->notification(get_string('enabled_requires_app', 'tool_moodiymobile'), 'warning');
    } else {
        set_config('enabled', 1, 'tool_moodiymobile');
        \tool_moodiymobile\utility::apply_airnotifier_settings($airnotifiersetting);
    }
} else {
    // External client - check registration and airnotifier settings.
    if (!$isregister) {
        // Site not registered - show registration warning.
        $registrationurl = (new moodle_url(
            '/admin/tool/moodiyregistration/index.php',
            ['returnurl' => $PAGE->url->out(false)]
        ))->out(false);
        $notify = new \core\output\notification(
            get_string('app_siteregistrationwarning', 'tool_moodiymobile', $registrationurl),
            \core\output\notification::NOTIFY_WARNING
        );
        $out = $OUTPUT->render($notify);
    } else if (!$hasairnotifier) {
        // Site registered but no airnotifier - show signup page.
        $utility = new \tool_moodiymobile\utility();
        $out = $utility->index_page_moodiysignup();
    }
}

// Initialize and process the settings form.
$isconfigurable = $isregister && $hasairnotifier;
$form = new \tool_moodiymobile\setting_form(null, ['isconfigurable' => $isconfigurable]);
$form->set_data((object) [
    'enabled' => get_config('tool_moodiymobile', 'enabled') ?? 0,
]);

// Process form submission.
if ($formdata = $form->get_data()) {
    // If internal client, settings already saved, redirect to avoid changes.
    if ($isinternal) {
        redirect($PAGE->url);
    }
    if (!empty($formdata->enabled)) {
        set_config('enabled', $formdata->enabled, 'tool_moodiymobile');
        if ($hasairnotifier) {
            \tool_moodiymobile\utility::apply_airnotifier_settings($airnotifiersetting);
        }
    } else {
        set_config('enabled', 0, 'tool_moodiymobile');
        \tool_moodiymobile\utility::reset_airnotifier_settings(true);
    }
    \core\notification::success(get_string('changessaved'));
    redirect($PAGE->url);
}
// Display the form.
$out .= $form->render();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_moodiymobile'));
echo $out;
echo $OUTPUT->footer();
