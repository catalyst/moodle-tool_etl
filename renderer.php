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
 * Plugin renderer.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\table\task_table;
use tool_etl\task_manager;
use \tool_etl\form\task_form;

defined('MOODLE_INTERNAL') || die;

class tool_etl_renderer extends plugin_renderer_base {

    /**
     * Display a list of tasks with control buttons.
     */
    public function display_tasks_table() {
        $table = new task_table();
        $table->display(task_manager::get_all_tasks());
    }

    /**
     * Render task form.
     *
     * @param \tool_etl\form\task_form $form
     */
    public function display_tasks_form(task_form $form) {
        $form->display();
    }

}
