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
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The Moodiy mobile setting form.
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_form extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $isconfigurable = $this->_customdata['isconfigurable'];
        $isinternal = utility::is_internal_site();

        $attributes = ['class' => 'text-right'];
        if (!$isconfigurable || $isinternal) {
            $attributes['disabled'] = 'disabled';
        }
        $mform->addElement(
            'checkbox',
            'enabled',
            get_string('enabled', 'tool_moodiymobile'),
            '<span class="text-muted">Default: No</span>',
            $attributes
        );

        $mform->setDefault('enabled', 0);
        $mform->setType('enabled', PARAM_BOOL);

        $mform->addElement('static', 'description', '', get_string('enabled_desc', 'tool_moodiymobile'));

        $this->add_action_buttons(false);
    }
}
