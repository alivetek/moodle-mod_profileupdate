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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the participant profile update form.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_profileupdate_edit_form::class)]
final class edit_form_test extends \advanced_testcase {
    /**
     * Load the form definition before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/mod/profileupdate/edit_form.php');
        $this->resetAfterTest();
    }

    /**
     * Build a form instance for the given field selection.
     *
     * @param array $fields Selected fields as returned by profileupdate_get_selected_fields().
     * @param int $cmid Course module id to carry in the form's hidden field.
     * @return \mod_profileupdate_edit_form
     */
    protected function make_form(array $fields, int $cmid = 0): \mod_profileupdate_edit_form {
        return new \mod_profileupdate_edit_form(null, [
            'fields' => $fields,
            'cmid' => $cmid,
        ]);
    }

    /**
     * Standard fields are written back to the user record and description is HTML.
     */
    public function test_save_data_updates_standard_fields(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user([
            'city' => 'Oldtown',
            'country' => 'GB',
        ]);
        $this->setUser($user);

        $fields = [
            ['type' => 'standard', 'name' => 'city', 'label' => 'City/town'],
            ['type' => 'standard', 'name' => 'country', 'label' => 'Country'],
            ['type' => 'standard', 'name' => 'description', 'label' => 'Description'],
        ];
        $form = $this->make_form($fields);

        $data = (object) [
            'city' => 'Newville',
            'country' => 'FR',
            'description' => 'Hello world',
        ];
        $form->save_data($data);

        $record = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertSame('Newville', $record->city);
        $this->assertSame('FR', $record->country);
        $this->assertSame('Hello world', $record->description);
        $this->assertEquals(FORMAT_HTML, $record->descriptionformat);
    }

    /**
     * The in-memory session user reflects the saved standard values.
     */
    public function test_save_data_refreshes_session_user(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user(['city' => 'Oldtown']);
        $this->setUser($user);

        $fields = [
            ['type' => 'standard', 'name' => 'city', 'label' => 'City/town'],
        ];
        $form = $this->make_form($fields);
        $form->save_data((object) ['city' => 'Freshcity']);

        $this->assertSame('Freshcity', $USER->city);
    }

    /**
     * Only the current user's profile is edited, never another user's.
     */
    public function test_save_data_only_affects_current_user(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_user(['city' => 'Othertown']);
        $current = $this->getDataGenerator()->create_user(['city' => 'Oldtown']);
        $this->setUser($current);

        $fields = [
            ['type' => 'standard', 'name' => 'city', 'label' => 'City/town'],
        ];
        $form = $this->make_form($fields);
        $form->save_data((object) ['city' => 'Newville']);

        $this->assertSame('Newville', $DB->get_field('user', 'city', ['id' => $current->id]));
        $this->assertSame('Othertown', $DB->get_field('user', 'city', ['id' => $other->id]));
    }

    /**
     * Custom profile fields are persisted through the profile field API.
     */
    public function test_save_data_updates_custom_fields(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'favcolour',
            'name' => 'Favourite colour',
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $fields = [
            ['type' => 'custom', 'name' => 'favcolour', 'label' => 'Favourite colour'],
        ];
        $form = $this->make_form($fields);

        $data = (object) ['profile_field_favcolour' => 'Blue'];
        $form->save_data($data);

        $profile = profile_user_record($user->id);
        $this->assertSame('Blue', $profile->favcolour);
    }

    /**
     * Loading the current user's data must not clobber the course module id.
     *
     * set_user_data() populates the form defaults from a clone of $USER, and
     * moodleform::set_data() overwrites any element whose name matches a
     * property on that object. $USER has its own "id" property (the user's
     * database id), so the hidden field carrying the course module id must not
     * be named "id" or it silently gets replaced with the logged-in user's id.
     */
    public function test_set_user_data_does_not_clobber_course_module_id(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $this->getDataGenerator()->create_user(['city' => 'Oldtown']);
        $this->setUser($user);

        // Distinct from the user id so a collision would be caught either way.
        $cmid = $user->id + 1000;

        $fields = [
            ['type' => 'standard', 'name' => 'city', 'label' => 'City/town'],
        ];
        $form = $this->make_form($fields, $cmid);
        $form->set_user_data($user);

        $html = $form->render();

        // Assert hidden cmid exists.
        $this->assertStringContainsString('name="cmid"', $html);

        // Assert expected cmid value is preserved.
        $this->assertStringContainsString('value="' . $cm->id . '"', $html);
    }
}
