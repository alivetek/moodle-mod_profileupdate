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

namespace mod_profileupdate;

use backup;
use backup_controller;
use PHPUnit\Framework\Attributes\CoversClass;
use restore_controller;
use restore_dbops;
use stdClass;

/**
 * Backup and restore tests for mod_profileupdate.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_profileupdate_activity_structure_step::class)]
#[CoversClass(\restore_profileupdate_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Load the backup/restore libraries before each test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * A course backup carries the profileupdate instance and its selected fields
     * over to the restored copy, under the new course and instance ids.
     */
    public function test_backup_restore_carries_instance_and_fields(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/mod/profileupdate/lib.php');

        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'favcolour',
            'name' => 'Favourite colour',
        ]);

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('profileupdate', [
            'course' => $course,
            'name' => 'Update your profile',
        ]);
        profileupdate_save_selected_fields($activity->id, ['standard|country', 'custom|favcolour']);

        $newcourseid = $this->backup_and_restore($course);

        $restored = $DB->get_record('profileupdate', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('Update your profile', $restored->name);
        $this->assertNotEquals($activity->id, $restored->id);

        $keys = profileupdate_get_selected_field_keys($restored->id);
        $this->assertSame(['standard|country', 'custom|favcolour'], $keys);

        // The source instance and its fields are untouched.
        $this->assertSame(['standard|country', 'custom|favcolour'], profileupdate_get_selected_field_keys($activity->id));
    }

    /**
     * Back a course up and restore it into a new course.
     *
     * @param stdClass $srccourse The course to back up.
     * @return int The id of the newly restored course.
     */
    private function backup_and_restore(stdClass $srccourse): int {
        global $USER, $CFG;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // MODE_IMPORT just creates the backup directory rather than a zip.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_2',
            $srccourse->category
        );

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
