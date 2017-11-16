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
 * Logger class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

defined('MOODLE_INTERNAL') || die;

final class logger {
    /**
     * Error type record.
     */
    const TYPE_ERROR = 'ERROR';

    /**
     * Warning type record.
     */
    const TYPE_WARNING = 'WARNING';

    /**
     * Info type record.
     */
    const TYPE_INFO = 'INFO';

    /**
     * Singleton logger instance.
     *
     * @var logger
     */
    protected static $instance;

    /**
     * Id on the current run.
     *
     * @var int|mixed
     */
    protected $runid;

    /**
     * String a name of the element who logs data.
     *
     * @var string
     */
    protected $element;

    /**
     * Current task id.
     *
     * @var int
     */
    protected $taskid;

    /**
     * Get a single instance of logger per run.
     *
     * @return \tool_etl\logger
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new logger();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->runid = $this->get_last_run_id() + 1;
        set_config('lastrunid', $this->runid, 'tool_etl');
    }

    /**
     * disable clone.
     */
    private function __clone() {

    }

    /**
     * Disable unserialization.
     *
     * @throws \coding_exception
     */
    private function __wakeup() {
        throw new \coding_exception("Cannot unserialize singleton");
    }

    /**
     * Set task ID.
     *
     * @param int $taskid Task ID.
     */
    public function set_task_id($taskid) {
        $this->taskid = $taskid;
    }

    /**
     * Set element.
     *
     * @param $element
     */
    public function set_element($element) {
        $this->element = $element;
    }

    /**
     * Return last run ID.
     *
     * @return int|mixed
     * @throws \dml_exception
     */
    protected function get_last_run_id() {
        $runid = get_config('tool_etl', 'lastrunid');

        if (empty($runid)) {
            return 0;
        } else {
            return $runid;
        }
    }

    /**
     * Add a single log record.
     *
     * @param string $logtype One of self::TYPE_*
     * @param string $action Logged action.
     * @param string $info Info text.
     * @param string $trace Some code trace.
     *
     * @return bool|int
     * @throws \coding_exception
     */
    public function add_to_log($logtype, $action, $info='', $trace='') {
        global $DB;

        if (empty($this->taskid) || empty($this->element)) {
            throw new \coding_exception('Task or Element is not set. Can write to the log');
        }

        $log = new \stdClass;
        $log->time = time();
        $log->runid = $this->runid;
        $log->taskid = $this->taskid;
        $log->logtype = $logtype;
        $log->element = $this->element;
        $log->action = substr($action, 0, 255);
        $log->info = $this->to_string($info);
        $log->trace = $trace;

        return $DB->insert_record('tool_etl_log', $log);
    }

    /**
     * Convert info to string.
     *
     * @param mixed $info Info string or array.
     *
     * @return string
     */
    private function to_string($info = null) {
        $retval = '';
        if (is_string($info)) {
            $retval = $info;
        } else if (is_array($info) or is_object($info)) {
            foreach ($info as $key => $value) {
                if ($value <> '') {
                    if ($retval <> '') {
                        $retval .= ', ';
                    }
                    $retval .= "$key=$value";
                }
            }
        }

        return $retval;
    }

}
