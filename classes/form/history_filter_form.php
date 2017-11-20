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
 * A form for manipulating tasks.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\form;

use tool_etl\logger;
use tool_etl\task_manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

class history_filter_form extends \moodleform {

    /**
     * Definition of the Mform for filters displayed in the report.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'runid', 'Run ID');
        $mform->setType('runid', PARAM_ALPHANUM);

        $mform->addElement('select', 'taskid', 'Task ID', $this->get_task_ids());
        $mform->setType('taskid', PARAM_INT);

        $mform->addElement('date_selector', 'datefrom', get_string('from'), array('optional' => true));
        $mform->addElement('date_selector', 'datetill', get_string('to'), array('optional' => true));

        $mform->addElement('select', 'logtype', 'Type', $this->get_log_types(), array('multiple' => 'multiple', 'size' => 3));

        $mform->addElement('select', 'element', 'Task element', $this->get_task_elements());
        $mform->setType('element', PARAM_RAW);

        $mform->addElement('select', 'action', 'Element action', $this->get_actions());
        $mform->setType('action', PARAM_RAW);

        $mform->addElement('submit', 'submitbutton', get_string('filter'));
    }

    /**
     * Return a list of task ids.
     *
     * @return array
     */
    protected function get_task_ids() {
        $tasks = task_manager::get_all_tasks();

        foreach ($tasks as $task) {
            $ids[$task->id] = $task->id;
        }

        asort($ids);
        array_unshift($ids, 'Choose task...');

        return $ids;
    }

    /**
     * Return a list of possible log types.
     *
     * @return array
     */
    protected function get_log_types() {
        return array(
            logger::TYPE_INFO => get_string('ok'),
            logger::TYPE_WARNING => get_string('warning'),
            logger::TYPE_ERROR => get_string('error'),
        );
    }

    /**
     * Return a list of elements.
     *
     * @return array
     */
    protected function get_task_elements() {
        $elements = array();
        foreach (logger::get_existing_elements() as $element) {
            $elements[$element] = $element;
        }

        array_unshift($elements, 'Choose task element...');

        return $elements;
    }

    /**
     * Return a list of existing actions.
     *
     * @return array
     */
    protected function get_actions() {
        $actions = array();
        foreach (logger::get_existing_actions() as $action) {
            $actions[$action] = $action;
        }

        array_unshift($actions, 'Choose action...');

        return $actions;
    }
}
