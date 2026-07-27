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
 * Defines backup_profileupdate_activity_task class.
 *
 * @package     mod_profileupdate
 * @category    backup
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/profileupdate/backup/moodle2/backup_profileupdate_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the profileupdate instance.
 */
class backup_profileupdate_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the profileupdate.xml file.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_profileupdate_activity_structure_step('profileupdate_structure', 'profileupdate.xml'));
    }

    /**
     * Encodes URLs to the view.php script.
     *
     * @param string $content Some HTML text that eventually contains URLs to the activity instance script.
     * @return string The content with the URLs encoded.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/profileupdate\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@PROFILEUPDATEVIEWBYID*$2@$', $content);

        return $content;
    }
}
