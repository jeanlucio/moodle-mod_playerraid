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
 * Tests for playerraid_delete_instance().
 *
 * @package    mod_playerraid
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerraid;

/**
 * Deleting an activity must remove its attempts too, and only its own.
 *
 * @package    mod_playerraid
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::playerraid_delete_instance
 */
final class lib_delete_instance_test extends \advanced_testcase {
    /**
     * The attempts of a deleted activity are removed with it; those of another activity stay.
     */
    public function test_delete_instance_removes_its_attempts_and_only_its_own(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerraid');
        $doomed = $generator->create_instance(['course' => $course->id]);
        $kept = $generator->create_instance(['course' => $course->id]);
        $generator->create_attempt($doomed->id, $user->id);
        $generator->create_attempt($doomed->id, $user->id);
        $generator->create_attempt($kept->id, $user->id);

        course_delete_module($doomed->cmid);

        $this->assertFalse($DB->record_exists('playerraid', ['id' => $doomed->id]));
        $this->assertSame(0, $DB->count_records('playerraid_attempts', ['playerraidid' => $doomed->id]));
        $this->assertSame(1, $DB->count_records('playerraid_attempts', ['playerraidid' => $kept->id]));
    }
}
