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
 * Defines the backup steps used by backup_profileupdate_activity_task.
 *
 * @package     mod_profileupdate
 * @category    backup
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete profileupdate structure for backup, with file annotations.
 */
class backup_profileupdate_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure to back up, sourced from the profileupdate and profileupdate_fields tables.
     *
     * @return backup_activity_structure The structure wrapped in the standard activity envelope.
     */
    protected function define_structure() {

        $profileupdate = new backup_nested_element('profileupdate', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
        ]);

        $fields = new backup_nested_element('fields');

        $field = new backup_nested_element('field', ['id'], [
            'fieldtype', 'fieldname', 'sortorder',
        ]);

        // Build the tree.
        $profileupdate->add_child($fields);
        $fields->add_child($field);

        // Define sources. The selected fields are activity configuration, not personal
        // data, so they are backed up regardless of the "include user info" setting.
        $profileupdate->set_source_table('profileupdate', ['id' => backup::VAR_ACTIVITYID]);
        $field->set_source_table('profileupdate_fields', ['profileupdateid' => backup::VAR_PARENTID], 'sortorder ASC');

        // Define file annotations.
        $profileupdate->annotate_files('mod_profileupdate', 'intro', null);

        // Return the root element (profileupdate), wrapped into standard activity structure.
        return $this->prepare_activity_structure($profileupdate);
    }
}
