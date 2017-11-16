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
 * Table to display a history of tasks processing.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\table;

defined('MOODLE_INTERNAL') || die;

class history_table extends \table_sql implements \renderable {

    /**
     * A list of filters to be applied to the sql query.
     *
     * @var \stdClass
     */
    protected $filters;

    /**
     * A current page number.
     *
     * @var int
     */
    protected $page;

    /**
     * Constructor.
     *
     * @param string $uniqueid a string identifying this table.Used as a key in session vars.
     * @param \moodle_url $url Page URL.
     * @param array $filters A list of selected filters.
     * @param string $download Should we download?
     * @param int $page A current page.
     * @param int $perpage A number of records per page.
     */
    public function __construct($uniqueid, \moodle_url $url, $filters = array(), $download = '', $page = 0, $perpage = 100) {
        parent::__construct($uniqueid);

        $this->set_attribute('class', 'tool_etl_history generaltable generalbox');
        $this->pagesize = $perpage;
        $this->page = $page;
        $this->filters = (object)$filters;

        // Define columns in the table.
        $this->define_table_columns();

        // Define configs.
        $this->define_table_configs($url);

        // Set download status.
        $this->is_downloading($download, 'task_history');
    }

    /**
     * Define table configs.
     *
     * @param \moodle_url $url url of the page where this table would be displayed.
     */
    protected function define_table_configs(\moodle_url $url) {
        $urlparams = (array)$this->filters;

        unset($urlparams['submitbutton']);

        $url->params($urlparams);
        $this->define_baseurl($url);

        // Set table configs.
        $this->collapsible(false);
        $this->sortable(true, 'time', SORT_DESC);
        $this->pageable(true);

        $this->no_sorting('info');
        $this->no_sorting('trace');

        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);
    }

    /**
     * Setup the headers for the html table.
     */
    protected function define_table_columns() {

        $cols = array(
            'runid' => 'Run ID',
            'taskid' => 'Task ID',
            'time' => 'Time',
            'logtype' => 'Type',
            'element' => 'Element',
            'action' => 'Action',
            'info' => 'Info',
            'trace' => 'Trace',
        );

        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        list($countsql, $countparams) = $this->get_sql_and_params(true);
        list($sql, $params) = $this->get_sql_and_params();

        $total = $DB->count_records_sql($countsql, $countparams);
        $this->pagesize($pagesize, $total);

        if ($this->is_downloading()) {
            $histories = $DB->get_records_sql($sql, $params);
        } else {
            $histories = $DB->get_records_sql($sql, $params, $this->pagesize * $this->page, $this->pagesize);
        }
        foreach ($histories as $history) {
            $this->rawdata[] = $history;
        }

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * Builds the complete sql with all the joins to get the grade history data.
     *
     * @param bool $count setting this to true, returns an sql to get count only instead of the complete data records.
     *
     * @return array containing sql to use and an array of params.
     */
    protected function get_sql_and_params($count = false) {
        if ($count) {
            $select = "COUNT(1)";
        } else {
            $select = "id, time, runid, taskid, logtype, element, action, info, trace ";
        }

        list($where, $params) = $this->get_filters_sql_and_params();

        $sql = "SELECT $select FROM {tool_etl_log} WHERE $where";

        // Add order by if needed.
        if (!$count && $sqlsort = $this->get_sql_sort()) {
            $sql .= " ORDER BY " . $sqlsort;
        }

        return array($sql, $params);
    }

    /**
     * Builds the sql and param list needed, based on the user selected filters.
     *
     * @return array containing sql to use and an array of params.
     */
    protected function get_filters_sql_and_params() {
        global $DB;

        $filter = 'id IS NOT NULL';
        $params = array();

        if (!empty($this->filters->runid)) {
            $filter .= ' AND runid = :runid';
            $params['runid'] = $this->filters->runid;
        }

        if (!empty($this->filters->taskid)) {
            $filter .= ' AND taskid = :taskid';
            $params['taskid'] = $this->filters->taskid;
        }

        if (!empty($this->filters->logtype)) {
            $list = explode(',', $this->filters->logtype);
            list($insql, $plist) = $DB->get_in_or_equal($list, SQL_PARAMS_NAMED);
            $filter .= " AND logtype $insql";
            $params += $plist;
        }

        if (!empty($this->filters->element)) {
            $filter .= ' AND element = :element';
            $params['element'] = $this->filters->element;
        }

        if (!empty($this->filters->action)) {
            $filter .= ' AND action = :action';
            $params['action'] = $this->filters->action;
        }

        if (!empty($this->filters->datefrom)) {
            $filter .= " AND time >= :datefrom";
            $params += array('datefrom' => $this->filters->datefrom);
        }
        if (!empty($this->filters->datetill)) {
            $filter .= " AND time <= :datetill";
            $params += array('datetill' => $this->filters->datetill);
        }

        return array($filter, $params);
    }

    /**
     * Get the SQL fragment to sort by.
     *
     * @return string SQL fragment.
     */
    public function get_sql_sort() {
        $columns = array('id' => SORT_DESC); // Always sort by ID first to keep order.
        $columns = array_merge($columns, $this->get_sort_columns());

        return self::construct_order_by($columns);
    }

    /**
     * Method to display column time.
     *
     * @param \stdClass $history an entry of history record.
     *
     * @return string HTML to display
     */
    public function col_time(\stdClass $history) {
        return userdate($history->time);
    }

}
