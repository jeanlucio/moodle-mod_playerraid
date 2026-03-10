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
 * Prints a particular instance of playerraid.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$p = optional_param('p', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('playerraid', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $playerraid = $DB->get_record('playerraid', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($p) {
    $playerraid = $DB->get_record('playerraid', ['id' => $p], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $playerraid->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('playerraid', $playerraid->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcm', 'playerraid');
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
require_capability('mod/playerraid:view', $modulecontext);

$event = \mod_playerraid\event\course_module_viewed::create(
    [
        'objectid' => $playerraid->id,
        'context' => $modulecontext,
    ]
);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('playerraid', $playerraid);
$event->trigger();

$PAGE->set_url('/mod/playerraid/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($playerraid->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

echo $OUTPUT->header();

// Display the playerraid name and intro.
echo $OUTPUT->heading(format_string($playerraid->name));

if ($playerraid->intro) {
    echo $OUTPUT->box(format_module_intro('playerraid', $playerraid, $cm->id), 'generalbox mod_introbox', 'playerraidintro');
}

// Check if question category is set.
if (empty($playerraid->questioncategoryid)) {
    echo $OUTPUT->notification(get_string('noquestioncategory', 'playerraid'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// Display boss health bar.
// The total health remains intact. The current health is calculated by the number of correct attempts.
$totalhealth = $playerraid->boss_health;
$damagepercorrect = 10;
$correctcount = $DB->count_records('playerraid_attempts', ['playerraidid' => $playerraid->id, 'iscorrect' => 1]);
$totaldamage = $correctcount * $damagepercorrect;

$currenthealth = max(0, $totalhealth - $totaldamage);
$healthpercentage = ($currenthealth / $totalhealth) * 100;

echo html_writer::start_div('playerraid-boss-container mb-4');
echo html_writer::tag('h3', get_string('bossremaining', 'playerraid', ['current' => $currenthealth, 'total' => $totalhealth]));
echo html_writer::start_div('progress');
echo html_writer::div(
    html_writer::span(
        get_string('bossremaining', 'playerraid', ['current' => $currenthealth, 'total' => $totalhealth]),
        'visually-hidden'
    ),
    'progress-bar bg-danger',
    [
        'role' => 'progressbar',
        'aria-valuenow' => $currenthealth,
        'aria-valuemin' => '0',
        'aria-valuemax' => $totalhealth,
        'style' => 'width: ' . $healthpercentage . '%',
    ]
);
echo html_writer::end_div();
echo html_writer::end_div();

// Check if boss is defeated.
if ($currenthealth <= 0) {
    echo $OUTPUT->notification(get_string('defeated', 'playerraid'), 'notifysuccess');
    echo $OUTPUT->footer();
    exit;
}

// Check cooldown for the current user.
$sql = "SELECT MAX(cooldown_expires) AS maxcooldown
          FROM {playerraid_attempts}
         WHERE playerraidid = :pid AND userid = :uid";
$cooldownrecord = $DB->get_record_sql($sql, ['pid' => $playerraid->id, 'uid' => $USER->id]);
$cooldownexpires = $cooldownrecord ? (int)$cooldownrecord->maxcooldown : 0;
$currenttime = time();

echo html_writer::start_div('playerraid-attack-section');

if ($cooldownexpires > $currenttime) {
    // User is on cooldown. Display message and disabled button.
    $waittime = $cooldownexpires - $currenttime;
    echo $OUTPUT->notification(get_string('attackcooldown', 'playerraid', $waittime), 'notifywarning');

    echo html_writer::tag(
        'button',
        get_string('attack', 'playerraid'),
        [
            'type' => 'button',
            'class' => 'btn btn-secondary btn-lg',
            'disabled' => 'disabled',
            'aria-disabled' => 'true',
        ]
    );
} else {
    // User can attack. Display active form.
    echo html_writer::start_tag(
        'form',
        [
            'method' => 'post',
            'action' => new moodle_url('/mod/playerraid/attempt.php'),
            'class' => 'playerraid-attack-form',
        ]
    );
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::tag(
        'button',
        get_string('attack', 'playerraid'),
        [
            'type' => 'submit',
            'class' => 'btn btn-primary btn-lg',
        ]
    );
    echo html_writer::end_tag('form');
}

echo html_writer::end_div();

echo $OUTPUT->footer();
