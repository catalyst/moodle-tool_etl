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
 * Delete task
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\task_manager;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('tool_etl_settings');

$action = 'delete';
$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_ALPHANUM);

$output = $PAGE->get_renderer('tool_etl');

$indexurl = new moodle_url('/admin/tool/etl/index.php');

$task = task_manager::get_task($id);

if ($confirm != md5($id)) {
    $confirmstring = get_string($action . '_confirm', 'tool_etl', $id);
    $cinfirmoptions = array('action' => $action, 'id' => $id, 'confirm' => md5($id), 'sesskey' => sesskey());
    $deleteurl = new moodle_url('/admin/tool/etl/delete.php', $cinfirmoptions);
    $PAGE->navbar->add(get_string($action . '_breadcrumb', 'tool_etl'));
    echo $output->header();
    echo $output->heading(get_string($action . '_heading', 'tool_etl'));
    echo $output->confirm($confirmstring, $deleteurl, $indexurl);
    echo $output->footer();
} else if (data_submitted() and confirm_sesskey()) {
    $task->delete();
    redirect($indexurl);
}
