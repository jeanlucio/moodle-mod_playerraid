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
 * Data generator for mod_playerraid.
 *
 * @package    mod_playerraid
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator class for the playerraid activity module.
 */
class mod_playerraid_generator extends testing_module_generator {
    /**
     * Creates a new instance of the playerraid activity.
     *
     * @param array|\stdClass|null $record Field values for the instance.
     * @param array|null $options Module options (e.g. idnumber, section).
     * @return \stdClass Created course-module record.
     */
    public function create_instance($record = null, ?array $options = null): \stdClass {
        $record = (object) (array) $record;

        $defaults = [
            'boss_health'        => 100,
            'questioncategoryid' => 0,
            'cooldown_time'      => 60,
            'visual_theme'       => 'boss',
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Records an attempt of a user on an instance.
     *
     * @param int $instanceid Instance (not course-module) id.
     * @param int $userid User id.
     * @param array $fields Column overrides.
     * @return int The new attempt id.
     */
    public function create_attempt(int $instanceid, int $userid, array $fields = []): int {
        global $DB;

        return (int) $DB->insert_record('playerraid_attempts', (object) ($fields + [
            'playerraidid'     => $instanceid,
            'userid'           => $userid,
            'timecreated'      => time(),
            'iscorrect'        => 1,
            'cooldown_expires' => 0,
        ]));
    }
}
