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
 * @package    tool_etl
 * @copyright  2020 Tendai Mpita <tendaimpita@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_etl_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2020012801) {
        $dtable = 'tool_etl_task';

        // Get all tasks with all their settings.
        $tasks = $DB->get_records($dtable, null, null, 'id, target_settings');

        // Loop through them.
        foreach ($tasks as $task) {

            // Unserialize the tartget settings.
            $targetsettings = unserialize($task->target_settings);

            // Check if the date format setting is set.
            if ($targetsettings['dateformat']) {

                // Search for the hi and replace with Hi and initialise the dateformat the new value.
                $targetsettings['dateformat'] = str_replace('hi', 'Hi', $targetsettings['dateformat']);
                $task->target_settings = serialize($targetsettings);

                // Save the data again with updated value.
                $id = $DB->update_record($dtable, $task);

            }

        }

        upgrade_plugin_savepoint(true, 2020012801, 'tool', 'etl');
    }

    return true;
}
