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

use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * Unit tests for the mod_profileupdate library functions.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('profileupdate_supports')]
#[CoversFunction('profileupdate_add_instance')]
#[CoversFunction('profileupdate_update_instance')]
#[CoversFunction('profileupdate_delete_instance')]
#[CoversFunction('profileupdate_view')]
#[CoversFunction('profileupdate_get_standard_fields')]
#[CoversFunction('profileupdate_get_custom_fields')]
#[CoversFunction('profileupdate_get_available_fields')]
#[CoversFunction('profileupdate_get_selected_field_keys')]
#[CoversFunction('profileupdate_get_selected_fields')]
#[CoversFunction('profileupdate_save_selected_fields')]
final class lib_test extends \advanced_testcase {

    /**
     * A course used by the tests.
     *
     * @var \stdClass
     */
    protected $course;

    /**
     * Set up shared fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/profileupdate/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Build the module form data used to create an instance.
     *
     * @param array $fields Field identifiers to select.
     * @param string $name The instance name.
     * @return \stdClass
     */
    protected function build_instance_data(array $fields = [], string $name = 'Update your profile'): \stdClass {
        $data = new \stdClass();
        $data->course = $this->course->id;
        $data->name = $name;
        $data->intro = '<p>Intro</p>';
        $data->introformat = FORMAT_HTML;
        $data->profileupdatefields = $fields;
        return $data;
    }

