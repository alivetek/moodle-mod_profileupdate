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
 * Defines the restore steps used by restore_profileupdate_activity_task.
 *
 * @package     mod_profileupdate
 * @category    backup
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one profileupdate activity.
 */
class restore_profileupdate_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the paths to restore, mirroring the backup structure.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('profileupdate', '/activity/profileupdate');
        $paths[] = new restore_path_element('profileupdate_field', '/activity/profileupdate/fields/field');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the profileupdate instance record.
     *
     * @param array $data
     * @return void
     */
    protected function process_profileupdate($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('profileupdate', $data);
        // Immediately after inserting the "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one selected profile field, remapped to the new instance id.
     *
     * @param array $data
     * @return void
     */
    protected function process_profileupdate_field($data) {
        global $DB;

        $data = (object) $data;
        $data->profileupdateid = $this->get_new_parentid('profileupdate');

        $DB->insert_record('profileupdate_fields', $data);
    }

    /**
     * Re-attach the intro files after the structure has been restored.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_profileupdate', 'intro', null);
    }
}
