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
 * Backup and restore tests for mod_playerraid.
 *
 * @package    mod_playerraid
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerraid;

use core_courseformat\local\cmactions;

/**
 * Duplicating an activity and restoring a full course must keep every column.
 *
 * @package    mod_playerraid
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_playerraid_activity_structure_step
 * @covers     \restore_playerraid_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Backs up the course and restores it into a brand new one.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The course the backup was restored into.
     */
    private function backup_and_restore_into_new_course(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($tempdir)
        );
        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourse;
    }

    /**
     * Every instance setting survives a course backup and restore, including the visual theme,
     * which used to come back as the column default because the backup never saved it.
     */
    public function test_backup_restore_preserves_the_instance_settings(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->get_plugin_generator('mod_playerraid')->create_instance([
            'course'        => $course->id,
            'boss_health'   => 250,
            'cooldown_time' => 30,
            'visual_theme'  => 'vault',
        ]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $restored = $DB->get_record('playerraid', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertSame('vault', $restored->visual_theme);
        $this->assertSame(250, (int) $restored->boss_health);
        $this->assertSame(30, (int) $restored->cooldown_time);
    }

    /**
     * Duplicating an activity copies its settings and its attempts.
     */
    public function test_duplicate_activity_keeps_the_visual_theme(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerraid');
        $instance = $generator->create_instance(['course' => $course->id, 'visual_theme' => 'thief']);
        $cm = get_coursemodule_from_instance('playerraid', $instance->id, $course->id, false, MUST_EXIST);

        // Core's duplicate_module() is deprecated since Moodle 5.2 and cmactions::duplicate()
        // does not exist before it, so the choice stays guarded while the plugin supports 4.5.
        if (method_exists(cmactions::class, 'duplicate')) {
            $newcm = (new cmactions($course))->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }

        $copy = $DB->get_record('playerraid', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame('thief', $copy->visual_theme);
    }

    /**
     * The attempts of the activity travel with a course backup that includes user data.
     */
    public function test_backup_restore_preserves_the_attempts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerraid');
        $instance = $generator->create_instance(['course' => $course->id]);
        $generator->create_attempt($instance->id, $user->id, ['iscorrect' => 0, 'cooldown_expires' => time() + 60]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $restored = $DB->get_record('playerraid', ['course' => $newcourse->id], '*', MUST_EXIST);
        $attempt = $DB->get_record('playerraid_attempts', ['playerraidid' => $restored->id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $attempt->iscorrect);
        $this->assertGreaterThan(0, (int) $attempt->cooldown_expires);
    }
}
