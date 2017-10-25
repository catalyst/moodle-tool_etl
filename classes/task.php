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

        if ($this->id == 0) {
            $this->source = common_base::init('source', 'source_ftp');
            $this->target = common_base::init('target', 'target_dataroot');
            $this->processor = common_base::init('processor', 'processor_default');
        } else {

            if (!$data = $DB->get_record(self::TASK_TABLE, array('id' => $this->id))) {
                throw new \invalid_parameter_exception('Task ' . $this->id . ' is not exist');
            }

            $data = (array)$data;

            $this->source = common_base::init('source', $data['source'], unserialize($data['source_settings']));
            $this->target = common_base::init('target', $data['target'], unserialize($data['target_settings']));
            $this->processor = common_base::init('processor', $data['processor'], unserialize($data['processor_settings']));
        }
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
     * Ass a schedule to the task.
     *
     * @param \tool_etl\scheduler $schedule
     */
    public function add_schedule(scheduler $schedule) {
        // TODO: Implement add_schedule() method.
    }

    /**
     * Save the task to DB.
     */
    public function save() {
        $task = new \stdClass();

        $task->source = $this->source->get_short_name();
        $task->source_settings = serialize($this->source->get_settings());
        $task->processor = $this->processor->get_short_name();
        $task->processor_settings = serialize($this->processor->get_settings());
        $task->target = $this->target->get_short_name();
        $task->target_settings = serialize($this->target->get_settings());

        if (!empty($this->id)) {
            $task->id = $this->id;
            $this->db->update_record(self::TASK_TABLE, $task);
        } else {
            $this->db->insert_record(self::TASK_TABLE, $task);
        }
    }

    /**
     * Delete the task from DB.
     */
    public function delete() {
        $this->db->delete_records(self::TASK_TABLE, array('id' => $this->id));
    }

    /**
     * Execute the task.
     */
    public function execute() {

    }
}
