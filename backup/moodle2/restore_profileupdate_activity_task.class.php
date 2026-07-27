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
 * Defines restore_profileupdate_activity_task class.
 *
 * @package     mod_profileupdate
 * @category    backup
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/profileupdate/backup/moodle2/restore_profileupdate_stepslib.php');

/**
 * Profileupdate restore task that provides all the settings and steps to perform
 * one complete restore of the activity.
 */
class restore_profileupdate_activity_task extends restore_activity_task {
    /**
     * No particular settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Profileupdate only has one structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_profileupdate_activity_structure_step('profileupdate_structure', 'profileupdate.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('profileupdate', ['intro'], 'profileupdate');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by
     * the link decoder.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('PROFILEUPDATEVIEWBYID', '/mod/profileupdate/view.php?id=$1', 'course_module');

        return $rules;
    }
}
