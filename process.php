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
 * Processes answer submissions for PlayerRaid.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$answer = optional_param('answer', 0, PARAM_INT);
$answertext = optional_param('answer_text', '', PARAM_TEXT);

$cm = get_coursemodule_from_id('playerraid', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playerraid = $DB->get_record('playerraid', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$modulecontext = context_module::instance($cm->id);
require_capability('mod/playerraid:attempt', $modulecontext);

$returnurl = new moodle_url('/mod/playerraid/view.php', ['id' => $cm->id]);

// Check if answer is correct.
$iscorrect = false;
if ($answer > 0) {
    $answerrecord = $DB->get_record('question_answers', ['id' => $answer]);
    if ($answerrecord && $answerrecord->fraction > 0) {
        $iscorrect = true;
    }
}

// Save the attempt and calculate cooldown.
$attempt = new stdClass();
$attempt->playerraidid = $playerraid->id;
$attempt->userid = $USER->id;
$attempt->timecreated = time();
$attempt->iscorrect = $iscorrect ? 1 : 0;
$attempt->cooldown_expires = $iscorrect ? 0 : (time() + $playerraid->cooldown_time);

$DB->insert_record('playerraid_attempts', $attempt);

if ($iscorrect) {
    // We no longer reduce the boss_health in the DB here. It is calculated dynamically on view.php.
    redirect($returnurl, get_string('correctanswer', 'playerraid'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($returnurl, get_string('wronganswer', 'playerraid'), null, \core\output\notification::NOTIFY_ERROR);
}
