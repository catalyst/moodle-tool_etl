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
 * Index file.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\task_manager;
use tool_etl\form\task_form;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$action = 'create';

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('tool_etl_settings');

$PAGE->requires->js_call_amd('tool_etl/task_form', 'init');

$output = $PAGE->get_renderer('tool_etl');

if (!empty($id)) {
    $action = 'edit';
}

$task = task_manager::get_task($id);
$form = new task_form(null, array('task' => $task));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/etl/index.php'));
}

if ($data = $form->get_data()) {
    task_manager::save_task($data);
    redirect(new moodle_url('/admin/tool/etl/index.php', array('id' => $id)));
}

$PAGE->navbar->add(get_string($action . '_breadcrumb', 'tool_etl'));

echo $output->header();
echo $output->heading(get_string($action . '_heading', 'tool_etl'));

$output->display_tasks_form($form);
$output->display_tasks_table();

echo $output->footer();
