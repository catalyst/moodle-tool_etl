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
 * Database source.
 *
 * @package    tool_etl
 * @copyright  2018 Ilya Tregubov <ilyatregubov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\source;

use tool_etl\config_field;
use tool_etl\data;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class source_database extends source_base {

    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "Database";

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'querysql' => '',
        'columnheader' => 0,
        'weekstart' => 6,
        'columnfields' => '',
    );

    /**
     * @inheritdoc
     */
    public function extract() {
        global $DB;

        $recordset = $DB->get_recordset_sql($this->get_settings()['querysql']);

        $arraytoprocess = array();
        while ($recordset->valid()) {
            $arraytoprocess[] = $recordset->current();
            $recordset->next();
        }
        $recordset->close();

        if ($this->settings['columnheader']) {
            $columnheaders = new \StdClass;
            if (count($arraytoprocess) > 0) {
                foreach ($arraytoprocess[0] as $key => $value) {
                    $columnheaders->$key = $key;
                }
                array_unshift($arraytoprocess, $columnheaders);
            } else {
                $columnfields = $this->get_settings()['columnfields'];
                $fields = explode('\r\n', $columnfields);
                foreach ($fields as $field) {
                    $field = trim($field);
                    $columnheaders->$field = $field;
                }
                $arraytoprocess[] = $columnheaders;
            }
        }

        $this->data = new data(null, null, $arraytoprocess, null);
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'querysql' => new config_field('querysql', 'SQL query', 'textarea', $this->settings['querysql'], PARAM_RAW),
            'weekstart' => new config_field('weekstart', 'Week start', 'select', $this->settings['weekstart'], PARAM_INT, $this->get_list_of_days()),
            'columnheader' => new config_field('columnheader', 'Column headers as a first row', 'advcheckbox', $this->settings['columnheader'], PARAM_BOOL),
            'columnfields' => new config_field('columnfields', 'Column headers', 'textarea', $this->settings['columnfields'], PARAM_RAW),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));

    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        global $CFG;

        if (empty($data[$this->get_config_form_prefix() . 'querysql'])) {
            $errors[$this->get_config_form_prefix() . 'querysql'] = 'SQL query could not be empty';
        } else {

            $sql = $data['source_database-querysql'];

            if ($this->tool_etl_contains_bad_word($sql)) {
                // Obviously evil stuff in the SQL.
                $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('notallowedwords', 'tool_etl',
                    implode(', ', $this->tool_etl_bad_words_list()));

            } else if (strpos($sql, ';') !== false) {
                // Do not allow any semicolons.
                $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('nosemicolon', 'tool_etl');

            } else if ($CFG->prefix != '' && preg_match('/\b' . $CFG->prefix . '\w+/i', $sql)) {
                // Make sure prefix is prefix_, not explicit.
                $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('noexplicitprefix', 'tool_etl', $CFG->prefix);

            } else {
                // Now try running the SQL, and ensure it runs without errors.
                $report = new \stdClass;
                $report->querysql = $sql;
                $report->runable = 'daily';
                $report->at = "00:00";
                if (isset($data['source_database-weekstart'])) {
                    $report->weekstart = $data['source_database-weekstart'];
                }
                $sql = $this->tool_etl_prepare_sql($report, time());

                // Check for required query parameters if there are any.
                $queryparams = array();
                foreach ($this->tool_etl_get_query_placeholders($sql) as $queryparam) {
                    $queryparam = substr($queryparam, 1);
                    $formparam = 'queryparam' . $queryparam;
                    if (!isset($data[$formparam])) {
                        $errors[$this->get_config_form_prefix() . 'params'] = get_string('queryparamschanged', 'tool_etl');
                        break;
                    }
                    $queryparams[$queryparam] = $data[$formparam];
                }

                if (!isset($errors[$this->get_config_form_prefix() . 'params'])) {
                    try {
                        $rs = $this->tool_etl_execute_query($sql, $queryparams, 2);

                        if (!empty($data['singlerow'])) {
                            // Count rows for Moodle 2 as all Moodle 1.9 useful and more performant
                            // recordset methods removed.
                            $rows = 0;
                            foreach ($rs as $value) {
                                $rows++;
                            }
                            if (!$rows) {
                                $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('norowsreturned', 'tool_etl');
                            } else if ($rows >= 2) {
                                $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('morethanonerowreturned',
                                    'tool_etl');
                            }
                        }
                        $rs->close();
                    } catch (\dml_exception $e) {
                        $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('queryfailed', 'tool_etl',
                            $e->getMessage() . ' ' . $e->debuginfo);
                    } catch (\Exception $e) {
                        $errors[$this->get_config_form_prefix() . 'querysql'] = get_string('queryfailed', 'tool_etl',
                            $e->getMessage());
                    }
                }
            }
        }

        $columnfields = $data[$this->get_config_form_prefix() . 'columnfields'];
        if (!empty($columnfields)) {
            if ($this->tool_etl_column_headers_contains_invalid_symbols($columnfields)) {
                $errors[$this->get_config_form_prefix() . 'columnfields'] = get_string('errorinvalidsymbols', 'tool_etl');
            }
        }

        return $errors;
    }

    /**
     * Executes SQL query.
     * @param string $sql SQL query.
     * @param array $params Query parameters.
     * @return moodle_recordset A moodle_recordset instance.
     * @throws dml_exception A DML specific exception is thrown for any errors.
     */
    public function tool_etl_execute_query($sql, $params = null) {
        global $CFG, $DB;

        $sql = preg_replace('/\bprefix_(?=\w+)/i', $CFG->prefix, $sql);

        // Note: throws Exception if there is an error.
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Extract all the placeholder names from the SQL.
     * @param object $report The report to run.
     * @param int $timenow a timestamp.
     * @return string SQL to run
     * @throws \exception
     */
    public function tool_etl_prepare_sql($report, $timenow) {
        global $USER;
        $sql = $report->querysql;
        if ($report->runable != 'manual') {
            list($end, $start) = $this->tool_etl_get_starts($report, $timenow);
            $sql = $this->tool_etl_substitute_time_tokens($sql, $start, $end);
        }
        $sql = $this->tool_etl_substitute_user_token($sql, $USER->id);
        return $sql;
    }

    /**
     * Extract all the placeholder names from the SQL.
     * @param string $sql The sql.
     * @return array placeholder names
     */
    public function tool_etl_get_query_placeholders($sql) {
        preg_match_all('/(?<!:):[a-z][a-z0-9_]*/', $sql, $matches);
        return $matches[0];
    }

    /**
     * Return the type of form field to use for a placeholder, based on its name.
     * @param string $name the placeholder name.
     * @return string a formslib element type, for example 'text' or 'date_time_selector'.
     */
    public function tool_etl_get_element_type($name) {
        $regex = '/^date|date$/';
        if (preg_match($regex, $name)) {
            return 'date_time_selector';
        }
        return 'text';
    }

    /**
     * Checks if $value is integer
     * @param mixed $value some value
     * @return bool whether $value is an integer, or a string that looks like an integer.
     */
    public function tool_etl_is_integer($value) {
        return (string) (int) $value === (string) $value;
    }

    /**
     * Substitute time tokens in SQL query
     * @param string $sql sql query
     * @param string $start start time
     * @param string $end end time
     * @return string sql query with time tokens replaced.
     */
    public function tool_etl_substitute_time_tokens($sql, $start, $end) {
        return str_replace(array('%%STARTTIME%%', '%%ENDTIME%%'), array($start, $end), $sql);
    }

    /**
     * Substitute user token in SQL query
     * @param string $sql sql query
     * @param int $userid USER id
     * @return string sql query with userid token replaced.
     */
    public function tool_etl_substitute_user_token($sql, $userid) {
        return str_replace('%%USERID%%', $userid, $sql);
    }

    /**
     * List of bad words for query
     * @return array list of bad words.
     */
    public function tool_etl_bad_words_list() {
        return array('ALTER', 'CREATE', 'DELETE', 'DROP', 'GRANT', 'INSERT', 'INTO',
            'TRUNCATE', 'UPDATE');
    }

    /**
     * Checks if query contains bad words
     * @return int 0 - no bad words, 1 - has bad words.
     */
    public function tool_etl_contains_bad_word($string) {
        return preg_match('/\b('.implode('|', $this->tool_etl_bad_words_list()).')\b/i', $string);
    }

    /**
     * Make column names more readable
     * @return array column names.
     */
    public function tool_etl_pretify_column_names($row) {
        $colnames = array();
        foreach (get_object_vars($row) as $colname => $ignored) {
            $colnames[] = str_replace('_', ' ', $colname);
        }
        return $colnames;
    }

    /**
     * Timestamp for hour
     * @param int $timenow a timestamp.
     * @param int $at an hour, 0 to 23.
     * @return array with two elements: the timestamp for hour $at today (where today
     *      is defined by $timenow) and the timestamp for hour $at yesterday.
     */
    public function tool_etl_get_daily_time_starts($timenow, $at) {
        $hours = $at;
        $minutes = 0;
        $dateparts = getdate($timenow);
        return array(
            mktime((int)$hours, (int)$minutes, 0,
                $dateparts['mon'], $dateparts['mday'], $dateparts['year']),
            mktime((int)$hours, (int)$minutes, 0,
                $dateparts['mon'], $dateparts['mday'] - 1, $dateparts['year']),
        );
    }

    /**
     * Timestamp for week
     * @param int $timenow a timestamp.
     * @param int $weekstart week start.
     * @return array with two elements: the timestamp for week $dateparts this week (defined
     * by $timenow) and the timestamp for week $dateparts before.
     */
    public function tool_etl_get_week_starts($timenow, $weekstart) {
        $dateparts = getdate($timenow);
        $daysafterweekstart = ($dateparts['wday'] - $weekstart + 7) % 7;

        return array(
            mktime(0, 0, 0, $dateparts['mon'], $dateparts['mday'] - $daysafterweekstart,
                $dateparts['year']),
            mktime(0, 0, 0, $dateparts['mon'], $dateparts['mday'] - $daysafterweekstart - 7,
                $dateparts['year']),
        );
    }

    /**
     * Timestamp for month
     * @param int $timenow a timestamp.
     * @return array with two elements: the timestamp for week $dateparts this month (defined
     * by $timenow) and the timestamp for month $dateparts before.
     */
    public function tool_etl_get_month_starts($timenow) {
        $dateparts = getdate($timenow);

        return array(
            mktime(0, 0, 0, $dateparts['mon'], 1, $dateparts['year']),
            mktime(0, 0, 0, $dateparts['mon'] - 1, 1, $dateparts['year']),
        );
    }

    /**
     * Timestamp for hour/week/month
     * @param object $report report to run.
     * @param int $timenow a timestamp.
     * @return array
     * @throws \exception.
     */
    public function tool_etl_get_starts($report, $timenow) {
        switch ($report->runable) {
            case 'daily':
                return $this->tool_etl_get_daily_time_starts($timenow, $report->at);
            case 'weekly':
                return $this->tool_etl_get_week_starts($timenow, $report->weekstart);
            case 'monthly':
                return $this->tool_etl_get_month_starts($timenow);
            default:
                throw new Exception('unexpected $report->runable.');
        }
    }

    /**
     * Validates users
     * @param string $userstring string with usernames
     * @param string $capability the name of the capability to check
     * @return string
     * @throws \exception.
     */
    public function tool_etl_validate_users($userstring, $capability) {
        global $DB;
        if (empty($userstring)) {
            return null;
        }

        $a = new stdClass();
        $a->capability = $capability;
        $a->whocanaccess = get_string('whocanaccess', 'tool_etl');

        $usernames = preg_split("/[\s,;]+/", $userstring);
        if ($usernames) {
            foreach ($usernames as $username) {
                // Cannot find the user in the database.
                if (!$user = $DB->get_record('user', array('username' => $username))) {
                    return get_string('usernotfound', 'tool_etl', $username);
                }
                // User does not have the chosen access level.
                $context = context_user::instance($user->id);
                $a->username = $username;
                if (!has_capability($capability, $context, $user)) {
                    return get_string('userhasnothiscapability', 'tool_etl', $a);
                }
            }
        }
        return null;
    }

    /**
     * Gets list of users from a string
     * @param string $str string with userids
     * @param string $inputfield
     * @param string $outputfield
     * @return string $users
     * @throws \exception.
     */
    public function tool_etl_get_list_of_users($str, $inputfield = 'username', $outputfield = 'id') {
        global $DB;
        if (!$userarray = preg_split("/[\s,;]+/", $str)) {
            return null;
        }
        $users = array();
        foreach ($userarray as $user) {
            $users[$user] = $DB->get_field('user', $outputfield, array($inputfield => $user));
        }
        if (!$users) {
            return null;
        }
        return implode(',', $users);
    }


    /**
     * Returns list of week days.
     *
     * @return array
     */
    protected function get_list_of_days() {
        return array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    }

    /**
     * Check if column headers has invalid symbols
     * @param  $string Column header
     * @return boolean
     */
    public function tool_etl_column_headers_contains_invalid_symbols($string) {
        return preg_match('/[-!$%^&*()_+|~=`{}\[\]:";<>?,.\/]/i', $string);
    }

}
