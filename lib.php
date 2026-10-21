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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module playerraid.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns whether the module supports a feature or not.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function playerraid_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the playerraid into the database.
 *
 * @param stdClass $playerraid An object from the form in mod_form.php
 * @param mod_playerraid_mod_form $mform The form instance itself (not used here)
 * @return int The id of the newly inserted playerraid record
 */
function playerraid_add_instance(stdClass $playerraid, mod_playerraid_mod_form $mform = null) {
    global $DB;

    $playerraid->timecreated = time();
    $playerraid->timemodified = $playerraid->timecreated;

    $playerraid->id = $DB->insert_record('playerraid', $playerraid);

    return $playerraid->id;
}

/**
 * Updates an instance of the playerraid in the database.
 *
 * @param stdClass $playerraid An object from the form in mod_form.php
 * @param mod_playerraid_mod_form $mform The form instance itself (not used here)
 * @return bool True if successful, false otherwise
 */
function playerraid_update_instance(stdClass $playerraid, mod_playerraid_mod_form $mform = null) {
    global $DB;

    $playerraid->timemodified = time();
    $playerraid->id = $playerraid->instance;

    return $DB->update_record('playerraid', $playerraid);
}

/**
 * Removes an instance of the playerraid from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false on failure
 */
function playerraid_delete_instance($id) {
    global $DB;

    if (!$playerraid = $DB->get_record('playerraid', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('playerraid_attempts', ['playerraidid' => $id]);
    $DB->delete_records('playerraid', ['id' => $id]);

    return true;
}

/**
 * Adds the playerraid section to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 * @return void
 */
function playerraid_reset_course_form_definition(MoodleQuickForm $mform): void {
    $mform->addElement('header', 'playerraidheader', get_string('modulenameplural', 'mod_playerraid'));
    $mform->addElement('advcheckbox', 'reset_playerraid_userdata', get_string('resetplayerraiduserdata', 'mod_playerraid'));
}

/**
 * Returns the default values for the playerraid course reset form.
 *
 * @param stdClass $course The course being reset.
 * @return array
 */
function playerraid_reset_course_form_defaults(stdClass $course): array {
    return ['reset_playerraid_userdata' => 1];
}

/**
 * Removes the students' attempts from the course's activities when it is reset.
 *
 * Core clears the completion and the grades by itself but only asks the activities that define this
 * function to clear their own data. Left behind, the rows would satisfy the completion rule again and
 * bring the old grade back for a student enrolled again. What the teacher wrote stays.
 *
 * @param stdClass $data Reset form data, must contain courseid.
 * @return array Status rows for the course reset report.
 */
function playerraid_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];
    if (empty($data->reset_playerraid_userdata)) {
        return $status;
    }

    $instances = $DB->get_records('playerraid', ['course' => $data->courseid]);
    if (empty($instances)) {
        return $status;
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED, 'prid');
    $DB->delete_records_select('playerraid_attempts', "playerraidid $insql", $inparams);

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_playerraid'),
        'item'      => get_string('resetplayerraiduserdata', 'mod_playerraid'),
        'error'     => false,
    ];

    return $status;
}
