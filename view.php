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
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/questionlib.php');

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
$canattempt = has_capability('mod/playerraid:attempt', $modulecontext);

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
$PAGE->requires->js_call_amd('mod_playerraid/cooldown_timer', 'init');

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

// Check cooldown for the current user.
$sql = "SELECT MAX(cooldown_expires) AS maxcooldown
          FROM {playerraid_attempts}
         WHERE playerraidid = :pid AND userid = :uid";
$cooldownrecord = $DB->get_record_sql($sql, ['pid' => $playerraid->id, 'uid' => $USER->id]);
$cooldownexpires = $cooldownrecord ? (int)$cooldownrecord->maxcooldown : 0;
$currenttime = time();

// Load a random question if user can attack.
$randomquestionid = 0;
$question = null;
if ($currenthealth > 0 && $cooldownexpires <= $currenttime && $canattempt) {
    $categoryid = $playerraid->questioncategoryid;
    $sql = "SELECT q.id
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid
               AND qv.status = 'ready'
               AND q.qtype IN ('multichoice', 'truefalse')";

    $questions = $DB->get_records_sql($sql, ['categoryid' => $categoryid]);

    if (!empty($questions)) {
        $questionids = array_keys($questions);
        $randomquestionid = $questionids[array_rand($questionids)];
        $question = question_bank::load_question($randomquestionid);
    }
}

$theme = !empty($playerraid->visual_theme) ? $playerraid->visual_theme : 'boss';

echo html_writer::start_div('playerraid-boss-container mb-4');
echo html_writer::start_div('row');

// Left Column: Boss Status.
echo html_writer::start_div('col-md-6 mb-4');
echo html_writer::start_div('playerraid-boss-card playerraid-theme-' . $theme);

$progress = 100 - $healthpercentage;
if ($progress <= 20) {
    $state = 1;
} else if ($progress <= 40) {
    $state = 2;
} else if ($progress <= 60) {
    $state = 3;
} else if ($progress <= 80) {
    $state = 4;
} else {
    $state = 5;
}

$imagepath = $CFG->dirroot . '/mod/playerraid/pix/themes/' . $theme . '/state_' . $state . '.png';
if (file_exists($imagepath)) {
    $imageurl = $OUTPUT->image_url('themes/' . $theme . '/state_' . $state, 'mod_playerraid');
    echo html_writer::start_div('playerraid-theme-image-wrapper');
    echo html_writer::empty_tag('img', [
        'src' => $imageurl,
        'alt' => get_string('visualtheme', 'playerraid'),
        'class' => 'playerraid-boss-img',
    ]);
    echo html_writer::end_div();
}

$iconclass = ($theme === 'vault') ? 'fa-database' : 'fa-crosshairs';
$hptitle = html_writer::tag('i', '', [
    'class' => 'fa ' . $iconclass . ' me-2',
    'aria-hidden' => 'true',
]);
$hptitle .= get_string('bossremaining', 'playerraid', ['current' => $currenthealth, 'total' => $totalhealth]);
echo html_writer::tag('h3', $hptitle, ['class' => 'playerraid-hp-title']);

$islow = ($currenthealth < ($totalhealth * 0.2)) ? ' playerraid-hp-low' : '';
echo html_writer::start_div('playerraid-progress-wrapper');
echo html_writer::start_div('progress playerraid-hp-bar' . $islow);
echo html_writer::div(
    html_writer::span(
        get_string('bossremaining', 'playerraid', ['current' => $currenthealth, 'total' => $totalhealth]),
        'visually-hidden'
    ),
    'progress-bar playerraid-hp-bar-fill',
    [
        'role' => 'progressbar',
        'aria-valuenow' => $currenthealth,
        'aria-valuemin' => '0',
        'aria-valuemax' => $totalhealth,
        'style' => 'width: ' . $healthpercentage . '%',
    ]
);
echo html_writer::end_div(); // Progress.
echo html_writer::end_div(); // Wrapper.

echo html_writer::end_div(); // Boss card.
echo html_writer::end_div(); // Left column.

// Right Column: Battle / Status Panel.
echo html_writer::start_div('col-md-6 mb-4');

if ($currenthealth <= 0) {
    // Defeated/Victory state.
    echo html_writer::start_div('playerraid-status-card playerraid-victory-card text-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-trophy fa-3x mb-3 text-warning', 'aria-hidden' => 'true']);
    echo html_writer::tag('h4', get_string('defeated', 'playerraid'), ['class' => 'text-success']);
    echo html_writer::end_div();
} else if (!$canattempt) {
    // User does not have permission to participate.
    echo html_writer::start_div('playerraid-status-card playerraid-cooldown-card text-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-user-times fa-3x mb-3 text-danger', 'aria-hidden' => 'true']);
    echo html_writer::tag('h4', get_string('cannotattempt', 'playerraid'), ['class' => 'text-muted']);
    echo html_writer::end_div();
} else if ($cooldownexpires > $currenttime) {
    // Cooldown state.
    $waittime = $cooldownexpires - $currenttime;
    echo html_writer::start_div('playerraid-status-card playerraid-cooldown-card text-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-lock fa-3x mb-3 text-danger', 'aria-hidden' => 'true']);
    $secondshtml = html_writer::span($waittime, 'playerraid-cooldown-seconds', [
        'data-expiry' => $cooldownexpires,
    ]);
    echo html_writer::tag('h4', get_string('attackcooldown', 'playerraid', $secondshtml), ['class' => 'text-muted']);
    echo html_writer::end_div();
} else if (empty($question)) {
    // No questions found.
    echo html_writer::start_div('playerraid-status-card playerraid-error-card text-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle fa-3x mb-3 text-warning', 'aria-hidden' => 'true']);
    echo html_writer::tag('h4', get_string('noquestions', 'playerraid'), ['class' => 'text-danger']);
    echo html_writer::end_div();
} else {
    // Active battle state with question.
    echo html_writer::start_div('playerraid-battle-card');
    echo html_writer::tag('h3', get_string('question', 'playerraid'), ['class' => 'mb-3']);

    // Display question text.
    echo html_writer::div(
        format_text($question->questiontext, $question->questiontextformat),
        'playerraid-question-text mb-4'
    );

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

        echo html_writer::start_div('form-group mb-3');
        echo html_writer::tag('label', get_string('youranswer', 'playerraid'), ['class' => 'form-label font-weight-bold']);

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
        // Default fallback to text input.
        echo html_writer::start_div('form-group mb-3');
        echo html_writer::tag('label', get_string('youranswer', 'playerraid'), [
            'for' => 'answer_text',
            'class' => 'form-label font-weight-bold',
        ]);
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
        html_writer::tag('i', '', ['class' => 'fa fa-bolt me-2', 'aria-hidden' => 'true']) . get_string('attack', 'playerraid'),
        [
            'type' => 'submit',
            'class' => 'btn playerraid-btn-attack w-100 mt-3',
        ]
    );
    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // Battle card.
}

echo html_writer::end_div(); // Right column.

echo html_writer::end_div(); // Row.
echo html_writer::end_div(); // Main container.

echo $OUTPUT->footer();
