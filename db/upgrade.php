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
 * Upgrade code for the PlayerRaid module.
 *
 * @package   mod_playerraid
 * @copyright 2026 Jean Lúcio
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute playerraid upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_playerraid_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Upgrade to version 2026030801.
    if ($oldversion < 2026030801) {
        // Define field cooldown_time to be added to playerraid.
        $table = new xmldb_table('playerraid');
        $field = new xmldb_field(
            'cooldown_time',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '60',
            'questioncategoryid'
        );

        // Conditionally launch add field cooldown_time.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table playerraid_attempts to be created.
        $tableattempts = new xmldb_table('playerraid_attempts');

        // Adding fields to table playerraid_attempts.
        $tableattempts->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tableattempts->add_field('playerraidid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tableattempts->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tableattempts->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tableattempts->add_field('iscorrect', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $tableattempts->add_field('cooldown_expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table playerraid_attempts.
        $tableattempts->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tableattempts->add_key('fk_playerraid', XMLDB_KEY_FOREIGN, ['playerraidid'], 'playerraid', ['id']);
        $tableattempts->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for playerraid_attempts.
        if (!$dbman->table_exists($tableattempts)) {
            $dbman->create_table($tableattempts);
        }

        // Playerraid savepoint reached.
        upgrade_mod_savepoint(true, 2026030801, 'playerraid');
    }

    return true;
}
