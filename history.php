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
 * Task processing history.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('tool_etl_settings');

$download = optional_param('download', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$PAGE->set_pagelayout('report');
$indexurl = new moodle_url('/admin/tool/etl/history.php');
$PAGE->set_url($indexurl);

$output = $PAGE->get_renderer('tool_etl');

$mform = new \tool_etl\form\history_filter_form(null, array());
$filters = array();

if ($data = $mform->get_data()) {
    $filters = (array)$data;

    $filters['runid'] = (int)$filters['runid']; // We don't use PARAM_INT in the form as it sets the field to 0 all the time.

    if (isset($filters['logtype']) && is_array($filters['logtype'])) {
        $filters['logtype'] = implode(',', $filters['logtype']);
    }

    if (!empty($filters['datetill'])) {
        $filters['datetill'] += DAYSECS - 1; // Set to end of the chosen day.
    }
} else {
    $filters = array(
        'runid' => optional_param('runid', '', PARAM_INT),
        'taskid' => optional_param('taskid', '', PARAM_INT),
        'element' => optional_param('element', '', PARAM_ALPHANUMEXT),
        'action' => optional_param('action', '', PARAM_ALPHANUMEXT),
        'logtype' => optional_param('logtype', '', PARAM_SEQUENCE),
        'datefrom' => optional_param('datefrom', 0, PARAM_INT),
        'datetill' => optional_param('datetill', 0, PARAM_INT),
    );
}

$mform->set_data($filters);
$table = new \tool_etl\table\history_table('task_history', $indexurl, $filters, $download, $page);

if ($table->is_downloading()) {
    \core\session\manager::write_close();
    echo $output->render($table);
    die();
}

$PAGE->navbar->add('Task history');

echo $output->header();
echo $output->heading('Task history');
$mform->display();
echo $output->render($table);
echo $output->footer();
