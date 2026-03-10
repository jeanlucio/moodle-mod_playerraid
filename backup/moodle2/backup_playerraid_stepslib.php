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
 * Defines backup_playerraid_activity_structure_step class.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete playerraid structure for backup, with file and id annotations.
 */
class backup_playerraid_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // Define the root element and its attributes (added cooldown_time).
        $playerraid = new backup_nested_element(
            'playerraid',
            ['id'],
            [
                'course',
                'name',
                'intro',
                'introformat',
                'timecreated',
                'timemodified',
                'boss_health',
                'questioncategoryid',
                'cooldown_time',
            ]
        );

        // Define the nested elements for user attempts.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element(
            'attempt',
            ['id'],
            [
                'userid',
                'timecreated',
                'iscorrect',
                'cooldown_expires',
            ]
        );

        // Build the tree structure.
        $playerraid->add_child($attempts);
        $attempts->add_child($attempt);

        // Define data sources.
        $playerraid->set_source_table('playerraid', ['id' => backup::VAR_ACTIVITYID]);

        // Only backup attempts if the user chose to include user data in the backup.
        if ($this->get_setting_value('userinfo')) {
            $attempt->set_source_table('playerraid_attempts', ['playerraidid' => backup::VAR_PARENTID]);
        }

        // Define id annotations (tells Moodle to map the user ID correctly during restore).
        $attempt->annotate_ids('user', 'userid');

        // Define file annotations.
        $playerraid->annotate_files('mod_playerraid', 'intro', null);

        // Return the root element (playerraid), wrapped into standard activity structure.
        return $this->prepare_activity_structure($playerraid);
    }
}