    /**
     * Feature support reflects what the plugin declares.
     */
    public function test_supports(): void {
        $this->assertTrue(profileupdate_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(profileupdate_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(profileupdate_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(profileupdate_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertNull(profileupdate_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertNull(profileupdate_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(profileupdate_supports('some_unknown_feature'));
    }

    /**
     * Create a course with completion enabled and a profileupdate instance in it.
     *
     * @param int $completion One of the COMPLETION_TRACKING_* constants.
     * @param int $completionview COMPLETION_VIEW_REQUIRED or COMPLETION_VIEW_NOT_REQUIRED.
     * @return array [$course, $moduleinstance, $cm, $context]
     */
    protected function create_instance_with_completion(
        int $completion = COMPLETION_TRACKING_AUTOMATIC,
        int $completionview = COMPLETION_VIEW_REQUIRED
    ): array {
        global $CFG;

        $CFG->enablecompletion = 1;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $moduleinstance = $this->getDataGenerator()->create_module('profileupdate', [
            'course' => $course->id,
            'completion' => $completion,
            'completionview' => $completionview,
        ]);
        $cm = get_coursemodule_from_instance('profileupdate', $moduleinstance->id, $course->id, false, MUST_EXIST);

        return [$course, $moduleinstance, $cm, \context_module::instance($cm->id)];
    }

    /**
     * Viewing the activity logs the view and completes it when a view is required.
     */
    public function test_view_completes_when_view_is_required(): void {
        [$course, $moduleinstance, $cm, $context] = $this->create_instance_with_completion();

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $completion = new \completion_info($course);
        $this->assertEquals(COMPLETION_NOT_VIEWED, $completion->get_data($cm, false, $student->id)->viewed);

        $sink = $this->redirectEvents();
        profileupdate_view($moduleinstance, $course, $cm, $context);
        $events = $sink->get_events();
        $sink->close();

        $viewed = array_values(array_filter($events, function ($event) {
            return $event instanceof \mod_profileupdate\event\course_module_viewed;
        }));
        $this->assertCount(1, $viewed);
        $this->assertEquals($context, $viewed[0]->get_context());
        $this->assertEquals($moduleinstance->id, $viewed[0]->objectid);
        $this->assertEquals('profileupdate', $viewed[0]->objecttable);

        $data = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_VIEWED, $data->viewed);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
    }

    /**
     * An instance with a view requirement is selectable as a course completion
     * condition and reports "View the activity" as its requirement.
     */
    public function test_view_requirement_is_reported_for_completion(): void {
        [$course, , $cm] = $this->create_instance_with_completion();

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $completion = new \completion_info($course);

        // "Condition: Activity completion" in the course completion settings offers
        // every activity with completion tracking enabled.
        $this->assertArrayHasKey($cm->id, $completion->get_activities());

        $cminfo = get_fast_modinfo($course, $student->id)->get_cm($cm->id);
        $details = (new \core_completion\cm_completion_details($completion, $cminfo, $student->id))->get_details();

        $this->assertArrayHasKey('completionview', $details);
        $this->assertEquals(COMPLETION_INCOMPLETE, $details['completionview']->status);
    }

    /**
     * With manual completion, viewing is logged but does not complete the activity.
     */
    public function test_view_does_not_complete_manual_tracking(): void {
        [$course, $moduleinstance, $cm, $context] = $this->create_instance_with_completion(
            COMPLETION_TRACKING_MANUAL,
            COMPLETION_VIEW_NOT_REQUIRED
        );

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $sink = $this->redirectEvents();
        profileupdate_view($moduleinstance, $course, $cm, $context);
        $events = $sink->get_events();
        $sink->close();

        $viewed = array_filter($events, function ($event) {
            return $event instanceof \mod_profileupdate\event\course_module_viewed;
        });
        $this->assertCount(1, $viewed);

        $data = (new \completion_info($course))->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_NOT_VIEWED, $data->viewed);
        $this->assertEquals(COMPLETION_INCOMPLETE, $data->completionstate);
    }

    /**
     * Adding an instance stores the record, timestamps and the selected fields.
     */
    public function test_add_instance(): void {
        global $DB;

        $data = $this->build_instance_data(['standard|country', 'standard|city']);
        $id = profileupdate_add_instance($data);

        $this->assertIsInt($id);
        $record = $DB->get_record('profileupdate', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Update your profile', $record->name);
        $this->assertSame((int) $this->course->id, (int) $record->course);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertGreaterThan(0, $record->timemodified);

        $keys = profileupdate_get_selected_field_keys($id);
        $this->assertSame(['standard|country', 'standard|city'], $keys);
    }

    /**
     * An instance may be created with no fields selected and defaults applied.
     */
    public function test_add_instance_with_no_fields_and_defaults(): void {
        global $DB;

        $data = $this->build_instance_data([]);
        // Simulate a form that supplied no intro at all.
        unset($data->intro, $data->introformat);
        $id = profileupdate_add_instance($data);

        $record = $DB->get_record('profileupdate', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('', $record->intro);
        $this->assertEquals(FORMAT_HTML, $record->introformat);
        $this->assertSame([], profileupdate_get_selected_field_keys($id));
    }

    /**
     * Updating an instance replaces the selection without leaving stale rows.
     */
    public function test_update_instance_replaces_selection(): void {
        global $DB;

        $data = $this->build_instance_data(['standard|country', 'standard|city']);
        $id = profileupdate_add_instance($data);

        $update = $this->build_instance_data(['standard|email'], 'Renamed');
        $update->instance = $id;
        $result = profileupdate_update_instance($update);

        $this->assertTrue((bool) $result);
        $record = $DB->get_record('profileupdate', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Renamed', $record->name);

        $keys = profileupdate_get_selected_field_keys($id);
        $this->assertSame(['standard|email'], $keys);
        $this->assertEquals(1, $DB->count_records('profileupdate_fields', ['profileupdateid' => $id]));
    }

    /**
     * Deleting an instance removes the record and its selected field rows.
     */
    public function test_delete_instance(): void {
        global $DB;

        $data = $this->build_instance_data(['standard|country']);
        $id = profileupdate_add_instance($data);

        $this->assertTrue(profileupdate_delete_instance($id));
        $this->assertFalse($DB->record_exists('profileupdate', ['id' => $id]));
        $this->assertEquals(0, $DB->count_records('profileupdate_fields', ['profileupdateid' => $id]));

        // Deleting a non-existent instance returns false.
        $this->assertFalse(profileupdate_delete_instance($id));
    }

    /**
     * The standard field list exposes the 14 documented fields with labels.
     */
    public function test_get_standard_fields(): void {
        $fields = profileupdate_get_standard_fields();

        $expected = [
            'firstname', 'lastname', 'email', 'city', 'country', 'timezone',
            'description', 'phone1', 'phone2', 'institution', 'department',
            'address', 'idnumber', 'url',
        ];
        $this->assertCount(14, $fields);
        $this->assertSame($expected, array_keys($fields));
        $this->assertSame(get_string('field_country', 'mod_profileupdate'), $fields['country']);
    }

    /**
     * Custom profile fields are discovered by shortname.
     */
    public function test_get_custom_fields(): void {
        $this->assertSame([], profileupdate_get_custom_fields());

        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'favcolour',
            'name' => 'Favourite colour',
        ]);

        $custom = profileupdate_get_custom_fields();
        $this->assertArrayHasKey('favcolour', $custom);
        $this->assertSame('Favourite colour', $custom['favcolour']);
    }

    /**
     * Available fields are grouped by category with type|name keys.
     */
    public function test_get_available_fields(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'favcolour',
            'name' => 'Favourite colour',
        ]);

        $available = profileupdate_get_available_fields();

        $standardcat = get_string('fieldscategory_standard', 'mod_profileupdate');
        $customcat = get_string('fieldscategory_custom', 'mod_profileupdate');
        $this->assertArrayHasKey($standardcat, $available);
        $this->assertArrayHasKey($customcat, $available);

        $this->assertArrayHasKey('standard|country', $available[$standardcat]);
        $this->assertArrayHasKey('custom|favcolour', $available[$customcat]);
    }

    /**
     * With no custom fields, the custom category is omitted entirely.
     */
    public function test_get_available_fields_without_custom(): void {
        $available = profileupdate_get_available_fields();
        $customcat = get_string('fieldscategory_custom', 'mod_profileupdate');
        $this->assertArrayNotHasKey($customcat, $available);
    }

    /**
     * Selected field keys preserve their stored display order.
     */
    public function test_get_selected_field_keys_order(): void {
        $data = $this->build_instance_data(['standard|country', 'standard|city', 'standard|email']);
        $id = profileupdate_add_instance($data);

        $keys = profileupdate_get_selected_field_keys($id);
        $this->assertSame(['standard|country', 'standard|city', 'standard|email'], $keys);
    }

    /**
     * Selected fields are returned with resolved labels for both types.
     */
    public function test_get_selected_fields_labels(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'favcolour',
            'name' => 'Favourite colour',
        ]);

        $data = $this->build_instance_data(['standard|country', 'custom|favcolour']);
        $id = profileupdate_add_instance($data);

        $fields = profileupdate_get_selected_fields($id);
        $this->assertCount(2, $fields);

        $this->assertSame('standard', $fields[0]['type']);
        $this->assertSame('country', $fields[0]['name']);
        $this->assertSame(get_string('field_country', 'mod_profileupdate'), $fields[0]['label']);

        $this->assertSame('custom', $fields[1]['type']);
        $this->assertSame('favcolour', $fields[1]['name']);
        $this->assertSame('Favourite colour', $fields[1]['label']);
    }

    /**
     * Unknown field names fall back to the raw name as their label.
     */
    public function test_get_selected_fields_unknown_label_fallback(): void {
        $data = $this->build_instance_data([]);
        $id = profileupdate_add_instance($data);

        // Persist a selection referencing fields that no longer resolve.
        profileupdate_save_selected_fields($id, ['standard|doesnotexist', 'custom|missing']);

        $fields = profileupdate_get_selected_fields($id);
        $this->assertSame('doesnotexist', $fields[0]['label']);
        $this->assertSame('missing', $fields[1]['label']);
    }

    /**
     * Saving selections stores rows in order and skips malformed identifiers.
     */
    public function test_save_selected_fields_filters_and_orders(): void {
        global $DB;

        $data = $this->build_instance_data([]);
        $id = profileupdate_add_instance($data);

        profileupdate_save_selected_fields($id, [
            'standard|country',
            'invalidnopipe',
            'bogus|type',
            'standard|',
            'custom|favcolour',
        ]);

        $records = $DB->get_records('profileupdate_fields', ['profileupdateid' => $id], 'sortorder ASC');
        $records = array_values($records);

        $this->assertCount(2, $records);
        $this->assertSame('standard', $records[0]->fieldtype);
        $this->assertSame('country', $records[0]->fieldname);
        $this->assertEquals(0, $records[0]->sortorder);
        $this->assertSame('custom', $records[1]->fieldtype);
        $this->assertSame('favcolour', $records[1]->fieldname);
        $this->assertEquals(1, $records[1]->sortorder);
    }

    /**
     * Re-saving replaces the previous selection completely.
     */
    public function test_save_selected_fields_replaces_previous(): void {
        global $DB;

        $data = $this->build_instance_data(['standard|country', 'standard|city']);
        $id = profileupdate_add_instance($data);

        profileupdate_save_selected_fields($id, ['standard|email']);

        $this->assertEquals(1, $DB->count_records('profileupdate_fields', ['profileupdateid' => $id]));
        $this->assertSame(['standard|email'], profileupdate_get_selected_field_keys($id));
    }
}
