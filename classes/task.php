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
 * Target interface.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

use tool_etl\common\common_base;
use tool_etl\source\source_interface;
use tool_etl\processor\processor_interface;
use tool_etl\target\target_interface;

defined('MOODLE_INTERNAL') || die;

class task implements task_interface {
    /**
     * Task table.
     */
    const TASK_TABLE = 'tool_etl_task';

    /**
     * Schedule table.
     */
    const SCHEDULE_TABLE = 'tool_etl_schedule';

    /**
     * Default source.
     */
    const DEFAULT_SOURCE = 'source_ftp';

    /**
     * Default target.
     */
    const DEFAULT_TARGET = 'target_dataroot';

    /**
     * Default processor.
     */
    const DEFAULT_PROCESSOR = 'processor_default';

    /**
     * Source for the task.
     *
     * @var source_interface
     */
    protected $source;

    /**
     * Target for the task.
     *
     * @var target_interface
     */
    protected $target;

    /**
     * Processor for the task.
     *
     * @var processor_interface
     */
    protected $processor;

    /**
     * Schedule for the task.
     *
     * @var scheduler
     */
    protected $schedule;

    /**
     * ID of schedule record.
     *
     * @var int
     */
    protected $scheduleid = 0;

    /**
     * DB object.
     *
     * @var \moodle_database
     */
    protected $db;

    /**
     * Task ID from DB.
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Is the task enabled?
     *
     * @var int
     */
    protected $enabled = 1;

    /**
     * Constructor.
     *
     * @param int $id ID of the task record from DB.
     *
     * @throws \Exception If a task is not exist.
     */
    public function __construct($id = 0) {
        global $DB;

        $this->db = $DB;
        $this->id = $id;

        $this->init();
    }

    /**
     * Initial set up of the instance.
     *
     * @throws \invalid_parameter_exception
     */
    protected function init() {
        $this->schedule = new scheduler();

        if ($this->id == 0) {
            $source = self::DEFAULT_SOURCE;
            $target = self::DEFAULT_TARGET;
            $processor = self::DEFAULT_PROCESSOR;
            $sourcesettings = array();
            $targetsettings = array();
            $processorsettings = array();
        } else {
            if (!$data = $this->db->get_record(self::TASK_TABLE, array('id' => $this->id))) {
                throw new \invalid_parameter_exception('Task ' . $this->id . ' is not exist');
            }

            $this->enabled = $data->enabled;

            $source = $data->source;
            $target = $data->target;
            $processor = $data->processor;
            $sourcesettings = unserialize($data->source_settings);
            $targetsettings = unserialize($data->target_settings);
            $processorsettings = unserialize($data->processor_settings);

            if ($schedulerow = $this->db->get_record(self::SCHEDULE_TABLE, array('taskid' => $this->id))) {
                $this->scheduleid = $schedulerow->id;
                $this->schedule = new scheduler($schedulerow);
            }
        }

        $this->source = common_base::init('source', $source, $sourcesettings);
        $this->target = common_base::init('target', $target, $targetsettings);
        $this->processor = common_base::init('processor', $processor, $processorsettings);
    }

    /**
     * A magic method to return task's properties.
     *
     * @param string $name A name of the property/
     *
     * @return mixed
     * @throws \coding_exception If the property if not exist.
     */
    public function __get($name) {
        if (!isset($this->$name)) {
            throw new \coding_exception('Incorrect property ' . $name);
        }

        return $this->$name;
    }

    /**
     * Sets a source to the task.
     *
     * @param \tool_etl\source\source_interface $source
     */
    public function set_source(source_interface $source) {
        $this->source = $source;
    }

    /**
     * Sets a target to the task.
     *
     * @param \tool_etl\target\target_interface $target
     */
    public function set_target(target_interface $target) {
        $this->target = $target;
    }

    /**
     * Set processor to the task.
     *
     * @param \tool_etl\processor\processor_interface $processor
     */
    public function set_processor(processor_interface $processor) {
        $this->processor = $processor;
    }

    /**
     * Set a schedule to the task.
     *
     * @param \tool_etl\scheduler $schedule
     */
    public function set_schedule(scheduler $schedule) {
        $this->schedule = $schedule;
    }

    /**
     * Set enabled to the task.
     *
     * @param int $enabled
     */
    public function set_enabled($enabled = null) {
        if (is_null($enabled)) {
            $enabled = 0;
        }

        $this->enabled = $enabled;
    }

    /**
     * Check if the task is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return !empty($this->enabled);
    }

    /**
     * Save the task to DB.
     */
    public function save() {
        if (empty($this->source) || empty($this->processor) || empty($this->target) || empty($this->schedule)) {
            throw new \coding_exception('Task should have source, processor, target and schedule configured!');
        }

        if (!empty($this->id)) {
            $this->update_task();
        } else {
            $this->insert_task();
        }
    }

    /**
     * Create a new task in DB.
     */
    protected function insert_task() {
        $this->id = $this->db->insert_record(self::TASK_TABLE, $this->task_to_object());
        $this->scheduleid = $this->db->insert_record(self::SCHEDULE_TABLE, $this->schedule_to_object());
    }

    /**
     * Update the task in DB.
     */
    protected function update_task() {
        if (!$this->id) {
            throw new \coding_exception('To be able to update task it should be inserted to DB first');
        }

        $this->db->update_record(self::TASK_TABLE, $this->task_to_object());

        if ($this->scheduleid) {
            $this->db->update_record(self::SCHEDULE_TABLE, $this->schedule_to_object());
        } else {
            $this->scheduleid = $this->db->insert_record(self::SCHEDULE_TABLE, $this->schedule_to_object());
        }
    }

    /**
     * Return task as object for saving to DB.
     *
     * @return \stdClass
     */
    protected function task_to_object() {
        $task = new \stdClass();

        if (!empty($this->id)) {
            $task->id = $this->id;
        }

        $task->source = $this->source->get_short_name();
        $task->source_settings = serialize($this->source->get_settings());
        $task->processor = $this->processor->get_short_name();
        $task->processor_settings = serialize($this->processor->get_settings());
        $task->target = $this->target->get_short_name();
        $task->target_settings = serialize($this->target->get_settings());
        $task->enabled = $this->enabled;

        return $task;
    }

    /**
     * Return a schedule as object for saving to DB.
     *
     * @return \stdClass
     */
    protected function schedule_to_object() {
        $scheduleobj = $this->schedule->to_object();

        if (!empty($this->id)) {
            $scheduleobj->taskid = $this->id;
        }

        if (!empty($this->scheduleid)) {
            $scheduleobj->id = $this->scheduleid;
        }

        return $scheduleobj;
    }

    /**
     * Delete the task from DB.
     */
    public function delete() {
        $this->db->delete_records(self::SCHEDULE_TABLE, array('taskid' => $this->id));
        $this->db->delete_records(self::TASK_TABLE, array('id' => $this->id));
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if ($this->is_enabled() && $this->schedule->is_time()) {
            $this->processor->set_source($this->source);
            $this->processor->set_target($this->target);
            $this->processor->process();
            $this->schedule->next();
            $this->update_task();
        }
    }
}
