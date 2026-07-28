<?php
// This file is part of Moodle - http://moodle.org/
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
 * List all profileupdate instances in a course.
 *
 * @package   mod_profileupdate
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/profileupdate/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'profileupdate'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'profileupdate'));

// Fetch all instances for this course.
if (!$profileupdates = get_all_instances_in_course('profileupdate', $course)) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'profileupdate')), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Build a standard activity index table.
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format === 'weeks') {
    $table->head  = [get_string('week'), get_string('name')];
    $table->align = ['center', 'left'];
} else if ($course->format === 'topics') {
    $table->head  = [get_string('topic'), get_string('name')];
    $table->align = ['center', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left'];
}

foreach ($profileupdates as $profileupdate) {
    $link = html_writer::link(
        new moodle_url('/mod/profileupdate/view.php', ['id' => $profileupdate->coursemodule]),
        format_string($profileupdate->name, true, ['context' => context_module::instance($profileupdate->coursemodule)])
    );

    if ($course->format === 'weeks' || $course->format === 'topics') {
        $table->data[] = [$profileupdate->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
