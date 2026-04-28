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
 * Handles question attempts for PlayerRaid.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/questionlib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'showquestion', PARAM_ALPHA);

$cm = get_coursemodule_from_id('playerraid', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playerraid = $DB->get_record('playerraid', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$modulecontext = context_module::instance($cm->id);
require_capability('mod/playerraid:attempt', $modulecontext);

$PAGE->set_url('/mod/playerraid/attempt.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($playerraid->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Get a random question from the category using Moodle 4.0+ schema.
$categoryid = $playerraid->questioncategoryid;
$sql = "SELECT q.id
          FROM {question} q
          JOIN {question_versions} qv ON qv.questionid = q.id
          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
         WHERE qbe.questioncategoryid = :categoryid
           AND qv.status = 'ready'
           AND q.qtype IN ('multichoice', 'truefalse')";

$questions = $DB->get_records_sql($sql, ['categoryid' => $categoryid]);

if (empty($questions)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noquestions', 'playerraid'), 'notifyproblem');
    echo $OUTPUT->continue_button(new moodle_url('/mod/playerraid/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

// Pick a random question.
$questionids = array_keys($questions);
$randomquestionid = $questionids[array_rand($questionids)];
$question = question_bank::load_question($randomquestionid);

echo $OUTPUT->header();

echo html_writer::start_div('playerraid-question-container');
echo $OUTPUT->heading(get_string('question', 'playerraid'), 3);

// Display question text.
echo html_writer::div(format_text($question->questiontext, $question->questiontextformat), 'playerraid-question-text');

// Simple form for answer submission.
echo html_writer::start_tag(
    'form',
    [
        'method' => 'post',
        'action' => new moodle_url('/mod/playerraid/process.php'),
        'class' => 'playerraid-answer-form',
    ]
);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]);

// Display answer options based on question type.
$qtypename = $question->qtype->name();
if ($qtypename === 'multichoice' || $qtypename === 'truefalse') {
    $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id');

    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('youranswer', 'playerraid'));

    foreach ($answers as $answer) {
        $radiohtml = html_writer::empty_tag(
            'input',
            [
                'type' => 'radio',
                'name' => 'answer',
                'value' => $answer->id,
                'id' => 'answer_' . $answer->id,
                'class' => 'form-check-input',
            ]
        );
        $labelhtml = html_writer::tag(
            'label',
            format_text($answer->answer, FORMAT_HTML),
            ['for' => 'answer_' . $answer->id, 'class' => 'form-check-label']
        );

        echo html_writer::div($radiohtml . ' ' . $labelhtml, 'form-check');
    }
    echo html_writer::end_div();
} else {
    // For other question types, use a text input.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('youranswer', 'playerraid'), ['for' => 'answer_text']);
    echo html_writer::empty_tag(
        'input',
        [
            'type' => 'text',
            'name' => 'answer_text',
            'id' => 'answer_text',
            'class' => 'form-control',
        ]
    );
    echo html_writer::end_div();
}

echo html_writer::tag(
    'button',
    get_string('submit', 'playerraid'),
    [
        'type' => 'submit',
        'class' => 'btn btn-primary mt-3',
    ]
);
echo html_writer::end_tag('form');

echo html_writer::end_div();

echo $OUTPUT->footer();
