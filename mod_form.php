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
 * Defines the editing form for the PlayerRaid activity.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_playerraid_mod_form extends moodleform_mod {
    /**
     * Defines form elements.
     */
    public function definition() {
        global $DB, $COURSE;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('playerraidname', 'playerraid'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'playerraidname', 'playerraid');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements(get_string('intro', 'playerraid'));

        // Boss health.
        $mform->addElement('text', 'boss_health', get_string('bosshealth', 'playerraid'), ['size' => '10']);
        $mform->setType('boss_health', PARAM_INT);
        $mform->addRule('boss_health', null, 'required', null, 'client');
        $mform->addRule('boss_health', get_string('positiveint', 'playerraid'), 'numeric', null, 'client');
        $mform->setDefault('boss_health', 100);
        $mform->addHelpButton('boss_health', 'bosshealth', 'playerraid');

        // Cooldown time.
        $mform->addElement('text', 'cooldown_time', get_string('cooldowntime', 'playerraid'), ['size' => '10']);
        $mform->setType('cooldown_time', PARAM_INT);
        $mform->addRule('cooldown_time', null, 'required', null, 'client');
        $mform->addRule('cooldown_time', get_string('positiveint', 'playerraid'), 'numeric', null, 'client');
        $mform->setDefault('cooldown_time', 60);
        $mform->addHelpButton('cooldown_time', 'cooldowntime', 'playerraid');

        // Question category selector.
        $mform->addElement('header', 'questionsection', get_string('questioncategory', 'playerraid'));

        // Safely retrieve categories using a broad database query.
        // In recent Moodle versions, course question banks might be internally mapped to module contexts.
        // This radar approach scans the system, category, course, AND all modules within the course.
        $categories = [];
        $coursecontext = \context_course::instance($COURSE->id);

        $contextstocheck = [];

        // 1. Parent contexts (System, Course Category, Course).
        foreach ($coursecontext->get_parent_contexts(true) as $ctx) {
            $contextstocheck[$ctx->id] = $ctx;
        }

        // 2. Module contexts within the course.
        $modinfo = get_fast_modinfo($COURSE);
        foreach ($modinfo->cms as $cm) {
            $modcontext = \context_module::instance($cm->id);
            $contextstocheck[$modcontext->id] = $modcontext;
        }

        $validcontextids = [];
        foreach ($contextstocheck as $ctx) {
            try {
                if (has_capability('moodle/question:useall', $ctx) || has_capability('moodle/question:usemine', $ctx)) {
                    $validcontextids[] = $ctx->id;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!empty($validcontextids)) {
            [$insql, $params] = $DB->get_in_or_equal($validcontextids, SQL_PARAMS_NAMED);

            $sql = "SELECT id, name, contextid
                      FROM {question_categories}
                     WHERE contextid $insql
                  ORDER BY contextid, name ASC";

            $dbcategories = $DB->get_records_sql($sql, $params);

            if ($dbcategories) {
                foreach ($dbcategories as $cat) {
                    try {
                        $catcontext = \context::instance_by_id($cat->contextid);
                        $contextname = $catcontext->get_context_name(false, true);
                        $categories[$cat->id] = format_string($cat->name) . ' (' . $contextname . ')';
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        if (empty($categories)) {
            $categories[0] = get_string('nocategories', 'playerraid');
        }

        $mform->addElement(
            'select',
            'questioncategoryid',
            get_string('questioncategory', 'playerraid'),
            $categories
        );
        $mform->addRule('questioncategoryid', null, 'required', null, 'client');
        $mform->addHelpButton('questioncategoryid', 'questioncategory', 'playerraid');

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }

    /**
     * Validate the form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     * or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate boss health.
        if ($data['boss_health'] <= 0) {
            $errors['boss_health'] = get_string('positiveint', 'playerraid');
        }

        // Validate question category.
        if (empty($data['questioncategoryid'])) {
            $errors['questioncategoryid'] = get_string('required');
        }

        return $errors;
    }
}
