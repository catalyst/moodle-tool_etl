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
 * Base common class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\common;

use tool_etl\logger;
use tool_etl\regex_validator;

abstract class common_base implements common_interface {
    /**
     * Default date format.
     */
    const DEFAULT_DATE_FORMAT = 'YmdHis';

    /**
     * Default delimiter.
     */
    const DEFAULT_DELIMITER = '';

    /**
     * Name of the element.
     *
     * @var string
     */
    protected $name = '';

    /**
     * A list of settings.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = array()) {
        $this->set_settings($settings);
    }

    /**
     * @inheritdoc
     */
    public function get_name() {
        if (empty($this->name)) {
            throw new \coding_exception('Name should be not empty');
        }

        return $this->name;
    }

    /**
     * @inheritdoc
     */
    final public function get_short_name() {
        $reflection = new \ReflectionClass($this);

        return $reflection->getShortName();
    }

    /**
     * @inheritdoc
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_for_display() {
        return $this->get_settings();
    }

    /**
     * @inheritdoc
     */
    public function set_settings(array $data) {
        foreach ($data as $name => $value) {
            if (isset($this->settings[$name])) {
                $this->settings[$name] = $value;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        return array();
    }

    /**
     * Return a prefix for config form.
     *
     * @return string
     */
    final public function get_config_form_prefix() {
        return $this->get_short_name() . '-';
    }

    /**
     * Helper function to retrieve settings from submitted data.
     *
     * @param array $data Submitted data of the task settings form.
     *
     * @return array
     */
    public function get_settings_from_submitted_data(array $data) {
        $settings = array();

        foreach ($data as $name => $value) {
            if (strpos($name, $this->get_config_form_prefix()) === 0 ) {
                $result = substr($name, strlen($this->get_config_form_prefix()));

                if ($result) {
                    $settings[$result] = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Init an instance of required type element.
     *
     * @param string $type Element type: source, target or processor.
     * @param string $name A name of the class to initialise.
     * @param array $settings Settings to pass to the element.
     *
     * @return common_interface
     * @throws \coding_exception If required class is not exist.
     */
    public static function init($type, $name, $settings = array()) {
        $classname = "tool_etl\\$type\\" . $name;

        if (!class_exists($classname)) {
            throw new \coding_exception('Can not initialise element. Class ' . $classname . ' is not exists');
        }

        return new $classname($settings);
    }

    /**
     * Return a list of options of provided type.
     *
     * @param string $type Element type: source, target or processor.
     *
     * @return array
     * @throws \coding_exception If required provided invalid type of element.
     */
    public static function options($type) {
        if (!self::is_valid_type($type)) {
            throw new \coding_exception('Invalid type ' . $type);
        }

        $baseclass = "tool_etl\\$type\\$type" . "_base";

        return $baseclass::get_options();
    }

    /**
     * Check if provided type is valid.
     *
     * @param string $type Element type.
     *
     * @return bool
     */
    public static function is_valid_type($type) {
        $validtypes = array('source', 'target', 'processor');

        if (in_array($type, $validtypes)) {
            return true;
        }

        return false;
    }

    /**
     * A helper function to create elements in the provided form for particular instance of element.
     *
     * @param \MoodleQuickForm $mform
     * @param array $fields
     *
     * @return array
     */
    public function get_config_form_elements(\MoodleQuickForm $mform, array $fields) {
        $elements = array();

        foreach ($fields as $field) {
            $fieldname = $this->get_config_form_prefix() . $field->name;
            $element = $mform->createElement($field->type, $fieldname, $field->title);
            if ($field->type == 'select') {
                $mform->addElement($field->type, $fieldname, $field->title, $field->options);
            } else {
                $mform->addElement($field->type, $fieldname, $field->title);
            }
            if ($field->help) {
                $mform->addHelpButton($fieldname, $field->name, 'tool_etl');
            }
            $mform->setDefault($fieldname, $field->default);
            $mform->setType($fieldname, $field->filter);
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * Log an action.
     *
     * @param string $action Logged action.
     * @param string $info Info text.
     * @param string $logtype One of self::TYPE_*
     * @param string $trace Some code trace.
     *
     * @throws \coding_exception
     */
    protected function log($action, $info='', $logtype = logger::TYPE_INFO, $trace='') {
        logger::get_instance()->set_element($this->get_name());
        logger::get_instance()->add_to_log($logtype, $action, $info, $trace);

        if ($logtype == logger::TYPE_ERROR) {
            try {
                $this->notify($action, $info);
            } catch (\moodle_exception $e) {
                logger::get_instance()->add_to_log(logger::TYPE_ERROR, 'notify', $e->getMessage(), $e->getTrace());
            }
        }
    }

    /**
     * Notify of failure
     *
     * @param string $subject
     * @param string $msg
     * @return bool
     */
    protected function notify($action, $msg) {
        if (empty($this->settings['notifymailto'])) {
            return;
        }

        $notifyemails = explode(',', $this->settings['notifymailto']);

        $sitename = get_site();
        $sitename = format_string($sitename->fullname);
        $subject = "$sitename $action error";

        // Send emails.
        $supportuser = \core_user::get_support_user();
        $user = \core_user::get_noreply_user();
        $sent = true;
        foreach ($notifyemails as $emailaddress) {
            $user->email = $emailaddress;
            $sent = $sent && email_to_user($user, $supportuser, $subject, $msg);
        }
        if (!$sent) {
            throw new \moodle_exception('notify_failed', 'tool_etl', null, $notifyemails);
        }
    }

    /**
     * Validates provided regex.
     *
     * @param string $regex
     *
     * @return null|string
     */
    public function validate_regex($regex) {
        $validator = new regex_validator($regex);

        return $validator->get_error();
    }

    /**
     * Get date format from configuration.
     *
     * @return string Date format for php date function.
     */
    public function get_date_format() {
        if (isset($this->settings['dateformat'])) {
            return $this->settings['dateformat'];
        }

        return self::DEFAULT_DATE_FORMAT;
    }

    /**
     * Get date delimiter based on configuration.
     *
     * @return string
     */
    public function get_date_delimiter() {
        if (isset($this->settings['delimiter'])) {
            return $this->settings['delimiter'];
        }

        return self::DEFAULT_DELIMITER;
    }

    /**
     * Append provided file by date in configured format.
     *
     * @param string $filename File name or file path.
     * @param null|string  $time Timestamp of the time.
     *
     * @return string
     */
    public function append_filename_by_date($filename, $time = null) {
        if (empty($time)) {
            $time = time();
        }

        $pathinfo = pathinfo($filename);

        $dot = '.';
        $filename = !empty($pathinfo['filename']) ? $pathinfo['filename'] : $pathinfo['basename'];
        $extension = !empty($pathinfo['extension']) ? $pathinfo['extension'] : '';

        // If a file starts with dot (e.g. .test).
        if (empty($pathinfo['filename']) && !empty($pathinfo['extension'])) {
            $extension = '';
            $dot = '';
        }
        // If a file ends with dot (e.g. test.).
        if (empty($pathinfo['extension']) && !empty($pathinfo['basename'])) {
            $filename = $pathinfo['basename'];
        }

        // No extra dot if there is no extension.
        if (empty($pathinfo['extension'])) {
            $dot = '';
        }

        $delimiter = $this->get_date_delimiter();
        $date = date($this->get_date_format(), $time);

        return $filename . $delimiter . $date . $dot . $extension;
    }

}
