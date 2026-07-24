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
 * Form allowing a user to update the profile fields required by the activity.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Profile update form.
 *
 * Dynamically renders the standard and custom user profile fields that the
 * activity has been configured to require, pre-filled with the current user's
 * data, and persists any changes back to the user's profile.
 */
class mod_profileupdate_edit_form extends moodleform {

    /**
     * Custom profile field helper objects, keyed by field shortname.
     *
     * @var profile_field_base[]
     */
    protected array $customfields = [];

    /**
     * Defines the form elements for each selected profile field.
     */
    public function definition() {
        $mform = $this->_form;
        $fields = $this->_customdata['fields'];

        foreach ($fields as $field) {
            if ($field['type'] === 'custom') {
                $this->add_custom_element($field['name']);
            } else {
                $this->add_standard_element($field['name'], $field['label']);
            }
        }

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Add a standard user field element to the form.
     *
     * @param string $name The standard user field name.
     * @param string $label The human readable label to display.
     * @return void
     */
    protected function add_standard_element($name, $label) {
        $mform = $this->_form;

        switch ($name) {
            case 'country':
                $choices = ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries();
                $mform->addElement('select', $name, $label, $choices);
                $mform->setType($name, PARAM_ALPHA);
                break;

            case 'timezone':
                $choices = core_date::get_list_of_timezones(null, true);
                $mform->addElement('select', $name, $label, $choices);
                $mform->setType($name, PARAM_TIMEZONE);
                break;

            case 'description':
                $mform->addElement('textarea', $name, $label, ['rows' => 6, 'cols' => 50]);
                $mform->setType($name, PARAM_TEXT);
                break;

            case 'email':
                $mform->addElement('text', $name, $label, ['size' => 40]);
                $mform->setType($name, PARAM_RAW_TRIMMED);
                $mform->addRule($name, get_string('required'), 'required', null, 'client');
                break;

            case 'url':
                $mform->addElement('text', $name, $label, ['size' => 40]);
                $mform->setType($name, PARAM_URL);
                break;

            default:
                $mform->addElement('text', $name, $label, ['size' => 40]);
                $mform->setType($name, PARAM_NOTAGS);
                break;
        }
    }

    /**
     * Add a custom profile field element to the form using the profile field API.
     *
     * @param string $shortname The custom profile field shortname.
     * @return void
     */
    protected function add_custom_element($shortname) {
        global $DB, $CFG, $USER;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            return;
        }

        $classfile = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
        if (!file_exists($classfile)) {
            return;
        }
        require_once($classfile);

        $classname = 'profile_field_' . $field->datatype;
        if (!class_exists($classname)) {
            return;
        }

        /** @var profile_field_base $formfield */
        $formfield = new $classname($field->id, $USER->id);
        $formfield->edit_field($this->_form);

        $this->customfields[$shortname] = $formfield;
    }

    /**
     * Pre-fill the form with the given user's current profile data.
     *
     * @param stdClass $user The user whose data should populate the form.
     * @return void
     */
    public function set_user_data($user) {
        $data = clone $user;

        foreach ($this->customfields as $formfield) {
            $formfield->edit_load_user_data($data);
        }

        $this->set_data($data);
    }

    /**
     * Persist the submitted profile data back to the current user's profile.
     *
     * @param stdClass $data The validated data returned by get_data().
     * @return void
     */
    public function save_data($data) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/user/lib.php');

        $userupdate = (object) ['id' => $USER->id];
        $hasstandard = false;

        foreach ($this->_customdata['fields'] as $field) {
            if ($field['type'] !== 'standard') {
                continue;
            }
            $name = $field['name'];
            if (!property_exists($data, $name)) {
                continue;
            }
            $userupdate->$name = $data->$name;
            if ($name === 'description') {
                $userupdate->descriptionformat = FORMAT_HTML;
            }
            $hasstandard = true;
        }

        if ($hasstandard) {
            user_update_user($userupdate, false, true);
        }

        if ($this->customfields) {
            // The profile field API expects the user id in the "id" property.
            $customdata = clone $data;
            $customdata->id = $USER->id;
            foreach ($this->customfields as $formfield) {
                $formfield->edit_save_data($customdata);
            }
        }

        // Refresh the in-memory user session so subsequent pages show fresh data.
        if ($hasstandard) {
            $refreshed = \core_user::get_user($USER->id);
            foreach ((array) $refreshed as $key => $value) {
                if (property_exists($USER, $key)) {
                    $USER->$key = $value;
                }
            }
        }
    }
}
