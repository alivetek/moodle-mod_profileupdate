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
 * The main module configuration form.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/profileupdate/lib.php');

/**
 * Module instance settings form.
 */
class mod_profileupdate_mod_form extends moodleform_mod {
    /**
     * Defines forms elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('profileupdatename', 'mod_profileupdate'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // Section allowing the configuration of the profile fields to be updated.
        $mform->addElement('header', 'fieldstoupdateheader', get_string('fieldstoupdate', 'mod_profileupdate'));
        $mform->setExpanded('fieldstoupdateheader', true);

        // Build a flat list of options (autocomplete does not support option groups).
        $options = [];
        foreach (profileupdate_get_available_fields() as $category => $fields) {
            foreach ($fields as $key => $label) {
                $options[$key] = $category . ': ' . $label;
            }
        }

        $select = $mform->addElement(
            'autocomplete',
            'profileupdatefields',
            get_string('fieldstoupdate', 'mod_profileupdate'),
            $options,
            ['multiple' => true]
        );
        $select->setMultiple(true);
        $mform->addHelpButton('profileupdatefields', 'fieldstoupdate', 'mod_profileupdate');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Preprocess form data before it is displayed.
     *
     * Loads the currently selected profile fields for the instance being edited.
     *
     * @param array $defaultvalues The default values passed to the form.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->_instance)) {
            $defaultvalues['profileupdatefields'] = profileupdate_get_selected_field_keys($this->_instance);
        }
    }
}
