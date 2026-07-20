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
 * Prints an instance of mod_profileupdate.
 *
 * @package     mod_profileupdate
 * @copyright   2026 Profile Update
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Locate Moodle's config.php. The standard relative path works for regular
// installations. When the plugin directory is a symlink (as in the Docker-based
// local development setup), __DIR__ resolves outside the Moodle webroot, so we
// fall back to the request's document root to find config.php.
$configpath = __DIR__ . '/../../config.php';
if (!file_exists($configpath) && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $configpath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php';
}
require($configpath);
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
// Activity instance id.
$p = optional_param('p', 0, PARAM_INT);
// Set after a successful save so a "Continue" shortcut back to the course is shown.
$saved = optional_param('saved', 0, PARAM_BOOL);

if ($id) {
    $cm = get_coursemodule_from_id('profileupdate', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('profileupdate', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($p) {
    $moduleinstance = $DB->get_record('profileupdate', ['id' => $p], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('profileupdate', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'mod_profileupdate');
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$pageurl = new moodle_url('/mod/profileupdate/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$selectedfields = profileupdate_get_selected_fields($moduleinstance->id);

// Build the profile update form for the selected fields (if any).
$mform = null;
if ($selectedfields) {
    require_once(__DIR__ . '/edit_form.php');
    $mform = new mod_profileupdate_edit_form($pageurl->out(false), [
        'fields' => $selectedfields,
        'cmid' => $cm->id,
    ]);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    } else if ($data = $mform->get_data()) {
        $mform->save_data($data);
        redirect(
            new moodle_url($pageurl, ['saved' => 1]),
            get_string('profileupdated', 'mod_profileupdate'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        $mform->set_user_data($USER);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($moduleinstance->name));

if (!empty($moduleinstance->intro)) {
    echo $OUTPUT->box(format_module_intro('profileupdate', $moduleinstance, $cm->id), 'generalbox', 'intro');
}

if ($mform) {
    echo html_writer::tag('p', get_string('updateprofileintro', 'mod_profileupdate'));
    $mform->display();

    // After a successful save, offer a clear primary shortcut back to the course.
    // The "Cancel" button already returns here, but a prominent primary button
    // makes it obvious how to continue when the course index is hidden or closed.
    if ($saved) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        echo html_writer::div(
            html_writer::link(
                $courseurl,
                get_string('continuetocourse', 'mod_profileupdate'),
                ['class' => 'btn btn-primary', 'role' => 'button']
            ),
            'profileupdate-continue'
        );
    }
} else {
    echo $OUTPUT->notification(get_string('nofieldsselected', 'mod_profileupdate'), 'info');
}

// Offer a shortcut to the activity settings for users who can manage it.
if (has_capability('moodle/course:manageactivities', $modulecontext)) {
    $editurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);
    echo html_writer::div(
        html_writer::link($editurl, get_string('configurefieldslink', 'mod_profileupdate')),
        'profileupdate-editlink'
    );
}

echo $OUTPUT->footer();
