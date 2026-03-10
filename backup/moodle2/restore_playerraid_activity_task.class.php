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
 * Defines restore_playerraid_activity_task class.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/playerraid/backup/moodle2/restore_playerraid_stepslib.php');

/**
 * Restore task that provides all the settings and steps to perform one complete restore of the activity.
 */
class restore_playerraid_activity_task extends restore_activity_task {
    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_playerraid_activity_structure_step('playerraid_structure', 'playerraid.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('playerraid', ['intro'], 'playerraid');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('PLAYERRAIDVIEWBYID', '/mod/playerraid/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('PLAYERRAIDINDEX', '/mod/playerraid/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('playerraid', 'add', 'view.php?id={course_module}', '{playerraid}');
        $rules[] = new restore_log_rule('playerraid', 'update', 'view.php?id={course_module}', '{playerraid}');
        $rules[] = new restore_log_rule('playerraid', 'view', 'view.php?id={course_module}', '{playerraid}');

        return $rules;
    }

    /**
     * Define the restore log rules for course logs.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('playerraid', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
