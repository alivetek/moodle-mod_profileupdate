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
 * Library of interface functions and constants for module profileupdate.
 *
 * @package     mod_profileupdate
 * @copyright   2026 AliveTek Inc.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return whether the plugin supports the given feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true|null True if the feature is supported, null otherwise.
 */
function profileupdate_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/**
 * Add a new profileupdate instance.
 *
 * @param stdClass $data The data submitted from the module form.
 * @param mod_profileupdate_mod_form|null $mform The module form instance.
 * @return int The id of the newly inserted record.
 */
function profileupdate_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    if (empty($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    $id = $DB->insert_record('profileupdate', $data);

    $fields = isset($data->profileupdatefields) ? (array) $data->profileupdatefields : [];
    profileupdate_save_selected_fields($id, $fields);

    return $id;
}

/**
 * Update an existing profileupdate instance.
 *
 * @param stdClass $data The data submitted from the module form.
 * @param mod_profileupdate_mod_form|null $mform The module form instance.
 * @return bool True on success.
 */
function profileupdate_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $result = $DB->update_record('profileupdate', $data);

    $fields = isset($data->profileupdatefields) ? (array) $data->profileupdatefields : [];
    profileupdate_save_selected_fields($data->id, $fields);

    return $result;
}

/**
 * Delete a profileupdate instance.
 *
 * @param int $id The id of the instance to delete.
 * @return bool True on success.
 */
function profileupdate_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('profileupdate', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('profileupdate_fields', ['profileupdateid' => $id]);
    $DB->delete_records('profileupdate', ['id' => $id]);

    return true;
}

/**
 * Mark the activity as viewed by the current user.
 *
 * Triggers the course module viewed event and, when the instance is configured
 * with "View the activity" as a completion requirement, records the view so the
 * activity is marked complete.
 *
 * @param stdClass $moduleinstance The profileupdate instance record.
 * @param stdClass $course The course record.
 * @param stdClass|cm_info $cm The course module record.
 * @param context_module $context The module context.
 * @return void
 */
function profileupdate_view($moduleinstance, $course, $cm, $context) {
    global $CFG;

    // completionlib.php is not loaded on every request, so make sure it is here.
    require_once($CFG->libdir . '/completionlib.php');

    $event = \mod_profileupdate\event\course_module_viewed::create([
        'context' => $context,
        'objectid' => $moduleinstance->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('profileupdate', $moduleinstance);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Return the list of standard user profile fields that can be selected for updating.
 *
 * @return array Map of field name => human readable label.
 */
function profileupdate_get_standard_fields() {
    $fields = [
        'firstname',
        'lastname',
        'email',
        'city',
        'country',
        'timezone',
        'description',
        'phone1',
        'phone2',
        'institution',
        'department',
        'address',
        'idnumber',
        'url',
    ];

    $result = [];
    foreach ($fields as $field) {
        $result[$field] = get_string('field_' . $field, 'mod_profileupdate');
    }

    return $result;
}

/**
 * Return the list of custom user profile fields that can be selected for updating.
 *
 * @return array Map of field shortname => human readable label.
 */
function profileupdate_get_custom_fields() {
    global $DB;

    $result = [];
    $records = $DB->get_records('user_info_field', null, 'sortorder ASC', 'id, shortname, name');
    foreach ($records as $record) {
        $result[$record->shortname] = format_string($record->name);
    }

    return $result;
}

/**
 * Build the list of all selectable profile fields, keyed by a "type|name" identifier.
 *
 * The identifier encodes both the field type (standard or custom) and its name so
 * that selections can be stored and matched unambiguously.
 *
 * @return array Grouped options: [category label => [key => label]].
 */
function profileupdate_get_available_fields() {
    $options = [];

    $standard = [];
    foreach (profileupdate_get_standard_fields() as $name => $label) {
        $standard['standard|' . $name] = $label;
    }
    if ($standard) {
        $options[get_string('fieldscategory_standard', 'mod_profileupdate')] = $standard;
    }

    $custom = [];
    foreach (profileupdate_get_custom_fields() as $name => $label) {
        $custom['custom|' . $name] = $label;
    }
    if ($custom) {
        $options[get_string('fieldscategory_custom', 'mod_profileupdate')] = $custom;
    }

    return $options;
}

/**
 * Return the selected field identifiers for a profileupdate instance.
 *
 * @param int $profileupdateid The profileupdate instance id.
 * @return string[] Array of "type|name" identifiers in display order.
 */
function profileupdate_get_selected_field_keys($profileupdateid) {
    global $DB;

    $records = $DB->get_records('profileupdate_fields', ['profileupdateid' => $profileupdateid], 'sortorder ASC');

    $keys = [];
    foreach ($records as $record) {
        $keys[] = $record->fieldtype . '|' . $record->fieldname;
    }

    return $keys;
}

/**
 * Return the selected fields for a profileupdate instance with their display labels.
 *
 * @param int $profileupdateid The profileupdate instance id.
 * @return array Ordered list of ['type' => ..., 'name' => ..., 'label' => ...].
 */
function profileupdate_get_selected_fields($profileupdateid) {
    global $DB;

    $standard = profileupdate_get_standard_fields();
    $custom = profileupdate_get_custom_fields();

    $records = $DB->get_records('profileupdate_fields', ['profileupdateid' => $profileupdateid], 'sortorder ASC');

    $fields = [];
    foreach ($records as $record) {
        if ($record->fieldtype === 'custom') {
            $label = $custom[$record->fieldname] ?? $record->fieldname;
        } else {
            $label = $standard[$record->fieldname] ?? $record->fieldname;
        }
        $fields[] = [
            'type' => $record->fieldtype,
            'name' => $record->fieldname,
            'label' => $label,
        ];
    }

    return $fields;
}

/**
 * Persist the selected profile fields for a profileupdate instance.
 *
 * Existing selections are replaced with the given set of identifiers.
 *
 * @param int $profileupdateid The profileupdate instance id.
 * @param string[] $keys Array of "type|name" identifiers to store.
 * @return void
 */
function profileupdate_save_selected_fields($profileupdateid, array $keys) {
    global $DB;

    $DB->delete_records('profileupdate_fields', ['profileupdateid' => $profileupdateid]);

    $sortorder = 0;
    foreach ($keys as $key) {
        if (strpos($key, '|') === false) {
            continue;
        }
        [$type, $name] = explode('|', $key, 2);
        if ($type !== 'standard' && $type !== 'custom') {
            continue;
        }
        if ($name === '') {
            continue;
        }

        $record = new stdClass();
        $record->profileupdateid = $profileupdateid;
        $record->fieldtype = $type;
        $record->fieldname = $name;
        $record->sortorder = $sortorder++;

        $DB->insert_record('profileupdate_fields', $record);
    }
}
