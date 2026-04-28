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
 * Lists all playerraid instances in a course.
 *
 * @package    mod_playerraid
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(new moodle_url('/mod/playerraid/index.php', ['id' => $id]));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_playerraid'));

$moduleinstances = get_all_instances_in_course('playerraid', $course);

if (empty($moduleinstances)) {
    notice(get_string('nonewmodules', 'mod_playerraid'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format === 'weeks') {
    $table->head = [get_string('week'), get_string('name')];
    $table->align = ['center', 'left'];
} else if ($course->format === 'topics') {
    $table->head = [get_string('topic'), get_string('name')];
    $table->align = ['center', 'left', 'left', 'left'];
} else {
    $table->head = [get_string('name')];
    $table->align = ['left', 'left', 'left'];
}

foreach ($moduleinstances as $moduleinstance) {
    $link = html_writer::link(
        new moodle_url('/mod/playerraid/view.php', ['id' => $moduleinstance->coursemodule]),
        format_string($moduleinstance->name, true, ['context' => context_module::instance($moduleinstance->coursemodule)])
    );

    if ($course->format === 'weeks' || $course->format === 'topics') {
        $table->data[] = [$moduleinstance->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
