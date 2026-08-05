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
 * Privacy provider implementation for mod_playerraid.
 *
 * @package    mod_playerraid
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerraid\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_playerraid.
 *
 * Personal data is stored in playerraid_attempts (userid, iscorrect,
 * cooldown_expires, timecreated).
 *
 * @package    mod_playerraid
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('playerraid_attempts', [
            'userid'           => 'privacy:metadata:userid',
            'iscorrect'        => 'privacy:metadata:iscorrect',
            'cooldown_expires' => 'privacy:metadata:cooldown_expires',
            'timecreated'      => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playerraid_attempts');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {playerraid_attempts} pa
                  JOIN {playerraid} pr ON pr.id = pa.playerraidid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pr.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE pa.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'activityname' => 'playerraid',
            'modlevel'     => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT pa.userid
                  FROM {playerraid_attempts} pa
                  JOIN {playerraid} pr ON pr.id = pa.playerraidid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pr.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, [
            'activityname' => 'playerraid',
            'modlevel'     => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contexts = array_reduce($contextlist->get_contexts(), function (array $carry, \context $context): array {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[$context->id] = $context;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'ctx');

        $sql = "SELECT pa.id, pa.iscorrect, pa.cooldown_expires, pa.timecreated, ctx.id AS contextid
                  FROM {playerraid_attempts} pa
                  JOIN {playerraid} pr ON pr.id = pa.playerraidid
                  JOIN {modules} m ON m.name = 'playerraid'
                  JOIN {course_modules} cm ON cm.instance = pr.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND pa.userid = :userid";
        $records = $DB->get_recordset_sql($sql, array_merge($inparams, ['userid' => $userid]));

        $allattempts = [];
        foreach ($records as $record) {
            $allattempts[$record->contextid][] = (object) [
                'iscorrect'       => transform::yesno($record->iscorrect),
                'cooldownexpires' => $record->cooldown_expires
                    ? transform::datetime($record->cooldown_expires) : null,
                'timecreated'     => transform::datetime($record->timecreated),
            ];
        }
        $records->close();

        foreach ($allattempts as $contextid => $attempts) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:playerraid_attempts', 'mod_playerraid')],
                (object) ['attempts' => $attempts]
            );
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('playerraid', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('playerraid_attempts', ['playerraidid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $instanceids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('playerraid', $context->instanceid);
            if ($cm) {
                $instanceids[] = (int) $cm->instance;
            }
        }

        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'pr');
        $DB->delete_records_select(
            'playerraid_attempts',
            "playerraidid $insql AND userid = :userid",
            array_merge($inparams, ['userid' => $userid])
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $cm = get_coursemodule_from_id('playerraid', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select(
            'playerraid_attempts',
            "playerraidid = :playerraidid AND userid $insql",
            array_merge(['playerraidid' => $cm->instance], $inparams)
        );
    }
}
