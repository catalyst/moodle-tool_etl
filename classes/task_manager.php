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
 * Task  manager helper class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

use tool_etl\common\common_base;

defined('MOODLE_INTERNAL') || die();

class task_manager {

    /**
     * Save task using data submitted by task form.
     *
     * @param \stdClass $data Submitted data.
     */
    public static function save_task(\stdClass $data) {
        $data = (array)$data;

        $sourceinstance = common_base::init('source', $data['source']);
        $targetinstance = common_base::init('target', $data['target']);
        $processorinstance = common_base::init('processor', $data['processor']);

        $sourceinstance->set_settings($sourceinstance->get_settings_from_submitted_data($data));
        $targetinstance->set_settings($targetinstance->get_settings_from_submitted_data($data));
        $processorinstance->set_settings($processorinstance->get_settings_from_submitted_data($data));

        $task = self::get_task($data['id']);
        $task->set_source($sourceinstance);
        $task->set_target($targetinstance);
        $task->set_processor($processorinstance);

        $task->save();
    }

    /**
     * Return an instance of the task by ID.
     *
     * @param int $id Task ID.
     *
     * @return \tool_etl\task
     */
    public static function get_task($id) {
        return new task($id);
    }

    /**
     * Delete task.
     *
     * @param int $id Task ID.
     */
    public static function delete_task($id) {
        self::get_task($id)->delete();
    }

    /**
     * Return a list of all tasks.
     *
     * @return array A list of task objects.
     */
    public static function get_all_tasks() {
        global $DB;

        $tasks = array();

        if ($records = $DB->get_records(task::TASK_TABLE)) {
            foreach ($records as $record) {
                $tasks[] = self::get_task($record->id);
            }
        }

        return $tasks;
    }

}
