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
 * Defines restore_playerraid_activity_structure_step class.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one playerraid activity.
 */
class restore_playerraid_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines structure of path elements to be processed during the restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('playerraid', '/activity/playerraid');
        // Add the path for restoring user attempts.
        $paths[] = new restore_path_element('playerraid_attempt', '/activity/playerraid/attempts/attempt');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given playerraid data.
     *
     * @param array $data
     */
    protected function process_playerraid($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        // Mapeia o ID antigo da categoria de questões para o ID recém-criado na restauração.
        if (!empty($data->questioncategoryid)) {
            $mappedcategory = $this->get_mappingid('question_category', $data->questioncategoryid);
            if ($mappedcategory) {
                $data->questioncategoryid = $mappedcategory;
            }
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the playerraid record.
        $newitemid = $DB->insert_record('playerraid', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the user attempts data.
     *
     * @param array $data
     */
    protected function process_playerraid_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Map the IDs to the new course/instance.
        $data->playerraidid = $this->get_new_parentid('playerraid');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // If the user wasn't mapped (e.g., they weren't restored), skip this attempt.
        if (empty($data->userid)) {
            return;
        }

        // Apply date offsets for course shifts.
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->cooldown_expires = $this->apply_date_offset($data->cooldown_expires);

        // Insert the attempt record.
        $newitemid = $DB->insert_record('playerraid_attempts', $data);

        // Set mapping just in case other plugins need it.
        $this->set_mapping('playerraid_attempt', $oldid, $newitemid);
    }

    /**
     * Post-execution actions.
     */
    protected function after_execute() {
        // Add playerraid related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_playerraid', 'intro', null);
    }
}
